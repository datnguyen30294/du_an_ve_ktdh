<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicketCategory\Models\OgTicketCategory;
use App\Modules\PMC\OgTicketCategory\Repositories\OgTicketCategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OgTicketCategoryTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/og-ticket-categories';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // ==================== CRUD MASTER ====================

    public function test_can_list_categories(): void
    {
        OgTicketCategory::query()->create(['name' => 'Điện', 'code' => 'dien']);
        OgTicketCategory::query()->create(['name' => 'Nước', 'code' => 'nuoc']);

        $response = $this->getJson($this->baseUrl);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_can_create_category(): void
    {
        $response = $this->postJson($this->baseUrl, ['name' => 'Bảo trì']);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Bảo trì')
            ->assertJsonPath('data.code', 'bao-tri');

        $this->assertDatabaseHas('og_ticket_categories', ['name' => 'Bảo trì']);
    }

    public function test_create_fails_with_duplicate_name(): void
    {
        OgTicketCategory::query()->create(['name' => 'Điện', 'code' => 'dien']);

        $response = $this->postJson($this->baseUrl, ['name' => 'Điện']);

        $response->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_can_update_category(): void
    {
        $cat = OgTicketCategory::query()->create(['name' => 'Điện', 'code' => 'dien']);

        $response = $this->putJson("{$this->baseUrl}/{$cat->id}", ['name' => 'Điện nước']);

        $response->assertOk()->assertJsonPath('data.name', 'Điện nước');
    }

    public function test_can_delete_category_without_links(): void
    {
        $cat = OgTicketCategory::query()->create(['name' => 'Temp', 'code' => 'temp']);

        $this->deleteJson("{$this->baseUrl}/{$cat->id}")->assertOk();
        $this->assertSoftDeleted('og_ticket_categories', ['id' => $cat->id]);
    }

    public function test_delete_blocks_when_has_links(): void
    {
        $cat = OgTicketCategory::query()->create(['name' => 'InUse', 'code' => 'inuse']);
        $ogTicket = OgTicket::factory()->create();
        $ogTicket->categories()->attach($cat->id);

        $response = $this->deleteJson("{$this->baseUrl}/{$cat->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('og_ticket_categories', ['id' => $cat->id, 'deleted_at' => null]);
    }

    public function test_check_delete_reports_link_count(): void
    {
        $cat = OgTicketCategory::query()->create(['name' => 'Linked', 'code' => 'linked']);
        $ogTicket = OgTicket::factory()->create();
        $ogTicket->categories()->attach($cat->id);

        $response = $this->getJson("{$this->baseUrl}/{$cat->id}/check-delete");

        $response->assertOk()
            ->assertJsonPath('can_delete', false)
            ->assertJsonPath('link_count', 1);
    }

    // ==================== REPOSITORY ====================

    public function test_first_or_create_by_name_is_case_insensitive(): void
    {
        $repo = app(OgTicketCategoryRepository::class);

        $a = $repo->firstOrCreateByName('Điện');
        $b = $repo->firstOrCreateByName('điện');
        $c = $repo->firstOrCreateByName('  ĐIỆN  ');

        $this->assertEquals($a->id, $b->id);
        $this->assertEquals($a->id, $c->id);
    }

    public function test_first_or_create_auto_slugs_code(): void
    {
        $repo = app(OgTicketCategoryRepository::class);

        $a = $repo->firstOrCreateByName('Điện nước');
        $b = $repo->firstOrCreateByName('Điện Nước');

        $this->assertSame('dien-nuoc', $a->code);
        $this->assertSame($a->id, $b->id);
    }

    // ==================== SYNC TO OG TICKET ====================

    public function test_can_sync_categories_to_og_ticket(): void
    {
        $ogTicket = OgTicket::factory()->create();
        $c1 = OgTicketCategory::query()->create(['name' => 'Điện', 'code' => 'dien']);
        $c2 = OgTicketCategory::query()->create(['name' => 'Nước', 'code' => 'nuoc']);

        $response = $this->putJson("/api/v1/pmc/og-tickets/{$ogTicket->id}/categories", [
            'category_ids' => [$c1->id, $c2->id],
        ]);

        $response->assertOk();
        $this->assertCount(2, $ogTicket->fresh()->categories);
    }

    public function test_sync_categories_replaces_existing(): void
    {
        $ogTicket = OgTicket::factory()->create();
        $c1 = OgTicketCategory::query()->create(['name' => 'Điện', 'code' => 'dien']);
        $c2 = OgTicketCategory::query()->create(['name' => 'Nước', 'code' => 'nuoc']);
        $ogTicket->categories()->attach($c1->id);

        $this->putJson("/api/v1/pmc/og-tickets/{$ogTicket->id}/categories", [
            'category_ids' => [$c2->id],
        ])->assertOk();

        $fresh = $ogTicket->fresh()->categories;
        $this->assertCount(1, $fresh);
        $this->assertEquals($c2->id, $fresh->first()->id);
    }

    public function test_sync_categories_with_empty_detaches_all(): void
    {
        $ogTicket = OgTicket::factory()->create();
        $c = OgTicketCategory::query()->create(['name' => 'Điện', 'code' => 'dien']);
        $ogTicket->categories()->attach($c->id);

        $this->putJson("/api/v1/pmc/og-tickets/{$ogTicket->id}/categories", [
            'category_ids' => [],
        ])->assertOk();

        $this->assertCount(0, $ogTicket->fresh()->categories);
    }

    public function test_sync_rejects_nonexistent_category_id(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $response = $this->putJson("/api/v1/pmc/og-tickets/{$ogTicket->id}/categories", [
            'category_ids' => [99999],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('category_ids.0');
    }

    // ==================== AUTO-RESOLVE FROM SUBJECT ====================

    public function test_resolve_matches_catalog_items_to_categories(): void
    {
        $catDien = ServiceCategory::factory()->create(['name' => 'Điện']);
        $catNuoc = ServiceCategory::factory()->create(['name' => 'Nước']);
        CatalogItem::factory()->create(['name' => 'Sửa đèn', 'service_category_id' => $catDien->id]);
        CatalogItem::factory()->create(['name' => 'Sửa vòi', 'service_category_id' => $catNuoc->id]);

        $ogTicket = OgTicket::factory()->create(['subject' => 'Sửa đèn, Sửa vòi']);

        $this->invokeResolve($ogTicket, 'Sửa đèn, Sửa vòi');

        $names = $ogTicket->fresh()->categories->pluck('name')->sort()->values()->all();
        $this->assertSame(['Nước', 'Điện'], $names);
    }

    public function test_resolve_adds_khac_when_any_name_unmatched(): void
    {
        $catDien = ServiceCategory::factory()->create(['name' => 'Điện']);
        CatalogItem::factory()->create(['name' => 'Sửa đèn', 'service_category_id' => $catDien->id]);

        $ogTicket = OgTicket::factory()->create();

        $this->invokeResolve($ogTicket, 'Sửa đèn, Yêu cầu tự do');

        $names = $ogTicket->fresh()->categories->pluck('name')->sort()->values()->all();
        $this->assertSame(['Khác', 'Điện'], $names);
    }

    public function test_resolve_empty_subject_yields_only_khac(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $this->invokeResolve($ogTicket, '');

        $names = $ogTicket->fresh()->categories->pluck('name')->values()->all();
        $this->assertSame(['Khác'], $names);
    }

    public function test_resolve_is_case_insensitive(): void
    {
        $catDien = ServiceCategory::factory()->create(['name' => 'Điện']);
        CatalogItem::factory()->create(['name' => 'Sửa đèn', 'service_category_id' => $catDien->id]);

        $ogTicket = OgTicket::factory()->create();

        $this->invokeResolve($ogTicket, 'SỬA ĐÈN');

        $names = $ogTicket->fresh()->categories->pluck('name')->values()->all();
        $this->assertSame(['Điện'], $names);
    }

    public function test_resolve_reuses_existing_og_ticket_categories(): void
    {
        $catDien = ServiceCategory::factory()->create(['name' => 'Điện']);
        CatalogItem::factory()->create(['name' => 'Sửa đèn', 'service_category_id' => $catDien->id]);

        // Pre-existing og_ticket_category with same name
        OgTicketCategory::query()->create(['name' => 'Điện', 'code' => 'dien-existing']);

        $ogTicket = OgTicket::factory()->create();
        $this->invokeResolve($ogTicket, 'Sửa đèn');

        $this->assertCount(1, OgTicketCategory::query()->where('name', 'Điện')->get());
    }

    /**
     * Invoke the private resolver in OgTicketExternalService.
     */
    private function invokeResolve(OgTicket $ogTicket, string $subject): void
    {
        $service = app(\App\Modules\Platform\Ticket\ExternalServices\OgTicketExternalServiceInterface::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('attachCategoriesFromSubject');
        $method->setAccessible(true);
        $method->invoke($service, $ogTicket, $subject);
    }
}
