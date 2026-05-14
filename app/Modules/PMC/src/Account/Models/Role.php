<?php

namespace App\Modules\PMC\Account\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Account\Enums\RoleType;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends BaseModel
{
    protected $fillable = [
        'name',
        'type',
        'department_id',
        'job_title_id',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RoleType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<JobTitle, $this>
     */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions->contains('name', $permissionName);
    }

    public function isDefault(): bool
    {
        return $this->type === RoleType::Default;
    }

    public function isCustom(): bool
    {
        return $this->type === RoleType::Custom;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByType(Builder $query, RoleType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('type', RoleType::Default->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('type', RoleType::Custom->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('name', static::likeOperator(), "%{$keyword}%");
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): \Database\Factories\Tenant\RoleFactory
    {
        return \Database\Factories\Tenant\RoleFactory::new();
    }
}
