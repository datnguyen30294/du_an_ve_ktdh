<?php

namespace App\Common\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    /**
     * @return array{success: true}
     */
    public function with(Request $request): array
    {
        return ['success' => true];
    }
}
