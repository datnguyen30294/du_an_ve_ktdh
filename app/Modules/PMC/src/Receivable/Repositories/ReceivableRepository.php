<?php

namespace App\Modules\PMC\Receivable\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Receivable\Models\Receivable;
use Illuminate\Pagination\LengthAwarePaginator;

class ReceivableRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Receivable);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with([
                'order:id,code,quote_id',
                'order.quote:id,og_ticket_id',
                'order.quote.ogTicket:id,subject,requester_name,apartment_name,customer_id',
                'order.quote.ogTicket.customer:id,code,full_name,phone',
                'project:id,name',
            ]);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->byStatus(ReceivableStatus::from($filters['status']));
        }

        if (! empty($filters['project_id'])) {
            $query->byProject((int) $filters['project_id']);
        }

        $this->applySorting($query, $filters, 'created_at');

        return $query->paginate($this->getPerPage($filters, 15));
    }

    /**
     * Find receivable by order ID (non-written-off).
     */
    public function findByOrderId(int $orderId): ?Receivable
    {
        /** @var Receivable|null */
        return $this->newQuery()
            ->where('order_id', $orderId)
            ->where('status', '!=', ReceivableStatus::WrittenOff->value)
            ->first();
    }

    /**
     * Get payment receipt audit records for the given payment IDs.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $paymentIds
     * @return \Illuminate\Support\Collection<int, \OwenIt\Auditing\Models\Audit>
     */
    public function getPaymentReceiptAudits(\Illuminate\Support\Collection $paymentIds): \Illuminate\Support\Collection
    {
        if ($paymentIds->isEmpty()) {
            return collect();
        }

        return \OwenIt\Auditing\Models\Audit::query()
            ->where('auditable_type', \App\Modules\PMC\Receivable\Models\PaymentReceipt::class)
            ->whereIn('auditable_id', $paymentIds)
            ->with('user')
            ->latest()
            ->get();
    }

    /**
     * Get summary KPI and aging buckets.
     *
     * @return array{kpi: array<string, mixed>, aging: list<array<string, mixed>>}
     */
    public function getSummary(?int $projectId = null): array
    {
        $baseQuery = $this->newQuery();

        if ($projectId) {
            $baseQuery->byProject($projectId);
        }

        // KPI: exclude written_off and completed
        $kpiQuery = (clone $baseQuery)->active();
        $kpi = [
            'total_amount' => number_format((float) $kpiQuery->sum('amount'), 2, '.', ''),
            'total_paid' => number_format((float) $kpiQuery->sum('paid_amount'), 2, '.', ''),
            'total_outstanding' => number_format((float) $kpiQuery->sum('amount') - (float) $kpiQuery->sum('paid_amount'), 2, '.', ''),
            'total_overpaid' => number_format(
                max(0, (float) (clone $baseQuery)->where('status', ReceivableStatus::Overpaid->value)->selectRaw('SUM(paid_amount - amount)')->value('SUM(paid_amount - amount)') ?? 0),
                2, '.', ''
            ),
            'count' => $kpiQuery->count(),
        ];

        // Aging: only outstanding statuses
        $agingQuery = (clone $baseQuery)->outstanding();
        $receivables = $agingQuery->select(['id', 'amount', 'paid_amount', 'due_date'])->get();

        $buckets = [
            ['bucket' => '0-7', 'label' => '0–7 ngày', 'total' => 0.0, 'count' => 0],
            ['bucket' => '8-30', 'label' => '8–30 ngày', 'total' => 0.0, 'count' => 0],
            ['bucket' => '31-60', 'label' => '31–60 ngày', 'total' => 0.0, 'count' => 0],
            ['bucket' => '61+', 'label' => '>60 ngày', 'total' => 0.0, 'count' => 0],
        ];

        foreach ($receivables as $receivable) {
            $days = max(0, (int) now()->startOfDay()->diffInDays($receivable->due_date, false) * -1);
            $outstanding = (float) $receivable->amount - (float) $receivable->paid_amount;

            $index = match (true) {
                $days <= 7 => 0,
                $days <= 30 => 1,
                $days <= 60 => 2,
                default => 3,
            };

            $buckets[$index]['total'] += $outstanding;
            $buckets[$index]['count']++;
        }

        // Format totals
        foreach ($buckets as &$bucket) {
            $bucket['total'] = number_format($bucket['total'], 2, '.', '');
        }

        return ['kpi' => $kpi, 'aging' => $buckets];
    }
}
