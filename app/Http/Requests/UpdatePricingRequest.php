<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pricing' => ['required', 'array'],
            'pricing.*' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pricing.required' => 'The pricing array is required.',
            'pricing.array' => 'Pricing data must be an object/array of key-value pairs.',
            'pricing.*.numeric' => 'Each pricing value must be a non-negative number.',
        ];
    }
}
