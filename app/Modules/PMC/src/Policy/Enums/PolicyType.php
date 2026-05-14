<?php

namespace App\Modules\PMC\Policy\Enums;

enum PolicyType: string
{
    case TermsOfService = 'terms_of_service';
    case PrivacyPolicy = 'privacy_policy';

    public function label(): string
    {
        return match ($this) {
            self::TermsOfService => 'Điều khoản sử dụng',
            self::PrivacyPolicy => 'Chính sách bảo mật',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
