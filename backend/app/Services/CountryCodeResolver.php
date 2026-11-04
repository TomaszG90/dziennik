<?php

namespace App\Services;

use App\Exceptions\GeocodingProviderException;
use Illuminate\Support\Facades\Log;

class CountryCodeResolver
{
    public function __construct(
        private GeocodingService $geocodingService
    ) {}

    /**
     * Resolve the ISO 3166-1 alpha-2 country code of a point.
     *
     * A code supplied by the caller (for example a geocoding result chosen
     * by the user) takes precedence and skips the provider call. Otherwise
     * the country is looked up from the coordinates. An unavailable provider
     * must not block saving the point, so it yields null instead of an error.
     */
    public function resolve(?string $provided, float $latitude, float $longitude): ?string
    {
        $countryCode = $this->normalize($provided);

        if ($countryCode !== null) {
            return $countryCode;
        }

        try {
            return $this->normalize(
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

    /**
     * Normalize a country code to upper case and reject unusable values.
     */
    private function normalize(?string $countryCode): ?string
    {
        if ($countryCode === null) {
            return null;
        }

        $countryCode = strtoupper(trim($countryCode));

        return preg_match('/^[A-Z]{2}$/', $countryCode) === 1 ? $countryCode : null;
    }
}
