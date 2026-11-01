<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\LoginService;
use App\Services\LogoutService;
use App\Services\RegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private RegisterService $registerService,
        private LoginService $loginService,
        private LogoutService $logoutService
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerService->register($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Login a user and return token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginService->login($request->validated());

        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Logout the current user.
     */
    public function logout(): JsonResponse
    {
        $this->logoutService->logout();

        return response()->json([
            'message' => 'Successfully logged out',
        ], Response::HTTP_OK);
    }
}
