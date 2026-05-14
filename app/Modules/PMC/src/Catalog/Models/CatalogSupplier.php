<?php

namespace App\Modules\PMC\Catalog\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Catalog\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogSupplier extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'contact',
        'phone',
        'address',
        'email',
        'commission_rate',
        'status',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'status' => SupplierStatus::class,
        ];
    }

    /**
     * @return HasMany<CatalogItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class, 'supplier_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SupplierStatus::Active);
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

    protected static function newFactory(): \Database\Factories\Tenant\CatalogSupplierFactory
    {
        return \Database\Factories\Tenant\CatalogSupplierFactory::new();
    }
}
