<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetService $passwordResetService) {}

    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->validated('email'));

        return response()->json([
            'message' => 'Jeśli konto z podanym adresem istnieje, otrzymasz wiadomość z linkiem do zmiany hasła.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->reset($request->validated());

        return response()->json([
            'message' => 'Hasło zostało zmienione.',
        ]);
    }
}
