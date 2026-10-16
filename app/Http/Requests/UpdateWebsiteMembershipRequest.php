<?php

namespace App\Http\Requests;

use App\Enums\WebsiteRole;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Website|null $website */
        $website = $this->route('website');
        /** @var WebsiteMembership|null $membership */
        $membership = $this->route('membership');

        if (! $website || ! $membership) {
            abort(404);
        }

        // Prevent IDOR: Membership must belong to this website route
        if ($membership->website_id !== $website->id) {
            abort(404);
        }

        return $this->user()?->can('update', [$membership, $website]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::in([WebsiteRole::EDITOR->value, WebsiteRole::VIEWER->value]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Membership role must be either editor or viewer.',
        ];
    }
}
