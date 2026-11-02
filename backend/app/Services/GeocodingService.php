<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    /**
     * Search for places using Photon API.
     *
     * Results of successful lookups are cached. Provider errors and network
     * failures are not cached, so a transient outage does not hide a place
     * for the whole cache lifetime.
     *
     * @param  string  $query  Search query
     * @return array Array of place results
     */
    public function search(string $query): array
    {
        $cacheKey = 'geocode:'.md5($query);

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $results = $this->fetch($query);

        if ($results === null) {
            return [];
        }

        Cache::put($cacheKey, $results, now()->addHours(24));

        return $results;
    }

    /**
     * Fetch and map results from Photon.
     *
     * @return array|null Null when the provider cannot be used, so the caller
     *                    can distinguish a failure from an empty result set.
     */
    private function fetch(string $query): ?array
    {
        try {
            $response = Http::timeout(5)
                ->get(config('services.photon.url'), [
                    'q' => $query,
                    'limit' => 10,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $features = $response->json('features');

        if (! is_array($features)) {
            return null;
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
