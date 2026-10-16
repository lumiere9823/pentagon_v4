<?php

namespace App\Http\Requests;

use App\Enums\WebsiteRole;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWebsiteMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Website|null $website */
        $website = $this->route('website');

        return $website !== null && ($this->user()?->can('create', [WebsiteMembership::class, $website]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'exists:users,email',
            ],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var Website|null $website */
            $website = $this->route('website');

            if ($website && $this->email) {
                $user = User::where('email', $this->email)->first();
                if ($user && $website->hasMember($user)) {
                    $validator->errors()->add('email', 'This user is already a member of this website.');
                }
            }
        });
    }
}
