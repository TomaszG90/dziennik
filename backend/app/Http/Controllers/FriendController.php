<?php

namespace App\Http\Controllers;

use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FriendController extends Controller
{
    /**
     * Get accepted friends of the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $friendRequests = FriendRequest::query()
            ->where('status', 'accepted')
            ->where(function ($query) use ($user) {
                $query->where('sender_id', $user->id)
                    ->orWhere('recipient_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $friendRequests->map(function (FriendRequest $fr) use ($user) {
            $friendId = $fr->sender_id === $user->id ? $fr->recipient_id : $fr->sender_id;
            $friend = User::findOrFail($friendId);

            return [
                'id' => $friend->id,
                'name' => $friend->name,
                'avatar_path' => $friend->avatar_path,
                'created_at' => $fr->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
        ], Response::HTTP_OK);
    }

    /**
     * Remove an accepted friend relationship (symmetric).
     */
    public function destroy(Request $request, User $user): Response
    {
        $authUser = $request->user();

        $friendRequest = FriendRequest::query()
            ->where('status', 'accepted')
            ->where(function ($query) use ($authUser, $user) {
                $query->where('sender_id', $authUser->id)
                    ->where('recipient_id', $user->id);
            })
            ->orWhere(function ($query) use ($authUser, $user) {
                $query->where('sender_id', $user->id)
                    ->where('recipient_id', $authUser->id);
            })
            ->first();

        if (! $friendRequest) {
            return response()->noContent(Response::HTTP_NOT_FOUND);
        }

        $friendRequest->delete();

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
