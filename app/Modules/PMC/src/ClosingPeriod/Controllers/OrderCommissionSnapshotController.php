<?php

namespace App\Modules\PMC\ClosingPeriod\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\ClosingPeriod\Repositories\ClosingPeriodRepository;
use App\Modules\PMC\ClosingPeriod\Resources\OrderCommissionSnapshotResource;
use Illuminate\Http\JsonResponse;

/**
 * @tags Order Commission Snapshot
 */
class OrderCommissionSnapshotController extends BaseController
{
    public function __construct(
        protected ClosingPeriodRepository $repository,
    ) {}

    /**
     * List commission snapshots attached to an order (frozen amounts from closed periods).
     * Returns empty list when the order has not been included in any period yet.
     *
     * @return array{
     *     success: bool,
     *     data: list<array<string, mixed>>,
     * }
     */
    public function show(int $orderId): JsonResponse
    {
        $snapshots = $this->repository->getSnapshotsByOrderId($orderId);

        return response()->json([
            'success' => true,
            'data' => OrderCommissionSnapshotResource::collection($snapshots)->toArray(request()),
        ]);
    }
}
