<?php

namespace App\Modules\Platform\ExternalApi\Contracts;

use App\Modules\Platform\ExternalApi\Models\ApiClient;
use Illuminate\Pagination\LengthAwarePaginator;

interface ApiClientServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(string $id): ApiClient;

    /**
     * @param  array{organization_id: string, project_id: int, name: string, scopes: array<string>}  $data
     * @return array{client: ApiClient, secret_key: string}
     */
    public function create(array $data): array;

    /**
     * @param  array{name?: string, scopes?: array<string>, is_active?: bool}  $data
     */
    public function update(string $id, array $data): ApiClient;

    public function delete(string $id): void;

    /**
     * @return array{client: ApiClient, secret_key: string}
     */
    public function regenerateSecret(string $id): array;
}
