<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\RegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private RegisterService $registerService
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
}
