<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Catalog\Enums\CatalogItemType;
use App\Modules\PMC\Catalog\Enums\CatalogStatus;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\Catalog\Models\CatalogItemImage;
use App\Modules\PMC\Catalog\Models\CatalogSupplier;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;

class CatalogItemSeeder extends Seeder
{
    public function run(string $orgCode = ''): void
    {
        if (! $orgCode) {
            return;
        }

        foreach (OrganizationSeedData::catalogItems($orgCode) as $data) {
            $supplierId = null;
            if (! empty($data['supplier_code'])) {
                $supplierId = CatalogSupplier::where('code', $data['supplier_code'])->value('id');
            }

            $categoryId = null;
            if (! empty($data['category_code'])) {
                $categoryId = ServiceCategory::where('code', $data['category_code'])->value('id');
            }

            $type = CatalogItemType::from($data['type']);

            $item = CatalogItem::firstOrCreate(
                ['type' => $data['type'], 'code' => $data['code']],
                [
                    'type' => $data['type'],
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'unit' => $data['unit'],
                    'unit_price' => $data['unit_price'],
                    'purchase_price' => $data['purchase_price'],
                    'commission_rate' => $data['commission_rate'],
                    'supplier_id' => $supplierId,
                    'service_category_id' => $categoryId,
                    'description' => $data['description'],
                    'content' => $data['content'] ?? null,
                    'image_path' => $data['image_path'] ?? null,
                    'price_note' => $data['price_note'] ?? null,
                    'is_featured' => $data['is_featured'] ?? false,
                    'slug' => CatalogItem::generateSlug($data['name'], $type),
                    'status' => CatalogStatus::Active,
                    'is_published' => true,
                ],
            );

            if (! $item->wasRecentlyCreated) {
                $updates = [];

                if (! $item->is_published || $item->status !== CatalogStatus::Active) {
                    $updates['status'] = CatalogStatus::Active;
                    $updates['is_published'] = true;
                    $updates['slug'] = $item->slug ?: CatalogItem::generateSlug($data['name'], $type, $item->id);
                }

                // Backfill rich fields on existing items only when empty — preserve admin edits.
                if (empty($item->content) && ! empty($data['content'])) {
                    $updates['content'] = $data['content'];
                }
                if (empty($item->image_path) && ! empty($data['image_path'])) {
                    $updates['image_path'] = $data['image_path'];
                }
                if (empty($item->price_note) && ! empty($data['price_note'])) {
                    $updates['price_note'] = $data['price_note'];
                }
                if (! $item->is_featured && ! empty($data['is_featured'])) {
                    $updates['is_featured'] = true;
                }

                if ($updates !== []) {
                    $item->update($updates);
                }
            }

            $this->seedGalleryImages($item, $data['gallery'] ?? []);
        }
    }

    /**
     * @param  list<string>  $urls
     */
    private function seedGalleryImages(CatalogItem $item, array $urls): void
    {
        if ($urls === []) {
            return;
        }

        // Skip if item already has gallery images — do not duplicate or overwrite.
        if ($item->images()->exists()) {
            return;
        }

        foreach ($urls as $index => $url) {
            CatalogItemImage::create([
                'catalog_item_id' => $item->id,
                'image_path' => $url,
                'sort_order' => $index,
            ]);
        }
    }
}
