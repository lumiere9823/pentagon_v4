<?php

namespace App\Models;

use App\Enums\PageReleaseStatus;
use App\Enums\PageStatus;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $website_id
 * @property string $title
 * @property string $slug
 * @property bool $is_homepage
 * @property array<string, mixed>|null $document_json
 * @property array<string, mixed>|null $seo_metadata
 * @property PageStatus $status
 * @property int $lock_version
 * @property int|null $published_revision_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PageRevision> $revisions
 * @property-read PageRevision|null $publishedRevision
 * @property-read Collection<int, PageRelease> $releases
 * @property-read PageRelease|null $activeRelease
 */
#[Fillable([
    'website_id',
    'title',
    'slug',
    'is_homepage',
    'document_json',
    'seo_metadata',
    'status',
    'lock_version',
    'published_revision_id',
    'created_by',
    'updated_by',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'is_homepage' => 'boolean',
            'document_json' => 'array',
            'seo_metadata' => 'array',
            'lock_version' => 'integer',
            'published_revision_id' => 'integer',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<PageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->orderBy('revision_number', 'desc');
    }

    /**
     * @return BelongsTo<PageRevision, $this>
     */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'published_revision_id');
    }

    /**
     * @return HasMany<PageRelease, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(PageRelease::class)->orderBy('release_number', 'desc');
    }

    /**
     * @return HasOne<PageRelease, $this>
     */
    public function activeRelease(): HasOne
    {
        return $this->hasOne(PageRelease::class)->where('status', PageReleaseStatus::ACTIVE);
    }
}
