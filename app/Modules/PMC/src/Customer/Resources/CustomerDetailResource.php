<?php

namespace App\Modules\PMC\Customer\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Customer\Models\Customer;
use Illuminate\Http\Request;

/**
 * Wraps a Customer + aggregates payload: ['customer' => Customer, 'aggregates' => array].
 */
class CustomerDetailResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string|null,
     *     full_name: string,
     *     phone: string,
     *     email: string|null,
     *     note: string|null,
     *     first_contacted_at: string|null,
     *     last_contacted_at: string|null,
     *     created_at: string|null,
     *     updated_at: string|null,
     *     aggregates: array{
     *         ticket_count: int,
     *         ticket_by_status: array<string, int>,
     *         avg_rating: float|null,
     *         rating_count: int,
     *         order_count: int,
     *         total_paid: string,
     *         total_outstanding: string,
     *     },
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var Customer $customer */
        $customer = $this->resource['customer'];
        /** @var array<string, mixed> $aggregates */
        $aggregates = $this->resource['aggregates'];

        return [
            /** @var int */
            'id' => $customer->id,
            'code' => $customer->code,
            'full_name' => $customer->full_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'note' => $customer->note,
            'first_contacted_at' => $customer->first_contacted_at?->toIso8601String(),
            'last_contacted_at' => $customer->last_contacted_at?->toIso8601String(),
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
            'aggregates' => [
                /** @var int */
                'ticket_count' => (int) $aggregates['ticket_count'],
                /** @var array<string, int> */
                'ticket_by_status' => $aggregates['ticket_by_status'],
                /** @var float|null */
                'avg_rating' => $aggregates['avg_rating'],
                /** @var int */
                'rating_count' => (int) $aggregates['rating_count'],
                /** @var int */
                'order_count' => (int) $aggregates['order_count'],
                'total_paid' => (string) $aggregates['total_paid'],
                'total_outstanding' => (string) $aggregates['total_outstanding'],
            ],
        ];
    }
}
