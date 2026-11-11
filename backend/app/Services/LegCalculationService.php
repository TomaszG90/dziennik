<?php

namespace App\Services;

use App\Models\Place;
use InvalidArgumentException;

class LegCalculationService
{
    public function __construct(
        private readonly DistanceService $distanceService,
        private readonly RoutingService $routingService,
    ) {}

    /**
     * @return array{
     *     distance: float,
     *     geometry: array{
     *         type: string,
     *         coordinates: array
     *     }
     * }
     */
    public function calculate(
        Place $from,
        Place $to,
        string $transport
    ): array {
        $fromLatitude = (float) $from->latitude;
        $fromLongitude = (float) $from->longitude;
        $toLatitude = (float) $to->latitude;
        $toLongitude = (float) $to->longitude;

        if ($transport === 'car') {
            $route = $this->routingService->calculateCarRoute(
                $fromLatitude,
                $fromLongitude,
                $toLatitude,
                $toLongitude
            );

            return [
                'distance' => round($route['distance'] / 1000, 2),
                'geometry' => $route['geometry'],
            ];
        }

        $distance = match ($transport) {
            'plane' => $this->distanceService->calculateHaversineDistance(
                $fromLatitude,
                $fromLongitude,
                $toLatitude,
                $toLongitude
            ),
            'walking', 'train', 'other' => $this->distanceService
                ->calculateStraightLineDistance(
                    $fromLatitude,
                    $fromLongitude,
                    $toLatitude,
                    $toLongitude
                ),
            default => throw new InvalidArgumentException(
                'Unsupported transport type.'
            ),
        };

        $coordinates = $transport === 'plane'
            ? $this->createPlaneArc(
                $fromLatitude,
                $fromLongitude,
                $toLatitude,
                $toLongitude
            )
            : [
                [$fromLongitude, $fromLatitude],
                [$toLongitude, $toLatitude],
            ];

        return [
            'distance' => $distance,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $coordinates,
            ],
        ];
    }

    /**
     * Create a sampled visual arc for flight legs, using [longitude, latitude]
     * GeoJSON coordinates and the shortest path across the date line.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function createPlaneArc(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): array {
        $longitudeDelta = fmod($toLongitude - $fromLongitude + 540, 360) - 180;
        $distance = hypot($toLatitude - $fromLatitude, $longitudeDelta);
        $arcHeight = min(24, $distance * 0.18);
        $coordinates = [];

        for ($step = 0; $step <= 32; $step++) {
            $progress = $step / 32;
            $latitude = $fromLatitude + ($toLatitude - $fromLatitude) * $progress;
            $longitude = $fromLongitude + $longitudeDelta * $progress;
            $arc = sin(M_PI * $progress) * $arcHeight;

            $coordinates[] = [
                $longitude,
                max(-85, min(85, $latitude + $arc)),
            ];
        }

        return $coordinates;
    }
}
