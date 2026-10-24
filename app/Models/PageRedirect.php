<?php

namespace App\Models;

use Database\Factories\PageRedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $website_id
 * @property string $source_slug
 * @property string $target_slug
 * @property int $status_code
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Website $website
 */
#[Fillable([
    'website_id',
    'source_slug',
    'target_slug',
    'status_code',
    'is_active',
])]
class PageRedirect extends Model
{
    /** @use HasFactory<PageRedirectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
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
}
