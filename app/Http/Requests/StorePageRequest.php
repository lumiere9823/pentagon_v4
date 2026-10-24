<?php

namespace App\Http\Requests;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Website|null $website */
        $website = $this->route('website');

        return $website !== null && ($this->user()?->can('create', [Page::class, $website]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Website $website */
        $website = $this->route('website');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('pages', 'slug')->where('website_id', $website->id),
            ],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('page_templates', 'id')->where(function ($query) use ($website) {
                    $query->where('is_active', true)
                        ->where(function ($sub) use ($website) {
                            $sub->where('website_id', $website->id)
                                ->orWhereNull('website_id');
                        });
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
            'slug.unique' => 'A page with this slug already exists on this website.',
            'template_id.exists' => 'The selected template is unavailable or does not belong to this website.',
        ];
    }
}
