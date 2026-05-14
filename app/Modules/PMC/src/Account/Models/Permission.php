<?php

namespace App\Modules\PMC\Account\Models;

use App\Modules\PMC\Account\Enums\PermissionAction;
use App\Modules\PMC\Account\Enums\PermissionSubModule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'module',
        'sub_module',
        'action',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sub_module' => PermissionSubModule::class,
            'action' => PermissionAction::class,
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBySubModule(Builder $query, string $subModule): Builder
    {
        return $query->where('sub_module', $subModule);
    }

    protected static function newFactory(): \Database\Factories\Tenant\PermissionFactory
    {
        return \Database\Factories\Tenant\PermissionFactory::new();
    }
}
