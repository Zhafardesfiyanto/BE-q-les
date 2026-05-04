<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinClassRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]{6,8}$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode kelas wajib diisi.',
            'code.string' => 'Kode kelas harus berupa teks.',
            'code.regex' => 'Format kode kelas tidak valid. Kode harus terdiri dari 6-8 karakter alfanumerik huruf besar.',
        ];
    }
}