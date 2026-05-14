<?php

namespace App\Modules\Platform\Auth\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\Auth\Contracts\AuthServiceInterface;
use App\Modules\Platform\Auth\Requests\LoginRequest;
use App\Modules\Platform\Auth\Resources\AuthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Requester Authentication
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
     * Logout the current requester account (revoke current token).
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
     * Get the authenticated requester account info.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new AuthResource($request->user()),
        ]);
    }
}
