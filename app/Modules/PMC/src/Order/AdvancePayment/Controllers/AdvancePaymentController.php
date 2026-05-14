<?php

namespace App\Modules\PMC\Order\AdvancePayment\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Order\AdvancePayment\Requests\CreateAdvancePaymentRequest;
use App\Modules\PMC\Order\AdvancePayment\Requests\CreateBatchAdvancePaymentRequest;
use App\Modules\PMC\Order\AdvancePayment\Requests\ListAdvancePaymentRequest;
use App\Modules\PMC\Order\AdvancePayment\Resources\AdvancePaymentHistoryResource;
use App\Modules\PMC\Order\AdvancePayment\Services\AdvancePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Advance Payment
 */
class AdvancePaymentController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected AdvancePaymentService $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth:sanctum'),
        ];
    }

    /**
     * List pending / paid advance rows derived from order_lines.
     */
    public function index(ListAdvancePaymentRequest $request): JsonResponse
    {
        $rows = $this->service->list($request->validated());

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * KPI stats for the "Tiền ứng vật tư" screen.
     */
    public function stats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->stats()]);
    }

    /**
     * Payment history (list of AdvancePaymentRecord records, newest first).
     */
    public function history(): AnonymousResourceCollection
    {
        $paginator = $this->service->history(request()->query());

        return AdvancePaymentHistoryResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Record payment for a single line.
     */
    public function store(CreateAdvancePaymentRequest $request): JsonResponse
    {
        $record = $this->service->recordSingle(
            (int) $request->validated('order_line_id'),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã ghi nhận hoàn tiền ứng.',
            'data' => new AdvancePaymentHistoryResource($record->loadMissing(['account', 'order', 'orderLine'])),
        ]);
    }

    /**
     * Record payment for multiple lines (batch).
     */
    public function storeBatch(CreateBatchAdvancePaymentRequest $request): JsonResponse
    {
        /** @var array<int> $ids */
        $ids = $request->validated('order_line_ids');
        $records = $this->service->recordBatch($ids, $request->validated());

        return response()->json([
            'success' => true,
            'message' => "Đã hoàn {$records->count()} mục tiền ứng.",
            'data' => [
                'count' => $records->count(),
                'batch_id' => $records->first()?->batch_id,
            ],
        ]);
    }

    /**
     * Soft-delete a payment record (in case of wrong entry).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Đã xoá bản ghi.']);
    }
}
