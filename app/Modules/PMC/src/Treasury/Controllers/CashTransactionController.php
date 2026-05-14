<?php

namespace App\Modules\PMC\Treasury\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Requests\DeleteCashTransactionRequest;
use App\Modules\PMC\Treasury\Requests\ListCashTransactionRequest;
use App\Modules\PMC\Treasury\Requests\ManualTopupRequest;
use App\Modules\PMC\Treasury\Requests\ManualWithdrawRequest;
use App\Modules\PMC\Treasury\Resources\CashTransactionDetailResource;
use App\Modules\PMC\Treasury\Resources\CashTransactionListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Treasury
 */
class CashTransactionController extends BaseController implements HasMiddleware
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
            new Middleware('permission:treasury.view', only: ['index', 'show']),
            new Middleware('permission:treasury.store', only: ['manualTopup', 'manualWithdraw']),
            new Middleware('permission:treasury.destroy', only: ['destroy']),
        ];
    }

    /**
     * List cash transactions with filters and pagination.
     */
    public function index(ListCashTransactionRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->listTransactions($request->validated());

        return CashTransactionListResource::collection($paginator);
    }

    /**
     * Get a cash transaction by ID, including audit history.
     */
    public function show(int $id): CashTransactionDetailResource
    {
        return new CashTransactionDetailResource($this->service->findTransactionById($id));
    }

    /**
     * Record a manual cash inflow (nạp tiền thủ công).
     */
    public function manualTopup(ManualTopupRequest $request): JsonResponse
    {
        $transaction = $this->service->recordManualTopup($request->validated());

        return (new CashTransactionDetailResource($transaction->refresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Record a manual cash outflow (rút tiền thủ công).
     */
    public function manualWithdraw(ManualWithdrawRequest $request): JsonResponse
    {
        $transaction = $this->service->recordManualWithdraw($request->validated());

        return (new CashTransactionDetailResource($transaction->refresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Soft delete a manual cash transaction with a reason.
     */
    public function destroy(DeleteCashTransactionRequest $request, int $id): JsonResponse
    {
        $this->service->softDeleteManual($id, $request->validated('reason'));

        return response()->json(['success' => true, 'message' => 'Đã xóa giao dịch.']);
    }
}
