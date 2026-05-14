<?php

namespace App\Modules\PMC\Account\Contracts;

use App\Modules\PMC\Account\Models\Account;

interface AuthServiceInterface
{
    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: Account, token: string}
     */
    public function login(array $credentials): array;

    /**
     * @param  array{name: string, email: string, password: string, department_ids: list<int>, job_title_id: int, role_id: int}  $data
     * @return array{user: Account, token: string}
     */
    public function register(array $data): array;

    public function logout(): void;
}
