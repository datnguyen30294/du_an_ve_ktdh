<?php

namespace App\Modules\PMC\Department\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends BaseModel
{
    protected $fillable = [
        'project_id',
        'code',
        'name',
        'parent_id',
        'description',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Account, $this>
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_department', 'department_id', 'account_id')
            ->withTimestamps();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('name', static::likeOperator(), "%{$keyword}%")
                ->orWhere('code', static::likeOperator(), "%{$keyword}%");
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByParent(Builder $query, ?int $parentId): Builder
    {
        if ($parentId === 0) {
            return $query->whereNull('parent_id');
        }

        return $query->where('parent_id', $parentId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    protected static function newFactory(): \Database\Factories\Tenant\DepartmentFactory
    {
        return \Database\Factories\Tenant\DepartmentFactory::new();
    }
}
