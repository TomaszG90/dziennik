<?php

namespace App\Rules;

use App\Support\CountryCodes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CountryCode implements ValidationRule
{
    /**
     * Accept only country codes supported by the application, ignoring case
     * so a lower case code is normalized rather than rejected.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (CountryCodes::normalize($value) === null) {
            $fail('The selected country code is invalid.');
        }
    }
}
