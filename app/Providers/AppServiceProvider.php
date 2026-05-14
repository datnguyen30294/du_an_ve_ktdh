<?php

namespace App\Providers;

use App\Common\OpenApi\CustomAuthenticationExceptionExtension;
use App\Common\OpenApi\CustomValidationExceptionExtension;
use App\Common\OpenApi\JsonResponseHelperExtension;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Server;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureScramble();
        $this->configureTenancy();
    }

    /**
     * Configure tenancy domain middleware behavior.
     */
    private function configureTenancy(): void
    {
        InitializeTenancyByDomain::$onFail = function ($exception, $request, $next) {
            // Host is a central domain — tenant-only routes are not available on platform.
            return response()->json([
                'success' => false,
                'message' => 'Endpoint này chỉ khả dụng trên domain của tổ chức.',
            ], 400);
        };
    }

    /**
     * Configure Scramble API documentation.
     */
    private function configureScramble(): void
    {
        // Default API docs (internal PMC + Platform)
        Scramble::configure()
            ->routes(fn (Route $route) => str_starts_with($route->uri, 'api/v1') && ! str_starts_with($route->uri, 'api/v1/ext'))
            ->withOperationTransformers([JsonResponseHelperExtension::class]);

        Scramble::registerExtensions([
            CustomAuthenticationExceptionExtension::class,
            CustomValidationExceptionExtension::class,
        ]);

        Scramble::afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi): void {
            $openApi->secure(
                \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer')
            );
        });

        // External API docs (separate, for third-party developers)
        Scramble::registerApi('external')
            ->routes(fn (Route $route) => str_starts_with($route->uri, 'api/v1/ext'))
            ->expose(
                ui: 'docs/external',
                document: 'docs/external/openapi.json',
            )
            ->afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi): void {
                $openApi->info->title = 'Residential Management - External API';
                $openApi->info->description = file_get_contents(resource_path('docs/external-api.md'));

                $openApi->servers = [];
                $openApi->addServer(
                    Server::make(rtrim((string) config('app.url'), '/').'/api/v1')
                        ->setDescription('API server (sử dụng domain của tổ chức, ví dụ https://api.pse.demego.vn)')
                );

                $openApi->secure(
                    \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer')
                );
            });

        Gate::define('viewApiDocs', function () {
            return true;

            return app()->environment(['local', 'staging']);
        });
    }
}
