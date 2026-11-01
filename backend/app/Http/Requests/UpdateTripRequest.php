<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip
            && $this->user() !== null
            && $this->user()->can('update', $trip);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('end_date') || $this->input('end_date') === null) {
                return;
            }

            $trip = $this->route('trip');
            $startDate = $this->input('start_date', $trip->start_date?->toDateString());
            $endDate = $this->input('end_date');

            if ($startDate !== null && $endDate < $startDate) {
                $validator->errors()->add(
                    'end_date',
                    'Data zakończenia nie może być wcześniejsza niż data rozpoczęcia.'
                );
            }
        });
    }
}
