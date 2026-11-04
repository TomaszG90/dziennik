<?php

namespace App\Services;

use App\Exceptions\GeocodingProviderException;
use App\Support\CountryCodes;
use Illuminate\Support\Facades\Log;

class CountryCodeResolver
{
    public function __construct(
        private GeocodingService $geocodingService
    ) {}

    /**
     * Resolve the ISO 3166-1 alpha-2 country code of a point.
     *
     * A code supported by the application (for example a geocoding result
     * chosen by the user) takes precedence and skips the provider call.
     * Otherwise the country is looked up from the coordinates. Codes the
     * application does not support are treated as no country, whether they
     * come from the caller or from the provider. An unavailable provider
     * must not block saving the point, so it yields null instead of an
     * error.
     */
    public function resolve(?string $provided, float $latitude, float $longitude): ?string
    {
        $countryCode = CountryCodes::normalize($provided);

        if ($countryCode !== null) {
            return $countryCode;
        }

        try {
            return CountryCodes::normalize(
                $this->geocodingService->reverseCountryCode($latitude, $longitude)
            );
        } catch (GeocodingProviderException $exception) {
            Log::warning('Country code could not be resolved from coordinates.', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'exception' => $exception,
            ]);

            return null;
        }
    }
}
