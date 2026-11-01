<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_belongs_to_its_owner(): void
    {
        $user = User::factory()->create();

        $trip = Trip::create([
            'user_id' => $user->id,
            'name' => 'Wycieczka testowa',
            'start_date' => '2026-10-31',
            'end_date' => '2026-11-02',
            'description' => 'Opis podróży',
        ]);

        $this->assertEquals($user->id, $trip->user->id);
        $this->assertTrue($user->trips->contains($trip));
    }

    public function test_owner_can_view_update_and_delete_trip(): void
    {
        $owner = User::factory()->create();

        $trip = Trip::create([
            'user_id' => $owner->id,
            'name' => 'Wycieczka właściciela',
            'start_date' => '2026-10-31',
        ]);

        $this->assertTrue($owner->can('view', $trip));
        $this->assertTrue($owner->can('update', $trip));
        $this->assertTrue($owner->can('delete', $trip));
    }

    public function test_other_user_cannot_view_update_or_delete_trip(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $trip = Trip::create([
            'user_id' => $owner->id,
            'name' => 'Prywatna podróż',
            'start_date' => '2026-10-31',
        ]);

        $this->assertFalse($otherUser->can('view', $trip));
        $this->assertFalse($otherUser->can('update', $trip));
        $this->assertFalse($otherUser->can('delete', $trip));
    }
}
