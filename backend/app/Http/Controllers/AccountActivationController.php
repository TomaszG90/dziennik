<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountActivationController extends Controller
{
    public function __construct(
        private AccountActivationService $activationService
    ) {}

    /**
     * Verify user email and activate account via signed link.
     */
    public function verify(Request $request, int|string $id, string $hash): JsonResponse|RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        if (! $request->hasValidSignature()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Link aktywacyjny jest nieprawidłowy lub wygasł.',
                ], 403);
            }

            return redirect()->to($frontendUrl.'/auth?verified=0');
        }

        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Link aktywacyjny jest nieprawidłowy.',
                ], 403);
            }

            return redirect()->to($frontendUrl.'/auth?verified=0');
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Konto zostało już wcześniej aktywowane.',
                ], 200);
            }

            return redirect()->to($frontendUrl.'/auth?verified=already');
        }

        $user->markEmailAsVerified();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Konto zostało pomyślnie aktywowane.',
            ], 200);
        }

        return redirect()->to($frontendUrl.'/auth?verified=1');
    }

    /**
     * Resend the activation email.
     */
    public function resend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $this->activationService->resendActivationEmail($validated['email']);

        return response()->json([
            'message' => 'Jeśli konto z podanym adresem istnieje i nie zostało jeszcze aktywowane, wysłaliśmy nowy link aktywacyjny.',
        ]);
    }
}
