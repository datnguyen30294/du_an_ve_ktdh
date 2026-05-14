<?php

namespace App\Modules\Platform\Customer\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\Platform\Customer\Models\Customer;
use Illuminate\Http\Request;

/**
 * @mixin Customer
 */
class CustomerListResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'name' => $this->name,
            /** @var string */
            'phone' => $this->phone,
            /** @var string|null */
            'email' => $this->email,
            /** @var string|null */
            'address' => $this->address,
            /** @var int */
            'tickets_count' => $this->tickets_count ?? 0,
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
