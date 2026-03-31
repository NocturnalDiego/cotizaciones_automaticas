<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference_code' => ['nullable', 'string', 'max:120'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_rfc' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:120'],
            'issued_at' => ['required', 'date'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'item_description' => ['required', 'array', 'min:1'],
            'item_description.*' => ['nullable', 'string', 'max:1000'],
            'item_quantity' => ['required', 'array', 'min:1'],
            'item_quantity.*' => ['nullable', 'numeric', 'min:0.01'],
            'item_unit_price' => ['required', 'array', 'min:1'],
            'item_unit_price.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
