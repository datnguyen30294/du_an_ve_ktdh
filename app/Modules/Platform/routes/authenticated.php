<?php

use App\Modules\Platform\Customer\Controllers\CustomerController;
use App\Modules\Platform\Setting\Controllers\PlatformSettingController;
use App\Modules\Platform\Ticket\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:requester')->group(function (): void {
    Route::get('tickets', [TicketController::class, 'index']);
    Route::get('tickets/{id}', [TicketController::class, 'show']);

    Route::get('customers', [CustomerController::class, 'index']);
    Route::get('customers/{id}', [CustomerController::class, 'show']);

    Route::get('settings/{group}', [PlatformSettingController::class, 'show']);
    Route::put('settings/{group}', [PlatformSettingController::class, 'update']);
});
