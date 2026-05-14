<?php

namespace App\Modules\PMC\Report\Csat\Enums;

enum CsatFeedbackBucket: string
{
    case Promoter = 'promoter';
    case Passive = 'passive';
    case Detractor = 'detractor';

    public function label(): string
    {
        return match ($this) {
            self::Promoter => 'Khuyến nghị',
            self::Passive => 'Trung lập',
            self::Detractor => 'Chưa hài lòng',
        };
    }

    public static function fromRating(int $rating): self
    {
        return match (true) {
            $rating >= 5 => self::Promoter,
            $rating === 4 => self::Passive,
            default => self::Detractor,
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
