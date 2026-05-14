<?php

namespace App\Modules\PMC\ClosingPeriod\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use Illuminate\Pagination\LengthAwarePaginator;

class ClosingPeriodRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new ClosingPeriod);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with(['project:id,name', 'closedBy:id,name'])
            ->withCount('orders')
            ->withSum('orders as total_receivable', 'frozen_receivable_amount')
            ->withSum('orders as total_commission', 'frozen_commission_total');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->byStatus(ClosingPeriodStatus::from($filters['status']));
        }

        if (array_key_exists('project_id', $filters)) {
            $query->byProject($filters['project_id']);
        }

        $this->applySorting($query, $filters, 'created_at', 'desc');

        return $query->paginate($this->getPerPage($filters));
    }

    /**
     * Find closing period with full detail (orders + snapshots).
     */
    public function findWithDetail(int $id): ClosingPeriod
    {
        /** @var ClosingPeriod */
        return $this->newQuery()
            ->with([
                'project:id,name',
                'closedBy:id,name',
                'orders.order:id,code,total_amount',
                'orders.order.receivable:id,order_id,amount',
            ])
            ->findOrFail($id);
    }

    /**
     * Find the ClosingPeriodOrder pivot for a given order in a period.
     */
    public function findPeriodOrder(int $periodId, int $orderId): ?ClosingPeriodOrder
    {
        return ClosingPeriodOrder::query()
            ->where('closing_period_id', $periodId)
            ->where('order_id', $orderId)
            ->first();
    }

    /**
     * Check if an order is already in any closing period.
     */
    public function isOrderInAnyPeriod(int $orderId): bool
    {
        return ClosingPeriodOrder::query()
            ->where('order_id', $orderId)
            ->exists();
    }

    /**
     * Create a ClosingPeriodOrder pivot record.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPeriodOrder(array $data): ClosingPeriodOrder
    {
        return ClosingPeriodOrder::query()->create($data);
    }

    /**
     * Delete a ClosingPeriodOrder and its snapshots.
     */
    public function deletePeriodOrder(int $periodId, int $orderId): void
    {
        OrderCommissionSnapshot::query()
            ->where('closing_period_id', $periodId)
            ->where('order_id', $orderId)
            ->delete();

        ClosingPeriodOrder::query()
            ->where('closing_period_id', $periodId)
            ->where('order_id', $orderId)
            ->delete();
    }

    /**
     * Delete all snapshots for an order in a period.
     */
    public function deleteSnapshots(int $periodId, int $orderId): void
    {
        OrderCommissionSnapshot::query()
            ->where('closing_period_id', $periodId)
            ->where('order_id', $orderId)
            ->delete();
    }

    /**
     * Whether any snapshot in the period still has an ACTIVE (non-trashed)
     * cash transaction attached. Used by the reopen/recalc guards so we can
     * block the operation with a friendly message instead of letting the
     * hard-delete cascade nullify a real cash ledger link.
     */
    public function hasActivePaidCommission(int $periodId): bool
    {
        return OrderCommissionSnapshot::query()
            ->where('closing_period_id', $periodId)
            ->whereHas('cashTransaction')
            ->exists();
    }

    /**
     * Get all snapshots attached to an order (across any period it belongs to).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, OrderCommissionSnapshot>
     */
    public function getSnapshotsByOrderId(int $orderId): \Illuminate\Database\Eloquent\Collection
    {
        return OrderCommissionSnapshot::query()
            ->where('order_id', $orderId)
            ->with('closingPeriod:id,name,status')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get snapshots for a specific order in a period.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, OrderCommissionSnapshot>
     */
    public function getSnapshotsForOrder(int $periodId, int $orderId): \Illuminate\Database\Eloquent\Collection
    {
        return OrderCommissionSnapshot::query()
            ->where('closing_period_id', $periodId)
            ->where('order_id', $orderId)
            ->get();
    }

    /**
     * Get all snapshots for a period grouped by order.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, OrderCommissionSnapshot>
     */
    public function getSnapshotsForPeriod(int $periodId): \Illuminate\Database\Eloquent\Collection
    {
        return OrderCommissionSnapshot::query()
            ->where('closing_period_id', $periodId)
            ->with('account:id,name,employee_code')
            ->get();
    }

    /**
     * Get filtered snapshots for commission summary report.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, OrderCommissionSnapshot>
     */
    public function getFilteredSnapshots(array $filters): \Illuminate\Database\Eloquent\Collection
    {
        $query = OrderCommissionSnapshot::query()
            ->with([
                'order:id,code,quote_id',
                'order.quote:id,og_ticket_id',
                'order.quote.ogTicket:id,project_id',
                'order.quote.ogTicket.project:id,name,bqt_bank_bin,bqt_bank_name,bqt_account_number,bqt_account_holder',
                'closingPeriod:id,name',
                'cashTransaction:id,code,commission_snapshot_id',
                'account:id,name,employee_code,bank_bin,bank_label,bank_account_number,bank_account_name',
            ]);

        $closingPeriodId = $filters['closing_period_id'];

        if ($closingPeriodId === 'pending') {
            $query->whereHas('closingPeriod', fn ($q) => $q->where('status', ClosingPeriodStatus::Open->value));
        } elseif ($closingPeriodId !== 'all') {
            $query->where('closing_period_id', (int) $closingPeriodId);
        }

        if (! empty($filters['project_id'])) {
            $query->whereHas('order', function ($q) use ($filters): void {
                $q->whereHas('quote.ogTicket', fn ($q2) => $q2->where('project_id', $filters['project_id']));
            });
        }

        if (! empty($filters['recipient_type'])) {
            $query->where('recipient_type', $filters['recipient_type']);
        }

        if (! empty($filters['resolved_from'])) {
            $query->where('resolved_from', $filters['resolved_from']);
        }

        return $query->orderBy('order_id')->orderBy('recipient_type')->get();
    }

    /**
     * Update payout status for given snapshot IDs.
     *
     * Zero-amount snapshots are never allowed to be marked unpaid — they
     * are auto-paid at creation time (see CommissionSnapshotService) and
     * must keep that invariant. This guard protects against direct API
     * calls bypassing the UI filter.
     *
     * @param  array<int>  $snapshotIds
     */
    public function updatePayoutStatus(array $snapshotIds, PayoutStatus $status): int
    {
        $data = ['payout_status' => $status->value];

        if ($status === PayoutStatus::Paid) {
            $data['paid_out_at'] = now();
        } else {
            $data['paid_out_at'] = null;
        }

        $query = OrderCommissionSnapshot::query()->whereIn('id', $snapshotIds);

        if ($status === PayoutStatus::Unpaid) {
            $query->where('amount', '>', 0);
        }

        return $query->update($data);
    }
}
