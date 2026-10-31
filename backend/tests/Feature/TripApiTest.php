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
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $ownTrip->id])
            ->assertJsonMissing(['name' => 'Other user trip']);
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
            ->assertJsonFragment([
                'name' => 'New trip',
                'user_id' => $user->id,
            ]);

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

    public function test_trip_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/trips')->assertUnauthorized();

        $this->postJson('/api/v1/trips', [
            'name' => 'Unauthenticated trip',
            'start_date' => '2026-11-01',
        ])->assertUnauthorized();
    }
}
