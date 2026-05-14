<?php

namespace Tests\Modules\Platform;

use App\Modules\Platform\Ticket\Contracts\TicketServiceInterface;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\ExternalServices\OgTicketExternalServiceInterface;
use App\Modules\Platform\Ticket\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AutoReleaseStaleTicketsTest extends TestCase
{
    use RefreshDatabase;

    // ==================== COMMAND ====================

    public function test_command_calls_service_and_reports_releases(): void
    {
        $mock = Mockery::mock(TicketServiceInterface::class);
        $mock->shouldReceive('autoReleaseStaleTickets')
            ->once()
            ->andReturn(['checked' => 3, 'released' => 2]);
        $this->app->instance(TicketServiceInterface::class, $mock);

        $this->artisan('app:auto-release-stale-tickets')
            ->expectsOutput('Done. Released 2/3 stale ticket(s).')
            ->assertExitCode(0);
    }

    public function test_command_reports_no_stale_tickets(): void
    {
        $mock = Mockery::mock(TicketServiceInterface::class);
        $mock->shouldReceive('autoReleaseStaleTickets')
            ->once()
            ->andReturn(['checked' => 0, 'released' => 0]);
        $this->app->instance(TicketServiceInterface::class, $mock);

        $this->artisan('app:auto-release-stale-tickets')
            ->expectsOutput('No stale tickets found.')
            ->assertExitCode(0);
    }

    // ==================== SERVICE LOGIC ====================

    public function test_service_releases_stale_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Received,
            'claimed_by_org_id' => 'test-org',
            'claimed_at' => now()->subMinutes(Ticket::STALE_TIMEOUT_MINUTES + 10),
        ]);

        $mock = Mockery::mock(OgTicketExternalServiceInterface::class);
        $mock->shouldReceive('autoReleaseOgTicket')
            ->with($ticket->id, 'test-org', Ticket::STALE_TIMEOUT_MINUTES)
            ->once()
            ->andReturn(true);
        $mock->shouldReceive('createFromTicket')->never();
        $mock->shouldReceive('getProcessingInfoByTicketId')->never();
        $mock->shouldReceive('hasRecentStatusChange')->never();
        $this->app->instance(OgTicketExternalServiceInterface::class, $mock);

        $service = $this->app->make(TicketServiceInterface::class);
        $result = $service->autoReleaseStaleTickets();

        $this->assertEquals(1, $result['checked']);
        $this->assertEquals(1, $result['released']);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::Pending->value, $ticket->status->value);
        $this->assertNull($ticket->claimed_by_org_id);
        $this->assertNull($ticket->claimed_at);
    }

    public function test_service_skips_recently_claimed_tickets(): void
    {
        Ticket::factory()->create([
            'status' => TicketStatus::Received,
            'claimed_by_org_id' => 'test-org',
            'claimed_at' => now()->subSeconds(Ticket::STALE_TIMEOUT_MINUTES * 30),
        ]);

        $mock = Mockery::mock(OgTicketExternalServiceInterface::class);
        $mock->shouldNotReceive('autoReleaseOgTicket');
        $this->app->instance(OgTicketExternalServiceInterface::class, $mock);

        $service = $this->app->make(TicketServiceInterface::class);
        $result = $service->autoReleaseStaleTickets();

        $this->assertEquals(0, $result['checked']);
        $this->assertEquals(0, $result['released']);
    }

    public function test_service_skips_completed_tickets(): void
    {
        Ticket::factory()->create([
            'status' => TicketStatus::Completed,
            'claimed_by_org_id' => 'test-org',
            'claimed_at' => now()->subMinutes(Ticket::STALE_TIMEOUT_MINUTES + 10),
        ]);

        $mock = Mockery::mock(OgTicketExternalServiceInterface::class);
        $mock->shouldNotReceive('autoReleaseOgTicket');
        $this->app->instance(OgTicketExternalServiceInterface::class, $mock);

        $service = $this->app->make(TicketServiceInterface::class);
        $result = $service->autoReleaseStaleTickets();

        $this->assertEquals(0, $result['checked']);
    }

    public function test_service_skips_cancelled_tickets(): void
    {
        Ticket::factory()->create([
            'status' => TicketStatus::Cancelled,
            'claimed_by_org_id' => 'test-org',
            'claimed_at' => now()->subMinutes(Ticket::STALE_TIMEOUT_MINUTES + 10),
        ]);

        $mock = Mockery::mock(OgTicketExternalServiceInterface::class);
        $mock->shouldNotReceive('autoReleaseOgTicket');
        $this->app->instance(OgTicketExternalServiceInterface::class, $mock);

        $service = $this->app->make(TicketServiceInterface::class);
        $result = $service->autoReleaseStaleTickets();

        $this->assertEquals(0, $result['checked']);
    }

    public function test_service_skips_pre_assigned_tickets(): void
    {
        Ticket::factory()->create([
            'status' => TicketStatus::Received,
            'claimed_by_org_id' => 'test-org',
            'claimed_at' => now()->subMinutes(Ticket::STALE_TIMEOUT_MINUTES + 10),
            'is_from_pool' => false,
        ]);

        $mock = Mockery::mock(OgTicketExternalServiceInterface::class);
        $mock->shouldNotReceive('autoReleaseOgTicket');
        $this->app->instance(OgTicketExternalServiceInterface::class, $mock);

        $service = $this->app->make(TicketServiceInterface::class);
        $result = $service->autoReleaseStaleTickets();

        $this->assertEquals(0, $result['checked']);
    }

    public function test_service_skips_in_progress_tickets(): void
    {
        Ticket::factory()->create([
            'status' => TicketStatus::InProgress,
            'claimed_by_org_id' => 'test-org',
            'claimed_at' => now()->subMinutes(Ticket::STALE_TIMEOUT_MINUTES + 10),
        ]);

        $mock = Mockery::mock(OgTicketExternalServiceInterface::class);
        $mock->shouldNotReceive('autoReleaseOgTicket');
        $this->app->instance(OgTicketExternalServiceInterface::class, $mock);

        $service = $this->app->make(TicketServiceInterface::class);
        $result = $service->autoReleaseStaleTickets();

        $this->assertEquals(0, $result['checked']);
    }

    public function test_service_does_not_reset_ticket_when_external_returns_false(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Received,
            'claimed_by_org_id' => 'test-org',
            'claimed_at' => now()->subMinutes(Ticket::STALE_TIMEOUT_MINUTES + 10),
        ]);

        $mock = Mockery::mock(OgTicketExternalServiceInterface::class);
        $mock->shouldReceive('autoReleaseOgTicket')
            ->once()
            ->andReturn(false);
        $mock->shouldReceive('createFromTicket')->never();
        $mock->shouldReceive('getProcessingInfoByTicketId')->never();
        $mock->shouldReceive('hasRecentStatusChange')->never();
        $this->app->instance(OgTicketExternalServiceInterface::class, $mock);

        $service = $this->app->make(TicketServiceInterface::class);
        $result = $service->autoReleaseStaleTickets();

        $this->assertEquals(1, $result['checked']);
        $this->assertEquals(0, $result['released']);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::Received->value, $ticket->status->value);
        $this->assertEquals('test-org', $ticket->claimed_by_org_id);
    }

    public function test_service_handles_multiple_tickets(): void
    {
        $stale1 = Ticket::factory()->create([
            'status' => TicketStatus::Received,
            'claimed_by_org_id' => 'org-a',
            'claimed_at' => now()->subMinutes(Ticket::STALE_TIMEOUT_MINUTES + 10),
        ]);
        $stale2 = Ticket::factory()->create([
            'status' => TicketStatus::Received,
            'claimed_by_org_id' => 'org-b',
            'claimed_at' => now()->subMinutes(Ticket::STALE_TIMEOUT_MINUTES + 20),
        ]);
        // In progress — should not be checked
        Ticket::factory()->create([
            'status' => TicketStatus::InProgress,
            'claimed_by_org_id' => 'org-x',
            'claimed_at' => now()->subMinutes(Ticket::STALE_TIMEOUT_MINUTES + 30),
        ]);
        // Recent — should not be checked
        Ticket::factory()->create([
            'status' => TicketStatus::Received,
            'claimed_by_org_id' => 'org-c',
            'claimed_at' => now()->subSeconds(Ticket::STALE_TIMEOUT_MINUTES * 30),
        ]);

        $mock = Mockery::mock(OgTicketExternalServiceInterface::class);
        $mock->shouldReceive('autoReleaseOgTicket')
            ->with($stale1->id, 'org-a', Ticket::STALE_TIMEOUT_MINUTES)
            ->once()
            ->andReturn(true);
        $mock->shouldReceive('autoReleaseOgTicket')
            ->with($stale2->id, 'org-b', Ticket::STALE_TIMEOUT_MINUTES)
            ->once()
            ->andReturn(false);
        $mock->shouldReceive('createFromTicket')->never();
        $mock->shouldReceive('getProcessingInfoByTicketId')->never();
        $mock->shouldReceive('hasRecentStatusChange')->never();
        $this->app->instance(OgTicketExternalServiceInterface::class, $mock);

        $service = $this->app->make(TicketServiceInterface::class);
        $result = $service->autoReleaseStaleTickets();

        $this->assertEquals(2, $result['checked']);
        $this->assertEquals(1, $result['released']);

        $stale1->refresh();
        $this->assertEquals(TicketStatus::Pending->value, $stale1->status->value);
        $this->assertNull($stale1->claimed_by_org_id);

        $stale2->refresh();
        $this->assertEquals(TicketStatus::Received->value, $stale2->status->value);
        $this->assertEquals('org-b', $stale2->claimed_by_org_id);
    }
}
