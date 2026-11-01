<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPlacesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'place_ids' => ['required', 'array', 'min:1'],
            'place_ids.*' => ['required', 'integer', 'distinct', 'exists:places,id'],
        ];
    }
}
