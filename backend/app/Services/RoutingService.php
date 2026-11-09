<?php

namespace App\Services;

use App\Exceptions\RoutingProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class RoutingService
{
    /**
     * Calculate a car route using OpenRouteService.
     *
     * @return array{
     *     distance: float,
     *     geometry: array{
     *         type: string,
     *         coordinates: array
     *     }
     *
     * @throws InvalidArgumentException
     * @throws RoutingProviderException
     */
    public function calculateCarRoute(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): array {
        $this->validateCoordinates($fromLatitude, $fromLongitude);
        $this->validateCoordinates($toLatitude, $toLongitude);

        $apiKey = config('services.routing.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw RoutingProviderException::unavailable();
        }

        $url = rtrim((string) config('services.routing.url'), '/').'/geojson';

        try {
            $response = Http::timeout(
                max(1, (int) config('services.routing.timeout', 10))
            )
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/geo+json',
                ])
                ->post($url, [
                    'coordinates' => [
                        [$fromLongitude, $fromLatitude],
                        [$toLongitude, $toLatitude],
                    ],
                ]);
        } catch (ConnectionException) {
            throw RoutingProviderException::unavailable();
        }

        if ($response->status() === 429) {
            throw RoutingProviderException::rateLimited();
        }

        if (! $response->successful()) {
            throw RoutingProviderException::unavailable();
        }

        return $this->normalizeResponse($response->json());
    }

    /**
     * Validate a latitude and longitude pair.
     */
    private function validateCoordinates(float $latitude, float $longitude): void
    {
        if (
            ! is_finite($latitude)
            || $latitude < -90
            || $latitude > 90
        ) {
            throw new InvalidArgumentException(
                'Latitude must be between -90 and 90.'
            );
        }

        if (
            ! is_finite($longitude)
            || $longitude < -180
            || $longitude > 180
        ) {
            throw new InvalidArgumentException(
                'Longitude must be between -180 and 180.'
            );
        }
    }

    /**
     * Validate and normalize the ORS GeoJSON response.
     *
     * @return array{
     *     distance: float,
     *     geometry: array{
     *         type: string,
     *         coordinates: array
     *     }
     *
     * @throws RoutingProviderException
     */
    private function normalizeResponse(mixed $data): array
    {
        if (! is_array($data)) {
            throw RoutingProviderException::unavailable();
        }

        $feature = $data['features'][0] ?? null;

        if (! is_array($feature)) {
            throw RoutingProviderException::unavailable();
        }

        $distance = $feature['properties']['summary']['distance'] ?? null;
        $geometry = $feature['geometry'] ?? null;

        if (
            ! is_numeric($distance)
            || ! is_finite((float) $distance)
            || (float) $distance < 0
        ) {
            throw RoutingProviderException::unavailable();
        }

        if (
            ! is_array($geometry)
            || ($geometry['type'] ?? null) !== 'LineString'
            || ! is_array($geometry['coordinates'] ?? null)
            || count($geometry['coordinates']) < 2
        ) {
            throw RoutingProviderException::unavailable();
        }

        foreach ($geometry['coordinates'] as $coordinate) {
            if (
                ! is_array($coordinate)
                || count($coordinate) < 2
                || ! is_numeric($coordinate[0] ?? null)
                || ! is_numeric($coordinate[1] ?? null)
                || ! is_finite((float) $coordinate[0])
                || ! is_finite((float) $coordinate[1])
                || (float) $coordinate[0] < -180
                || (float) $coordinate[0] > 180
                || (float) $coordinate[1] < -90
                || (float) $coordinate[1] > 90
            ) {
                throw RoutingProviderException::unavailable();
            }
        }

        return [
            'distance' => (float) $distance,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $geometry['coordinates'],
            ],
        ];
    }
}
