<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\ClosingPeriod\Contracts\CommissionSnapshotServiceInterface;
use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use Illuminate\Database\Seeder;

class ClosingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        if (ClosingPeriod::query()->exists()) {
            return;
        }

        // Find eligible orders: completed + receivable paid
        $eligibleOrders = Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->whereHas('receivable', fn ($q) => $q->where('status', ReceivableStatus::Paid->value))
            ->with(['receivable', 'commissionOverrides.account:id,name', 'lines', 'quote.ogTicket'])
            ->get();

        if ($eligibleOrders->isEmpty()) {
            return;
        }

        $snapshotService = app(CommissionSnapshotServiceInterface::class);

        // Create a closed period for last month
        /** @var ClosingPeriod $closedPeriod */
        $closedPeriod = ClosingPeriod::query()->create([
            'project_id' => null,
            'name' => 'Tháng '.now()->subMonth()->format('n/Y'),
            'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
            'status' => ClosingPeriodStatus::Closed->value,
            'closed_at' => now()->subDays(3),
            'note' => 'Đã đối soát xong tháng trước',
        ]);

        // Add first eligible order (if any)
        $firstOrder = $eligibleOrders->first();
        if ($firstOrder) {
            $this->addOrderToPeriod($closedPeriod, $firstOrder, $snapshotService);
        }

        // If there's more than 1 eligible order, create an open period for this month
        if ($eligibleOrders->count() > 1) {
            /** @var ClosingPeriod $openPeriod */
            $openPeriod = ClosingPeriod::query()->create([
                'project_id' => null,
                'name' => 'Tháng '.now()->format('n/Y'),
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'status' => ClosingPeriodStatus::Open->value,
            ]);

            $secondOrder = $eligibleOrders->skip(1)->first();
            if ($secondOrder) {
                $this->addOrderToPeriod($openPeriod, $secondOrder, $snapshotService);
            }
        }
    }

    private function addOrderToPeriod(
        ClosingPeriod $period,
        Order $order,
        CommissionSnapshotServiceInterface $snapshotService,
    ): void {
        $snapshots = $snapshotService->createSnapshotsForOrder($period, $order);
        $commissionTotal = array_sum(array_map(fn ($s) => (float) $s->amount, $snapshots));

        ClosingPeriodOrder::query()->create([
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
            'frozen_receivable_amount' => $order->receivable->amount,
            'frozen_commission_total' => round($commissionTotal, 2),
        ]);
    }
}
