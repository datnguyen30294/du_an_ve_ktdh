<?php

namespace App\Modules\PMC\Report\CashFlow\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CashFlowReportRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new CashTransaction);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(array $filters): array
    {
        $dateTo = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();
        $dateFrom = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : $dateTo->copy()->subDays(29)->startOfDay();

        return [$dateFrom, $dateTo];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getPeriodLabel(array $filters): string
    {
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            return '30 ngày gần nhất';
        }

        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->subDays(29))->format('d/m/Y');
        $dateTo = Carbon::parse($filters['date_to'] ?? now())->format('d/m/Y');

        return "{$dateFrom} - {$dateTo}";
    }

    /**
     * Build the scoped base query (account + date + optional project filter).
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildBaseQuery(int $cashAccountId, array $filters): Builder
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $query = DB::table('cash_transactions')
            ->where('cash_transactions.cash_account_id', $cashAccountId)
            ->whereNull('cash_transactions.deleted_at')
            ->whereDate('cash_transactions.transaction_date', '>=', $dateFrom->toDateString())
            ->whereDate('cash_transactions.transaction_date', '<=', $dateTo->toDateString());

        if (! empty($filters['project_id'])) {
            $query->join('orders', 'cash_transactions.order_id', '=', 'orders.id')
                ->join('quotes', 'orders.quote_id', '=', 'quotes.id')
                ->join('og_tickets', 'quotes.og_ticket_id', '=', 'og_tickets.id')
                ->where('og_tickets.project_id', (int) $filters['project_id'])
                ->whereNotNull('cash_transactions.order_id');
        }

        return $query;
    }

    /**
     * Compute current balance (all transactions, no date or project filter).
     */
    public function computeCurrentBalance(int $cashAccountId, float $openingBalance): float
    {
        $inflow = (float) DB::table('cash_transactions')
            ->where('cash_account_id', $cashAccountId)
            ->whereNull('deleted_at')
            ->where('direction', CashTransactionDirection::Inflow->value)
            ->sum('amount');

        $outflow = (float) DB::table('cash_transactions')
            ->where('cash_account_id', $cashAccountId)
            ->whereNull('deleted_at')
            ->where('direction', CashTransactionDirection::Outflow->value)
            ->sum('amount');

        return $openingBalance + $inflow - $outflow;
    }

    /**
     * Aggregate KPI numbers and per-category breakdown.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total_inflow: float, total_outflow: float, transaction_count: int, inflow_by_category: list<array{category: string, amount: float, count: int}>, outflow_by_category: list<array{category: string, amount: float, count: int}>}
     */
    public function getSummary(int $cashAccountId, array $filters): array
    {
        $baseQuery = $this->buildBaseQuery($cashAccountId, $filters);

        $totalInflow = (float) (clone $baseQuery)
            ->where('cash_transactions.direction', CashTransactionDirection::Inflow->value)
            ->sum('cash_transactions.amount');

        $totalOutflow = (float) (clone $baseQuery)
            ->where('cash_transactions.direction', CashTransactionDirection::Outflow->value)
            ->sum('cash_transactions.amount');

        $transactionCount = (clone $baseQuery)->count('cash_transactions.id');

        $inflowByCategory = (clone $baseQuery)
            ->where('cash_transactions.direction', CashTransactionDirection::Inflow->value)
            ->groupBy('cash_transactions.category')
            ->selectRaw('cash_transactions.category as category_value, SUM(cash_transactions.amount) as total_amount, COUNT(cash_transactions.id) as total_count')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category_value,
                'amount' => (float) $row->total_amount,
                'count' => (int) $row->total_count,
            ])
            ->values()
            ->all();

        $outflowByCategory = (clone $baseQuery)
            ->where('cash_transactions.direction', CashTransactionDirection::Outflow->value)
            ->groupBy('cash_transactions.category')
            ->selectRaw('cash_transactions.category as category_value, SUM(cash_transactions.amount) as total_amount, COUNT(cash_transactions.id) as total_count')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category_value,
                'amount' => (float) $row->total_amount,
                'count' => (int) $row->total_count,
            ])
            ->values()
            ->all();

        return [
            'total_inflow' => $totalInflow,
            'total_outflow' => $totalOutflow,
            'transaction_count' => $transactionCount,
            'inflow_by_category' => $inflowByCategory,
            'outflow_by_category' => $outflowByCategory,
        ];
    }

    /**
     * Daily aggregation: GROUP BY transaction_date.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{date: string, total_inflow: float, total_outflow: float}>
     */
    public function getDaily(int $cashAccountId, array $filters): array
    {
        return $this->buildBaseQuery($cashAccountId, $filters)
            ->selectRaw(
                'DATE(cash_transactions.transaction_date) as date,
                SUM(CASE WHEN cash_transactions.direction = ? THEN cash_transactions.amount ELSE 0 END) as total_inflow,
                SUM(CASE WHEN cash_transactions.direction = ? THEN cash_transactions.amount ELSE 0 END) as total_outflow',
                [CashTransactionDirection::Inflow->value, CashTransactionDirection::Outflow->value],
            )
            ->groupByRaw('DATE(cash_transactions.transaction_date)')
            ->orderByRaw('DATE(cash_transactions.transaction_date) DESC')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total_inflow' => (float) $row->total_inflow,
                'total_outflow' => (float) $row->total_outflow,
            ])
            ->all();
    }

    /**
     * Paginated transaction list with order and project details.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, mixed>
     */
    public function getTransactions(int $cashAccountId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $hasProjectFilter = ! empty($filters['project_id']);

        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $query = DB::table('cash_transactions')
            ->where('cash_transactions.cash_account_id', $cashAccountId)
            ->whereNull('cash_transactions.deleted_at')
            ->whereDate('cash_transactions.transaction_date', '>=', $dateFrom->toDateString())
            ->whereDate('cash_transactions.transaction_date', '<=', $dateTo->toDateString());

        if ($hasProjectFilter) {
            $query->join('orders', 'cash_transactions.order_id', '=', 'orders.id')
                ->join('quotes', 'orders.quote_id', '=', 'quotes.id')
                ->join('og_tickets', 'quotes.og_ticket_id', '=', 'og_tickets.id')
                ->leftJoin('projects', 'og_tickets.project_id', '=', 'projects.id')
                ->where('og_tickets.project_id', (int) $filters['project_id'])
                ->whereNotNull('cash_transactions.order_id');
        } else {
            $query->leftJoin('orders', function ($join): void {
                $join->on('cash_transactions.order_id', '=', 'orders.id')
                    ->whereNull('orders.deleted_at');
            })
                ->leftJoin('quotes', function ($join): void {
                    $join->on('orders.quote_id', '=', 'quotes.id')
                        ->whereNull('quotes.deleted_at');
                })
                ->leftJoin('og_tickets', function ($join): void {
                    $join->on('quotes.og_ticket_id', '=', 'og_tickets.id')
                        ->whereNull('og_tickets.deleted_at');
                })
                ->leftJoin('projects', function ($join): void {
                    $join->on('og_tickets.project_id', '=', 'projects.id')
                        ->whereNull('projects.deleted_at');
                });
        }

        return $query
            ->select([
                'cash_transactions.id',
                'cash_transactions.code',
                'cash_transactions.transaction_date',
                'cash_transactions.direction',
                'cash_transactions.category',
                'cash_transactions.amount',
                'cash_transactions.note',
                'orders.code as order_code',
                'projects.name as project_name',
            ])
            ->orderByDesc('cash_transactions.transaction_date')
            ->orderByDesc('cash_transactions.id')
            ->paginate($perPage);
    }
}
