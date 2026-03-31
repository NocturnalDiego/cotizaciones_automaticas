<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingSettingsRequest extends FormRequest
{
    protected $errorBag = 'brandingSettings';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'issuer_name' => ['nullable', 'string', 'max:255'],
            'issuer_rfc' => ['nullable', 'string', 'max:30'],
            'issuer_business_name' => ['nullable', 'string', 'max:255'],
            'quote_brand_name' => ['nullable', 'string', 'max:255'],
            'brand_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }
}
