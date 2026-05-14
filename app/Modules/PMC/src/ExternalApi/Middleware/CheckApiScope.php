<?php

namespace App\Modules\PMC\ExternalApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $scopes = $request->attributes->get('api_scopes', []);

        if (! in_array($scope, $scopes, true)) {
            abort(Response::HTTP_FORBIDDEN, "Không có quyền: {$scope}.");
        }

        return $next($request);
    }
}
