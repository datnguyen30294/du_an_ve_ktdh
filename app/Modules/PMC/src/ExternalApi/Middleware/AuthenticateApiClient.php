<?php

namespace App\Modules\PMC\ExternalApi\Middleware;

use App\Modules\Platform\ExternalApi\Repositories\ApiClientRepository;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiClient
{
    private const MAX_LIFETIME_SECONDS = 365 * 24 * 3600; // 1 year

    private const CLOCK_TOLERANCE_SECONDS = 30;

    public function __construct(
        protected ApiClientRepository $repository,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            abort(Response::HTTP_UNAUTHORIZED, 'JWT token là bắt buộc.');
        }

        // 1. Decode header+payload without verification to get client_key (sub)
        $payload = $this->decodePayloadUnsafe($bearerToken);

        if (! $payload || empty($payload['sub'])) {
            abort(Response::HTTP_UNAUTHORIZED, 'JWT không hợp lệ: thiếu claim "sub".');
        }

        // 2. Lookup client by client_key
        $apiClient = $this->repository->findByClientKey($payload['sub']);

        if (! $apiClient || ! $apiClient->is_active) {
            abort(Response::HTTP_UNAUTHORIZED, 'API client không tồn tại hoặc đã bị vô hiệu hóa.');
        }

        // 3. Validate lifetime (exp - iat <= 1 year)
        if (! empty($payload['iat']) && ! empty($payload['exp'])) {
            $lifetime = $payload['exp'] - $payload['iat'];
            if ($lifetime > self::MAX_LIFETIME_SECONDS) {
                abort(Response::HTTP_UNAUTHORIZED, 'JWT lifetime vượt quá giới hạn cho phép (tối đa 1 năm).');
            }
        }

        // 4. Verify JWT signature using client's secret
        $secret = $apiClient->encrypted_secret;

        try {
            JWT::$leeway = self::CLOCK_TOLERANCE_SECONDS;
            JWT::decode($bearerToken, new Key($secret, 'HS256'));
        } catch (\Exception $e) {
            abort(Response::HTTP_UNAUTHORIZED, 'JWT không hợp lệ hoặc đã hết hạn.');
        }

        // 5. Check tenant match
        $currentTenant = tenant();
        if ($currentTenant && $apiClient->organization_id !== $currentTenant->getTenantKey()) {
            abort(Response::HTTP_FORBIDDEN, 'API client không thuộc tenant này.');
        }

        // 6. Update last_used_at
        $apiClient->update(['last_used_at' => now()]);

        // 7. Set request attributes from DB (NOT from JWT claims)
        $request->attributes->set('api_client', $apiClient);
        $request->attributes->set('api_project_id', $apiClient->project_id);
        $request->attributes->set('api_scopes', $apiClient->scopes);

        return $next($request);
    }

    /**
     * Decode JWT payload without signature verification (to extract sub for DB lookup).
     *
     * @return array<string, mixed>|null
     */
    private function decodePayloadUnsafe(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'));

        if (! $payload) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }
}
