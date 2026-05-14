<?php

namespace App\Modules\PMC\Treasury\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Models\CashAccount;
use App\Modules\PMC\Treasury\Resources\CashAccountResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Treasury
 */
class CashAccountController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected TreasuryServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:treasury.view'),
        ];
    }

    /**
     * List active cash accounts (with current balance).
     */
    public function index(): AnonymousResourceCollection
    {
        $accounts = $this->service->listCashAccounts()->map(function (CashAccount $account): CashAccount {
            $account->setAttribute('current_balance', $this->service->getCurrentBalance($account));

            return $account;
        });

        return CashAccountResource::collection($accounts);
    }

    /**
     * Get the tenant's default cash account with current balance.
     */
    public function default(): CashAccountResource
    {
        $account = $this->service->getDefaultCashAccount();
        $account->setAttribute('current_balance', $this->service->getCurrentBalance($account));

        return new CashAccountResource($account);
    }

    /**
     * Get a cash account by ID (with current balance).
     */
    public function show(int $id): CashAccountResource
    {
        $account = $this->service->findCashAccountById($id);
        $account->setAttribute('current_balance', $this->service->getCurrentBalance($account));

        return new CashAccountResource($account);
    }
}
