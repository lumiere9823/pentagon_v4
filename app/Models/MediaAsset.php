<?php

namespace App\Models;

use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $website_id
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt_text
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $url
 * @property-read string $formatted_size
 */
#[Fillable([
    'website_id',
    'storage_disk',
    'storage_path',
    'original_filename',
    'mime_type',
    'size_bytes',
    'width',
    'height',
    'alt_text',
    'uploaded_by',
])]
class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $appends = [
        'url',
        'formatted_size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the public URL for the media asset.
     *
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                return Storage::disk($this->storage_disk)->url($this->storage_path);
            }
        );
    }

    /**
     * Get the human-readable formatted file size.
     *
     * @return Attribute<string, never>
     */
    protected function formattedSize(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $bytes = $this->size_bytes;
                if ($bytes >= 1048576) {
                    return number_format($bytes / 1048576, 2).' MB';
                }
                if ($bytes >= 1024) {
                    return number_format($bytes / 1024, 1).' KB';
                }

                return $bytes.' B';
            }
        );
    }
}
