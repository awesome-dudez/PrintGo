<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:51200', // 50MB
                'mimes:pdf,docx,pptx,jpg,jpeg,png,heic,webp',
            ],
            'session_id' => [
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file must be uploaded.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.max' => 'The file size may not exceed 50MB.',
            'file.mimes' => 'The file must be a file of type: pdf, docx, pptx, jpg, jpeg, png, heic, webp.',
        ];
    }
}
