<?php

namespace App\Modules\PMC\Account\Enums;

enum PermissionAction: string
{
    case View = 'view';
    case Store = 'store';
    case Update = 'update';
    case Destroy = 'destroy';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Xem',
            self::Store => 'Tạo mới',
            self::Update => 'Cập nhật',
            self::Destroy => 'Xóa',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
