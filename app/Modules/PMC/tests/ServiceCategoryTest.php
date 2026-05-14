<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCategoryTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/catalog/service-categories';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    // ==================== LIST ====================

    public function test_can_list_categories(): void
    {
        ServiceCategory::factory()->count(3)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_search_categories(): void
    {
        ServiceCategory::factory()->create(['name' => 'Sửa chữa', 'code' => 'SC-SC']);
        ServiceCategory::factory()->create(['name' => 'Vệ sinh', 'code' => 'SC-VS']);

        $response = $this->getJson("{$this->baseUrl}?search=Sửa");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'SC-SC');
    }

    public function test_can_filter_by_status(): void
    {
        ServiceCategory::factory()->create(['status' => 'active']);
        ServiceCategory::factory()->inactive()->create();

        $response = $this->getJson("{$this->baseUrl}?status=inactive");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ==================== SHOW ====================

    public function test_can_show_category(): void
    {
        $category = ServiceCategory::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', $category->name);
    }

    public function test_show_returns_404(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CREATE ====================

    public function test_can_create_category(): void
    {
        $data = [
            'name' => 'Sửa chữa',
            'code' => 'SC-SC',
            'description' => 'Dịch vụ sửa chữa',
            'sort_order' => 1,
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Sửa chữa')
            ->assertJsonPath('data.code', 'SC-SC');

        $this->assertDatabaseHas('service_categories', ['code' => 'SC-SC']);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_create_fails_with_duplicate_code(): void
    {
        ServiceCategory::factory()->create(['code' => 'SC-SC']);

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Another',
            'code' => 'SC-SC',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    // ==================== UPDATE ====================

    public function test_can_update_category(): void
    {
        $category = ServiceCategory::factory()->create(['name' => 'Old']);

        $response = $this->putJson("{$this->baseUrl}/{$category->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_update_returns_404(): void
    {
        $response = $this->putJson("{$this->baseUrl}/99999", ['name' => 'Test']);

        $response->assertStatus(404);
    }

    // ==================== CHECK DELETE ====================

    public function test_check_delete_returns_can_delete_when_no_items(): void
    {
        $category = ServiceCategory::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$category->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', true);
    }

    public function test_check_delete_returns_cannot_delete_when_has_items(): void
    {
        $category = ServiceCategory::factory()->create();
        CatalogItem::factory()->service()->create(['service_category_id' => $category->id]);

        $response = $this->getJson("{$this->baseUrl}/{$category->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', false)
            ->assertJsonPath('item_count', 1);
    }

    // ==================== DELETE ====================

    public function test_can_delete_category_without_items(): void
    {
        $category = ServiceCategory::factory()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('service_categories', ['id' => $category->id]);
    }

    public function test_delete_fails_when_has_items(): void
    {
        $category = ServiceCategory::factory()->create();
        CatalogItem::factory()->service()->create(['service_category_id' => $category->id]);

        $response = $this->deleteJson("{$this->baseUrl}/{$category->id}");

        $response->assertStatus(422);
    }

    public function test_delete_returns_404(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }
}
