<?php

namespace App\Modules\Platform\Auth\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\Platform\Auth\Models\RequesterAccount;
use Illuminate\Http\Request;

/**
 * @mixin RequesterAccount
 */
class AuthResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'name' => $this->name,
            /** @var string */
            'email' => $this->email,
        ];
    }
}
