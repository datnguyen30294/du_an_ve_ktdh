<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\Catalog\Models\CatalogSupplier;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogItemTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/catalog/items';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    // ==================== LIST ====================

    public function test_can_list_items(): void
    {
        CatalogItem::factory()->material()->count(2)->create();
        CatalogItem::factory()->service()->count(2)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    public function test_can_filter_items_by_type(): void
    {
        CatalogItem::factory()->material()->count(2)->create();
        CatalogItem::factory()->service()->count(3)->create();

        $response = $this->getJson("{$this->baseUrl}?type=service");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_search_items_by_name(): void
    {
        CatalogItem::factory()->material()->create(['name' => 'Ống nước PVC', 'code' => 'VT-001']);
        CatalogItem::factory()->service()->create(['name' => 'Dịch vụ sơn', 'code' => 'DV-001']);

        $response = $this->getJson("{$this->baseUrl}?search=PVC");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'VT-001');
    }

    public function test_can_filter_items_by_supplier(): void
    {
        $supplier = CatalogSupplier::factory()->create();
        CatalogItem::factory()->material()->count(2)->create(['supplier_id' => $supplier->id]);
        CatalogItem::factory()->material()->create();

        $response = $this->getJson("{$this->baseUrl}?supplier_id={$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_items_by_status(): void
    {
        CatalogItem::factory()->material()->create(['status' => 'active']);
        CatalogItem::factory()->material()->inactive()->create();

        $response = $this->getJson("{$this->baseUrl}?status=inactive");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ==================== SHOW ====================

    public function test_can_show_item(): void
    {
        $supplier = CatalogSupplier::factory()->create();
        $item = CatalogItem::factory()->material()->create(['supplier_id' => $supplier->id]);

        $response = $this->getJson("{$this->baseUrl}/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.supplier.id', $supplier->id);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CREATE ====================

    public function test_can_create_material_item(): void
    {
        $supplier = CatalogSupplier::factory()->create();

        $data = [
            'type' => 'material',
            'code' => 'VT-001',
            'name' => 'Ống nước PVC D21',
            'unit' => 'm',
            'unit_price' => 25000,
            'purchase_price' => 18000,
            'commission_rate' => 5.5,
            'supplier_id' => $supplier->id,
            'description' => 'Ống nước chất lượng cao',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'VT-001')
            ->assertJsonPath('data.type.value', 'material');

        $this->assertDatabaseHas('catalog_items', ['code' => 'VT-001', 'type' => 'material']);
    }

    public function test_create_fills_commission_rate_from_supplier_when_not_provided(): void
    {
        $supplier = CatalogSupplier::factory()->create(['commission_rate' => '8.50']);

        $data = [
            'type' => 'material',
            'code' => 'VT-FILL',
            'name' => 'Test fill commission',
            'unit' => 'cái',
            'unit_price' => 50000,
            'supplier_id' => $supplier->id,
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.commission_rate', '8.50');

        $this->assertDatabaseHas('catalog_items', ['code' => 'VT-FILL', 'commission_rate' => '8.50']);
    }

    public function test_create_uses_provided_commission_rate_over_supplier_default(): void
    {
        $supplier = CatalogSupplier::factory()->create(['commission_rate' => '8.50']);

        $data = [
            'type' => 'material',
            'code' => 'VT-OVR',
            'name' => 'Test override commission',
            'unit' => 'cái',
            'unit_price' => 50000,
            'commission_rate' => 3.00,
            'supplier_id' => $supplier->id,
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.commission_rate', '3.00');
    }

    public function test_can_create_service_item(): void
    {
        $category = ServiceCategory::factory()->create();

        $data = [
            'type' => 'service',
            'code' => 'DV-001',
            'name' => 'Dịch vụ sơn tường',
            'unit' => 'lần',
            'unit_price' => 350000,
            'service_category_id' => $category->id,
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'DV-001')
            ->assertJsonPath('data.type.value', 'service')
            ->assertJsonPath('data.service_category.id', $category->id);

        $this->assertDatabaseHas('catalog_items', ['code' => 'DV-001', 'type' => 'service', 'service_category_id' => $category->id]);
    }

    public function test_can_create_item_with_is_published(): void
    {
        $category = ServiceCategory::factory()->create();

        $data = [
            'type' => 'service',
            'code' => 'DV-PUB',
            'name' => 'Dịch vụ công bố',
            'unit' => 'lần',
            'unit_price' => 100000,
            'service_category_id' => $category->id,
            'is_published' => true,
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('catalog_items', ['code' => 'DV-PUB', 'is_published' => true]);
    }

    public function test_is_published_defaults_to_false(): void
    {
        $data = [
            'type' => 'material',
            'code' => 'VT-DEF',
            'name' => 'Vật tư mặc định',
            'unit' => 'm',
            'unit_price' => 10000,
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_published', false);
    }

    public function test_can_update_is_published(): void
    {
        $item = CatalogItem::factory()->service()->create(['is_published' => false]);

        $response = $this->putJson("{$this->baseUrl}/{$item->id}", [
            'is_published' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('catalog_items', ['id' => $item->id, 'is_published' => true]);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'code', 'name', 'unit', 'unit_price']);
    }

    public function test_create_fails_with_duplicate_code_same_type(): void
    {
        CatalogItem::factory()->material()->create(['code' => 'VT-001']);

        $response = $this->postJson($this->baseUrl, [
            'type' => 'material',
            'code' => 'VT-001',
            'name' => 'Another material',
            'unit' => 'm',
            'unit_price' => 10000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_create_same_code_different_type(): void
    {
        CatalogItem::factory()->material()->create(['code' => 'CT-001']);
        $category = ServiceCategory::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'type' => 'service',
            'code' => 'CT-001',
            'name' => 'A service',
            'unit' => 'lần',
            'unit_price' => 50000,
            'service_category_id' => $category->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_can_create_with_same_code_as_soft_deleted(): void
    {
        $item = CatalogItem::factory()->material()->create(['code' => 'VT-001']);
        $item->delete();

        $response = $this->postJson($this->baseUrl, [
            'type' => 'material',
            'code' => 'VT-001',
            'name' => 'New material',
            'unit' => 'm',
            'unit_price' => 10000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'VT-001');
    }

    public function test_create_fails_with_negative_price(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'type' => 'material',
            'code' => 'VT-001',
            'name' => 'Test',
            'unit' => 'm',
            'unit_price' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_price']);
    }

    // ==================== UPDATE ====================

    public function test_can_update_item(): void
    {
        $item = CatalogItem::factory()->material()->create(['name' => 'Old Name']);

        $response = $this->putJson("{$this->baseUrl}/{$item->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('catalog_items', ['id' => $item->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_nonexistent(): void
    {
        $response = $this->putJson("{$this->baseUrl}/99999", ['name' => 'Test']);

        $response->assertStatus(404);
    }

    // ==================== DELETE ====================

    public function test_can_delete_item(): void
    {
        $item = CatalogItem::factory()->material()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('catalog_items', ['id' => $item->id]);
    }

    public function test_delete_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== PUBLIC SERVICES ====================

    public function test_public_services_only_returns_published(): void
    {
        $category = ServiceCategory::factory()->create();

        CatalogItem::factory()->service()->create([
            'service_category_id' => $category->id,
            'is_published' => true,
            'name' => 'Published Service',
        ]);
        CatalogItem::factory()->service()->create([
            'service_category_id' => $category->id,
            'is_published' => false,
            'name' => 'Unpublished Service',
        ]);

        $response = $this->getJson('/api/v1/public/services');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Published Service', $names);
        $this->assertNotContains('Unpublished Service', $names);
    }

    public function test_public_services_excludes_inactive_even_if_published(): void
    {
        $category = ServiceCategory::factory()->create();

        CatalogItem::factory()->service()->inactive()->create([
            'service_category_id' => $category->id,
            'is_published' => true,
            'name' => 'Inactive Published',
        ]);

        $response = $this->getJson('/api/v1/public/services');

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertNotContains('Inactive Published', $names);
    }
}
