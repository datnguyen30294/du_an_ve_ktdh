<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is running',
            'timestamp' => now()->toIso8601String(),
        ]);
    })->name('api.health');
});
