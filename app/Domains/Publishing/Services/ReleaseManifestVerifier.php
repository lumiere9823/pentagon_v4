<?php

namespace App\Domains\Publishing\Services;

use App\Domains\Publishing\Exceptions\ReleaseStagingException;
use App\Models\PageRelease;
use Illuminate\Support\Facades\Storage;

class ReleaseManifestVerifier
{
    /**
     * Verify the integrity of a sealed release on disk.
     */
    public function verify(PageRelease $release, ?string $disk = 'local'): bool
    {
        $report = $this->verifyWithReport($release, $disk);

        return $report['valid'];
    }

    /**
     * Verify the integrity of a release directory on disk without requiring a model.
     */
    public function verifyDirectory(string $directoryPath, ?string $disk = 'local'): bool
    {
        $report = $this->verifyDirectoryWithReport($directoryPath, $disk);

        return $report['valid'];
    }

    /**
     * Verify the release and throw ReleaseStagingException on failure.
     *
     * @throws ReleaseStagingException
     */
    public function verifyOrFail(PageRelease $release, ?string $disk = 'local'): void
    {
        $report = $this->verifyWithReport($release, $disk);

        if (! $report['valid']) {
            throw new ReleaseStagingException(
                'Release integrity verification failed: '.implode('; ', $report['errors']),
                $report['errors']
            );
        }
    }

