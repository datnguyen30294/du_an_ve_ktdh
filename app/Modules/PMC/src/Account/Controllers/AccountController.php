<?php

namespace App\Modules\PMC\Account\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Account\Contracts\AccountServiceInterface;
use App\Modules\PMC\Account\Requests\ChangePasswordRequest;
use App\Modules\PMC\Account\Requests\CreateAccountRequest;
use App\Modules\PMC\Account\Requests\ListAccountRequest;
use App\Modules\PMC\Account\Requests\UpdateAccountRequest;
use App\Modules\PMC\Account\Requests\UploadAvatarRequest;
use App\Modules\PMC\Account\Resources\AccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Accounts
 */
class AccountController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected AccountServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:accounts.view', only: ['index', 'show']),
            new Middleware('permission:accounts.store', only: ['store']),
            new Middleware('permission:accounts.update', only: ['update', 'changePassword', 'uploadAvatar', 'deleteAvatar']),
            new Middleware('permission:accounts.destroy', only: ['destroy']),
        ];
    }

    /**
     * List all accounts.
     */
    public function index(ListAccountRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return AccountResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get an account by ID.
     */
    public function show(int $id): AccountResource
    {
        return new AccountResource($this->service->findById($id));
    }

    /**
     * Create a new account.
     */
    public function store(CreateAccountRequest $request): JsonResponse
    {
        return (new AccountResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing account.
     */
    public function update(UpdateAccountRequest $request, int $id): AccountResource
    {
        return new AccountResource($this->service->update($id, $request->validated()));
    }

    /**
     * Delete an account.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }

    /**
     * Change an account's password.
     */
    public function changePassword(ChangePasswordRequest $request, int $id): AccountResource
    {
        return new AccountResource($this->service->changePassword($id, $request->validated()));
    }

    /**
     * Upload an account's avatar.
     */
    public function uploadAvatar(UploadAvatarRequest $request, int $id): AccountResource
    {
        return new AccountResource($this->service->uploadAvatar($id, $request->file('avatar')));
    }

    /**
     * Delete an account's avatar.
     */
    public function deleteAvatar(int $id): AccountResource
    {
        return new AccountResource($this->service->deleteAvatar($id));
    }
}
