<?php

namespace Tests\Feature\PMC;

use App\Common\Support\PhoneNormalizer;
use App\Modules\PMC\Customer\Models\Customer;
use App\Modules\PMC\Customer\Services\CustomerService;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/customers';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // --- PhoneNormalizer unit behaviour ---

    #[Test]
    public function test_phone_normalizer_strips_spaces_and_converts_prefixes(): void
    {
        $this->assertSame('0912345678', PhoneNormalizer::normalize('0912345678'));
        $this->assertSame('0912345678', PhoneNormalizer::normalize('+84912345678'));
        $this->assertSame('0912345678', PhoneNormalizer::normalize('84912345678'));
        $this->assertSame('0912345678', PhoneNormalizer::normalize('0912 345 678'));
        $this->assertSame('0912345678', PhoneNormalizer::normalize('0912-345-678'));
        $this->assertSame('0912345678', PhoneNormalizer::normalize('(+84) 912.345.678'));
        $this->assertSame('', PhoneNormalizer::normalize(null));
        $this->assertSame('', PhoneNormalizer::normalize('   '));
    }

    // --- LIST ---

    #[Test]
    public function test_index_returns_paginated_customers(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function test_index_search_by_name_and_phone(): void
    {
        Customer::factory()->create(['full_name' => 'Nguyễn Văn An', 'phone' => '0911000001']);
        Customer::factory()->create(['full_name' => 'Trần Thị Bình', 'phone' => '0922000002']);

        $byName = $this->getJson($this->baseUrl.'?search=Nguyễn');
        $byName->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Nguyễn Văn An');

        $byPhone = $this->getJson($this->baseUrl.'?search=0922');
        $byPhone->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.phone', '0922000002');
    }

    // --- SHOW ---

    #[Test]
    public function test_show_returns_customer_with_aggregates(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->getJson($this->baseUrl.'/'.$customer->id);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.aggregates.ticket_count', 0)
            ->assertJsonPath('data.aggregates.avg_rating', null)
            ->assertJsonPath('data.aggregates.total_paid', '0.00');
    }

    #[Test]
    public function test_show_returns_404_for_unknown_customer(): void
    {
        $this->getJson($this->baseUrl.'/999999')
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    // --- CREATE ---

    #[Test]
    public function test_store_creates_customer_and_autogen_code(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'full_name' => 'Lê Thu Hà',
            'phone' => '+84912345678',
            'email' => 'ha.le@example.com',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.full_name', 'Lê Thu Hà')
            ->assertJsonPath('data.phone', '0912345678'); // normalized

        $this->assertDatabaseHas('pmc_customers', [
            'full_name' => 'Lê Thu Hà',
            'phone' => '0912345678',
        ]);

        $customer = Customer::query()->where('phone', '0912345678')->first();
        $this->assertNotNull($customer);
        $this->assertMatchesRegularExpression('/^KH-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/', (string) $customer->code);
    }

    #[Test]
    public function test_store_blocks_duplicate_phone_after_normalize(): void
    {
        Customer::factory()->create(['phone' => '0912345678']);

        $response = $this->postJson($this->baseUrl, [
            'full_name' => 'Someone Else',
            'phone' => '+84 912 345 678', // different format → same normalized
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['phone']);
    }

    #[Test]
    public function test_store_requires_full_name_and_phone(): void
    {
        $this->postJson($this->baseUrl, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['full_name', 'phone']);
    }

    // --- UPDATE ---

    #[Test]
    public function test_update_patches_customer(): void
    {
        $customer = Customer::factory()->create(['phone' => '0911111111']);

        $response = $this->putJson($this->baseUrl.'/'.$customer->id, [
            'full_name' => 'Updated Name',
            'note' => 'VIP',
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.full_name', 'Updated Name')
            ->assertJsonPath('data.note', 'VIP');
    }

    #[Test]
    public function test_update_allows_keeping_same_phone(): void
    {
        $customer = Customer::factory()->create(['phone' => '0911111111']);

        $this->putJson($this->baseUrl.'/'.$customer->id, [
            'phone' => '+84911111111', // same normalized as existing
        ])->assertStatus(Response::HTTP_OK);
    }

    // --- DELETE + CHECK DELETE ---

    #[Test]
    public function test_check_delete_reports_true_when_no_dependencies(): void
    {
        $customer = Customer::factory()->create();

        $this->getJson($this->baseUrl.'/'.$customer->id.'/check-delete')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('can_delete', true);
    }

    #[Test]
    public function test_check_delete_reports_false_when_ticket_exists(): void
    {
        $customer = Customer::factory()->create();
        OgTicket::factory()->create(['customer_id' => $customer->id]);

        $response = $this->getJson($this->baseUrl.'/'.$customer->id.'/check-delete');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('can_delete', false)
            ->assertJsonPath('ticket_count', 1);
    }

    #[Test]
    public function test_destroy_soft_deletes_when_no_dependencies(): void
    {
        $customer = Customer::factory()->create();

        $this->deleteJson($this->baseUrl.'/'.$customer->id)
            ->assertStatus(Response::HTTP_OK);

        $this->assertSoftDeleted('pmc_customers', ['id' => $customer->id]);
    }

    #[Test]
    public function test_destroy_blocks_when_ticket_exists(): void
    {
        $customer = Customer::factory()->create();
        OgTicket::factory()->create(['customer_id' => $customer->id]);

        $this->deleteJson($this->baseUrl.'/'.$customer->id)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertDatabaseHas('pmc_customers', ['id' => $customer->id, 'deleted_at' => null]);
    }

    // --- TICKETS endpoint ---

    #[Test]
    public function test_tickets_endpoint_lists_customer_tickets(): void
    {
        $customer = Customer::factory()->create();
        OgTicket::factory()->count(2)->create(['customer_id' => $customer->id]);
        OgTicket::factory()->create(); // other customer

        $response = $this->getJson($this->baseUrl.'/'.$customer->id.'/tickets');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data');
    }

    // --- findOrCreateByPhone ---

    #[Test]
    public function test_find_or_create_by_phone_reuses_existing(): void
    {
        $existing = Customer::factory()->create(['phone' => '0923000001']);

        $service = app(CustomerService::class);
        $result = $service->findOrCreateByPhone('+84923000001', 'Different Name');

        $this->assertSame($existing->id, $result->id);
        $this->assertSame('0923000001', $result->phone);
    }

    #[Test]
    public function test_find_or_create_by_phone_creates_new_when_not_exists(): void
    {
        $service = app(CustomerService::class);
        $result = $service->findOrCreateByPhone('0934000009', 'Khách Mới');

        $this->assertNotNull($result->id);
        $this->assertSame('0934000009', $result->phone);
        $this->assertSame('Khách Mới', $result->full_name);
        $this->assertMatchesRegularExpression('/^KH-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/', (string) $result->code);
    }

    // --- mark_contacted ---

    #[Test]
    public function test_mark_contacted_sets_first_and_last(): void
    {
        $customer = Customer::factory()->create([
            'first_contacted_at' => null,
            'last_contacted_at' => null,
        ]);

        app(CustomerService::class)->markContacted($customer);

        $customer->refresh();
        $this->assertNotNull($customer->first_contacted_at);
        $this->assertNotNull($customer->last_contacted_at);

        $originalFirst = $customer->first_contacted_at;

        // second call: first stays same, last updates
        sleep(1);
        app(CustomerService::class)->markContacted($customer);
        $customer->refresh();

        $this->assertEquals($originalFirst->timestamp, $customer->first_contacted_at->timestamp);
    }

    // --- Integration: og_ticket create auto-gets customer_id ---

    #[Test]
    public function test_og_ticket_factory_auto_creates_customer(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $this->assertNotNull($ogTicket->customer_id);
        $this->assertDatabaseHas('pmc_customers', ['id' => $ogTicket->customer_id]);
    }

    #[Test]
    public function test_og_ticket_status_enum_check(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $this->assertSame(OgTicketStatus::Received, $ogTicket->status);
    }
}
