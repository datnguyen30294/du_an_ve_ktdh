<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Migrates legacy short-form entries in the `domains` table (stored when the app
 * used InitializeTenancyBySubdomain) into full FQDN entries required by
 * InitializeTenancyByDomain.
 *
 * Usage:
 *   php artisan tenancy:migrate-domains residential.test
 *   php artisan tenancy:migrate-domains demego.vn --prefix=api.
 *   php artisan tenancy:migrate-domains demego.vn --prefix=api. --dry-run
 *
 * Short-form entry `tnp` becomes `{prefix}tnp.{base}`, e.g. `api.tnp.demego.vn`.
 * Entries already containing a dot are skipped.
 */
class TenancyMigrateDomains extends Command
{
    protected $signature = 'tenancy:migrate-domains
                            {base : Base domain to append (e.g. demego.vn, residential.test)}
                            {--prefix= : Prefix prepended before the tenant slug (e.g. api.)}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Migrate legacy subdomain-form tenant domains to full FQDN form';

    public function handle(): int
    {
        $base = trim((string) $this->argument('base'), '.');
        $prefix = (string) $this->option('prefix');
        $dryRun = (bool) $this->option('dry-run');

        if ($base === '') {
            $this->error('Base domain không được rỗng.');

            return self::FAILURE;
        }

        $domains = Domain::query()
            ->where('domain', 'not like', '%.%')
            ->orderBy('domain')
            ->get();

        if ($domains->isEmpty()) {
            $this->info('Không có entry nào cần migrate (tất cả domain đã là FQDN).');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d entries với base="%s", prefix="%s":',
            $dryRun ? '[DRY RUN]' : 'Migrating',
            $domains->count(),
            $base,
            $prefix,
        ));

        $rows = [];
        foreach ($domains as $domain) {
            $newDomain = $prefix.$domain->domain.'.'.$base;
            $rows[] = [$domain->domain, $newDomain, $domain->tenant_id];

            if (! $dryRun) {
                $domain->domain = $newDomain;
                $domain->save();
            }
        }

        $this->table(['Old domain', 'New domain', 'Tenant'], $rows);

        if ($dryRun) {
            $this->warn('Dry run — không có thay đổi nào được lưu. Bỏ --dry-run để apply.');
        } else {
            $this->info('Done.');
        }

        return self::SUCCESS;
    }
}
