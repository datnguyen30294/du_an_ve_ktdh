<?php

namespace App\Modules\PMC\Catalog\Models;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Models\BaseModel;
use App\Modules\PMC\Catalog\Enums\CatalogStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'image_path',
        'sort_order',
        'status',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => CatalogStatus::class,
        ];
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path
                ? app(StorageServiceInterface::class)->getUrl($this->image_path)
                : null,
        );
    }

    /**
     * @return HasMany<CatalogItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class, 'service_category_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CatalogStatus::Active);
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

    protected static function newFactory(): \Database\Factories\Tenant\ServiceCategoryFactory
    {
        return \Database\Factories\Tenant\ServiceCategoryFactory::new();
    }
}
