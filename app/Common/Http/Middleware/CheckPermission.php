<?php

namespace App\Common\Http\Middleware;

use App\Common\Http\JsonResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return JsonResponseHelper::error(
                message: 'Chưa đăng nhập.',
                statusCode: Response::HTTP_UNAUTHORIZED,
                errorCode: 'UNAUTHENTICATED',
            );
        }

        if (! $user->is_active) {
            return JsonResponseHelper::error(
                message: 'Tài khoản đã bị vô hiệu hóa.',
                statusCode: Response::HTTP_FORBIDDEN,
                errorCode: 'ACCOUNT_INACTIVE',
            );
        }

        if ($user->role && ! $user->role->is_active) {
            return JsonResponseHelper::error(
                message: 'Vai trò của tài khoản đã bị vô hiệu hóa.',
                statusCode: Response::HTTP_FORBIDDEN,
                errorCode: 'ROLE_INACTIVE',
            );
        }

        if (! $user->hasAnyPermission($permissions)) {
            return JsonResponseHelper::error(
                message: 'Bạn không có quyền thực hiện hành động này.',
                statusCode: Response::HTTP_FORBIDDEN,
                errorCode: 'FORBIDDEN',
            );
        }

        return $next($request);
    }
}
