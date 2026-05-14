<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\Catalog\Models\CatalogSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSupplierTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/catalog/suppliers';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    // ==================== LIST ====================

    public function test_can_list_suppliers(): void
    {
        CatalogSupplier::factory()->count(3)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_search_suppliers_by_name(): void
    {
        CatalogSupplier::factory()->create(['name' => 'Công ty Vật tư X', 'code' => 'VTX']);
        CatalogSupplier::factory()->create(['name' => 'Công ty Điện lạnh Y', 'code' => 'DLY']);

        $response = $this->getJson("{$this->baseUrl}?search=Vật tư");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'VTX');
    }

    public function test_can_filter_suppliers_by_status(): void
    {
        CatalogSupplier::factory()->create(['status' => 'active']);
        CatalogSupplier::factory()->inactive()->create();

        $response = $this->getJson("{$this->baseUrl}?status=inactive");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_list_includes_items_count(): void
    {
        $supplier = CatalogSupplier::factory()->create();
        CatalogItem::factory()->material()->count(2)->create(['supplier_id' => $supplier->id]);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.items_count', 2);
    }

    // ==================== SHOW ====================

    public function test_can_show_supplier(): void
    {
        $supplier = CatalogSupplier::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $supplier->id)
            ->assertJsonPath('data.code', $supplier->code);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CREATE ====================

    public function test_can_create_supplier(): void
    {
        $data = [
            'name' => 'Công ty Vật tư X',
            'code' => 'VTX',
            'contact' => 'Phòng kinh doanh',
            'phone' => '0281111111',
            'address' => 'Q.1, TP.HCM',
            'email' => 'info@vtx.com',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'VTX')
            ->assertJsonPath('data.name', 'Công ty Vật tư X');

        $this->assertDatabaseHas('catalog_suppliers', ['code' => 'VTX']);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_create_fails_with_duplicate_code(): void
    {
        CatalogSupplier::factory()->create(['code' => 'VTX']);

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Another supplier',
            'code' => 'VTX',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_create_with_same_code_as_soft_deleted(): void
    {
        $supplier = CatalogSupplier::factory()->create(['code' => 'VTX']);
        $supplier->delete();

        $response = $this->postJson($this->baseUrl, [
            'name' => 'New supplier',
            'code' => 'VTX',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'VTX');
    }

    // ==================== UPDATE ====================

    public function test_can_update_supplier(): void
    {
        $supplier = CatalogSupplier::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("{$this->baseUrl}/{$supplier->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('catalog_suppliers', ['id' => $supplier->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_nonexistent(): void
    {
        $response = $this->putJson("{$this->baseUrl}/99999", ['name' => 'Test']);

        $response->assertStatus(404);
    }

    // ==================== CHECK DELETE ====================

    public function test_check_delete_returns_can_delete_true_when_no_items(): void
    {
        $supplier = CatalogSupplier::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$supplier->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', true)
            ->assertJsonPath('item_count', 0);
    }

    public function test_check_delete_returns_can_delete_false_with_item_count(): void
    {
        $supplier = CatalogSupplier::factory()->create();
        CatalogItem::factory()->material()->count(3)->create(['supplier_id' => $supplier->id]);

        $response = $this->getJson("{$this->baseUrl}/{$supplier->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', false)
            ->assertJsonPath('item_count', 3);
    }

    public function test_check_delete_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999/check-delete");

        $response->assertStatus(404);
    }

    // ==================== DELETE ====================

    public function test_can_delete_supplier_without_items(): void
    {
        $supplier = CatalogSupplier::factory()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Xoá thành công.');

        $this->assertSoftDeleted('catalog_suppliers', ['id' => $supplier->id]);
    }

    public function test_cannot_delete_supplier_with_items(): void
    {
        $supplier = CatalogSupplier::factory()->create();
        CatalogItem::factory()->material()->create(['supplier_id' => $supplier->id]);

        $response = $this->deleteJson("{$this->baseUrl}/{$supplier->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('catalog_suppliers', ['id' => $supplier->id, 'deleted_at' => null]);
    }

    public function test_delete_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }
}
