<?php

use App\Common\Http\JsonResponseHelper;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Common\Http\Middleware\CheckPermission::class,
            'tenant' => \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            'prevent-central-access' => \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            'organization' => \App\Http\Middleware\OrganizationMiddleware::class,
            'auth.api-client' => \App\Modules\PMC\ExternalApi\Middleware\AuthenticateApiClient::class,
            'scope' => \App\Modules\PMC\ExternalApi\Middleware\CheckApiScope::class,
        ]);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Helper function to create JSON response for ModelNotFoundException
        $createModelNotFoundResponse = function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $modelName = class_basename($e->getModel());

            return JsonResponseHelper::error(
                message: "Không tìm thấy {$modelName}.",
                statusCode: \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND,
                errorCode: 'RESOURCE_NOT_FOUND',
                errors: [
                    'model' => $e->getModel(),
                    'ids' => $e->getIds(),
                ],
            );
        };

        // Handle ModelNotFoundException directly
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, \Illuminate\Http\Request $request) use ($createModelNotFoundResponse) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $createModelNotFoundResponse($e);
            }
        });

        // Handle NotFoundHttpException (route not found or model not found)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) use ($createModelNotFoundResponse) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $previous = $e->getPrevious();

                if ($previous instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return $createModelNotFoundResponse($previous);
                }

                return JsonResponseHelper::error(
                    message: 'Không tìm thấy đường dẫn API.',
                    statusCode: \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND,
                    errorCode: 'ENDPOINT_NOT_FOUND',
                );
            }
        });

        // Handle AuthenticationException (unauthenticated)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return JsonResponseHelper::error(
                    message: 'Chưa đăng nhập.',
                    statusCode: \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED,
                    errorCode: 'UNAUTHENTICATED',
                );
            }
        });

        // Handle BusinessException
        $exceptions->render(function (\App\Common\Exceptions\BusinessException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return JsonResponseHelper::error(
                    message: $e->getMessage(),
                    statusCode: $e->getCode(),
                    errorCode: $e->getErrorCode(),
                    errors: $e->getContext(),
                );
            }
        });

        // Handle HttpException (abort() calls)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return JsonResponseHelper::error(
                    message: $e->getMessage() ?: 'Có lỗi xảy ra.',
                    statusCode: $e->getStatusCode(),
                );
            }
        });
    })->create();
