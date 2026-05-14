<?php

namespace App\Modules\PMC\Customer\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Customer\Models\Customer;
use Illuminate\Http\Request;

/**
 * @mixin Customer
 *
 * @property-read int|null $ticket_count
 * @property-read float|null $avg_rating
 */
class CustomerListResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string|null,
     *     full_name: string,
     *     phone: string,
     *     email: string|null,
     *     first_contacted_at: string|null,
     *     last_contacted_at: string|null,
     *     ticket_count: int,
     *     avg_rating: float|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'code' => $this->code,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'first_contacted_at' => $this->first_contacted_at?->toIso8601String(),
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            /** @var int */
            'ticket_count' => (int) ($this->ticket_count ?? 0),
            /** @var float|null */
            'avg_rating' => $this->avg_rating !== null ? (float) $this->avg_rating : null,
        ];
    }
}