    /**
     * Verify a PageRelease model against physical disk contents and return a detailed report.
     *
     * @return array{valid: bool, errors: list<string>}
     */
    public function verifyWithReport(PageRelease $release, ?string $disk = 'local'): array
    {
        $errors = [];

        if (empty($release->directory_path)) {
            return [
                'valid' => false,
                'errors' => ['Release directory_path is not set.'],
            ];
        }

        // Basic path traversal rejection on directory_path
        if (str_contains($release->directory_path, '..') || str_contains($release->directory_path, "\0")) {
            return [
                'valid' => false,
                'errors' => ['Path traversal or invalid characters detected in directory_path.'],
            ];
        }

        $report = $this->verifyDirectoryWithReport($release->directory_path, $disk);
        $errors = $report['errors'];

        // If directory is valid, verify model attributes against manifest
        if ($report['valid']) {
            $storage = Storage::disk($disk ?? 'local');
            $dirPath = $storage->path($release->directory_path);
            $manifestPath = $dirPath.DIRECTORY_SEPARATOR.'manifest.json';
            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($manifest)) {
                if (isset($manifest['release_uuid']) && (string) $manifest['release_uuid'] !== (string) $release->id) {
                    $errors[] = "Manifest release_uuid mismatch: expected {$release->id}, got {$manifest['release_uuid']}";
                }
                if (isset($manifest['page_id']) && (int) $manifest['page_id'] !== (int) $release->page_id) {
                    $errors[] = "Manifest page_id mismatch: expected {$release->page_id}, got {$manifest['page_id']}";
                }
                if (isset($manifest['page_revision_id']) && (int) $manifest['page_revision_id'] !== (int) $release->page_revision_id) {
                    $errors[] = "Manifest page_revision_id mismatch: expected {$release->page_revision_id}, got {$manifest['page_revision_id']}";
                }
                if (isset($manifest['release_number']) && (int) $manifest['release_number'] !== (int) $release->release_number) {
                    $errors[] = "Manifest release_number mismatch: expected {$release->release_number}, got {$manifest['release_number']}";
                }

                // If DB has manifest_json recorded, verify disk manifest matches database manifest
                if (! empty($release->manifest_json)) {
                    if (isset($release->manifest_json['html_hash']) && isset($manifest['html_hash']) && $release->manifest_json['html_hash'] !== $manifest['html_hash']) {
                        $errors[] = "Database manifest html_hash does not match disk manifest: {$release->manifest_json['html_hash']} vs {$manifest['html_hash']}";
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Inspect and verify a release directory on disk.
     *
     * @return array{valid: bool, errors: list<string>}
     */
    public function verifyDirectoryWithReport(string $directoryPath, ?string $disk = 'local'): array
    {
        $errors = [];
        $storage = Storage::disk($disk ?? 'local');

        // Path safety check
        if (str_contains($directoryPath, '..') || str_contains($directoryPath, "\0")) {
            return [
                'valid' => false,
                'errors' => ['Path traversal or invalid characters detected in directory path.'],
            ];
        }

        $absDir = $storage->path($directoryPath);

        if (! is_dir($absDir)) {
            return [
                'valid' => false,
                'errors' => ["Release directory does not exist on disk: {$directoryPath}"],
            ];
        }

        $realBase = realpath($absDir);
        if ($realBase === false) {
            return [
                'valid' => false,
                'errors' => ["Cannot resolve realpath for release directory: {$directoryPath}"],
            ];
        }

        // 1. Check manifest.json
        $manifestPath = $absDir.DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($manifestPath)) {
            return [
                'valid' => false,
                'errors' => ['manifest.json is missing from release directory.'],
            ];
        }

        $manifestContent = @file_get_contents($manifestPath);
        if ($manifestContent === false) {
            return [
                'valid' => false,
                'errors' => ['Unable to read manifest.json file.'],
            ];
        }

        $manifest = json_decode($manifestContent, true);
        if (! is_array($manifest)) {
            return [
                'valid' => false,
                'errors' => ['manifest.json contains invalid JSON syntax.'],
            ];
        }

        // 2. Check index.html primary document
        $indexPath = $absDir.DIRECTORY_SEPARATOR.'index.html';
        if (! is_file($indexPath)) {
            $errors[] = 'Primary document index.html is missing from release directory.';
        } else {
            $indexBytes = filesize($indexPath);
            if ($indexBytes === false || $indexBytes === 0) {
                $errors[] = 'Primary document index.html is empty (0 bytes).';
            } else {
                $indexContent = @file_get_contents($indexPath);
                if ($indexContent === false || ! str_contains($indexContent, '<!DOCTYPE html>')) {
                    $errors[] = 'Primary document index.html does not contain valid <!DOCTYPE html>.';
                }

                $indexHash = hash_file('sha256', $indexPath);
                if (isset($manifest['html_hash']) && $manifest['html_hash'] !== $indexHash) {
                    $errors[] = "index.html SHA-256 hash mismatch: expected {$manifest['html_hash']}, got {$indexHash}";
                }

                if (isset($manifest['html_bytes']) && (int) $manifest['html_bytes'] !== $indexBytes) {
                    $errors[] = "index.html byte size mismatch: expected {$manifest['html_bytes']}, got {$indexBytes}";
                }
            }
        }

        // 3. Check artifacts array
        if (! isset($manifest['artifacts']) || ! is_array($manifest['artifacts']) || empty($manifest['artifacts'])) {
            $errors[] = 'Manifest contains no artifacts or invalid artifacts list.';
        } else {
            foreach ($manifest['artifacts'] as $artifact) {
                if (! is_array($artifact) || ! isset($artifact['path'], $artifact['bytes'], $artifact['sha256'])) {
                    $errors[] = 'Malformed artifact record in manifest.';

                    continue;
                }

                $relArtPath = (string) $artifact['path'];
                if (str_contains($relArtPath, '..') || str_starts_with($relArtPath, '/') || str_starts_with($relArtPath, '\\')) {
                    $errors[] = "Path traversal detected in artifact path: {$relArtPath}";

                    continue;
                }

                $artFile = $absDir.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relArtPath);
                if (! is_file($artFile)) {
                    $errors[] = "Artifact file missing on disk: {$relArtPath}";

                    continue;
                }

                $realArt = realpath($artFile);
                if ($realArt === false || ! str_starts_with($realArt, $realBase)) {
                    $errors[] = "Artifact path escapes release directory: {$relArtPath}";

                    continue;
                }

                $artBytes = filesize($artFile);
                if ($artBytes !== (int) $artifact['bytes']) {
                    $errors[] = "Artifact {$relArtPath} byte size mismatch: expected {$artifact['bytes']}, got {$artBytes}";
                }

                $artHash = hash_file('sha256', $artFile);
                if ($artHash !== (string) $artifact['sha256']) {
                    $errors[] = "Artifact {$relArtPath} SHA-256 mismatch: expected {$artifact['sha256']}, got {$artHash}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
