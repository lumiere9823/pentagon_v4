<?php

namespace App\Models;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Enums\PageReleaseStatus;
use Database\Factories\PageReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $page_id
 * @property int $page_revision_id
 * @property int $release_number
 * @property PageReleaseStatus $status
 * @property int $schema_version
 * @property int $renderer_version
 * @property array<string, mixed>|null $manifest_json
 * @property string|null $directory_path
 * @property string|null $error_message
 * @property int|null $created_by
 * @property Carbon|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Page|null $page
 * @property-read PageRevision|null $revision
 * @property-read User|null $creator
 */
#[Fillable([
    'id',
    'page_id',
    'page_revision_id',
    'release_number',
    'status',
    'schema_version',
    'renderer_version',
    'manifest_json',
    'directory_path',
    'error_message',
    'created_by',
    'activated_at',
])]
class PageRelease extends Model
{
    /** @use HasFactory<PageReleaseFactory> */
    use HasFactory, HasUuids;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PageReleaseStatus::PENDING,
        'schema_version' => DocumentLimits::CURRENT_SCHEMA_VERSION,
        'renderer_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => PageReleaseStatus::class,
            'release_number' => 'integer',
            'schema_version' => 'integer',
            'renderer_version' => 'integer',
            'manifest_json' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<PageRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'page_revision_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query to only include active releases.
     *
     * @param  Builder<PageRelease>  $query
     * @return Builder<PageRelease>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PageReleaseStatus::ACTIVE);
    }
}
