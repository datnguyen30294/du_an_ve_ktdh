<?php

namespace App\Modules\PMC\Order\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Order\Models\OrderCommissionOverride;
use Illuminate\Database\Eloquent\Collection;

class OrderCommissionOverrideRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new OrderCommissionOverride);
    }

    /**
     * @return Collection<int, OrderCommissionOverride>
     */
    public function findByOrderId(int $orderId): Collection
    {
        return $this->newQuery()
            ->where('order_id', $orderId)
            ->with('account:id,name,employee_code')
            ->get();
    }

    public function hasOverrides(int $orderId): bool
    {
        return $this->newQuery()->where('order_id', $orderId)->exists();
    }

    /**
     * Replace all overrides for an order (delete + bulk insert).
     *
     * @param  array<array<string, mixed>>  $items
     * @return Collection<int, OrderCommissionOverride>
     */
    public function replaceOverrides(int $orderId, array $items): Collection
    {
        $this->deleteByOrderId($orderId);

        $now = now();
        $records = array_map(fn (array $item) => [
            'order_id' => $orderId,
            'recipient_type' => $item['recipient_type'],
            'account_id' => $item['account_id'] ?? null,
            'amount' => $item['amount'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $items);

        $this->newQuery()->insert($records);

        return $this->findByOrderId($orderId);
    }

    public function deleteByOrderId(int $orderId): void
    {
        $this->newQuery()->where('order_id', $orderId)->delete();
    }
}
