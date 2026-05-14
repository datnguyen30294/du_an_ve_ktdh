<?php

namespace Database\Seeders\Tenant;

use App\Modules\Platform\Tenant\Models\Organization;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = OrganizationSeedData::organizations();

        foreach (OrganizationSeedData::orgCodes() as $orgCode) {
            $data = $organizations[$orgCode];
            $schemaName = config('tenancy.database.prefix').$data['id'].config('tenancy.database.suffix');

            // Drop existing schema if it exists (handles migrate:fresh --seed)
            DB::connection(config('tenancy.database.central_connection'))
                ->statement("DROP SCHEMA IF EXISTS \"{$schemaName}\" CASCADE");

            $tenant = Organization::create($data);

            foreach ($this->domainsFor($data['id']) as $domain) {
                $tenant->domains()->create(['domain' => $domain]);
            }
        }
    }

    /**
     * Domains map cho mỗi tenant — bare slug (test/CLI) + FQDN local-dev (browser/SSR).
     *
     * @return list<string>
     */
    private function domainsFor(string $tenantId): array
    {
        $baseDomain = trim((string) config('app.tenant_dev_base_domain', ''));

        $domains = [$tenantId];

        if ($baseDomain !== '') {
            $domains[] = "{$tenantId}.{$baseDomain}";
            $domains[] = "api.{$tenantId}.{$baseDomain}";
        }

        return $domains;
    }
}
