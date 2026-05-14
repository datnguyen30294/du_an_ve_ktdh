<?php

namespace App\Modules\Platform\Providers;

use App\Modules\Platform\Auth\Contracts\AuthServiceInterface;
use App\Modules\Platform\Auth\Services\AuthService;
use App\Modules\Platform\Customer\Contracts\CustomerServiceInterface;
use App\Modules\Platform\Customer\Services\CustomerService;
use App\Modules\Platform\ExternalApi\Contracts\ApiClientServiceInterface;
use App\Modules\Platform\ExternalApi\Services\ApiClientService;
use App\Modules\Platform\Setting\Contracts\PlatformSettingServiceInterface;
use App\Modules\Platform\Setting\ExternalServices\PlatformBankInfoExternalService;
use App\Modules\Platform\Setting\ExternalServices\PlatformBankInfoExternalServiceInterface;
use App\Modules\Platform\Setting\Services\PlatformSettingService;
use App\Modules\Platform\Ticket\Commands\AutoReleaseStaleTicketsCommand;
use App\Modules\Platform\Ticket\Contracts\TicketServiceInterface;
use App\Modules\Platform\Ticket\ExternalServices\OgTicketExternalService;
use App\Modules\Platform\Ticket\ExternalServices\OgTicketExternalServiceInterface;
use App\Modules\Platform\Ticket\ExternalServices\OrganizationExternalService;
use App\Modules\Platform\Ticket\ExternalServices\OrganizationExternalServiceInterface;
use App\Modules\Platform\Ticket\Services\TicketService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(CustomerServiceInterface::class, CustomerService::class);
        $this->app->bind(TicketServiceInterface::class, TicketService::class);
        $this->app->bind(ApiClientServiceInterface::class, ApiClientService::class);
        $this->app->bind(OrganizationExternalServiceInterface::class, OrganizationExternalService::class);
        $this->app->bind(OgTicketExternalServiceInterface::class, OgTicketExternalService::class);
        $this->app->bind(PlatformSettingServiceInterface::class, PlatformSettingService::class);
        $this->app->bind(PlatformBankInfoExternalServiceInterface::class, PlatformBankInfoExternalService::class);
    }

    public function boot(): void
    {
        $this->loadCommands();
        $this->loadRoutes();
        $this->loadConsoleRoutes();
    }

    protected function loadCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AutoReleaseStaleTicketsCommand::class,
            ]);
        }
    }

    protected function loadConsoleRoutes(): void
    {
        require base_path('app/Modules/Platform/routes/console.php');
    }

    protected function loadRoutes(): void
    {
        Route::prefix('api/v1/platform/auth')
            ->middleware('api')
            ->group(base_path('app/Modules/Platform/routes/auth.php'));

        Route::prefix('api/v1')
            ->middleware('api')
            ->group(base_path('app/Modules/Platform/routes/api.php'));

        Route::prefix('api/v1/platform')
            ->middleware('api')
            ->group(base_path('app/Modules/Platform/routes/authenticated.php'));

        Route::prefix('api/v1/platform')
            ->middleware('api')
            ->group(base_path('app/Modules/Platform/routes/external-api.php'));
    }
}
