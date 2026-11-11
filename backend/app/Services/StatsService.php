<?php

namespace App\Services;

use App\Models\Leg;
use App\Models\Trip;
use App\Models\User;

class StatsService
{
    public function __construct(
        private readonly LegCalculationService $legCalculationService,
    ) {}

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

        $totalDistance = 0;
        $distanceByTransport = [];

        foreach ($legs as $leg) {
            $distance = $leg->distance;

            if ($distance === null) {
                $fromPlace = $leg->fromPlace;
                $toPlace = $leg->toPlace;

                if ($fromPlace && $toPlace) {
                    $calculated = $this->legCalculationService->calculate(
                        $fromPlace,
                        $toPlace,
                        $leg->transport
                    );
                    $distance = $calculated['distance'];
                } else {
                    $distance = 0;
                }
            }

            $totalDistance += $distance;

            $transport = $leg->transport;
            if (!isset($distanceByTransport[$transport])) {
                $distanceByTransport[$transport] = 0;
            }
            $distanceByTransport[$transport] += $distance;
        }

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
