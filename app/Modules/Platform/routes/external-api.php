<?php

use App\Modules\Platform\ExternalApi\Controllers\ApiClientController;
use App\Modules\Platform\ExternalApi\Controllers\ApiScopeController;
use App\Modules\Platform\ExternalApi\Controllers\OrganizationLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:requester')->group(function (): void {
    Route::get('api-clients', [ApiClientController::class, 'index']);
    Route::post('api-clients', [ApiClientController::class, 'store']);
    Route::get('api-clients/{id}', [ApiClientController::class, 'show']);
    Route::put('api-clients/{id}', [ApiClientController::class, 'update']);
    Route::delete('api-clients/{id}', [ApiClientController::class, 'destroy']);
    Route::post('api-clients/{id}/regenerate-secret', [ApiClientController::class, 'regenerateSecret']);

    Route::get('api-scopes', [ApiScopeController::class, 'index']);

    Route::get('organizations', [OrganizationLookupController::class, 'organizations']);
    Route::get('organizations/{orgId}/projects', [OrganizationLookupController::class, 'projects']);
});
