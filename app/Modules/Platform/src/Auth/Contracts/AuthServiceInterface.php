<?php

namespace App\Modules\Platform\Auth\Contracts;

use App\Modules\Platform\Auth\Models\RequesterAccount;

interface AuthServiceInterface
{
    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: RequesterAccount, token: string}
     */
    public function login(array $credentials): array;

    public function logout(): void;
}
