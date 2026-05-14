<?php

namespace App\Modules\PMC\Project\Models;

use Illuminate\Database\Eloquent\Model;

class AccountProject extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'project_id',
    ];
}
