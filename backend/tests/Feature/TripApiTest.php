<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripApiTest extends TestCase
{
    use RefreshDatabase;

    private function createTrip(User $user, array $attributes = []): Trip
    {
        return $user->trips()->create(array_merge([
            'name' => 'Test trip',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'description' => 'Test description',
        ], $attributes));
    }

    public function test_authenticated_user_can_list_only_their_trips(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownTrip = $this->createTrip($user);
        $this->createTrip($otherUser, ['name' => 'Other user trip']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/trips');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $ownTrip->id])
            ->assertJsonMissing(['name' => 'Other user trip']);
    }

    public function test_trip_list_is_paginated_with_a_maximum_of_five_trips_per_page(): void
    {
        $user = User::factory()->create();
        $trips = [];
        for ($index = 1; $index <= 7; $index++) {
            $trips[] = $this->createTrip($user, ['name' => "Trip {$index}"]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/trips?per_page=5&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 7)
            ->assertJsonFragment(['id' => $trips[5]->id])
            ->assertJsonFragment(['id' => $trips[6]->id]);

        $this->getJson('/api/v1/trips?per_page=6')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_trip_list_filters_before_paginating(): void
    {
        $user = User::factory()->create();
        for ($index = 1; $index <= 6; $index++) {
            $this->createTrip($user, ['name' => "Trip {$index}"]);
        }
        $matchingTrip = $this->createTrip($user, [
            'name' => 'Matching trip',
            'description' => 'Searchable destination',
            'start_date' => '2000-08-01',
            'end_date' => '2000-08-05',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/trips?search=searchable&year=2000&status=past')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingTrip->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_trip_years_returns_distinct_years_for_accessible_trips(): void
    {
        $user = User::factory()->create();
        $this->createTrip($user, ['start_date' => '2024-08-01', 'end_date' => '2025-08-05']);
        $this->createTrip($user, ['start_date' => '2024-09-01', 'end_date' => '2024-09-05']);
        $this->createTrip(User::factory()->create(), [
            'start_date' => '2030-01-01',
            'end_date' => '2030-01-05',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/trips/years')
            ->assertOk()
            ->assertExactJson(['data' => [2025, 2024]]);
    }

    public function test_authenticated_user_can_create_trip(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/trips', [
            'name' => 'New trip',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'description' => 'A trip description',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New trip')
            ->assertJsonPath('data.start_date', '2026-11-01')
            ->assertJsonPath('data.end_date', '2026-11-05');

        $this->assertDatabaseHas('trips', [
            'user_id' => $user->id,
            'name' => 'New trip',
        ]);
    }

    public function test_user_can_view_update_and_delete_their_trip(): void
    {
        $user = User::factory()->create();
        $trip = $this->createTrip($user);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $trip->id]);

        $this->patchJson("/api/v1/trips/{$trip->id}", [
            'name' => 'Updated trip',
        ])->assertOk()
            ->assertJsonFragment(['name' => 'Updated trip']);

        $this->deleteJson("/api/v1/trips/{$trip->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('trips', ['id' => $trip->id]);
    }

    public function test_user_cannot_access_another_users_trip(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $trip = $this->createTrip($otherUser);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/trips/{$trip->id}")->assertForbidden();

        $this->patchJson("/api/v1/trips/{$trip->id}", [
            'name' => 'Unauthorized update',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/trips/{$trip->id}")
            ->assertForbidden();
    }

    public function test_trip_creation_validates_required_fields_and_dates(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/trips', [
            'name' => '',
            'start_date' => 'not-a-date',
            'end_date' => '2026-10-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'start_date']);
    }

    public function test_trip_end_date_must_not_be_before_start_date(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/trips', [
            'name' => 'Invalid date range',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_trip_update_rejects_start_date_after_existing_end_date(): void
    {
        $user = User::factory()->create();
        $trip = $this->createTrip($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/trips/{$trip->id}", [
            'start_date' => '2026-11-06',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
        ]);
    }

    public function test_trip_update_rejects_end_date_before_existing_start_date(): void
    {
        $user = User::factory()->create();
        $trip = $this->createTrip($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/trips/{$trip->id}", [
            'end_date' => '2026-10-31',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
        ]);
    }

    public function test_trip_update_accepts_same_start_and_end_date(): void
    {
        $user = User::factory()->create();
        $trip = $this->createTrip($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/trips/{$trip->id}", [
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-10',
        ])->assertOk()
            ->assertJsonPath('data.start_date', '2026-11-10')
            ->assertJsonPath('data.end_date', '2026-11-10');
    }

    public function test_trip_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/trips')->assertUnauthorized();

        $this->postJson('/api/v1/trips', [
            'name' => 'Unauthenticated trip',
            'start_date' => '2026-11-01',
        ])->assertUnauthorized();
    }
}
