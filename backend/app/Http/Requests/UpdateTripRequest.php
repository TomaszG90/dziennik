<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Carbon\Carbon;
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
            if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
                return;
            }

            $trip = $this->route('trip');
            $startDate = $this->input('start_date', $trip->start_date?->toDateString());
            $endDate = $this->input('end_date', $trip->end_date?->toDateString());

            if ($startDate !== null && $endDate !== null && Carbon::parse($endDate)->lt(Carbon::parse($startDate))) {
                $validator->errors()->add(
                    'end_date',
                    'Data zakończenia nie może być wcześniejsza niż data rozpoczęcia.'
                );
            }
        });
    }
}
