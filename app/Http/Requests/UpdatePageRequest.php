<?php

namespace App\Http\Requests;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Website|null $website */
        $website = $this->route('website');
        /** @var Page|null $page */
        $page = $this->route('page');

        // Prevent IDOR: Page must belong to this website
        if ($website === null || $page === null || $page->website_id !== $website->id) {
            abort(404);
        }

        return $this->user()?->can('update', $page) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Website $website */
        $website = $this->route('website');
        /** @var Page $page */
        $page = $this->route('page');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('pages', 'slug')->where('website_id', $website->id)->ignore($page->id),
            ],
            'status' => [
                'required',
                Rule::enum(PageStatus::class),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'A page with this slug already exists on this website.',
        ];
    }
}
