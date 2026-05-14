<?php

namespace App\Http\Middleware;

use App\Common\Http\JsonResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganizationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant || ! $tenant->is_organization) {
            return JsonResponseHelper::error(
                message: 'Bạn không có quyền truy cập tính năng quản trị.',
                statusCode: Response::HTTP_FORBIDDEN,
                errorCode: 'ORGANIZATION_ACCESS_DENIED',
            );
        }

        return $next($request);
    }
}
