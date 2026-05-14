<?php

namespace App\Modules\PMC\WorkSnapshot\Enums;

enum SnapshotEntityTypeEnum: string
{
    case WorkSchedule = 'work_schedule';
    case Ticket = 'ticket';
    case Order = 'order';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
