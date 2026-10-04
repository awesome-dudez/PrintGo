<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'login' => ['required_without_all:email,username', 'nullable', 'string'],
            'email' => ['required_without_all:login,username', 'nullable', 'string'],
            'username' => ['required_without_all:login,email', 'nullable', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required_without_all' => 'Email or username is required.',
            'password.required' => 'Password is required.',
        ];
    }
}
