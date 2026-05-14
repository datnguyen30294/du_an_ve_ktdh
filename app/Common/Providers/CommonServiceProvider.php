<?php

namespace App\Common\Providers;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Services\StorageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class CommonServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * Discovers and registers all module ServiceProviders.
     */
    public function register(): void
    {
        $this->app->bind(StorageServiceInterface::class, StorageService::class);

        $this->registerModuleProviders();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Discover and register all module ServiceProviders.
     */
    protected function registerModuleProviders(): void
    {
        $modulesPath = app_path('Modules');

        if (! is_dir($modulesPath)) {
            return;
        }

        $topLevel = glob($modulesPath.'/*/Providers/*ServiceProvider.php') ?: [];
        $subModule = glob($modulesPath.'/*/*/Providers/*ServiceProvider.php') ?: [];
        $providerFiles = array_merge($topLevel, $subModule);

        foreach ($providerFiles as $providerFile) {
            $className = $this->resolveProviderClassName($providerFile);

            if ($className !== null && class_exists($className)) {
                $this->app->register($className);

                Log::debug("Registered module provider: {$className}");
            }
        }
    }

    /**
     * Resolve the fully qualified class name from a provider file path.
     *
     * Converts: /path/to/app/Modules/Payment/Providers/PaymentServiceProvider.php
     * Into:     App\Modules\Payment\Providers\PaymentServiceProvider
     */
    protected function resolveProviderClassName(string $filePath): ?string
    {
        $appPath = app_path().'/';
        $relativePath = str_replace($appPath, '', $filePath);
        $relativePath = str_replace('.php', '', $relativePath);

        return 'App\\'.str_replace('/', '\\', $relativePath);
    }
}
