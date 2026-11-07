<?php

namespace App\Support;

class CountryCodes
{
    /**
     * Normalize a country code to upper case and accept only codes the
     * application supports, so an unusable value is treated as no country.
     *
     * The supported list holds the codes assigned in ISO 3166-1 plus XK,
     * which the geocoding provider reports for Kosovo.
     */
    public static function normalize(mixed $countryCode): ?string
    {
        if (! is_string($countryCode)) {
            return null;
        }

        $countryCode = strtoupper(trim($countryCode));

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return null;
        }

        return in_array($countryCode, config('countries.codes'), true) ? $countryCode : null;
    }
}
