<?php

namespace App\Modules\PMC\Order\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Order\Contracts\OrderCommissionOverrideServiceInterface;
use App\Modules\PMC\Order\Requests\SaveCommissionOverrideRequest;
use App\Modules\PMC\Order\Resources\CommissionOverrideResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Orders / Commission Override
 */
class OrderCommissionOverrideController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected OrderCommissionOverrideServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:orders.view', only: ['show']),
            new Middleware('permission:orders.update', only: ['save', 'destroy']),
        ];
    }

    /**
     * Get commission override data for an order.
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->service->getOverrides($id);

        return response()->json([
            'success' => true,
            'data' => [
                'has_overrides' => $result['has_overrides'],
                'commissionable_total' => number_format($result['commissionable_total'], 2, '.', ''),
                'platform_amount' => number_format($result['platform_amount'], 2, '.', ''),
                'overrides' => CommissionOverrideResource::collection($result['overrides'])->resolve(),
            ],
        ]);
    }

    /**
     * Save (replace) commission overrides for an order.
     */
    public function save(SaveCommissionOverrideRequest $request, int $id): JsonResponse
    {
        $result = $this->service->saveOverrides($id, $request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'has_overrides' => $result['has_overrides'],
                'commissionable_total' => number_format($result['commissionable_total'], 2, '.', ''),
                'platform_amount' => number_format($result['platform_amount'], 2, '.', ''),
                'overrides' => CommissionOverrideResource::collection($result['overrides'])->resolve(),
            ],
        ]);
    }

    /**
     * Delete all commission overrides for an order.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->deleteOverrides($id);

        return response()->json([
            'success' => true,
            'message' => 'Đã xoá điều chỉnh hoa hồng.',
        ]);
    }
}
