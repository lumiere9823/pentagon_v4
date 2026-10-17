<?php

namespace App\Domains\Media\Services;

use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Website;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaStorageService
{
    /**
     * Allowed MIME types and their canonical extensions.
     */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * Maximum upload size: 5 MB (5,242,880 bytes).
     */
    public const MAX_FILE_SIZE_BYTES = 5242880;

    /**
     * Maximum dimension bounds.
     */
    public const MAX_DIMENSION = 4096;

    /**
     * Minimum dimension bounds.
     */
    public const MIN_DIMENSION = 16;

    /**
     * Validate an uploaded image file using binary inspection, header parsing, and boundary checks.
     *
     * @return array{mime_type: string, extension: string, width: int, height: int, size_bytes: int}
     *
     * @throws ValidationException
     */
    public function validateImageFile(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read.',
            ]);
        }

        $sizeBytes = $file->getSize();
        if ($sizeBytes > self::MAX_FILE_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'The file size must not exceed 5 MB.',
            ]);
        }

        // 1. Binary MIME sniffing via finfo (inspects magic bytes, ignores client headers)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($realPath);

        if (! is_string($detectedMime) || ! isset(self::ALLOWED_MIME_TYPES[$detectedMime])) {
            throw ValidationException::withMessages([
                'file' => 'The file must be an image of type: jpeg, png, webp, gif.',
            ]);
        }

        // 2. Image decodability and dimension check via getimagesize (header parser, <100KB RAM)
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is not a valid or decodable image.',
            ]);
        }

        // 3. Consistency check between finfo MIME and image header MIME
        $headerMime = $imageInfo['mime'] ?? null;
        if ($headerMime !== $detectedMime) {
            throw ValidationException::withMessages([
                'file' => 'The image format is inconsistent or corrupted.',
            ]);
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            throw ValidationException::withMessages([
                'file' => 'Image dimensions must be at least 16x16 pixels.',
            ]);
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw ValidationException::withMessages([
                'file' => 'Image dimensions must not exceed 4096x4096 pixels.',
            ]);
        }

        return [
            'mime_type' => $detectedMime,
            'extension' => self::ALLOWED_MIME_TYPES[$detectedMime],
            'width' => $width,
            'height' => $height,
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * Store the uploaded media file under the website-scoped directory on public disk.
     * Cleans up stored file if database insertion fails.
     *
     * @throws ValidationException|RuntimeException|Throwable
     */
    public function store(
        UploadedFile $file,
        Website $website,
        ?User $user = null,
        ?string $altText = null,
        string $disk = 'public'
    ): MediaAsset {
        $meta = $this->validateImageFile($file);

        // Generate server-controlled UUID-based filename; never trust client filename for disk path
        $uuid = Str::uuid()->toString();
        $storedFilename = "{$uuid}.{$meta['extension']}";
        $directory = "media/{$website->id}";

        $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $storedFilename);

        if ($storedPath === false) {
            throw new RuntimeException('Failed to store the uploaded file to disk.');
        }

        // Normalize path to relative without leading slashes
        $normalizedPath = ltrim(str_replace('\\', '/', $storedPath), '/');

        // Extract safe original filename without path traversal characters
        $clientOriginal = $file->getClientOriginalName();
        $safeOriginalName = $clientOriginal ? basename($clientOriginal) : $storedFilename;

        try {
            return MediaAsset::create([
                'website_id' => $website->id,
                'storage_disk' => $disk,
                'storage_path' => $normalizedPath,
                'original_filename' => $safeOriginalName,
                'mime_type' => $meta['mime_type'],
                'size_bytes' => $meta['size_bytes'],
                'width' => $meta['width'],
                'height' => $meta['height'],
                'alt_text' => $altText ? trim($altText) : null,
                'uploaded_by' => $user?->id,
            ]);
        } catch (Throwable $e) {
            // Clean up stored file on disk to prevent orphaned files
            Storage::disk($disk)->delete($normalizedPath);
            throw $e;
        }
    }
}
