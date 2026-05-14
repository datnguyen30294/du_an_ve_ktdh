<?php

namespace App\Modules\PMC\ClosingPeriod\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\Platform\Setting\ExternalServices\PlatformBankInfoExternalServiceInterface;
use App\Modules\PMC\ClosingPeriod\Contracts\ClosingPeriodServiceInterface;
use App\Modules\PMC\ClosingPeriod\Contracts\CommissionSnapshotServiceInterface;
use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\ClosingPeriod\Repositories\ClosingPeriodRepository;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use App\Modules\PMC\Treasury\Events\CommissionSnapshotPaid;
use App\Modules\PMC\Treasury\Events\CommissionSnapshotUnpaid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ClosingPeriodService extends BaseService implements ClosingPeriodServiceInterface
{
    public function __construct(
        protected ClosingPeriodRepository $repository,
        protected CommissionSnapshotServiceInterface $snapshotService,
        protected PlatformBankInfoExternalServiceInterface $platformBankInfo,
        protected SystemSettingServiceInterface $systemSettings,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): ClosingPeriod
    {
        return $this->repository->findWithDetail($id)
            ->load('snapshots.account:id,name,employee_code');
    }

    public function create(array $data): ClosingPeriod
    {
        $data['status'] = ClosingPeriodStatus::Open->value;

        /** @var ClosingPeriod */
        return $this->repository->create($data);
    }

    public function getEligibleOrders(int $periodId): Collection
    {
        $period = $this->repository->findById($periodId);
        $this->assertOpen($period);

        return Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->whereHas('receivable', fn ($q) => $q->whereIn('status', [ReceivableStatus::Paid->value, ReceivableStatus::Completed->value]))
            ->whereDoesntHave('closingPeriodOrder')
            ->with([
                'receivable:id,order_id,amount',
                'quote.ogTicket.project:id,name',
            ])
            ->select(['id', 'code', 'total_amount', 'quote_id'])
            ->get();
    }

    public function addOrders(int $periodId, array $orderIds): ClosingPeriod
    {
        /** @var ClosingPeriod $period */
        $period = $this->repository->findById($periodId);
        $this->assertOpen($period);

        // Batch-load all orders with relations to avoid N+1
        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->with(['receivable', 'commissionOverrides.account:id,name', 'lines'])
            ->get()
            ->keyBy('id');

        return $this->executeInTransaction(function () use ($period, $orderIds, $orders): ClosingPeriod {
            foreach ($orderIds as $orderId) {
                $order = $orders->get($orderId);
                if (! $order) {
                    throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Order {$orderId} not found.");
                }

                $this->assertOrderEligible($order);

                // Create commission snapshots
                $snapshots = $this->snapshotService->createSnapshotsForOrder($period, $order);
                $commissionTotal = $this->sumTopLevelCommission($snapshots);

                // Create pivot record
                $this->repository->createPeriodOrder([
                    'closing_period_id' => $period->id,
                    'order_id' => $orderId,
                    'frozen_receivable_amount' => $order->receivable->amount,
                    'frozen_commission_total' => round($commissionTotal, 2),
                ]);
            }

            return $this->findById($period->id);
        });
    }

    public function removeOrder(int $periodId, int $orderId): ClosingPeriod
    {
        /** @var ClosingPeriod $period */
        $period = $this->repository->findById($periodId);
        $this->assertOpen($period);

        $periodOrder = $this->repository->findPeriodOrder($periodId, $orderId);
        if (! $periodOrder) {
            throw new BusinessException(
                message: 'Đơn hàng không thuộc kỳ chốt này.',
                errorCode: 'ORDER_NOT_IN_PERIOD',
            );
        }

        $this->repository->deletePeriodOrder($periodId, $orderId);

        return $this->findById($periodId);
    }

    public function close(int $periodId, array $data): ClosingPeriod
    {
        /** @var ClosingPeriod $period */
        $period = $this->repository->findById($periodId);
        $this->assertOpen($period);

        $period->update([
            'status' => ClosingPeriodStatus::Closed->value,
            'closed_at' => now(),
            'closed_by_id' => auth()->id(),
            'note' => $data['note'] ?? null,
        ]);

        return $this->findById($periodId);
    }

    public function reopen(int $periodId, array $data): ClosingPeriod
    {
        /** @var ClosingPeriod $period */
        $period = $this->repository->findById($periodId);

        if ($period->status !== ClosingPeriodStatus::Closed) {
            throw new BusinessException(
                message: 'Chỉ có thể mở lại kỳ đã chốt.',
                errorCode: 'CLOSING_PERIOD_NOT_CLOSED',
            );
        }

        // Reopen recalculates every snapshot in the period (hard-delete +
        // recreate). Any snapshot still linked to an ACTIVE cash transaction
        // represents money that was actually paid out; silently dropping
        // that link via the FK's SET NULL would orphan the ledger. Force
        // the user to unpay first so the CommissionSnapshotUnpaid listener
        // can retire the matching cash transactions before we touch the
        // snapshots.
        if ($this->repository->hasActivePaidCommission($periodId)) {
            throw new BusinessException(
                message: 'Không thể mở lại kỳ do còn hoa hồng đã thanh toán. '
                    .'Vui lòng vào "Tổng hợp hoa hồng" của kỳ này, chuyển các dòng "Đã thanh toán" về "Chưa thanh toán", sau đó mở lại kỳ.',
                errorCode: 'CLOSING_PERIOD_HAS_PAID_COMMISSION',
            );
        }

        return $this->executeInTransaction(function () use ($period, $data): ClosingPeriod {
            $period->update([
                'status' => ClosingPeriodStatus::Open->value,
                'closed_at' => null,
                'closed_by_id' => null,
                'note' => $data['note'] ?? null,
            ]);

            // Recalculate all order snapshots in this period
            $period->load('orders');
            $orderIds = $period->orders->pluck('order_id')->all();

            $orders = Order::query()
                ->whereIn('id', $orderIds)
                ->with(['commissionOverrides.account:id,name', 'lines'])
                ->get()
                ->keyBy('id');

            foreach ($period->orders as $periodOrder) {
                /** @var Order $order */
                $order = $orders->get($periodOrder->order_id);

                $snapshots = $this->snapshotService->recalculateForOrder($period, $order);
                $commissionTotal = $this->sumTopLevelCommission($snapshots);

                $periodOrder->update([
                    'frozen_commission_total' => round($commissionTotal, 2),
                ]);
            }

            return $this->findById($period->id);
        });
    }

    public function delete(int $id): void
    {
        /** @var ClosingPeriod $period */
        $period = $this->repository->findById($id);

        if ($period->status !== ClosingPeriodStatus::Open) {
            throw new BusinessException(
                message: 'Chỉ có thể xóa kỳ chốt đang mở.',
                errorCode: 'CLOSING_PERIOD_NOT_OPEN',
            );
        }

        if ($period->orders()->exists()) {
            throw new BusinessException(
                message: 'Không thể xóa kỳ chốt đã có đơn hàng. Vui lòng xóa đơn trước.',
                errorCode: 'CLOSING_PERIOD_HAS_ORDERS',
            );
        }

        $period->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getCommissionSummary(array $filters): array
    {
        // Only terminal recipients are "real" commission in the report:
        //   - Zero-amount rows are audit noise (there is nothing to pay).
        //   - Management / Department are intermediary distribution buckets;
        //     including them would triple-count the commission pool
        //     (pool → management → department → staff are all equal sums).
        //   - Override rows are always terminal — they bypass the hierarchy.
        $snapshots = $this->repository->getFilteredSnapshots($filters)
            ->filter(fn (OrderCommissionSnapshot $s) => (float) $s->amount > 0
                && $this->isTerminalRecipient($s))
            ->values();

        // Stats
        $stats = [
            'total_commission' => number_format((float) $snapshots->sum('amount'), 2, '.', ''),
            'order_count' => $snapshots->pluck('order_id')->unique()->count(),
            'snapshot_count' => $snapshots->count(),
            'recipient_count' => $snapshots->unique(
                fn (OrderCommissionSnapshot $s) => match ($s->recipient_type) {
                    SnapshotRecipientType::BoardOfDirectors => 'board_of_directors|project='.($s->order?->quote?->ogTicket?->project_id ?? 'na'),
                    default => $s->recipient_type->value.'|'.$s->account_id.'|'.$s->recipient_name,
                }
            )->count(),
        ];

        // Platform bank info is a global singleton — fetch once and reuse.
        $platformBankInfo = $this->platformBankInfo->getPlatformBankInfo();

        // Operating-company bank info is also a per-tenant singleton (same
        // settings row used by the receivables QR). Fetch once and reuse.
        $operatingCompanyBankInfo = $this->buildOperatingCompanyBankInfo();

        // By recipient — group, aggregate, sort by total desc.
        //
        // BQT is a per-project commission bucket: different projects have
        // different BQT bank accounts and must stay in separate rows,
        // otherwise totals / order counts / QR would collapse into one line
        // and mix money across projects.
        $byRecipient = $snapshots
            ->groupBy(fn (OrderCommissionSnapshot $s) => match ($s->recipient_type) {
                SnapshotRecipientType::BoardOfDirectors => 'board_of_directors|project='.($s->order?->quote?->ogTicket?->project_id ?? 'na'),
                default => $s->recipient_type->value.'|'.$s->account_id.'|'.$s->recipient_name,
            })
            ->map(function (\Illuminate\Support\Collection $group) use ($platformBankInfo, $operatingCompanyBankInfo) {
                /** @var OrderCommissionSnapshot $first */
                $first = $group->first();
                $totalAmount = (float) $group->sum('amount');

                $paidCount = $group->where('payout_status', PayoutStatus::Paid)->count();
                $totalCount = $group->count();

                if ($paidCount === 0) {
                    $payoutLabel = 'unpaid';
                } elseif ($paidCount === $totalCount) {
                    $payoutLabel = 'paid';
                } else {
                    $payoutLabel = 'partial';
                }

                $project = $first->order?->quote?->ogTicket?->project;
                $isBqt = $first->recipient_type === SnapshotRecipientType::BoardOfDirectors;

                return [
                    'recipient_type' => ['value' => $first->recipient_type->value, 'label' => $first->recipient_type->label()],
                    'recipient_name' => $this->displayRecipientName($first),
                    'account_id' => $first->account_id,
                    'project_id' => $isBqt ? $project?->id : null,
                    'project_name' => $isBqt ? $project?->name : null,
                    'bank_info' => $this->resolveBankInfo($first, $platformBankInfo, $operatingCompanyBankInfo),
                    'total_amount' => number_format($totalAmount, 2, '.', ''),
                    'order_count' => $group->pluck('order_id')->unique()->count(),
                    'payout_status' => $payoutLabel,
                    'paid_lines' => $paidCount,
                    'total_lines' => $totalCount,
                    '_sort' => $totalAmount,
                ];
            })
            ->sortByDesc('_sort')
            ->map(fn (array $item) => collect($item)->except('_sort')->all())
            ->values()
            ->all();

        // Snapshot details
        //
        // recipient_name is suffixed with project for BQT so the FE's
        // "Thanh toán tổng" grouping key (recipient_type|account_id|recipient_name)
        // matches between a by_recipient row and its underlying snapshots.
        $formattedSnapshots = $snapshots->map(fn (OrderCommissionSnapshot $s) => [
            'id' => $s->id,
            'order_id' => $s->order_id,
            'order_code' => $s->order?->code,
            'closing_period_id' => $s->closing_period_id,
            'closing_period_name' => $s->closingPeriod?->name,
            'recipient_type' => ['value' => $s->recipient_type->value, 'label' => $s->recipient_type->label()],
            'recipient_name' => $this->displayRecipientName($s),
            'account_id' => $s->account_id,
            'bank_info' => $this->resolveBankInfo($s, $platformBankInfo, $operatingCompanyBankInfo),
            'value_type' => $s->value_type ? ['value' => $s->value_type->value, 'label' => $s->value_type->label()] : null,
            'percent' => $s->percent,
            'value_fixed' => $s->value_fixed,
            'amount' => $s->amount,
            'resolved_from' => $s->resolved_from,
            'payout_status' => ['value' => $s->payout_status->value, 'label' => $s->payout_status->label()],
            'paid_out_at' => $s->paid_out_at?->toIso8601String(),
            'cash_transaction' => $s->relationLoaded('cashTransaction') && $s->cashTransaction
                ? ['id' => $s->cashTransaction->id, 'code' => $s->cashTransaction->code]
                : null,
        ])->all();

        return [
            'stats' => $stats,
            'by_recipient' => $byRecipient,
            'snapshots' => $formattedSnapshots,
        ];
    }

    /**
     * A snapshot is "terminal" when money actually stops there. Overrides
     * always bypass the hierarchy and go straight to a payee; otherwise
     * fall back to the recipient type classification.
     */
    private function isTerminalRecipient(OrderCommissionSnapshot $snapshot): bool
    {
        if ($snapshot->resolved_from === 'override') {
            return true;
        }

        return $snapshot->recipient_type->isTerminal();
    }

    /**
     * Resolve the bank info displayed next to each recipient/snapshot row.
     *
     *   - override or staff: use the account's bank info.
     *   - platform: use the singleton bank info configured on the Platform module.
     *   - operating_company: use the tenant's bank_account settings (same row
     *     the receivables page uses for the customer payment QR).
     *   - board_of_directors: each project owns its own BQT bank info.
     *
     * @param  array{bin: string, label: string, account_number: string, account_name: string}|null  $platformBankInfo
     * @param  array{bin: string, label: string, account_number: string, account_name: string}|null  $operatingCompanyBankInfo
     * @return array{bin: string, label: string, account_number: string, account_name: string}|null
     */
    private function resolveBankInfo(
        OrderCommissionSnapshot $snapshot,
        ?array $platformBankInfo,
        ?array $operatingCompanyBankInfo,
    ): ?array {
        if ($snapshot->resolved_from === 'override' || $snapshot->account_id !== null) {
            return $snapshot->account?->bankInfo();
        }

        return match ($snapshot->recipient_type) {
            SnapshotRecipientType::Platform => $platformBankInfo,
            SnapshotRecipientType::OperatingCompany => $operatingCompanyBankInfo,
            SnapshotRecipientType::BoardOfDirectors => $snapshot->order?->quote?->ogTicket?->project?->bqtBankInfo(),
            default => null,
        };
    }

    /**
     * BQT rows are per-project: label them with the project name so the
     * FE can show multiple BQT rows without ambiguity, and so both
     * `by_recipient[]` and `snapshots[]` use the same display name — this
     * keeps the FE's grouping key (recipient_type|account_id|recipient_name)
     * consistent across the two tables.
     */
    private function displayRecipientName(OrderCommissionSnapshot $snapshot): string
    {
        if ($snapshot->recipient_type !== SnapshotRecipientType::BoardOfDirectors) {
            return $snapshot->recipient_name;
        }

        $projectName = $snapshot->order?->quote?->ogTicket?->project?->name;

        return $projectName
            ? "{$snapshot->recipient_name} — {$projectName}"
            : $snapshot->recipient_name;
    }

    /**
     * Read the operating-company bank info from the tenant `bank_account`
     * settings group and normalise it to the same shape as Account::bankInfo().
     *
     * Returns null when any required field is missing so the FE can decide
     * whether to show the QR button.
     *
     * @return array{bin: string, label: string, account_number: string, account_name: string}|null
     */
    private function buildOperatingCompanyBankInfo(): ?array
    {
        $settings = $this->systemSettings->getGroup('bank_account');

        $bin = $settings['bank_bin'] ?? null;
        $accountNumber = $settings['account_number'] ?? null;
        $accountHolder = $settings['account_holder'] ?? null;
        $bankName = $settings['bank_name'] ?? null;

        if (! $bin || ! $accountNumber || ! $accountHolder) {
            return null;
        }

        return [
            'bin' => (string) $bin,
            'label' => (string) ($bankName ?? ''),
            'account_number' => (string) $accountNumber,
            'account_name' => (string) $accountHolder,
        ];
    }

    /**
     * Update payout status for given snapshot IDs.
     *
     * Loops per-snapshot so Eloquent events fire and we can dispatch Treasury
     * events (CommissionSnapshotPaid / CommissionSnapshotUnpaid) that feed the
     * cash transaction ledger. A mass update would bypass Eloquent events.
     *
     * @param  array<int>  $snapshotIds
     */
    public function updatePayoutStatus(array $snapshotIds, PayoutStatus $status): int
    {
        return $this->executeInTransaction(function () use ($snapshotIds, $status): int {
            $snapshots = OrderCommissionSnapshot::query()
                ->whereIn('id', $snapshotIds)
                ->get();

            $count = 0;

            foreach ($snapshots as $snapshot) {
                $oldStatus = $snapshot->payout_status;

                if ($oldStatus === $status) {
                    continue;
                }

                // Zero-amount snapshots are auto-paid at creation time and must
                // never flip back to unpaid (invariant from CommissionSnapshotService).
                if ($status === PayoutStatus::Unpaid && (float) $snapshot->amount <= 0) {
                    continue;
                }

                $snapshot->update([
                    'payout_status' => $status->value,
                    'paid_out_at' => $status === PayoutStatus::Paid ? now() : null,
                ]);

                if ($oldStatus === PayoutStatus::Unpaid && $status === PayoutStatus::Paid) {
                    CommissionSnapshotPaid::dispatch($snapshot->fresh());
                } elseif ($oldStatus === PayoutStatus::Paid && $status === PayoutStatus::Unpaid) {
                    CommissionSnapshotUnpaid::dispatch($snapshot->fresh());
                }

                $count++;
            }

            return $count;
        });
    }

    /**
     * Assert that the period is open.
     */
    private function assertOpen(ClosingPeriod $period): void
    {
        if ($period->status !== ClosingPeriodStatus::Open) {
            throw new BusinessException(
                message: 'Kỳ chốt đã đóng. Không thể thực hiện thao tác này.',
                errorCode: 'CLOSING_PERIOD_NOT_OPEN',
            );
        }
    }

    /**
     * Sum only top-level commission amounts (Platform, Operating Company, Board of Directors).
     * Management/Department/Staff are internal distributions and should not be double-counted.
     *
     * @param  array<OrderCommissionSnapshot>  $snapshots
     */
    private function sumTopLevelCommission(array $snapshots): float
    {
        $topLevelTypes = SnapshotRecipientType::topLevel();

        return round(array_sum(array_map(
            fn ($s) => $s->resolved_from === 'override' || in_array($s->recipient_type, $topLevelTypes)
                ? (float) $s->amount
                : 0,
            $snapshots,
        )), 2);
    }

    /**
     * Assert that an order is eligible to be added to a period.
     */
    private function assertOrderEligible(Order $order): void
    {
        if ($order->status !== OrderStatus::Completed) {
            throw new BusinessException(
                message: "Đơn hàng {$order->code} chưa hoàn thành.",
                errorCode: 'ORDER_NOT_COMPLETED',
            );
        }

        $eligibleStatuses = [ReceivableStatus::Paid, ReceivableStatus::Completed];
        if (! $order->receivable || ! in_array($order->receivable->status, $eligibleStatuses)) {
            throw new BusinessException(
                message: "Đơn hàng {$order->code} chưa thu đủ tiền.",
                errorCode: 'RECEIVABLE_NOT_PAID',
            );
        }

        if ($this->repository->isOrderInAnyPeriod($order->id)) {
            throw new BusinessException(
                message: "Đơn hàng {$order->code} đã thuộc kỳ chốt khác.",
                errorCode: 'ORDER_ALREADY_IN_PERIOD',
            );
        }
    }
}
