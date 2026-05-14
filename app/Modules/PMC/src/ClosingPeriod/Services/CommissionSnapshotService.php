<?php

namespace App\Modules\PMC\ClosingPeriod\Services;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\ClosingPeriod\Contracts\CommissionSnapshotServiceInterface;
use App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\ClosingPeriod\Repositories\ClosingPeriodRepository;
use App\Modules\PMC\Commission\Enums\CommissionPartyType;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use App\Modules\PMC\Commission\Models\ProjectCommissionConfig;
use App\Modules\PMC\Order\Enums\CommissionOverrideRecipientType;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Enums\QuoteLineType;

class CommissionSnapshotService implements CommissionSnapshotServiceInterface
{
    public function __construct(
        protected ClosingPeriodRepository $repository,
    ) {}

    public function createSnapshotsForOrder(ClosingPeriod $period, Order $order): array
    {
        $snapshots = $this->calculateSnapshots($period, $order);

        $result = [];
        foreach ($snapshots as $data) {
            $result[] = OrderCommissionSnapshot::query()->create($data);
        }

        return $result;
    }

    public function recalculateForOrder(ClosingPeriod $period, Order $order): array
    {
        $this->repository->deleteSnapshots($period->id, $order->id);

        return $this->createSnapshotsForOrder($period, $order);
    }

    /**
     * Calculate commission snapshot data for an order.
     *
     * @return list<array<string, mixed>>
     */
    private function calculateSnapshots(ClosingPeriod $period, Order $order): array
    {
        // Check if order has overrides
        if (! $order->relationLoaded('commissionOverrides')) {
            $order->load('commissionOverrides.account:id,name');
        }

        if ($order->commissionOverrides->isNotEmpty()) {
            return $this->calculateFromOverrides($period, $order);
        }

        return $this->calculateFromConfig($period, $order);
    }

    /**
     * Calculate from override rules (bypass config entirely).
     *
     * @return list<array<string, mixed>>
     */
    private function calculateFromOverrides(ClosingPeriod $period, Order $order): array
    {
        $commissionableTotal = $this->getCommissionableTotal($order);
        $snapshots = [];

        // Platform amount calculated separately
        $platformAmount = $this->calculatePlatformAmount($commissionableTotal);
        $snapshots[] = $this->buildSnapshotData(
            periodId: $period->id,
            orderId: $order->id,
            recipientType: SnapshotRecipientType::Platform,
            accountId: null,
            recipientName: 'Platform',
            valueType: CommissionValueType::Both,
            percent: (float) config('commission.platform_default_percent', 5),
            valueFixed: (float) config('commission.platform_default_fixed', 1000),
            amount: $platformAmount,
            resolvedFrom: 'override',
        );

        // Override entries
        foreach ($order->commissionOverrides as $override) {
            $recipientType = match ($override->recipient_type) {
                CommissionOverrideRecipientType::OperatingCompany => SnapshotRecipientType::OperatingCompany,
                CommissionOverrideRecipientType::BoardOfDirectors => SnapshotRecipientType::BoardOfDirectors,
                CommissionOverrideRecipientType::Staff => SnapshotRecipientType::Staff,
            };

            $recipientName = match ($override->recipient_type) {
                CommissionOverrideRecipientType::OperatingCompany => 'Công ty vận hành',
                CommissionOverrideRecipientType::BoardOfDirectors => 'Ban quản trị',
                CommissionOverrideRecipientType::Staff => $override->account?->name ?? 'N/A',
            };

            $snapshots[] = $this->buildSnapshotData(
                periodId: $period->id,
                orderId: $order->id,
                recipientType: $recipientType,
                accountId: $override->account_id,
                recipientName: $recipientName,
                valueType: CommissionValueType::Fixed,
                percent: null,
                valueFixed: (float) $override->amount,
                amount: (float) $override->amount,
                resolvedFrom: 'override',
            );
        }

        return $snapshots;
    }

