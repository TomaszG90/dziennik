<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLegRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tripId = $this->route('trip')->id;

        return [
            'from_place_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('places', 'id')->where('trip_id', $tripId),
            ],
            'to_place_id' => [
                'sometimes',
                'required',
                'integer',
                'different:from_place_id',
                Rule::exists('places', 'id')->where('trip_id', $tripId),
            ],
            'transport' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['walking', 'car', 'plane', 'train', 'other']),
            ],
        ];
    }
}
