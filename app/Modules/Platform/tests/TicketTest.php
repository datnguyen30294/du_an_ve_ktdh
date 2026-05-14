<?php

namespace Tests\Modules\Platform;

use App\Events\TicketReceivedByOrganization;
use App\Modules\Platform\Auth\Models\RequesterAccount;
use App\Modules\Platform\Customer\Models\Customer;
use App\Modules\Platform\Tenant\Models\Organization;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\Platform\Ticket\Notifications\TicketReceivedNotification;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/tickets';

    // ==================== SUBMIT TICKET ====================

    public function test_can_submit_ticket(): void
    {
        $project = Project::factory()->create();

        $data = [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901111111',
            'apartment_name' => 'A-101',
            'project_id' => $project->id,
            'subject' => 'Hỏng máy lạnh',
            'description' => 'Máy lạnh phòng khách không lạnh.',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requester_name', 'Nguyễn Văn A')
            ->assertJsonPath('data.requester_phone', '0901111111')
            // apartment_name chưa implement trong flow submit
            ->assertJsonPath('data.subject', 'Hỏng máy lạnh')
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.channel.value', 'website')
            ->assertJsonPath('data.claimed_by_org_id', null)
            ->assertJsonPath('data.claimed_at', null);

        $this->assertDatabaseHas('tickets', [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901111111',
            'status' => 'pending',
            'channel' => 'website',
        ]);
    }

    public function test_submit_ticket_without_optional_fields(): void
    {
        $project = Project::factory()->create();

        $data = [
            'requester_name' => 'Trần Thị B',
            'requester_phone' => '0902222222',
            'project_id' => $project->id,
            'subject' => 'Cửa kính bị nứt',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.apartment_name', null)
            ->assertJsonPath('data.description', null);
    }

    public function test_submit_ticket_with_channel_param(): void
    {
        $project = Project::factory()->create();

        $data = [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Test',
            'channel' => 'phone',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.channel.value', 'phone')
            ->assertJsonPath('data.channel.label', 'Điện thoại');
    }

    public function test_submit_ticket_with_org_id_pre_assign(): void
    {
        $project = Project::factory()->create();
        $organization = Organization::withoutEvents(fn () => Organization::factory()->create());

        $data = [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Test',
            'claimed_by_org_id' => $organization->id,
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.status.value', 'received')
            ->assertJsonPath('data.claimed_by_org_id', $organization->id);

        $this->assertNotNull($response->json('data.claimed_at'));

        // Verify OgTicket was auto-created for the organization
        $ticketId = $response->json('data.id');
        $this->assertDatabaseHas('og_tickets', [
            'ticket_id' => $ticketId,
            'requester_name' => 'Nguyễn Văn A',
            'subject' => 'Test',
            'status' => 'received',
        ]);

        // Verify SLA fields auto-populated from settings
        $ogTicket = OgTicket::where('ticket_id', $ticketId)->first();
        $this->assertNotNull($ogTicket->received_at);
        $this->assertNotNull($ogTicket->sla_quote_due_at);
        $this->assertTrue($ogTicket->sla_quote_due_at->greaterThan($ogTicket->received_at));
    }

    // ==================== TICKET CODE GENERATION ====================

    public function test_ticket_code_auto_generated(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Test',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Test',
        ]);

        $response->assertStatus(201);

        $code = $response->json('data.code');
        $year = date('Y');
        $this->assertMatchesRegularExpression("/^TK-{$year}-\\d{3}$/", $code);
    }

    public function test_ticket_code_sequence_increments(): void
    {
        $project = Project::factory()->create();
        $year = date('Y');

        Ticket::factory()->create(['code' => "TK-{$year}-001", 'project_id' => $project->id]);

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Test',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Test',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', "TK-{$year}-002");
    }

    // ==================== VALIDATION ====================

    public function test_submit_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['requester_name', 'requester_phone', 'subject']);
    }

    public function test_submit_fails_with_invalid_channel(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Test',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Test',
            'channel' => 'invalid_channel',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_submit_fails_with_nonexistent_org_id(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Test',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Test',
            'claimed_by_org_id' => 99999,
        ]);

        $response->assertStatus(422);
    }

    public function test_submit_fails_with_name_exceeding_max_length(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => str_repeat('a', 256),
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['requester_name']);
    }

    public function test_submit_fails_with_subject_exceeding_max_length(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Test',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => str_repeat('a', 501),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject']);
    }

    // ==================== MODEL SCOPES ====================

    public function test_available_scope_returns_pending_unclaimed_tickets(): void
    {
        $project = Project::factory()->create();

        Ticket::factory()->create(['project_id' => $project->id, 'status' => TicketStatus::Pending, 'claimed_by_org_id' => null]);
        Ticket::factory()->create(['project_id' => $project->id, 'status' => TicketStatus::Received, 'claimed_by_org_id' => 1]);
        Ticket::factory()->create(['project_id' => $project->id, 'status' => TicketStatus::Completed, 'claimed_by_org_id' => null]);

        $available = Ticket::available()->get();

        $this->assertCount(1, $available);
        $this->assertEquals(TicketStatus::Pending, $available->first()->status);
    }

    public function test_search_scope_finds_by_subject(): void
    {
        $project = Project::factory()->create();

        Ticket::factory()->create(['project_id' => $project->id, 'subject' => 'Hỏng máy lạnh']);
        Ticket::factory()->create(['project_id' => $project->id, 'subject' => 'Rò rỉ nước']);

        $results = Ticket::search('máy lạnh')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Hỏng máy lạnh', $results->first()->subject);
    }

    public function test_search_scope_finds_by_requester_name(): void
    {
        $project = Project::factory()->create();

        Ticket::factory()->create(['project_id' => $project->id, 'requester_name' => 'Nguyễn Văn A']);
        Ticket::factory()->create(['project_id' => $project->id, 'requester_name' => 'Trần Thị B']);

        $results = Ticket::search('Nguyễn')->get();

        $this->assertCount(1, $results);
    }

    public function test_submit_ticket_without_project_id(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Test',
            'requester_phone' => '0901111111',
            'subject' => 'Test without project',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.project_id', null);
    }

    // ==================== NO AUTH REQUIRED ====================

    public function test_submit_does_not_require_authentication(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Test',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Test ticket',
        ]);

        $response->assertStatus(201);
    }

    // ==================== SHOW WITH PMC PROCESSING ====================

    public function test_show_returns_pmc_processing_when_og_ticket_exists(): void
    {
        $requester = RequesterAccount::create([
            'name' => 'Test Requester',
            'email' => 'requester@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $organization = Organization::withoutEvents(fn () => Organization::factory()->create());
        $project = Project::factory()->create();
        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'status' => TicketStatus::Received,
            'claimed_by_org_id' => $organization->id,
            'claimed_at' => now(),
        ]);

        $receivedBy = Account::factory()->create();
        $assignedTo = Account::factory()->create();

        OgTicket::create([
            'ticket_id' => $ticket->id,
            'project_id' => $project->id,
            'requester_name' => $ticket->requester_name,
            'requester_phone' => $ticket->requester_phone,
            'subject' => $ticket->subject,
            'channel' => $ticket->channel->value,
            'status' => OgTicketStatus::Assigned->value,
            'priority' => OgTicketPriority::High->value,
            'received_by_id' => $receivedBy->id,
            'received_at' => now(),
            'sla_completion_due_at' => now()->addDays(3),
        ]);

        // Assign via pivot table
        OgTicket::query()->where('ticket_id', $ticket->id)->first()->assignees()->attach($assignedTo->id);

        $response = $this->actingAs($requester, 'requester')
            ->getJson("/api/v1/platform/tickets/{$ticket->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.pmc_processing.status.value', 'assigned')
            ->assertJsonPath('data.pmc_processing.priority.value', 'high')
            ->assertJsonPath('data.pmc_processing.received_by.name', $receivedBy->name)
            ->assertJsonPath('data.pmc_processing.assignees.0.name', $assignedTo->name)
            ->assertJsonStructure([
                'data' => [
                    'pmc_processing' => [
                        'status' => ['value', 'label'],
                        'priority' => ['value', 'label'],
                        'received_at',
                        'received_by' => ['id', 'name'],
                        'assignees',
                        'sla_due_at',
                    ],
                ],
            ]);
    }

    public function test_show_returns_null_pmc_processing_when_no_og_ticket(): void
    {
        $requester = RequesterAccount::create([
            'name' => 'Test Requester',
            'email' => 'requester2@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $project = Project::factory()->create();
        $ticket = Ticket::factory()->create([
            'project_id' => $project->id,
            'status' => TicketStatus::Pending,
        ]);

        $response = $this->actingAs($requester, 'requester')
            ->getJson("/api/v1/platform/tickets/{$ticket->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.pmc_processing', null);
    }

    // ==================== CUSTOMER EMAIL + NOTIFICATIONS ====================

    public function test_submit_stores_customer_email_when_provided(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901111111',
            'requester_email' => 'nguyenvana@example.com',
            'project_id' => $project->id,
            'subject' => 'Test email',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'phone' => '0901111111',
            'email' => 'nguyenvana@example.com',
        ]);
    }

    public function test_submit_rejects_invalid_email(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901111111',
            'requester_email' => 'not-an-email',
            'project_id' => $project->id,
            'subject' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['requester_email']);
    }

    public function test_submit_preserves_existing_customer_email_when_not_provided(): void
    {
        $project = Project::factory()->create();

        Customer::factory()->create([
            'phone' => '0901111111',
            'email' => 'old@example.com',
            'name' => 'Old Name',
        ]);

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'New Name',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Test',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'phone' => '0901111111',
            'email' => 'old@example.com',
            'name' => 'New Name',
        ]);
    }

    public function test_submit_dispatches_ticket_received_event_when_claimed_by_org(): void
    {
        Event::fake([TicketReceivedByOrganization::class]);

        $project = Project::factory()->create();
        $organization = Organization::withoutEvents(fn () => Organization::factory()->create());

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901111111',
            'requester_email' => 'a@example.com',
            'project_id' => $project->id,
            'subject' => 'Issue',
            'claimed_by_org_id' => $organization->id,
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(
            TicketReceivedByOrganization::class,
            fn (TicketReceivedByOrganization $event): bool => $event->payload['customer_name'] === 'Nguyễn Văn A'
                && $event->payload['ticket_subject'] === 'Issue'
        );
    }

    public function test_submit_does_not_dispatch_event_when_ticket_goes_to_pool(): void
    {
        Event::fake([TicketReceivedByOrganization::class]);

        $project = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901111111',
            'project_id' => $project->id,
            'subject' => 'Pool issue',
        ]);

        $response->assertStatus(201);

        Event::assertNotDispatched(TicketReceivedByOrganization::class);
    }

    public function test_listener_sends_mail_with_tenant_aware_public_url(): void
    {
        Notification::fake();
        config()->set('app.frontend_url', 'http://residential.test:3000');

        $customer = Customer::factory()->withEmail()->create();

        $event = new TicketReceivedByOrganization($customer->id, [
            'ticket_code' => 'TK-2026-001',
            'ticket_subject' => 'Hỏng máy lạnh',
            'organization_name' => 'Tổ chức A',
            'customer_name' => $customer->name,
            'tenant_subdomain' => 'tnp',
        ]);

        (new \App\Listeners\SendTicketReceivedEmail)->handle($event);

        Notification::assertSentTo(
            $customer,
            TicketReceivedNotification::class,
            fn (TicketReceivedNotification $notification): bool => $notification->payload['ticket_code'] === 'TK-2026-001'
                && ($notification->payload['public_url'] ?? null) === 'http://tnp.residential.test:3000/tickets/TK-2026-001'
        );
    }

    public function test_listener_skips_mail_when_customer_has_no_email(): void
    {
        Notification::fake();

        $customer = Customer::factory()->withoutEmail()->create();

        $event = new TicketReceivedByOrganization($customer->id, [
            'ticket_code' => 'TK-2026-002',
            'ticket_subject' => 'Test',
            'organization_name' => null,
            'customer_name' => $customer->name,
            'tenant_subdomain' => 'tnp',
        ]);

        (new \App\Listeners\SendTicketReceivedEmail)->handle($event);

        Notification::assertNothingSent();
    }

    public function test_submit_event_payload_contains_org_id_as_tenant_subdomain(): void
    {
        Event::fake([TicketReceivedByOrganization::class]);

        $project = Project::factory()->create();
        $organization = Organization::withoutEvents(fn () => Organization::factory()->create());

        $response = $this->postJson($this->baseUrl, [
            'requester_name' => 'Nguyễn Văn A',
            'requester_phone' => '0901111111',
            'requester_email' => 'a@example.com',
            'project_id' => $project->id,
            'subject' => 'Issue',
            'claimed_by_org_id' => $organization->id,
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(
            TicketReceivedByOrganization::class,
            fn (TicketReceivedByOrganization $event): bool => ($event->payload['tenant_subdomain'] ?? null) === (string) $organization->id
        );
    }
}
