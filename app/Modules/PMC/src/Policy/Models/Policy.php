<?php

namespace App\Modules\PMC\Policy\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Policy\Enums\PolicyType;

class Policy extends BaseModel
{
    /** @var list<string> */
    protected $fillable = [
        'type',
        'title',
        'content',
        'is_published',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PolicyType::class,
            'is_published' => 'boolean',
        ];
    }
}
