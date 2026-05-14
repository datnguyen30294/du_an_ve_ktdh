<?php

namespace App\Modules\Platform\Setting\ExternalServices;

use App\Modules\Platform\Setting\Repositories\PlatformSettingRepository;

class PlatformBankInfoExternalService implements PlatformBankInfoExternalServiceInterface
{
    private const GROUP = 'bank_account';

    public function __construct(
        protected PlatformSettingRepository $repository,
    ) {}

    public function getPlatformBankInfo(): ?array
    {
        $settings = $this->repository->getGroup(self::GROUP);

        $bin = $settings['bank_bin'] ?? null;
        $accountNumber = $settings['account_number'] ?? null;
        $accountHolder = $settings['account_holder'] ?? null;
        $bankName = $settings['bank_name'] ?? null;

        if (! $bin || ! $accountNumber || ! $accountHolder) {
            return null;
        }

        return [
            'bin' => (string) $bin,
            'label' => (string) ($bankName ?? ''),
            'account_number' => (string) $accountNumber,
            'account_name' => (string) $accountHolder,
        ];
    }
}
