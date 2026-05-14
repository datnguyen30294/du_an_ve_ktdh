<?php

namespace App\Modules\PMC\Account\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Account\Contracts\AuthServiceInterface;
use App\Modules\PMC\Account\Requests\LoginRequest;
use App\Modules\PMC\Account\Requests\RegisterRequest;
use App\Modules\PMC\Account\Resources\AuthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Authentication
 */
class AuthController extends BaseController
{
    public function __construct(
        protected AuthServiceInterface $authService,
    ) {}

    /**
     * Login with email and password.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new AuthResource($result['user']),
                'token' => $result['token'],
            ],
        ]);
    }

    /**
     * Register a new account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new AuthResource($result['user']),
                'token' => $result['token'],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Logout the current user (revoke current token).
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    /**
     * Get the authenticated user's info.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new AuthResource($request->user()),
        ]);
    }
}
