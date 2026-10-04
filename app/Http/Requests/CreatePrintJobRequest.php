<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePrintJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation by normalizing casing.
     */
    protected function prepareForValidation(): void
    {
        $updates = [];

        if ($this->has('paper_size')) {
            $val = strtolower(trim((string) $this->input('paper_size')));
            $map = [
                'a4' => 'A4',
                'letter' => 'Letter',
                'legal' => 'Legal',
            ];
            $updates['paper_size'] = $map[$val] ?? $this->input('paper_size');
        }

        if ($this->has('color_mode')) {
            $val = strtolower(trim((string) $this->input('color_mode')));
            $map = [
                'color' => 'color',
                'colour' => 'color',
                'bw' => 'bw',
                'black_and_white' => 'bw',
            ];
            $updates['color_mode'] = $map[$val] ?? $this->input('color_mode');
        }

        if ($this->has('paper_type')) {
            $updates['paper_type'] = strtolower(trim((string) $this->input('paper_type')));
        }

        if ($this->has('orientation')) {
            $updates['orientation'] = strtolower(trim((string) $this->input('orientation')));
        }

        if (!empty($updates)) {
            $this->merge($updates);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_id' => ['required', 'exists:uploaded_files,id'],
            'session_id' => ['nullable', 'string'],
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
            'paper_size' => ['required', 'string', 'in:A4,Letter,Legal'],
            'paper_type' => ['required', 'string', 'in:bond,glossy,matte,photo'],
            'orientation' => ['required', 'string', 'in:portrait,landscape'],
            'color_mode' => ['required', 'string', 'in:color,bw'],
            'duplex' => ['required', 'boolean'],
            'adjustments' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file_id.required' => 'The file ID is required.',
            'file_id.exists' => 'The selected file does not exist.',
            'copies.required' => 'The number of copies is required.',
            'copies.min' => 'Copies must be at least 1.',
            'copies.max' => 'Copies cannot exceed 100.',
            'paper_size.in' => 'Paper size must be one of: A4, Letter, Legal.',
            'paper_type.in' => 'Paper type must be one of: bond, glossy, matte, photo.',
            'orientation.in' => 'Orientation must be portrait or landscape.',
            'color_mode.in' => 'Color mode must be color or bw.',
            'duplex.boolean' => 'Duplex option must be true or false.',
        ];
    }
}
