<?php

namespace App\Modules\Platform\Ticket\Enums;

enum TicketChannel: string
{
    case Phone = 'phone';
    case App = 'app';
    case Website = 'website';
    case Direct = 'direct';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Điện thoại',
            self::App => 'Ứng dụng',
            self::Website => 'Website',
            self::Direct => 'Trực tiếp',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
