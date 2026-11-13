<?php

namespace App\Http\Controllers;

use App\Mail\InviteFriendEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InviteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = $request->user();

        Mail::to($validated['email'])->send(new InviteFriendEmail($user, $validated['email']));

        return response()->json([
            'message' => 'Zaproszenie zostało wysłane.',
        ], 200);
    }
}
