<?php

namespace App\Modules\Platform\ExternalApi\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\ExternalApi\Enums\ApiScope;
use Illuminate\Http\JsonResponse;

/**
 * @tags API Client Lookups
 */
class ApiScopeController extends BaseController
{
    /**
     * List available API scope groups for the scope picker.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ApiScope::groups(),
        ]);
    }
}
