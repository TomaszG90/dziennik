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

        return [
            'distance' => $distance,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [$fromLongitude, $fromLatitude],
                    [$toLongitude, $toLatitude],
                ],
            ],
        ];
    }
}
