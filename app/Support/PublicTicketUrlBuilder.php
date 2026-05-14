<?php

namespace App\Support;

class PublicTicketUrlBuilder
{
    /**
     * Build the public URL used in resident notifications. Injects the
     * tenant subdomain into `FRONTEND_URL` so each tenant's emails link
     * back to the correct frontend host, e.g.
     *
     *   config('app.frontend_url') = http://residential.test:3000
     *   tenantSubdomain            = tnp
     *   ticketCode                 = TK-2026-001
     *   result                     = http://tnp.residential.test:3000/tickets/TK-2026-001
     *
     * Returns null when the frontend URL is not configured or malformed so
     * the notification can degrade gracefully (omit the action button).
     */
    public static function build(?string $tenantSubdomain, string $ticketCode): ?string
    {
        return self::buildForPath($tenantSubdomain, "/tickets/{$ticketCode}");
    }

    /**
     * Build the resident-facing URL for an acceptance report share token.
     */
    public static function buildAcceptanceReport(?string $tenantSubdomain, string $shareToken): ?string
    {
        return self::buildForPath($tenantSubdomain, "/acceptance-report/{$shareToken}");
    }

    /**
     * Build a tenant-aware frontend URL for the given path.
     */
    public static function buildForPath(?string $tenantSubdomain, string $path): ?string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        if ($frontendUrl === '') {
            return null;
        }

        $parsed = parse_url($frontendUrl);

        if ($parsed === false || empty($parsed['host'])) {
            return null;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        $subdomain = $tenantSubdomain !== null ? trim($tenantSubdomain) : '';
        $hostWithTenant = $subdomain !== '' ? "{$subdomain}.{$host}" : $host;

        $normalizedPath = '/'.ltrim($path, '/');

        return "{$scheme}://{$hostWithTenant}{$port}{$normalizedPath}";
    }
}
