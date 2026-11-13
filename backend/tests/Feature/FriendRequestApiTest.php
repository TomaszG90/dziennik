<?php

namespace Tests\Feature;

use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_friend_request(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $response = $this->actingAs($sender)->postJson('/api/v1/friend-requests', [
            'user_id' => $recipient->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.sender_id', $sender->id)
            ->assertJsonPath('data.recipient_id', $recipient->id);
    }

    public function test_cannot_send_friend_request_to_self(): void
    {
        $user = User::factory()->publicProfile()->create();

        $this->actingAs($user)->postJson('/api/v1/friend-requests', [
            'user_id' => $user->id,
        ])->assertUnprocessable();
    }

    public function test_cannot_send_friend_request_to_private_user(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $privateUser = User::factory()->privateProfile()->create();

        $this->actingAs($sender)->postJson('/api/v1/friend-requests', [
            'user_id' => $privateUser->id,
        ])->assertNotFound();
    }

    public function test_cannot_send_duplicate_friend_request(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)->postJson('/api/v1/friend-requests', [
            'user_id' => $recipient->id,
        ])->assertConflict();
    }

    public function test_cannot_send_request_if_already_friends(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($sender)->postJson('/api/v1/friend-requests', [
            'user_id' => $recipient->id,
        ])->assertConflict();
    }

    public function test_incoming_request_hinted_when_sending_back(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        FriendRequest::create([
            'sender_id' => $recipient->id,
            'recipient_id' => $sender->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)->postJson('/api/v1/friend-requests', [
            'user_id' => $recipient->id,
        ])->assertConflict()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_get_incoming_friend_requests(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)->getJson('/api/v1/friend-requests?direction=incoming')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_get_outgoing_friend_requests(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)->getJson('/api/v1/friend-requests?direction=outgoing')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_direction_parameter_is_required(): void
    {
        $user = User::factory()->publicProfile()->create();

        $this->actingAs($user)->getJson('/api/v1/friend-requests')
            ->assertUnprocessable();
    }

    public function test_recipient_can_accept_friend_request(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $request = FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)->patchJson("/api/v1/friend-requests/{$request->id}", [
            'action' => 'accept',
        ])->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        // Verify the sender now sees the friend
        $this->actingAs($sender)->getJson('/api/v1/friends')
            ->assertJsonCount(1, 'data');
    }

    public function test_recipient_can_decline_friend_request(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $request = FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)->patchJson("/api/v1/friend-requests/{$request->id}", [
            'action' => 'decline',
        ])->assertOk()
            ->assertJsonPath('data.status', 'declined');
    }

    public function test_sender_cannot_accept_their_own_request(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $request = FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)->patchJson("/api/v1/friend-requests/{$request->id}", [
            'action' => 'accept',
        ])->assertForbidden();
    }

    public function test_cannot_accept_already_processed_request(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $request = FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($recipient)->patchJson("/api/v1/friend-requests/{$request->id}", [
            'action' => 'accept',
        ])->assertConflict();
    }

    public function test_sender_can_cancel_pending_request(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $request = FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)->deleteJson("/api/v1/friend-requests/{$request->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('friend_requests', ['id' => $request->id]);
    }

    public function test_recipient_cannot_cancel_request(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $request = FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)->deleteJson("/api/v1/friend-requests/{$request->id}")
            ->assertForbidden();
    }

    public function test_cannot_cancel_non_pending_request(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $request = FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($sender)->deleteJson("/api/v1/friend-requests/{$request->id}")
            ->assertConflict();
    }

    public function test_friend_request_requires_authentication(): void
    {
        $this->postJson('/api/v1/friend-requests', ['user_id' => 1])->assertUnauthorized();
        $this->getJson('/api/v1/friend-requests?direction=incoming')->assertUnauthorized();
    }

    public function test_cannot_send_request_to_nonexistent_user(): void
    {
        $sender = User::factory()->publicProfile()->create();

        $this->actingAs($sender)->postJson('/api/v1/friend-requests', [
            'user_id' => 999999,
        ])->assertUnprocessable();
    }

    public function test_accepting_request_makes_both_users_friends(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $request = FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)->patchJson("/api/v1/friend-requests/{$request->id}", [
            'action' => 'accept',
        ])->assertOk();

        // Both users see each other as friends
        $this->actingAs($sender)->getJson('/api/v1/friends')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recipient->id);

        $this->actingAs($recipient)->getJson('/api/v1/friends')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sender->id);
    }

    public function test_declined_request_does_not_create_friendship(): void
    {
        $sender = User::factory()->publicProfile()->create();
        $recipient = User::factory()->publicProfile()->create();

        $request = FriendRequest::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)->patchJson("/api/v1/friend-requests/{$request->id}", [
            'action' => 'decline',
        ])->assertOk();

        $this->actingAs($sender)->getJson('/api/v1/friends')
            ->assertJsonCount(0, 'data');
    }
}
