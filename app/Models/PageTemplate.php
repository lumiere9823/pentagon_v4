<?php

namespace App\Models;

use Database\Factories\PageTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $website_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $category
 * @property int $schema_version
 * @property array<string, mixed> $document_json
 * @property string|null $thumbnail_path
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Website|null $website
 * @property-read User|null $creator
 */
class PageTemplate extends Model
{
    /** @use HasFactory<PageTemplateFactory> */
    use HasFactory;

    /**
     * Authoritative whitelist of supported template categories.
     *
     * @var list<string>
     */
    public const ALLOWED_CATEGORIES = [
        'general',
        'blank',
        'landing',
        'portfolio',
        'lead_gen',
        'business',
    ];

    protected $fillable = [
        'website_id',
        'name',
        'slug',
        'description',
        'category',
        'schema_version',
        'document_json',
        'thumbnail_path',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_json' => 'array',
            'schema_version' => 'integer',
            'is_active' => 'boolean',
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
     * Scope query to global system starter templates.
     *
     * @param  Builder<PageTemplate>  $query
     * @return Builder<PageTemplate>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('website_id');
    }

    /**
     * Scope query to templates available for a given website (global + website-scoped).
     *
     * @param  Builder<PageTemplate>  $query
     * @return Builder<PageTemplate>
     */
    public function scopeForWebsite(Builder $query, int $websiteId): Builder
    {
        return $query->where(function (Builder $q) use ($websiteId) {
            $q->where('website_id', $websiteId)
                ->orWhereNull('website_id');
        });
    }

    /**
     * Scope query to active templates.
     *
     * @param  Builder<PageTemplate>  $query
     * @return Builder<PageTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query by category.
     *
     * @param  Builder<PageTemplate>  $query
     * @return Builder<PageTemplate>
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Check if this template is a global system template.
     */
    public function isGlobal(): bool
    {
        return $this->website_id === null;
    }
}
