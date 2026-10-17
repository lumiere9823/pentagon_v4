<?php

namespace App\Http\Controllers;

use App\Domains\Media\Services\MediaStorageService;
use App\Domains\Media\Services\MediaUsageScanner;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Models\MediaAsset;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaAssetController extends Controller
{
    /**
     * Display a paginated listing of media assets for the website.
     */
    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('viewAny', [MediaAsset::class, $website]);

        $query = $website->mediaAssets()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($search = $request->query('search')) {
            $term = trim((string) $search);
            if ($term !== '') {
                $lowerTerm = '%'.mb_strtolower($term).'%';
                $query->where(function ($q) use ($lowerTerm) {
                    $q->whereRaw('LOWER(original_filename) LIKE ?', [$lowerTerm])
                        ->orWhereRaw('LOWER(alt_text) LIKE ?', [$lowerTerm]);
                });
            }
        }

        if ($type = $request->query('type')) {
            $typeStr = trim((string) $type);
            if ($typeStr !== '') {
                $query->where('mime_type', $typeStr);
            }
        }

        $perPage = min(max((int) $request->query('per_page', 24), 1), 48);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (MediaAsset $asset) => $this->transformAsset($asset)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Upload and store a new media asset for the website.
     */
    public function store(
        StoreMediaRequest $request,
        Website $website,
        MediaStorageService $storageService
    ): JsonResponse {
        $this->authorize('create', [MediaAsset::class, $website]);

        $file = $request->file('file');
        $altText = $request->validated('alt_text');

        $asset = $storageService->store(
            $file,
            $website,
            $request->user(),
            $altText
        );

        return response()->json([
            'data' => $this->transformAsset($asset),
        ], 201);
    }

    /**
     * Update metadata (e.g., alt text) for an existing media asset.
     */
    public function update(
        UpdateMediaRequest $request,
        Website $website,
        MediaAsset $mediaAsset
    ): JsonResponse {
        // Enforce IDOR protection: Media asset must belong to the given website
        if ($mediaAsset->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('update', $mediaAsset);

        $mediaAsset->update([
            'alt_text' => $request->validated('alt_text'),
        ]);

        return response()->json([
            'data' => $this->transformAsset($mediaAsset),
        ]);
    }

    /**
     * Delete a media asset, with reference usage verification.
     */
    public function destroy(
        Request $request,
        Website $website,
        MediaAsset $mediaAsset,
        MediaUsageScanner $scanner
    ): JsonResponse {
        // Enforce IDOR protection: Media asset must belong to the given website
        if ($mediaAsset->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('delete', $mediaAsset);

        // Check if asset is referenced in any document sources
        $referencingPages = $scanner->findUsagesInWebsite($website, $mediaAsset);
        if (! empty($referencingPages)) {
            return response()->json([
                'message' => 'Cannot delete media asset because it is currently used on '.count($referencingPages).' page(s).',
                'error_code' => 'media_in_use',
                'referencing_pages' => $referencingPages,
            ], 422);
        }

        $storagePath = $mediaAsset->storage_path;
        $disk = $mediaAsset->storage_disk;

        // Perform soft delete on the database record first
        $mediaAsset->delete();

        // Safely unlink the physical file from disk
        try {
            Storage::disk($disk)->delete($storagePath);
        } catch (Throwable $e) {
            Log::warning("Failed to unlink media asset {$mediaAsset->id} from disk: {$e->getMessage()}");
        }

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Transform a MediaAsset model into a consistent public JSON payload.
     *
     * @return array<string, mixed>
     */
    private function transformAsset(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'original_filename' => $asset->original_filename,
            'url' => $asset->url,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
            'formatted_size' => $asset->formatted_size,
            'width' => $asset->width,
            'height' => $asset->height,
            'alt_text' => $asset->alt_text,
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }
}
