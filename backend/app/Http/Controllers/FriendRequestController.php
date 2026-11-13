<?php

namespace App\Http\Controllers;

use App\Http\Resources\FriendRequestResource;
use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class FriendRequestController extends Controller
{
    /**
     * Get friend requests (incoming or outgoing).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:incoming,outgoing'],
        ]);

        $user = $request->user();
        $direction = $validated['direction'];

        $query = FriendRequest::query()->where('status', 'pending');

        if ($direction === 'incoming') {
            $query->where('recipient_id', $user->id);
        } else {
            $query->where('sender_id', $user->id);
        }

        $requests = $query->orderBy('created_at', 'desc')->get()->load(['sender', 'recipient']);

        return FriendRequestResource::collection($requests);
    }

    /**
     * Send a friend request to another user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $authUser = $request->user();
        $targetUser = User::find($validated['user_id']);

        // Cannot send to self
        if ($targetUser->id === $authUser->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Nie można wysłać zaproszenia do siebie samego.'],
            ]);
        }

        // Target user must exist and have a public profile
        if (! $targetUser || $targetUser->profile_visibility !== 'public') {
            return response()->json([
                'message' => 'Nie znaleziono użytkownika.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check for existing pending or accepted relationship (both directions)
        $existing = FriendRequest::query()
            ->where(function ($query) use ($authUser, $targetUser) {
                $query->where('sender_id', $authUser->id)
                    ->where('recipient_id', $targetUser->id);
            })
            ->orWhere(function ($query) use ($authUser, $targetUser) {
                $query->where('sender_id', $targetUser->id)
                    ->where('recipient_id', $authUser->id);
            })
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existing) {
            // If there's an incoming pending request, return it with a hint
            if ($existing->sender_id === $targetUser->id && $existing->status === 'pending') {
                return response()->json([
                    'message' => 'Istnieje oczekujące zaproszenie od tego użytkownika. Proszę je zaakceptować.',
                    'data' => new FriendRequestResource($existing),
                ], Response::HTTP_CONFLICT);
            }

            return response()->json([
                'message' => 'Relacja już istnieje.',
            ], Response::HTTP_CONFLICT);
        }

        $friendRequest = FriendRequest::create([
            'sender_id' => $authUser->id,
            'recipient_id' => $targetUser->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => new FriendRequestResource($friendRequest),
        ], Response::HTTP_CREATED);
    }

    /**
     * Accept or decline a friend request.
     */
    public function update(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:accept,decline'],
        ]);

        $authUser = $request->user();

        // Only the recipient can accept/decline
        if ($friendRequest->recipient_id !== $authUser->id) {
            return response()->json([
                'message' => 'Brak uprawnień do wykonania tej operacji.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Request must be pending
        if ($friendRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Zaproszenie zostało już obsłużone.',
            ], Response::HTTP_CONFLICT);
        }

        $newStatus = $validated['action'] === 'accept' ? 'accepted' : 'declined';
        $friendRequest->status = $newStatus;
        $friendRequest->save();

        return response()->json([
            'data' => new FriendRequestResource($friendRequest),
        ], Response::HTTP_OK);
    }

    /**
     * Cancel (withdraw) a sent friend request.
     */
    public function destroy(Request $request, FriendRequest $friendRequest): Response
    {
        $authUser = $request->user();

        // Only the sender can cancel
        if ($friendRequest->sender_id !== $authUser->id) {
            return response()->noContent(Response::HTTP_FORBIDDEN);
        }

        // Request must be pending
        if ($friendRequest->status !== 'pending') {
            return response()->noContent(Response::HTTP_CONFLICT);
        }

        $friendRequest->delete();

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
