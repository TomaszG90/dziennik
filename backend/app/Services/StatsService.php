<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\User;

class StatsService
{
    public function calculateStats(User $user, ?int $year = null): array
    {
        $tripsQuery = $user->trips();

        if ($year !== null) {
            $tripsQuery->where(function ($query) use ($year) {
                $query->whereYear('start_date', $year)
                    ->orWhereYear('end_date', $year);
            });
        }

        $trips = $tripsQuery->get();

        $tripsCount = $trips->count();

        $countries = $trips
            ->flatMap(fn (Trip $trip) => $trip->places->pluck('country_code'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $legs = $trips->flatMap(fn (Trip $trip) => $trip->legs);

        $totalDistance = $legs->sum('distance');

        $distanceByTransport = $legs
            ->groupBy('transport')
            ->map(fn ($legsGroup) => $legsGroup->sum('distance'))
            ->toArray();

        $placesCount = $trips->sum(fn (Trip $trip) => $trip->places->count());

        return [
            'trips_count' => $tripsCount,
            'countries' => $countries,
            'total_distance' => $totalDistance,
            'distance_by_transport' => $distanceByTransport,
            'places_count' => $placesCount,
        ];
    }
}