    /**
     * Calculate from commission config (3-level distribution).
     *
     * @return list<array<string, mixed>>
     */
    private function calculateFromConfig(ClosingPeriod $period, Order $order): array
    {
        $commissionableTotal = $this->getCommissionableTotal($order);

        // Find project config
        $projectId = $order->quote?->ogTicket?->project_id;
        $config = $projectId
            ? ProjectCommissionConfig::query()
                ->where('project_id', $projectId)
                ->with([
                    'partyRulesOrdered',
                    'deptRules' => fn ($q) => $q->orderBy('sort_order'),
                    'deptRules.department:id,name',
                    'deptRules.staffRules' => fn ($q) => $q->orderBy('sort_order'),
                    'deptRules.staffRules.account:id,name',
                ])
                ->first()
            : null;

        $snapshots = [];

        // === Level 1: Party distribution on commissionableTotal ===
        $partyRecipients = $this->buildPartyRecipients($config, $commissionableTotal);
        $partyResults = $this->distributePool($commissionableTotal, $partyRecipients);

        $managementAmount = 0;

        foreach ($partyResults as $result) {
            $snapshots[] = $this->buildSnapshotData(
                periodId: $period->id,
                orderId: $order->id,
                recipientType: $result['recipient_type'],
                accountId: null,
                recipientName: $result['recipient_name'],
                valueType: $result['value_type'],
                percent: $result['percent'],
                valueFixed: $result['value_fixed'],
                amount: $result['amount'],
                resolvedFrom: 'config',
            );

            if ($result['recipient_type'] === SnapshotRecipientType::Management) {
                $managementAmount = $result['amount'];
            }
        }

        if (! $config || $managementAmount <= 0) {
            return $snapshots;
        }

        // === Level 2: Department distribution on management amount ===
        $deptRules = $config->deptRules;
        $deptRecipients = [];
        foreach ($deptRules as $rule) {
            $deptRecipients[] = [
                'sort_order' => $rule->sort_order,
                'value_type' => $rule->value_type,
                'percent' => $rule->percent ? (float) $rule->percent : null,
                'value_fixed' => $rule->value_fixed ? (float) $rule->value_fixed : null,
                'dept_rule' => $rule,
            ];
        }

        $deptResults = $this->distributePool($managementAmount, $deptRecipients);

        foreach ($deptResults as $i => $result) {
            $rule = $deptRules[$i] ?? null;
            $deptName = $rule?->department?->name ?? 'Phòng ban';

            $snapshots[] = $this->buildSnapshotData(
                periodId: $period->id,
                orderId: $order->id,
                recipientType: SnapshotRecipientType::Department,
                accountId: null,
                recipientName: $deptName,
                valueType: $result['value_type'],
                percent: $result['percent'],
                valueFixed: $result['value_fixed'],
                amount: $result['amount'],
                resolvedFrom: 'config',
            );

            // === Level 3: Staff distribution on each department amount ===
            if ($rule && $result['amount'] > 0) {
                $staffSnapshots = $this->calculateStaffDistribution(
                    $period,
                    $order,
                    $rule,
                    $result['amount'],
                );
                $snapshots = array_merge($snapshots, $staffSnapshots);
            }
        }

        return $snapshots;
    }

