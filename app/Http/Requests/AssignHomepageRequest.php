<?php

namespace App\Http\Requests;

use App\Models\Website;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignHomepageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Website|null $website */
        $website = $this->route('website');

        return $website !== null && ($this->user()?->can('update', $website) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Website|null $website */
        $website = $this->route('website');
        $websiteId = $website?->id;

        return [
            'page_id' => [
                'required',
                'integer',
                Rule::exists('pages', 'id')->where(function ($query) use ($websiteId) {
                    $query->where('website_id', $websiteId);
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page_id.exists' => 'The selected page does not belong to this website.',
        ];
    }
}
