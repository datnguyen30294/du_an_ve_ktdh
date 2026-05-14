<?php

namespace App\Modules\PMC\Receivable\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Receivable\Contracts\ReceivableServiceInterface;
use App\Modules\PMC\Receivable\Requests\CreatePaymentReceiptRequest;
use App\Modules\PMC\Receivable\Requests\CreateRefundRequest;
use App\Modules\PMC\Receivable\Requests\ListReceivableRequest;
use App\Modules\PMC\Receivable\Requests\ReceivableSummaryRequest;
use App\Modules\PMC\Receivable\Requests\UpdatePaymentReceiptRequest;
use App\Modules\PMC\Receivable\Requests\WriteOffReceivableRequest;
use App\Modules\PMC\Receivable\Resources\ReceivableDetailResource;
use App\Modules\PMC\Receivable\Resources\ReceivableListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Receivables
 */
class ReceivableController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected ReceivableServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:receivables.view', only: ['index', 'show', 'summary', 'audits']),
            new Middleware('permission:receivables.update', only: ['recordPayment', 'updatePayment', 'recordRefund', 'markCompleted', 'writeOff']),
        ];
    }

    /**
     * List receivables with filters and pagination.
     */
    public function index(ListReceivableRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return ReceivableListResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get receivable detail with payment history.
     */
    public function show(int $id): ReceivableDetailResource
    {
        return new ReceivableDetailResource($this->service->findById($id));
    }

    /**
     * Get summary KPI and aging buckets.
     */
    public function summary(ReceivableSummaryRequest $request): JsonResponse
    {
        $projectId = $request->validated('project_id') ? (int) $request->validated('project_id') : null;
        $data = $this->service->summary($projectId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Record a payment for a receivable.
     */
    public function recordPayment(CreatePaymentReceiptRequest $request, int $id): ReceivableDetailResource
    {
        return new ReceivableDetailResource($this->service->recordPayment($id, $request->validated()));
    }

    /**
     * Update a payment receipt.
     */
    public function updatePayment(UpdatePaymentReceiptRequest $request, int $id, int $paymentId): ReceivableDetailResource
    {
        return new ReceivableDetailResource($this->service->updatePayment($id, $paymentId, $request->validated()));
    }

    /**
     * Get audit history for a receivable.
     *
     * @return \Illuminate\Http\JsonResponse<array{
     *     success: true,
     *     data: array<int, array{
     *         id: int,
     *         event: string,
     *         auditable_type: string,
     *         old_values: array<string, mixed>|null,
     *         new_values: array<string, mixed>|null,
     *         user: array{id: int, name: string}|null,
     *         created_at: string|null,
     *     }>,
     * }>
     */
    public function audits(int $id): JsonResponse
    {
        $audits = $this->service->getAudits($id);

        return response()->json(['success' => true, 'data' => $audits]);
    }

    /**
     * Record a refund for an overpaid receivable.
     */
    public function recordRefund(CreateRefundRequest $request, int $id): ReceivableDetailResource
    {
        return new ReceivableDetailResource($this->service->recordRefund($id, $request->validated()));
    }

    /**
     * Mark a receivable as completed.
     */
    public function markCompleted(int $id): ReceivableDetailResource
    {
        return new ReceivableDetailResource($this->service->markCompleted($id));
    }

    /**
     * Write off a receivable.
     */
    public function writeOff(WriteOffReceivableRequest $request, int $id): ReceivableDetailResource
    {
        return new ReceivableDetailResource($this->service->writeOff($id, $request->validated()));
    }
}