    /**
     * Build party-level recipients list (Platform + config party rules).
     *
     * @return list<array<string, mixed>>
     */
    private function buildPartyRecipients(?ProjectCommissionConfig $config, float $pool): array
    {
        $recipients = [];

        // Platform (always first, sort_order = 1)
        $recipients[] = [
            'sort_order' => 1,
            'recipient_type' => SnapshotRecipientType::Platform,
            'recipient_name' => 'Platform',
            'value_type' => CommissionValueType::Both,
            'percent' => (float) config('commission.platform_default_percent', 5),
            'value_fixed' => (float) config('commission.platform_default_fixed', 1000),
        ];

        if ($config) {
            foreach ($config->partyRulesOrdered as $rule) {
                $recipientType = match ($rule->party_type) {
                    CommissionPartyType::OperatingCompany => SnapshotRecipientType::OperatingCompany,
                    CommissionPartyType::BoardOfDirectors => SnapshotRecipientType::BoardOfDirectors,
                    CommissionPartyType::Management => SnapshotRecipientType::Management,
                };

                $recipients[] = [
                    'sort_order' => $rule->party_type->sortOrder(),
                    'recipient_type' => $recipientType,
                    'recipient_name' => $rule->party_type->label(),
                    'value_type' => $rule->value_type,
                    'percent' => $rule->percent ? (float) $rule->percent : null,
                    'value_fixed' => $rule->value_fixed ? (float) $rule->value_fixed : null,
                ];
            }
        }

        usort($recipients, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $recipients;
    }

    /**
     * 2-round distribution algorithm.
     *
     * Round 1: Fixed amount deduction (in sort_order).
     * Round 2: Percentage distribution on remaining.
     *
     * @param  list<array<string, mixed>>  $recipients
     * @return list<array<string, mixed>>
     */
    private function distributePool(float $pool, array $recipients): array
    {
        $results = [];
        $remaining = $pool;

        // Round 1 — Fixed deduction
        foreach ($recipients as &$r) {
            $fixedAmount = 0;
            $valueType = $r['value_type'] instanceof CommissionValueType ? $r['value_type'] : CommissionValueType::from($r['value_type']);

            if ($valueType->requiresFixed() && $r['value_fixed'] > 0) {
                $fixedAmount = min((float) $r['value_fixed'], $remaining);
                $remaining = max(0, $remaining - $fixedAmount);
            }

            $r['_fixed_amount'] = $fixedAmount;
        }
        unset($r);

        // Round 2 — Percent on remaining
        foreach ($recipients as $r) {
            $percentAmount = 0;
            $valueType = $r['value_type'] instanceof CommissionValueType ? $r['value_type'] : CommissionValueType::from($r['value_type']);

            if ($valueType->requiresPercent() && $r['percent'] > 0) {
                $percentAmount = round($remaining * (float) $r['percent'] / 100, 2);
            }

            $totalAmount = round($r['_fixed_amount'] + $percentAmount, 2);

            $results[] = [
                'recipient_type' => $r['recipient_type'] ?? null,
                'recipient_name' => $r['recipient_name'] ?? null,
                'value_type' => $valueType,
                'percent' => $r['percent'],
                'value_fixed' => $r['value_fixed'],
                'amount' => $totalAmount,
            ];
        }

        return $results;
    }

    /**
     * Calculate staff distribution for a department rule.
     *
     * @return list<array<string, mixed>>
     */
    private function calculateStaffDistribution(
        ClosingPeriod $period,
        Order $order,
        \App\Modules\PMC\Commission\Models\CommissionDeptRule $deptRule,
        float $deptAmount,
    ): array {
        if (! $deptRule->relationLoaded('staffRules')) {
            $deptRule->load('staffRules.account:id,name');
        }

        $staffRules = $deptRule->staffRules->sortBy('sort_order')->values();

        if ($staffRules->isEmpty()) {
            return [];
        }

        $staffRecipients = [];
        foreach ($staffRules as $rule) {
            $staffRecipients[] = [
                'sort_order' => $rule->sort_order,
                'value_type' => $rule->value_type,
                'percent' => $rule->percent ? (float) $rule->percent : null,
                'value_fixed' => $rule->value_fixed ? (float) $rule->value_fixed : null,
                'account' => $rule->account,
            ];
        }

        $staffResults = $this->distributePool($deptAmount, $staffRecipients);
        $snapshots = [];

        foreach ($staffResults as $i => $result) {
            /** @var Account|null $account */
            $account = $staffRecipients[$i]['account'] ?? null;

            $snapshots[] = $this->buildSnapshotData(
                periodId: $period->id,
                orderId: $order->id,
                recipientType: SnapshotRecipientType::Staff,
                accountId: $account?->id,
                recipientName: $account?->name ?? 'N/A',
                valueType: $result['value_type'],
                percent: $result['percent'],
                valueFixed: $result['value_fixed'],
                amount: $result['amount'],
                resolvedFrom: 'config',
            );
        }

        return $snapshots;
    }

    /**
     * Calculate commissionable total = SUM(line_amount) for service + adhoc lines.
     */
    private function getCommissionableTotal(Order $order): float
    {
        if (! $order->relationLoaded('lines')) {
            $order->load('lines');
        }

        return (float) $order->lines
            ->filter(fn ($line) => in_array($line->line_type, [QuoteLineType::Service, QuoteLineType::Adhoc]))
            ->sum('line_amount');
    }

    /**
     * Calculate platform amount (fixed first, then percent on remaining).
     */
    private function calculatePlatformAmount(float $commissionableTotal): float
    {
        $platformFixed = (float) config('commission.platform_default_fixed', 1000);
        $platformPercent = (float) config('commission.platform_default_percent', 5);

        $fixedDeduction = min($platformFixed, $commissionableTotal);
        $remaining = $commissionableTotal - $fixedDeduction;
        $percentAmount = $remaining * $platformPercent / 100;

        return round($fixedDeduction + $percentAmount, 2);
    }

    /**
     * Build snapshot data array for creation.
     *
     * Zero-amount snapshots are auto-marked as paid since there is nothing
     * to actually pay out. This keeps the invariant `amount = 0 ⇒ paid`
     * and prevents them from appearing in the commission summary report.
     *
     * @return array<string, mixed>
     */
    private function buildSnapshotData(
        int $periodId,
        int $orderId,
        SnapshotRecipientType $recipientType,
        ?int $accountId,
        string $recipientName,
        CommissionValueType $valueType,
        ?float $percent,
        ?float $valueFixed,
        float $amount,
        string $resolvedFrom,
    ): array {
        $isZero = $amount <= 0;

        return [
            'closing_period_id' => $periodId,
            'order_id' => $orderId,
            'recipient_type' => $recipientType->value,
            'account_id' => $accountId,
            'recipient_name' => $recipientName,
            'value_type' => $valueType->value,
            'percent' => $percent,
            'value_fixed' => $valueFixed,
            'amount' => $amount,
            'resolved_from' => $resolvedFrom,
            'payout_status' => $isZero ? PayoutStatus::Paid->value : PayoutStatus::Unpaid->value,
            'paid_out_at' => $isZero ? now() : null,
            'created_at' => now(),
        ];
    }
}
