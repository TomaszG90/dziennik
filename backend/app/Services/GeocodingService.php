<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    /**
     * Search for places using Photon API.
     *
     * @param string $query Search query
     * @return array Array of place results
     */
    public function search(string $query): array
    {
        $cacheKey = 'geocode:' . md5($query);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($query) {
            $response = Http::timeout(5)
                ->get(config('services.photon.url'), [
                    'q' => $query,
                    'limit' => 10,
                ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            if (!isset($data['features'])) {
                return [];
            }

            return collect($data['features'])
                ->map(function ($feature) {
                    $coordinates = $feature['geometry']['coordinates'] ?? null;
                    $properties = $feature['properties'] ?? [];

                    if (!$coordinates) {
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
        });
    }
}
