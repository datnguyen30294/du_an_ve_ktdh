<?php

namespace App\Modules\Platform\Tenant\Models;

use App\Common\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant;

class Organization extends Tenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory, SoftDeletes;

    protected $table = 'tenants';

    /**
     * Tenant id is the org code (string primary key).
     * Override GeneratesIds trait which dynamically returns true
     * when no UniqueIdentifierGenerator is bound.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    /**
     * Columns that have dedicated DB columns (not stored in `data` JSON).
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'is_organization',
            'is_active',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_organization' => 'boolean',
        ];
    }

    /**
     * Return the appropriate LIKE operator for the current database driver.
     */
    public static function likeOperator(): string
    {
        return BaseModel::likeOperator();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrganization(Builder $query): Builder
    {
        return $query->where('is_organization', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('name', static::likeOperator(), "%{$keyword}%")
                ->orWhere('id', static::likeOperator(), "%{$keyword}%");
        });
    }

    protected static function newFactory(): \Database\Factories\Platform\OrganizationFactory
    {
        return \Database\Factories\Platform\OrganizationFactory::new();
    }
}
