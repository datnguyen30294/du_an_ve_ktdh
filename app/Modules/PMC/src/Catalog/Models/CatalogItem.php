<?php

namespace App\Modules\PMC\Catalog\Models;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Models\BaseModel;
use App\Modules\PMC\Catalog\Enums\CatalogItemType;
use App\Modules\PMC\Catalog\Enums\CatalogStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CatalogItem extends BaseModel
{
    protected $fillable = [
        'type',
        'code',
        'name',
        'unit',
        'unit_price',
        'price_note',
        'purchase_price',
        'commission_rate',
        'supplier_id',
        'service_category_id',
        'description',
        'content',
        'slug',
        'sort_order',
        'image_path',
        'status',
        'is_published',
        'is_featured',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
            'unit_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'status' => CatalogStatus::class,
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(CatalogSupplier::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * @return HasMany<CatalogItemImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(CatalogItemImage::class)->orderBy('sort_order');
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
    public function scopeOfType(Builder $query, CatalogItemType $type): Builder
    {
        return $query->where('type', $type);
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
     * Generate a unique slug from the given name and type.
     */
    public static function generateSlug(string $name, CatalogItemType $type, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (
            static::query()
                ->where('slug', $slug)
                ->where('type', $type)
                ->whereNull('deleted_at')
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected static function newFactory(): \Database\Factories\Tenant\CatalogItemFactory
    {
        return \Database\Factories\Tenant\CatalogItemFactory::new();
    }
}
