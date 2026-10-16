<?php

namespace App\Models;

use App\Enums\WebsiteRole;
use Database\Factories\WebsiteMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $website_id
 * @property int $user_id
 * @property WebsiteRole $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['website_id', 'user_id', 'role'])]
class WebsiteMembership extends Model
{
    /** @use HasFactory<WebsiteMembershipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => WebsiteRole::class,
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
