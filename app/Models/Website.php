<?php

namespace App\Models;

use App\Enums\WebsiteRole;
use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property array<string, mixed>|null $settings_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'status', 'settings_json'])]
class Website extends Model
{
    /** @use HasFactory<WebsiteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<WebsiteMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(WebsiteMembership::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'website_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * @return HasMany<MediaAsset, $this>
     */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    /**
     * Check if user is a member with optional specific roles.
     *
     * @param  list<WebsiteRole|string>  $roles
     */
    public function hasMember(User|int $user, array $roles = []): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        $query = $this->memberships()->where('user_id', $userId);

        if (! empty($roles)) {
            $roleValues = array_map(fn ($r) => $r instanceof WebsiteRole ? $r->value : (string) $r, $roles);
            $query->whereIn('role', $roleValues);
        }

        return $query->exists();
    }

    /**
     * Get member role for given user.
     */
    public function getMemberRole(User|int $user): ?WebsiteRole
    {
        $userId = $user instanceof User ? $user->id : $user;

        /** @var WebsiteMembership|null $membership */
        $membership = $this->memberships()->where('user_id', $userId)->first();

        return $membership?->role;
    }
}
