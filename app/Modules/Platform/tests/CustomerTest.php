<?php

namespace Tests\Modules\Platform;

use App\Modules\Platform\Customer\Models\Customer;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/tickets';

    public function test_submit_ticket_creates_new_customer(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901234567',
            'subject' => 'Hỏng máy lạnh',
            'address' => '123 Nguyễn Huệ, Q1',
            'project_id' => $project->id,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('customers', [
            'name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'address' => '123 Nguyễn Huệ, Q1',
        ]);

        $ticket = Ticket::where('code', $response->json('data.code'))->first();
        $this->assertNotNull($ticket->customer_id);

        $customer = Customer::find($ticket->customer_id);
        $this->assertEquals('0901234567', $customer->phone);
    }

    public function test_submit_ticket_links_existing_customer_by_phone(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Tên cũ',
            'phone' => '0909999888',
            'address' => 'Địa chỉ cũ',
        ]);

        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Tên mới',
            'requester_phone' => '0909999888',
            'subject' => 'Rò rỉ nước',
            'address' => 'Địa chỉ mới',
            'project_id' => $project->id,
        ]);

        $response->assertCreated();

        // Should not create a new customer
        $this->assertEquals(1, Customer::where('phone', '0909999888')->count());

        // Should link to existing customer
        $ticket = Ticket::where('code', $response->json('data.code'))->first();
        $this->assertEquals($customer->id, $ticket->customer_id);

        // Should update name and address
        $customer->refresh();
        $this->assertEquals('Tên mới', $customer->name);
        $this->assertEquals('Địa chỉ mới', $customer->address);
    }

    public function test_existing_customer_address_preserved_when_not_provided(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Tên cũ',
            'phone' => '0908888777',
            'address' => 'Địa chỉ cũ',
        ]);

        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Tên mới',
            'requester_phone' => '0908888777',
            'subject' => 'Đèn hỏng',
            'project_id' => $project->id,
        ]);

        $response->assertCreated();

        // Address should be preserved since not provided in request
        $customer->refresh();
        $this->assertEquals('Tên mới', $customer->name);
        $this->assertEquals('Địa chỉ cũ', $customer->address);
    }

    public function test_customer_phone_unique_constraint(): void
    {
        Customer::factory()->create(['phone' => '0901111222']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Customer::factory()->create(['phone' => '0901111222']);
    }

    public function test_multiple_tickets_same_customer(): void
    {
        $project = Project::factory()->create();

        // First ticket
        $this->postJson($this->baseUrl, [
            'requester_name' => 'Nguyễn B',
            'requester_phone' => '0907777666',
            'subject' => 'Ticket 1',
            'project_id' => $project->id,
        ])->assertCreated();

        // Second ticket same phone
        $this->postJson($this->baseUrl, [
            'requester_name' => 'Nguyễn B',
            'requester_phone' => '0907777666',
            'subject' => 'Ticket 2',
            'project_id' => $project->id,
        ])->assertCreated();

        // Only 1 customer
        $this->assertEquals(1, Customer::where('phone', '0907777666')->count());

        // Both tickets link to same customer
        $customer = Customer::where('phone', '0907777666')->first();
        $this->assertEquals(2, Ticket::where('customer_id', $customer->id)->count());
    }

    public function test_ticket_response_includes_customer_data(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Trần Thị C',
            'requester_phone' => '0906666555',
            'subject' => 'Test customer in response',
            'address' => 'Quận 7',
            'project_id' => $project->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer.name', 'Trần Thị C')
            ->assertJsonPath('data.customer.phone', '0906666555')
            ->assertJsonPath('data.customer.address', 'Quận 7');
    }

    public function test_customer_has_many_tickets_relationship(): void
    {
        $customer = Customer::factory()->create();
        Ticket::factory()->count(3)->create(['customer_id' => $customer->id]);

        $this->assertEquals(3, $customer->tickets()->count());
    }
}
