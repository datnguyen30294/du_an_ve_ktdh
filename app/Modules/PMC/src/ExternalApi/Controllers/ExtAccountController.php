<?php

namespace App\Modules\PMC\ExternalApi\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Account\Contracts\AccountServiceInterface;
use App\Modules\PMC\Account\Requests\CreateAccountRequest;
use App\Modules\PMC\Account\Requests\ListAccountRequest;
use App\Modules\PMC\Account\Requests\UpdateAccountRequest;
use App\Modules\PMC\Account\Resources\AccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags External API - Accounts
 */
class ExtAccountController extends BaseController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('scope:accounts:read', only: ['index', 'show']),
            new Middleware('scope:accounts:write', only: ['store', 'update', 'destroy']),
        ];
    }

    public function __construct(
        protected AccountServiceInterface $service,
    ) {}

    public function index(ListAccountRequest $request): AnonymousResourceCollection
    {
        $filters = array_merge($request->validated(), [
            'project_id' => $request->attributes->get('api_project_id'),
        ]);

        return AccountResource::collection($this->service->list($filters))
            ->additional(['success' => true]);
    }

    public function show(int $id): AccountResource
    {
        return new AccountResource($this->service->findById($id));
    }

    public function store(CreateAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $projectId = $request->attributes->get('api_project_id');
        $data['project_ids'] = [$projectId];

        return (new AccountResource($this->service->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAccountRequest $request, int $id): AccountResource
    {
        return new AccountResource($this->service->update($id, $request->validated()));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Đã xóa nhân viên.']);
    }
}
