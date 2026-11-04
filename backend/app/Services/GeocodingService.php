<?php

namespace App\Services;

use App\Exceptions\GeocodingProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class GeocodingService
{
    /**
     * Search for places using Photon API.
     *
     * Successful lookups are cached. Provider errors and network failures
     * are reported to the caller instead of being cached, so a transient
     * outage is not presented as a search without results.
     *
     * @param  string  $query  Search query
     * @return array Array of place results
     *
     * @throws GeocodingProviderException When the provider cannot be used.
     */
    public function search(string $query): array
    {
        $cacheKey = 'geocode:'.md5($query);

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $results = $this->fetch($query);

        Cache::put($cacheKey, $results, now()->addHours(24));

        return $results;
    }

    /**
     * Resolve the ISO 3166-1 alpha-2 country code of a coordinate pair
     * using the Photon reverse endpoint.
     *
     * The provider value is returned as-is and the caller normalizes it
     * before storing. Successful lookups, including coordinates without a
     * country, are cached per rounded coordinate so nearby points share one
     * request. Provider errors are reported instead of being cached.
     *
     * @throws GeocodingProviderException When the provider cannot be used.
     */
    public function reverseCountryCode(float $latitude, float $longitude): ?string
    {
        $cacheKey = 'geocode:reverse:'.md5($this->coordinateKey($latitude, $longitude));

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached['country_code'];
        }

        $countryCode = $this->fetchCountryCode($latitude, $longitude);

        Cache::put($cacheKey, ['country_code' => $countryCode], now()->addHours(24));

        return $countryCode;
    }

    /**
     * Fetch and map results from Photon.
     *
     * @throws GeocodingProviderException When the provider is unreachable or
     *                                    returns an unusable payload.
     */
    private function fetch(string $query): array
    {
        try {
            $response = Http::timeout(5)
                ->get(config('services.photon.url'), [
                    'q' => $query,
                    'limit' => 10,
                ]);
        } catch (ConnectionException) {
            throw GeocodingProviderException::unavailable();
        }

        if (! $response->successful()) {
            throw GeocodingProviderException::unavailable();
        }

        $features = $response->json('features');

        if (! is_array($features)) {
            throw GeocodingProviderException::unavailable();
        }

        return collect($features)
            ->map(function ($feature) {
                $coordinates = $feature['geometry']['coordinates'] ?? null;
                $properties = $feature['properties'] ?? [];

                if (! $coordinates) {
                    return null;
                }

                return [
                    'name' => $properties['name'] ?? null,
                    'latitude' => $coordinates[1] ?? null,
                    'longitude' => $coordinates[0] ?? null,
                    'country_code' => $properties['countrycode'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Fetch the country code reported for the given coordinates.
     *
     * @throws GeocodingProviderException When the provider is unreachable or
     *                                    returns an unusable payload.
     */
    private function fetchCountryCode(float $latitude, float $longitude): ?string
    {
        $this->ensureLookupBudget();

        try {
            $response = Http::timeout(5)
                ->get(config('services.photon.reverse_url'), [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'limit' => 1,
                ]);
        } catch (ConnectionException) {
            throw GeocodingProviderException::unavailable();
        }

        if (! $response->successful()) {
            throw GeocodingProviderException::unavailable();
        }

        $features = $response->json('features');

        if (! is_array($features)) {
            throw GeocodingProviderException::unavailable();
        }

        if ($features === []) {
            return null;
        }

        $feature = $features[0];

        if (! is_array($feature)) {
            throw GeocodingProviderException::unavailable();
        }

        $properties = $feature['properties'] ?? null;

        if ($properties !== null && ! is_array($properties)) {
            throw GeocodingProviderException::unavailable();
        }

        $countryCode = $properties['countrycode'] ?? null;

        if ($countryCode !== null && ! is_string($countryCode)) {
            throw GeocodingProviderException::unavailable();
        }

        return $countryCode;
    }

    /**
     * Build a stable cache key fragment for a coordinate pair.
     *
     * Rounding to four decimals keeps the key stable for points within about
     * ten meters, which always share the same country.
     */
    private function coordinateKey(float $latitude, float $longitude): string
    {
        return round($latitude, 4).','.round($longitude, 4);
    }

    /**
     * Keep reverse lookups within the provider budget of the current caller.
     *
     * The budget is consumed only when the provider is actually queried, so
     * cached coordinates stay free. Reaching the limit is reported like any
     * other provider failure, which lets the caller store the point without a
     * country code instead of failing the request.
     *
     * @throws GeocodingProviderException When the caller has no budget left.
     */
    private function ensureLookupBudget(): void
    {
        $key = 'geocode:reverse:'.(auth()->id() ?? 'ip:'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, $this->reverseLookupLimit())) {
            throw GeocodingProviderException::rateLimited();
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * Number of reverse lookups allowed per caller within a minute.
     */
    private function reverseLookupLimit(): int
    {
        return max(1, (int) config('services.photon.reverse_lookups_per_minute'));
    }
}
