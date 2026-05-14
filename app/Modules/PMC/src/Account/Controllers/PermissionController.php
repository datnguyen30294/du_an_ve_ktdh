<?php

namespace App\Modules\PMC\Account\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Account\Models\Permission;
use App\Modules\PMC\Account\Resources\PermissionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Permissions
 */
class PermissionController extends BaseController
{
    /**
     * List all permissions grouped by sub_module.
     */
    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::query()
            ->orderBy('sub_module')
            ->orderBy('action')
            ->get();

        return PermissionResource::collection($permissions)->additional(['success' => true]);
    }
}
