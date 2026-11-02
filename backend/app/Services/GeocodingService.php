<?php

namespace App\Services;

use App\Exceptions\GeocodingProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
}
