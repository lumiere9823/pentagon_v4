<?php

namespace App\Http\Requests;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Foundation\Http\FormRequest;

class SavePageContentRequest extends FormRequest
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
        return [
            'document_json' => ['required', 'array'],
            'seo_metadata' => ['nullable', 'array'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'change_summary' => ['nullable', 'string', 'max:255'],
            'is_autosave' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_json.required' => 'The document tree payload is required.',
            'document_json.array' => 'The document tree must be an associative JSON object.',
            'lock_version.required' => 'The optimistic lock version token is required.',
            'lock_version.integer' => 'The lock version token must be an integer.',
        ];
    }
}
