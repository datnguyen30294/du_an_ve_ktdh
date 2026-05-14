<?php

use App\Modules\PMC\Account\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::apiResource('accounts', AccountController::class)
    ->parameters(['accounts' => 'id']);

Route::put('accounts/{id}/password', [AccountController::class, 'changePassword']);
Route::post('accounts/{id}/avatar', [AccountController::class, 'uploadAvatar']);
Route::delete('accounts/{id}/avatar', [AccountController::class, 'deleteAvatar']);
