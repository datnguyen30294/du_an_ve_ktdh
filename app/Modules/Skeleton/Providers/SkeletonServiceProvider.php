<?php

namespace App\Modules\Skeleton\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SkeletonServiceProvider extends ServiceProvider
{
    /**
     * Register module services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap module services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutes();
    }

    /**
     * Load the module's routes.
     */
    protected function loadRoutes(): void
    {
        Route::prefix('api/v1/skeleton')
            ->middleware('api')
            ->group(base_path('app/Modules/Skeleton/routes/skeleton.php'));
    }
}
