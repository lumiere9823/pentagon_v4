<?php

namespace Tests\Feature\Publishing;

use App\Domains\Publishing\Exceptions\PublishRenderingException;
use App\Domains\Publishing\Exceptions\ReleaseStagingException;
use App\Domains\Publishing\Services\DocumentHtmlRenderer;
use App\Domains\Publishing\Services\ReleaseManifestVerifier;
use App\Domains\Publishing\Services\ReleaseStagingService;
use App\Enums\MediaAssetStatus;
use App\Enums\PageReleaseStatus;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReleaseStagingTest extends TestCase
{
    use RefreshDatabase;

    protected ReleaseStagingService $stagingService;

    protected ReleaseManifestVerifier $manifestVerifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stagingService = app(ReleaseStagingService::class);
        $this->manifestVerifier = app(ReleaseManifestVerifier::class);
        Storage::fake('local');
    }

    private function createPageWithRevision(?array $customDocument = null): array
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);
        $page = Page::factory()->create(['website_id' => $website->id, 'created_by' => $user->id]);

        $attributes = ['page_id' => $page->id, 'created_by' => $user->id];
        if ($customDocument !== null) {
            $attributes['document_json'] = $customDocument;
            $attributes['document_hash'] = PageRevision::canonicalizeDocument($customDocument);
        }

        $revision = PageRevision::factory()->create($attributes);

        return [$page, $revision, $user, $website];
    }

    /**
     * 1. Test candidate release creation with sequential release_number and pending status.
     */
    public function test_creates_candidate_release_with_pending_status_and_sequential_release_number(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $candidate1 = $this->stagingService->createCandidate($revision, $user);

        $this->assertNotNull($candidate1->id);
        $this->assertTrue(Str::isUuid($candidate1->id));
        $this->assertEquals($page->id, $candidate1->page_id);
        $this->assertEquals($revision->id, $candidate1->page_revision_id);
        $this->assertEquals(1, $candidate1->release_number);
        $this->assertEquals(PageReleaseStatus::PENDING, $candidate1->status);
        $this->assertEquals($user->id, $candidate1->created_by);
        $this->assertNull($candidate1->directory_path);
        $this->assertNull($candidate1->manifest_json);

        // Second candidate on same page gets incremented release_number
        $candidate2 = $this->stagingService->createCandidate($revision, $user);
        $this->assertEquals(2, $candidate2->release_number);
        $this->assertEquals(PageReleaseStatus::PENDING, $candidate2->status);
    }

    /**
     * 2. Test staging valid HTML and generating index.html and manifest.json.
     */
    public function test_stages_valid_html_and_generates_artifacts(): void
    {
        [$page, $revision, $user, $website] = $this->createPageWithRevision();

        $candidate = $this->stagingService->createCandidate($revision, $user);
        $sealedRelease = $this->stagingService->stageAndSeal($candidate);

        $expectedDir = "releases/{$website->id}/{$page->id}/r_1_".substr($revision->document_hash, 0, 8);
        $this->assertEquals($expectedDir, $sealedRelease->directory_path);

        // Check files exist on disk
        $storage = Storage::disk('local');
        $this->assertTrue($storage->exists("{$expectedDir}/index.html"));
        $this->assertTrue($storage->exists("{$expectedDir}/manifest.json"));

        $htmlContent = $storage->get("{$expectedDir}/index.html");
        $this->assertStringContainsString('<!DOCTYPE html>', $htmlContent);
        $this->assertStringContainsString('<html lang="en">', $htmlContent);
    }

    public function test_staging_legacy_alias_revision_renders_canonical_html_without_mutating_revision(): void
    {
        $legacyDocument = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'legacy_release_root',
                'type' => 'Section',
                'props' => [],
                'children' => [[
                    'id' => 'legacy_release_text',
                    'type' => 'Rich Text',
                    'props' => ['content' => '<p onclick="alert(1)">Legacy release</p><script>alert(2)</script>'],
                    'children' => [],
                ]],
            ],
        ];
        [, $revision, $user] = $this->createPageWithRevision($legacyDocument);

        $release = $this->stagingService->stageRevision($revision, $user);

        $html = Storage::disk('local')->get($release->directory_path.'/index.html');
        $this->assertStringContainsString('Legacy release', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('alert(2)', $html);
        $this->assertSame($legacyDocument, $revision->fresh()->document_json);
    }

    /**
     * 3. Test manifest contains complete canonical metadata, artifacts, and hashes.
     */
    public function test_manifest_contains_canonical_metadata_and_hashes(): void
    {
        [$page, $revision, $user, $website] = $this->createPageWithRevision();

        $candidate = $this->stagingService->createCandidate($revision, $user);
        $sealedRelease = $this->stagingService->stageAndSeal($candidate);

        $manifest = $sealedRelease->manifest_json;
        $this->assertIsArray($manifest);
        $this->assertEquals($candidate->id, $manifest['release_uuid']);
        $this->assertEquals($page->id, $manifest['page_id']);
        $this->assertEquals($revision->id, $manifest['page_revision_id']);
        $this->assertEquals(1, $manifest['release_number']);
        $this->assertEquals(1, $manifest['schema_version']);
        $this->assertEquals(2, $manifest['renderer_version']);
        $this->assertEquals($revision->document_hash, $manifest['document_hash']);
        $this->assertArrayHasKey('created_at', $manifest);
        $this->assertArrayHasKey('html_bytes', $manifest);
        $this->assertArrayHasKey('html_hash', $manifest);

        // Artifacts list
        $this->assertIsArray($manifest['artifacts']);
        $this->assertCount(1, $manifest['artifacts']);
        $artifact = $manifest['artifacts'][0];
        $this->assertEquals('index.html', $artifact['path']);
        $this->assertEquals($manifest['html_bytes'], $artifact['bytes']);
        $this->assertEquals($manifest['html_hash'], $artifact['sha256']);

        // Physical file hash matches manifest
        $storage = Storage::disk('local');
        $physicalHash = hash_file('sha256', $storage->path($sealedRelease->directory_path.'/index.html'));
        $this->assertEquals($manifest['html_hash'], $physicalHash);
    }

    /**
     * 4. Test atomic sealing renames staging folder to sealed folder without leaving staging folder behind.
     */
    public function test_atomic_sealing_renames_staging_folder_and_cleans_up(): void
    {
        [$page, $revision, $user, $website] = $this->createPageWithRevision();

        $candidate = $this->stagingService->createCandidate($revision, $user);
        $stagingDir = "releases/{$website->id}/{$page->id}/staging_{$candidate->id}";

        $sealedRelease = $this->stagingService->stageAndSeal($candidate);

        $storage = Storage::disk('local');
        // Staging directory must no longer exist
        $this->assertFalse($storage->exists($stagingDir));
        // Sealed directory must exist
        $this->assertTrue($storage->exists($sealedRelease->directory_path));
    }

    /**
     * 5. Invariant check: Checkpoint B3 strictly leaves release non-active (never sets active or updates page).
     */
    public function test_checkpoint_b3_strictly_leaves_release_in_pending_status(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $candidate = $this->stagingService->createCandidate($revision, $user);
        $sealedRelease = $this->stagingService->stageAndSeal($candidate);

        // Status MUST be pending
        $this->assertEquals(PageReleaseStatus::PENDING, $sealedRelease->status);
        $this->assertEquals(PageReleaseStatus::PENDING, $sealedRelease->fresh()->status);

        // Page published_revision_id MUST remain null
        $this->assertNull($page->fresh()->published_revision_id);
        $this->assertNull($page->fresh()->activeRelease);
    }

    /**
     * 6. Test verifier detects tampered index.html file (SHA-256 mismatch).
     */
    public function test_verifier_detects_tampered_index_html_file(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $candidate = $this->stagingService->createCandidate($revision, $user);
        $sealedRelease = $this->stagingService->stageAndSeal($candidate);

        // Initially valid
        $this->assertTrue($this->manifestVerifier->verify($sealedRelease));

        // Tamper with index.html on disk
        $storage = Storage::disk('local');
        $indexPath = $storage->path($sealedRelease->directory_path.'/index.html');
        file_put_contents($indexPath, file_get_contents($indexPath).'<!-- tampered malicious payload -->');

        // Verification must fail
        $this->assertFalse($this->manifestVerifier->verify($sealedRelease));

        $report = $this->manifestVerifier->verifyWithReport($sealedRelease);
        $this->assertFalse($report['valid']);
        $this->assertTrue(collect($report['errors'])->contains(fn ($err) => str_contains($err, 'SHA-256') || str_contains($err, 'mismatch')));

        $this->expectException(ReleaseStagingException::class);
        $this->manifestVerifier->verifyOrFail($sealedRelease);
    }

    /**
     * 7. Test verifier detects missing artifact file.
     */
    public function test_verifier_detects_missing_file(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $candidate = $this->stagingService->createCandidate($revision, $user);
        $sealedRelease = $this->stagingService->stageAndSeal($candidate);

        // Remove index.html
        $storage = Storage::disk('local');
        $storage->delete($sealedRelease->directory_path.'/index.html');

        $this->assertFalse($this->manifestVerifier->verify($sealedRelease));
        $report = $this->manifestVerifier->verifyWithReport($sealedRelease);
        $this->assertFalse($report['valid']);
        $this->assertTrue(collect($report['errors'])->contains(fn ($err) => str_contains($err, 'missing')));
    }

    /**
     * 8. Test verifier detects tampered manifest where disk manifest differs from database manifest.
     */
    public function test_verifier_detects_tampered_manifest_mismatch_with_database(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $candidate = $this->stagingService->createCandidate($revision, $user);
        $sealedRelease = $this->stagingService->stageAndSeal($candidate);

        // Alter manifest.json on disk to claim a different html_hash
        $storage = Storage::disk('local');
        $manifestPath = $storage->path($sealedRelease->directory_path.'/manifest.json');
        $diskManifest = json_decode((string) file_get_contents($manifestPath), true);
        $diskManifest['html_hash'] = hash('sha256', 'fake-hash');
        file_put_contents($manifestPath, json_encode($diskManifest));

        $report = $this->manifestVerifier->verifyWithReport($sealedRelease);
        $this->assertFalse($report['valid']);
        $this->assertTrue(collect($report['errors'])->contains(fn ($err) => str_contains($err, 'Database manifest html_hash does not match disk manifest') || str_contains($err, 'SHA-256')));
    }

    /**
     * 9. Destination collision: If target destination directory already exists, fail closed without overwriting.
     */
    public function test_fail_closed_if_destination_directory_already_exists(): void
    {
        [$page, $revision, $user, $website] = $this->createPageWithRevision();

        $hash8 = substr($revision->document_hash, 0, 8);
        $existingTarget = "releases/{$website->id}/{$page->id}/r_1_{$hash8}";

        // Pre-create destination directory
        $storage = Storage::disk('local');
        $storage->makeDirectory($existingTarget);
        file_put_contents($storage->path($existingTarget.'/sentinel.txt'), 'do-not-overwrite');

        $candidate = $this->stagingService->createCandidate($revision, $user);

        try {
            $this->stagingService->stageAndSeal($candidate);
            $this->fail('Expected ReleaseStagingException was not thrown on collision');
        } catch (ReleaseStagingException $e) {
            $this->assertStringContainsString('already exists on disk', $e->getMessage());
        }

        // Sentinel must remain untouched
        $this->assertEquals('do-not-overwrite', file_get_contents($storage->path($existingTarget.'/sentinel.txt')));

        // Candidate must be marked failed
        $this->assertEquals(PageReleaseStatus::FAILED, $candidate->fresh()->status);
        $this->assertStringContainsString('already exists', $candidate->fresh()->error_message);

        // Staging folder must be deleted
        $stagingDir = "releases/{$website->id}/{$page->id}/staging_{$candidate->id}";
        $this->assertFalse($storage->exists($stagingDir));
    }

    /**
     * 10. Render failure: DocumentHtmlRenderer exception triggers staging cleanup and marks candidate failed.
     */
    public function test_fail_closed_on_render_failure_cleans_up_staging_and_marks_failed(): void
    {
        // Document with unknown unrenderable component in non-preview mode
        $brokenDocument = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'broken_node',
                'type' => 'NonExistentMaliciousComponent',
                'props' => [],
                'children' => [],
            ],
            'pageSettings' => ['title' => 'Broken Page'],
        ];

        [$page, $revision, $user, $website] = $this->createPageWithRevision($brokenDocument);

        $candidate = $this->stagingService->createCandidate($revision, $user);

        try {
            $this->stagingService->stageAndSeal($candidate);
            $this->fail('Expected PublishRenderingException was not thrown on invalid component');
        } catch (PublishRenderingException $e) {
            $this->assertStringContainsString('is not in the allowlist', $e->getMessage());
        }

        // Candidate marked FAILED
        $candidate->refresh();
        $this->assertEquals(PageReleaseStatus::FAILED, $candidate->status);
        $this->assertNotNull($candidate->error_message);
        $this->assertStringContainsString('is not in the allowlist', $candidate->error_message);

        // Staging folder cleaned up
        $storage = Storage::disk('local');
        $stagingDir = "releases/{$website->id}/{$page->id}/staging_{$candidate->id}";
        $this->assertFalse($storage->exists($stagingDir));
    }

    /**
     * 11. Test path traversal rejection on directory paths and identifiers.
     */
    public function test_path_traversal_rejection(): void
    {
        $fakeRelease = new PageRelease([
            'id' => (string) Str::uuid(),
            'page_id' => 1,
            'page_revision_id' => 1,
            'release_number' => 1,
            'directory_path' => 'releases/../../etc/passwd',
        ]);

        $report = $this->manifestVerifier->verifyWithReport($fakeRelease);
        $this->assertFalse($report['valid']);
        $this->assertTrue(collect($report['errors'])->contains(fn ($err) => str_contains($err, 'Path traversal')));
    }

    /**
     * 12. Test retry behavior: after a failure, a new candidate can be staged cleanly.
     */
    public function test_retry_after_failure_allows_new_candidate_to_succeed(): void
    {
        // First attempt with broken document
        $brokenDocument = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'bad_1',
                'type' => 'InvalidComp',
                'props' => [],
                'children' => [],
            ],
            'pageSettings' => ['title' => 'Bad'],
        ];

        [$page, $revision, $user] = $this->createPageWithRevision($brokenDocument);

        $failedCandidate = $this->stagingService->createCandidate($revision, $user);
        try {
            $this->stagingService->stageAndSeal($failedCandidate);
        } catch (PublishRenderingException) {
            // Expected
        }
        $this->assertEquals(PageReleaseStatus::FAILED, $failedCandidate->fresh()->status);

        // Create corrected revision
        $validDocument = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'sec_ok',
                'type' => 'Section',
                'props' => ['padding' => 'medium'],
                'children' => [],
            ],
            'pageSettings' => ['title' => 'Fixed'],
        ];
        $fixedRevision = PageRevision::factory()->create([
            'page_id' => $page->id,
            'revision_number' => 2,
            'document_json' => $validDocument,
            'document_hash' => PageRevision::canonicalizeDocument($validDocument),
            'created_by' => $user->id,
        ]);

        // Retry with new candidate
        $retryCandidate = $this->stagingService->createCandidate($fixedRevision, $user);
        $this->assertEquals(2, $retryCandidate->release_number);

        $sealed = $this->stagingService->stageAndSeal($retryCandidate);
        $this->assertEquals(PageReleaseStatus::PENDING, $sealed->status);
        $this->assertTrue($this->manifestVerifier->verify($sealed));
    }

    /**
     * 13. Test manifest extracts referenced media asset IDs from document.
     */
    public function test_manifest_extracts_referenced_media_asset_ids(): void
    {
        [$page, $revision, $user, $website] = $this->createPageWithRevision();

        // Create a media asset
        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => "media/{$website->id}/hero.png",
            'status' => MediaAssetStatus::ACTIVE,
        ]);

        // Build document referencing the asset
        $docWithMedia = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'sec_1',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'img_1',
                        'type' => 'Image',
                        'props' => [
                            'src' => "storage/media/{$website->id}/hero.png",
                            'alt' => 'Hero Banner',
                        ],
                        'children' => [],
                    ],
                ],
            ],
            'pageSettings' => ['title' => 'Page with Media'],
        ];

        $revisionWithMedia = PageRevision::factory()->create([
            'page_id' => $page->id,
            'revision_number' => 2,
            'document_json' => $docWithMedia,
            'document_hash' => PageRevision::canonicalizeDocument($docWithMedia),
            'created_by' => $user->id,
        ]);

        $candidate = $this->stagingService->createCandidate($revisionWithMedia, $user);
        $sealed = $this->stagingService->stageAndSeal($candidate);

        $this->assertContains($asset->id, $sealed->manifest_json['media_asset_ids']);
    }

    /**
     * 14. Test stageRevision convenience method works end-to-end.
     */
    public function test_stage_revision_convenience_method(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $release = $this->stagingService->stageRevision($revision, $user);

        $this->assertNotNull($release->id);
        $this->assertEquals(PageReleaseStatus::PENDING, $release->status);
        $this->assertNotNull($release->directory_path);
        $this->assertTrue($this->manifestVerifier->verify($release));
    }

    /**
     * 15. Test that staged release HTML completely omits hidden nodes and their subtrees from generated index.html.
     */
    public function test_staged_release_strictly_omits_hidden_nodes_and_their_descendants(): void
    {
        $customDoc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => ['padding' => 'medium', 'background' => 'transparent'],
                'children' => [
                    [
                        'id' => 'public_heading',
                        'type' => 'Heading',
                        'props' => ['text' => 'Publicly Published Title', 'level' => 1],
                        'children' => [],
                    ],
                    [
                        'id' => 'hidden_leaf',
                        'type' => 'Heading',
                        'props' => ['text' => 'Secret Unpublished Title', 'level' => 2],
                        'hidden' => true,
                        'children' => [],
                    ],
                    [
                        'id' => 'hidden_container',
                        'type' => 'Container',
                        'props' => ['maxWidth' => 'lg', 'centered' => true],
                        'hidden' => true,
                        'children' => [
                            [
                                'id' => 'hidden_descendant_button',
                                'type' => 'Button',
                                'props' => ['label' => 'Secret Hidden Action', 'url' => 'https://example.com'],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Page with Hidden Content',
                'description' => 'Verifying release sanitization',
            ],
        ];

        [$page, $revision, $user] = $this->createPageWithRevision($customDoc);

        $release = $this->stagingService->stageRevision($revision, $user);

        $this->assertTrue($this->manifestVerifier->verify($release));

        $indexPath = $release->directory_path.'/index.html';
        $html = Storage::disk('local')->get($indexPath);

        $this->assertNotNull($html);
        $this->assertStringContainsString('Publicly Published Title', $html);
        $this->assertStringContainsString('public_heading', $html);

        // Hidden leaf must be completely absent from HTML
        $this->assertStringNotContainsString('Secret Unpublished Title', $html);
        $this->assertStringNotContainsString('hidden_leaf', $html);

        // Hidden container and descendant must be completely absent from HTML
        $this->assertStringNotContainsString('hidden_container', $html);
        $this->assertStringNotContainsString('Secret Hidden Action', $html);
        $this->assertStringNotContainsString('hidden_descendant_button', $html);
    }
}
