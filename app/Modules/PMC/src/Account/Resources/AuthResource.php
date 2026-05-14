<?php

namespace App\Modules\PMC\Account\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Account\Models\Account;
use Illuminate\Http\Request;

/**
 * @mixin Account
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
            /** @var string|null */
            'avatar_url' => $this->avatar_url,
            /** @var list<string> */
            'permissions' => $this->getPermissionNames(),
        ];
    }
}
