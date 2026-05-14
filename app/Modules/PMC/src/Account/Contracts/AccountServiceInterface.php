<?php

namespace App\Modules\PMC\Account\Contracts;

use App\Modules\PMC\Account\Models\Account;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

interface AccountServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Account;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Account;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Account;

    public function delete(int $id): void;

    /**
     * @param  array{password: string}  $data
     */
    public function changePassword(int $id, array $data): Account;

    public function uploadAvatar(int $id, UploadedFile $file): Account;

    public function deleteAvatar(int $id): Account;
}
