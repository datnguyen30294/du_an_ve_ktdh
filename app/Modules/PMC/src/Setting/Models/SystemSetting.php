<?php

namespace App\Modules\PMC\Setting\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'group',
        'key',
        'value',
    ];
}
