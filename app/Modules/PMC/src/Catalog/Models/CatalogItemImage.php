<?php

namespace App\Modules\PMC\Catalog\Models;

use App\Common\Contracts\StorageServiceInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItemImage extends Model
{
    protected $fillable = [
        'catalog_item_id',
        'image_path',
        'sort_order',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path
                ? app(StorageServiceInterface::class)->getUrl($this->image_path)
                : null,
        );
    }
}
