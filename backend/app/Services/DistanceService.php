<?php

namespace App\Services;

class DistanceService
{
    public function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $this->validateCoordinates($lat1, $lon1);
        $this->validateCoordinates($lat2, $lon2);

        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
              cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
              sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    public function calculateStraightLineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $this->validateCoordinates($lat1, $lon1);
        $this->validateCoordinates($lat2, $lon2);

        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);

        $x = $dLon * cos(($lat1Rad + $lat2Rad) / 2);
        $y = $dLat;

        return round($earthRadius * sqrt($x * $x + $y * $y), 2);
    }

    private function validateCoordinates(float $lat, float $lon): void
    {
        if ($lat < -90 || $lat > 90) {
            throw new \InvalidArgumentException('Latitude must be between -90 and 90');
        }
        if ($lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException('Longitude must be between -180 and 180');
        }
    }
}
