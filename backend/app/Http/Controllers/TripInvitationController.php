<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripInvitationResource;
use App\Models\Trip;
use App\Models\TripInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TripInvitationController extends Controller
{
    /**
     * Get invitations for a specific trip (only trip owner).
     */
    public function index(Request $request, Trip $trip): AnonymousResourceCollection
    {
        $authUser = $request->user();

        // Only trip owner can view invitations
        if ($trip->user_id !== $authUser->id) {
            abort(403);
        }

        $invitations = $trip->invitations()
            ->orderBy('created_at', 'desc')
            ->get()
            ->load(['invitee', 'inviter']);

        return TripInvitationResource::collection($invitations);
    }

    /**
     * Invite a friend to a trip.
     */
    public function store(Request $request, Trip $trip): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $authUser = $request->user();

        // Only trip owner can invite
        if ($trip->user_id !== $authUser->id) {
            abort(403);
        }

        $targetUser = User::find($validated['user_id']);

        // Cannot invite self
        if ($targetUser->id === $authUser->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Nie można zaprosić siebie samego.'],
            ]);
        }

        // Check for existing invitation
        $existing = TripInvitation::where('trip_id', $trip->id)
            ->where('invitee_id', $targetUser->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'pending') {
                return response()->json([
                    'message' => 'Zaproszenie do tej wycieczki jest już oczekujące.',
                ], Response::HTTP_CONFLICT);
            }

            if ($existing->status === 'accepted') {
                return response()->json([
                    'message' => 'Ten użytkownik jest już uczestnikiem wycieczki.',
                ], Response::HTTP_CONFLICT);
            }

            // If declined, allow re-invite by updating the existing record
            $existing->status = 'pending';
            $existing->inviter_id = $authUser->id;
            $existing->save();

            return response()->json([
                'data' => new TripInvitationResource($existing->load(['invitee', 'inviter'])),
            ], Response::HTTP_CREATED);
        }

        $invitation = TripInvitation::create([
            'trip_id' => $trip->id,
            'inviter_id' => $authUser->id,
            'invitee_id' => $targetUser->id,
            'status' => 'pending',
        ]);

        $invitation->load(['invitee', 'inviter']);

        return response()->json([
            'data' => new TripInvitationResource($invitation),
        ], Response::HTTP_CREATED);
    }

    /**
     * Accept or decline a trip invitation.
     */
    public function update(Request $request, Trip $trip, TripInvitation $invitation): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:accept,decline'],
        ]);

        $authUser = $request->user();

        // Ensure invitation belongs to trip
        if ($invitation->trip_id !== $trip->id) {
            abort(404);
        }

        // Only the invitee can accept/decline
        if ($invitation->invitee_id !== $authUser->id) {
            return response()->json([
                'message' => 'Brak uprawnień do wykonania tej operacji.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Invitation must be pending
        if ($invitation->status !== 'pending') {
            return response()->json([
                'message' => 'Zaproszenie zostało już obsłużone.',
            ], Response::HTTP_CONFLICT);
        }

        $newStatus = $validated['action'] === 'accept' ? 'accepted' : 'declined';
        $invitation->status = $newStatus;
        $invitation->save();

        $invitation->load(['invitee', 'inviter']);

        return response()->json([
            'data' => new TripInvitationResource($invitation),
        ], Response::HTTP_OK);
    }

    /**
     * Cancel (withdraw) a trip invitation.
     */
    public function destroy(Request $request, Trip $trip, TripInvitation $invitation): Response
    {
        $authUser = $request->user();

        // Ensure invitation belongs to trip
        if ($invitation->trip_id !== $trip->id) {
            abort(404);
        }

        // Only the inviter (trip owner) can cancel
        if ($invitation->inviter_id !== $authUser->id) {
            return response()->noContent(Response::HTTP_FORBIDDEN);
        }

        // Invitation must be pending
        if ($invitation->status !== 'pending') {
            return response()->noContent(Response::HTTP_CONFLICT);
        }

        $invitation->delete();

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }

    /**
     * Get my incoming trip invitations (across all trips).
     */
    public function myInvitations(Request $request): AnonymousResourceCollection
    {
        $authUser = $request->user();

        $invitations = TripInvitation::where('invitee_id', $authUser->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->load(['trip', 'inviter', 'invitee']);

        return TripInvitationResource::collection($invitations);
    }
}
