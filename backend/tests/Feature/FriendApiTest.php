<?php

namespace Tests\Feature;

use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_friends_returns_accepted_relationships(): void
    {
        $user1 = User::factory()->publicProfile()->create(['name' => 'User One']);
        $user2 = User::factory()->publicProfile()->create(['name' => 'User Two']);

        FriendRequest::create([
            'sender_id' => $user1->id,
            'recipient_id' => $user2->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($user1)->getJson('/api/v1/friends');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user2->id);
    }

    public function test_get_friends_does_not_include_pending_requests(): void
    {
        $user1 = User::factory()->publicProfile()->create();
        $user2 = User::factory()->publicProfile()->create();

        FriendRequest::create([
            'sender_id' => $user1->id,
            'recipient_id' => $user2->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user1)->getJson('/api/v1/friends')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_get_friends_is_symmetric(): void
    {
        $user1 = User::factory()->publicProfile()->create();
        $user2 = User::factory()->publicProfile()->create();

        FriendRequest::create([
            'sender_id' => $user1->id,
            'recipient_id' => $user2->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($user2)->getJson('/api/v1/friends')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user1->id);
    }

    public function test_remove_friend_deletes_relationship(): void
    {
        $user1 = User::factory()->publicProfile()->create();
        $user2 = User::factory()->publicProfile()->create();

        FriendRequest::create([
            'sender_id' => $user1->id,
            'recipient_id' => $user2->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($user1)->deleteJson("/api/v1/friends/{$user2->id}")
            ->assertNoContent();

        $this->actingAs($user1)->getJson('/api/v1/friends')
            ->assertJsonCount(0, 'data');
    }

    public function test_remove_friend_is_symmetric(): void
    {
        $user1 = User::factory()->publicProfile()->create();
        $user2 = User::factory()->publicProfile()->create();

        FriendRequest::create([
            'sender_id' => $user1->id,
            'recipient_id' => $user2->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($user2)->deleteJson("/api/v1/friends/{$user1->id}")
            ->assertNoContent();

        $this->actingAs($user1)->getJson('/api/v1/friends')
            ->assertJsonCount(0, 'data');
    }

    public function test_remove_nonexistent_friend_returns_404(): void
    {
        $user1 = User::factory()->publicProfile()->create();
        $user2 = User::factory()->publicProfile()->create();

        $this->actingAs($user1)->deleteJson("/api/v1/friends/{$user2->id}")
            ->assertNotFound();
    }

    public function test_friends_requires_authentication(): void
    {
        $this->getJson('/api/v1/friends')->assertUnauthorized();
    }
}
