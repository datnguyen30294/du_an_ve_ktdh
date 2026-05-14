<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Receivable\Enums\PaymentMethod;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Receivable\Models\Receivable;
use Illuminate\Database\Seeder;

class ReceivableSeeder extends Seeder
{
    public function run(): void
    {
        if (Receivable::query()->exists()) {
            return;
        }

        // Get confirmed/in_progress/completed orders
        $orders = Order::query()
            ->whereIn('status', [
                OrderStatus::Confirmed->value,
                OrderStatus::InProgress->value,
                OrderStatus::Completed->value,
            ])
            ->with('quote.ogTicket')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $account = Account::query()->first();

        $scenarios = [
            ['receivable' => ReceivableStatus::Unpaid, 'payments' => 0],
            ['receivable' => ReceivableStatus::Partial, 'payments' => 1],
            ['receivable' => ReceivableStatus::Paid, 'payments' => 2],
        ];

        foreach ($orders as $index => $order) {
            $scenario = $scenarios[$index % count($scenarios)];
            $projectId = $order->quote?->ogTicket?->project_id;

            /** @var Receivable $receivable */
            $receivable = Receivable::query()->create([
                'order_id' => $order->id,
                'project_id' => $projectId,
                'amount' => $order->total_amount,
                'paid_amount' => 0,
                'status' => ReceivableStatus::Unpaid->value,
                'due_date' => now()->addDays(30 - ($index * 10)),
                'issued_at' => now()->subDays(7 - $index),
            ]);

            $totalPaid = 0;
            $amount = (float) $order->total_amount;

            if ($scenario['payments'] >= 1) {
                // First payment: 50%
                $paymentAmount = round($amount * 0.5, 2);
                $totalPaid += $paymentAmount;

                $receivable->payments()->create([
                    'amount' => $paymentAmount,
                    'payment_method' => PaymentMethod::Transfer->value,
                    'collected_by_id' => $account?->id,
                    'note' => 'Thu tiền đợt 1 — chuyển khoản',
                    'paid_at' => now()->subDays(5),
                ]);
            }

            if ($scenario['payments'] >= 2) {
                // Second payment: remaining
                $paymentAmount = $amount - $totalPaid;
                $totalPaid += $paymentAmount;

                $receivable->payments()->create([
                    'amount' => $paymentAmount,
                    'payment_method' => PaymentMethod::Cash->value,
                    'collected_by_id' => $account?->id,
                    'note' => 'Thu tiền đợt 2 — tiền mặt',
                    'paid_at' => now()->subDays(2),
                ]);
            }

            $receivable->update([
                'paid_amount' => $totalPaid,
                'status' => $scenario['receivable']->value,
            ]);
        }
    }
}
