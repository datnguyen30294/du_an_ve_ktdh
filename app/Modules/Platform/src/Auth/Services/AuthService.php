<?php

namespace App\Modules\Platform\Auth\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\Platform\Auth\Contracts\AuthServiceInterface;
use App\Modules\Platform\Auth\Models\RequesterAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthService extends BaseService implements AuthServiceInterface
{
    public const TOKEN_NAME = 'auth-token';

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: RequesterAccount, token: string}
     */
    public function login(array $credentials): array
    {
        $account = RequesterAccount::withoutGlobalScopes()
            ->where('email', $credentials['email'])
            ->first();

        if (! $account || ! Hash::check($credentials['password'], $account->password)) {
            throw new BusinessException(
                message: 'Email hoặc mật khẩu không đúng.',
                errorCode: 'INVALID_CREDENTIALS',
                httpStatusCode: Response::HTTP_UNAUTHORIZED,
            );
        }

        if (! $account->is_active) {
            throw new BusinessException(
                message: 'Tài khoản đã bị vô hiệu hóa.',
                errorCode: 'ACCOUNT_INACTIVE',
                httpStatusCode: Response::HTTP_FORBIDDEN,
            );
        }

        $token = $account->createToken(self::TOKEN_NAME)->plainTextToken;

        return ['user' => $account, 'token' => $token];
    }

    public function logout(): void
    {
        /** @var RequesterAccount $account */
        $account = Auth::guard('requester')->user();
        $account->currentAccessToken()->delete();
    }
}
