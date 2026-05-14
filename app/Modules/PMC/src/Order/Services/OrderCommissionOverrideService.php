<?php

namespace App\Modules\PMC\Order\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\Commission\Repositories\CommissionConfigRepository;
use App\Modules\PMC\Order\Contracts\OrderCommissionOverrideServiceInterface;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Repositories\OrderCommissionOverrideRepository;
use App\Modules\PMC\Order\Repositories\OrderRepository;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OrderCommissionOverrideService extends BaseService implements OrderCommissionOverrideServiceInterface
{
    public function __construct(
        protected OrderCommissionOverrideRepository $repository,
        protected OrderRepository $orderRepository,
        protected CommissionConfigRepository $commissionConfigRepository,
    ) {}

    /**
     * @return array{has_overrides: bool, commissionable_total: float, platform_amount: float, overrides: Collection}
     */
    public function getOverrides(int $orderId): array
    {
        $order = $this->orderRepository->findById($orderId);
        $commissionableTotal = $this->calculateCommissionableTotal($order);
        $platformAmount = $this->calculatePlatformAmount($commissionableTotal);
        $overrides = $this->repository->findByOrderId($orderId);

        return [
            'has_overrides' => $overrides->isNotEmpty(),
            'commissionable_total' => $commissionableTotal,
            'platform_amount' => $platformAmount,
            'overrides' => $overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{has_overrides: bool, commissionable_total: float, platform_amount: float, overrides: Collection}
     */
    public function saveOverrides(int $orderId, array $data): array
    {
        $order = $this->orderRepository->findById($orderId);

        $this->assertCanOverride($order);

        $commissionableTotal = $this->calculateCommissionableTotal($order);
        $platformAmount = $this->calculatePlatformAmount($commissionableTotal);

        // Validate total balance
        $overrideSum = array_sum(array_column($data['overrides'], 'amount'));
        $expectedSum = $commissionableTotal - $platformAmount;

        if (abs($overrideSum - $expectedSum) > 0.01) {
            throw new UnprocessableEntityHttpException(
                "Tổng số tiền override ({$overrideSum}) phải bằng tiền hoa hồng còn lại sau Platform ({$expectedSum})."
            );
        }

        $overrides = $this->executeInTransaction(function () use ($orderId, $data): Collection {
            return $this->repository->replaceOverrides($orderId, $data['overrides']);
        });

        return [
            'has_overrides' => true,
            'commissionable_total' => $commissionableTotal,
            'platform_amount' => $platformAmount,
            'overrides' => $overrides,
        ];
    }

    public function deleteOverrides(int $orderId): void
    {
        $order = $this->orderRepository->findById($orderId);
        $this->assertCanOverride($order);
        $this->repository->deleteByOrderId($orderId);
    }

    public function isAdjuster(int $orderId): bool
    {
        $accountId = (int) auth()->id();
        if (! $accountId) {
            return false;
        }

        $order = $this->orderRepository->findById($orderId);
        $projectId = $order->quote?->ogTicket?->project_id;

        if (! $projectId) {
            return false;
        }

        return $this->commissionConfigRepository->hasAdjuster($accountId, $projectId);
    }

    /**
     * Calculate commissionable total = SUM(line_amount) for service + adhoc lines.
     */
    private function calculateCommissionableTotal(Order $order): float
    {
        if (! $order->relationLoaded('lines')) {
            $order->load('lines');
        }

        return (float) $order->lines
            ->filter(fn ($line) => in_array($line->line_type, [QuoteLineType::Service, QuoteLineType::Adhoc]))
            ->sum('line_amount');
    }

    /**
     * Calculate platform amount using config defaults.
     * Algorithm: deduct fixed first, then take percent on remaining.
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
     * Assert that the current user can override commission for this order.
     */
    private function assertCanOverride(Order $order): void
    {
        // Financial lock check
        if ($order->isFinanciallyLocked()) {
            throw new UnprocessableEntityHttpException(
                'Đơn hàng đã nằm trong kỳ chốt. Không thể điều chỉnh hoa hồng.'
            );
        }

        // Status check
        if (! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::InProgress, OrderStatus::Completed])) {
            throw new UnprocessableEntityHttpException(
                'Chỉ có thể điều chỉnh hoa hồng khi đơn hàng ở trạng thái đã xác nhận, đang thực hiện hoặc hoàn thành.'
            );
        }

        // Adjuster ACL check
        $accountId = (int) auth()->id();
        $projectId = $order->quote?->ogTicket?->project_id;

        if (! $projectId || ! $this->commissionConfigRepository->hasAdjuster($accountId, $projectId)) {
            throw new AccessDeniedHttpException(
                'Bạn không có quyền điều chỉnh hoa hồng cho đơn hàng này.'
            );
        }
    }
}
