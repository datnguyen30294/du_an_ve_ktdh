<?php

namespace App\Modules\Platform\Setting\ExternalServices;

interface PlatformBankInfoExternalServiceInterface
{
    /**
     * Return Platform bank info in the same shape as Account::bankInfo(),
     * or null when the Platform admin has not configured it yet.
     *
     * @return array{bin: string, label: string, account_number: string, account_name: string}|null
     */
    public function getPlatformBankInfo(): ?array;
}
