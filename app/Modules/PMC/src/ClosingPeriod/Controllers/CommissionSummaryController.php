<?php

namespace App\Modules\PMC\ClosingPeriod\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\ClosingPeriod\Contracts\ClosingPeriodServiceInterface;
use App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus;
use App\Modules\PMC\ClosingPeriod\Requests\CommissionSummaryRequest;
use App\Modules\PMC\ClosingPeriod\Requests\UpdatePayoutStatusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Commission Summary
 */
class CommissionSummaryController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected ClosingPeriodServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:closing-periods.view', only: ['index']),
            new Middleware('permission:closing-periods.update', only: ['updatePayout']),
        ];
    }

    /**
     * Get commission summary with stats, by-recipient aggregation, and snapshot details.
     *
     * Only terminal recipients are returned — Management and Department are
     * intermediary distribution buckets and are filtered out server-side.
     * Zero-amount snapshots are also filtered out.
     *
     * @return array{
     *     success: bool,
     *     data: array{
     *         stats: array{
     *             total_commission: string,
     *             order_count: int,
     *             snapshot_count: int,
     *             recipient_count: int,
     *         },
     *         by_recipient: list<array{
     *             recipient_type: array{value: string, label: string},
     *             recipient_name: string,
     *             account_id: int|null,
     *             bank_info: array{bin: string, label: string, account_number: string, account_name: string}|null,
     *             total_amount: string,
     *             order_count: int,
     *             payout_status: string,
     *             paid_lines: int,
     *             total_lines: int,
     *         }>,
     *         snapshots: list<array{
     *             id: int,
     *             order_id: int,
     *             order_code: string|null,
     *             closing_period_id: int,
     *             closing_period_name: string|null,
     *             recipient_type: array{value: string, label: string},
     *             recipient_name: string,
     *             account_id: int|null,
     *             bank_info: array{bin: string, label: string, account_number: string, account_name: string}|null,
     *             value_type: array{value: string, label: string}|null,
     *             percent: string|null,
     *             value_fixed: string|null,
     *             amount: string,
     *             resolved_from: string,
     *             payout_status: array{value: string, label: string},
     *             paid_out_at: string|null,
     *             cash_transaction: array{id: int, code: string}|null,
     *         }>,
     *     },
     * }
     */
    public function index(CommissionSummaryRequest $request): JsonResponse
    {
        $data = $this->service->getCommissionSummary($request->validated());

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Bulk update payout status for commission snapshots.
     */
    public function updatePayout(UpdatePayoutStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $status = PayoutStatus::from($validated['payout_status']);
        $count = $this->service->updatePayoutStatus($validated['snapshot_ids'], $status);

        return response()->json([
            'success' => true,
            'message' => "Đã cập nhật {$count} dòng hoa hồng.",
            'updated_count' => $count,
        ]);
    }
}
