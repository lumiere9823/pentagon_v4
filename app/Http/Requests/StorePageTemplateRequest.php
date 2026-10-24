<?php

namespace App\Http\Requests;

use App\Models\PageTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorized via controller policy check
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', Rule::in(PageTemplate::ALLOWED_CATEGORIES)],
            'document_json' => ['required', 'array'],
            'thumbnail_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
