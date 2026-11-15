<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('create', Trip::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
                return;
            }

            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if (
                $startDate !== null
                && $endDate !== null
                && Carbon::parse($endDate)->gt(Carbon::parse($startDate)->addDays(360))
            ) {
                $validator->errors()->add(
                    'end_date',
                    'Wycieczka nie może trwać dłużej niż 360 dni.'
                );
            }
        });
    }
}
