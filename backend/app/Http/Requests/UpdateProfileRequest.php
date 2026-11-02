<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'avatar_path' => [
                'sometimes',
                'string',
                Rule::in([
                    '1.png', '2.png', '3.png', '4.png', '5.png',
                    '6.png', '7.png', '8.png', '9.png', '10.png',
                ]),
            ],
            'privacy_settings' => [
                'sometimes',
                'array:name,email,firstName,lastName,city,birthDate,avatar',
            ],
            'privacy_settings.name' => ['sometimes', 'boolean'],
            'privacy_settings.email' => ['sometimes', 'boolean'],
            'privacy_settings.firstName' => ['sometimes', 'boolean'],
            'privacy_settings.lastName' => ['sometimes', 'boolean'],
            'privacy_settings.city' => ['sometimes', 'boolean'],
            'privacy_settings.birthDate' => ['sometimes', 'boolean'],
            'privacy_settings.avatar' => ['sometimes', 'boolean'],
            ];
    }
}
