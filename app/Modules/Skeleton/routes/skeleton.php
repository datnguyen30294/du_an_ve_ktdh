<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Skeleton module is running',
        'module' => 'Skeleton',
    ]);
})->name('skeleton.health');
