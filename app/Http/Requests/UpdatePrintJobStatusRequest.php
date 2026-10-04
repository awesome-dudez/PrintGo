<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrintJobStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:queued,printing,completed,failed,cancelled'],
            'pages_completed' => ['nullable', 'integer', 'min:0'],
            'error_message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Job status is required.',
            'status.in' => 'Job status must be one of: queued, printing, completed, failed, cancelled.',
        ];
    }
}
