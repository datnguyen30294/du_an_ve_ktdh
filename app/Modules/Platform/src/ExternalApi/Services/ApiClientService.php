<?php

namespace App\Modules\Platform\ExternalApi\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\Platform\ExternalApi\Contracts\ApiClientServiceInterface;
use App\Modules\Platform\ExternalApi\Models\ApiClient;
use App\Modules\Platform\ExternalApi\Repositories\ApiClientRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiClientService extends BaseService implements ApiClientServiceInterface
{
    public function __construct(
        protected ApiClientRepository $repository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(string $id): ApiClient
    {
        /** @var ApiClient */
        return $this->repository->findById($id);
    }

    public function create(array $data): array
    {
        $secretKey = 'sk_'.Str::random(60);

        /** @var ApiClient */
        $client = $this->repository->create([
            'organization_id' => $data['organization_id'],
            'project_id' => $data['project_id'],
            'name' => $data['name'],
            'client_key' => 'ck_'.Str::random(60),
            'encrypted_secret' => $secretKey,
            'scopes' => $data['scopes'],
            'is_active' => true,
        ]);

        return ['client' => $client, 'secret_key' => $secretKey];
    }

    public function update(string $id, array $data): ApiClient
    {
        $this->repository->update($id, $data);

        return $this->findById($id);
    }

    public function delete(string $id): void
    {
        $this->repository->delete($id);
    }

    public function regenerateSecret(string $id): array
    {
        $client = $this->findById($id);

        $this->ensureClientActive($client);

        $secretKey = 'sk_'.Str::random(60);

        $this->repository->update($id, [
            'encrypted_secret' => $secretKey,
        ]);

        return ['client' => $client->refresh(), 'secret_key' => $secretKey];
    }

    private function ensureClientActive(ApiClient $client): void
    {
        if (! $client->is_active) {
            throw new BusinessException(
                message: 'API client đã bị vô hiệu hóa.',
                errorCode: 'CLIENT_DISABLED',
                httpStatusCode: Response::HTTP_FORBIDDEN,
            );
        }
    }
}
