<?php

namespace App\Modules\PMC\Account\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Contracts\AuthServiceInterface;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthService extends BaseService implements AuthServiceInterface
{
    public const TOKEN_NAME = 'auth-token';

    public function __construct(
        protected AccountRepository $accountRepository,
    ) {}

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: Account, token: string}
     */
    public function login(array $credentials): array
    {
        $account = $this->accountRepository->findByEmail($credentials['email']);

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

        $account->load('role');

        if ($account->role && ! $account->role->is_active) {
            throw new BusinessException(
                message: 'Vai trò của tài khoản đã bị vô hiệu hóa.',
                errorCode: 'ROLE_INACTIVE',
                httpStatusCode: Response::HTTP_FORBIDDEN,
            );
        }

        $token = $account->createToken(self::TOKEN_NAME)->plainTextToken;

        return ['user' => $account, 'token' => $token];
    }

    /**
     * @param  array{name: string, email: string, password: string, department_ids: list<int>, job_title_id: int, role_id: int}  $data
     * @return array{user: Account, token: string}
     */
    public function register(array $data): array
    {
        return $this->executeInTransaction(function () use ($data): array {
            /** @var Account $account */
            $account = $this->accountRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'job_title_id' => $data['job_title_id'],
                'role_id' => $data['role_id'],
            ]);

            $account->departments()->sync($data['department_ids']);

            $token = $account->createToken(self::TOKEN_NAME)->plainTextToken;

            return ['user' => $account, 'token' => $token];
        });
    }

    public function logout(): void
    {
        /** @var Account $account */
        $account = Auth::guard('sanctum')->user();
        $account->currentAccessToken()->delete();
    }
}
