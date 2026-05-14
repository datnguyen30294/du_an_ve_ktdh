<?php

namespace App\Modules\PMC\Reconciliation\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Reconciliation\Contracts\ReconciliationServiceInterface;
use App\Modules\PMC\Reconciliation\Requests\BatchReconcileRequest;
use App\Modules\PMC\Reconciliation\Requests\ListReconciliationRequest;
use App\Modules\PMC\Reconciliation\Requests\ReconcileRequest;
use App\Modules\PMC\Reconciliation\Requests\RejectReconciliationRequest;
use App\Modules\PMC\Reconciliation\Resources\ReconciliationDetailResource;
use App\Modules\PMC\Reconciliation\Resources\ReconciliationListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Reconciliations
 */
class ReconciliationController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected ReconciliationServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:reconciliations.view', only: ['index', 'show', 'summary']),
            new Middleware('permission:reconciliations.update', only: ['reconcile', 'reject', 'batchReconcile']),
        ];
    }

    /**
     * List reconciliations with filters and pagination.
     */
    public function index(ListReconciliationRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return ReconciliationListResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get reconciliation detail.
     */
    public function show(int $id): ReconciliationDetailResource
    {
        return new ReconciliationDetailResource($this->service->findById($id));
    }

    /**
     * Get reconciliation summary statistics.
     */
    public function summary(ListReconciliationRequest $request): JsonResponse
    {
        $data = $this->service->summary($request->validated());

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Confirm reconciliation for a single record.
     */
    public function reconcile(ReconcileRequest $request, int $id): ReconciliationDetailResource
    {
        return new ReconciliationDetailResource($this->service->reconcile($id, $request->validated()));
    }

    /**
     * Reject a reconciliation record.
     */
    public function reject(RejectReconciliationRequest $request, int $id): ReconciliationDetailResource
    {
        return new ReconciliationDetailResource($this->service->reject($id, $request->validated()));
    }

    /**
     * Batch reconcile multiple records.
     *
     * @return \Illuminate\Http\JsonResponse<array{success: true, data: array{reconciled_count: int, skipped_count: int}}>
     */
    public function batchReconcile(BatchReconcileRequest $request): JsonResponse
    {
        $result = $this->service->batchReconcile(
            $request->validated('ids'),
            $request->validated(),
        );

        return response()->json(['success' => true, 'data' => $result]);
    }
}
