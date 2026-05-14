<?php

namespace Tests\Modules\Platform;

use App\Modules\Platform\Tenant\Models\Organization;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketRatingTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/tickets';

    // ==================== GET RATING INFO ====================

    public function test_get_rating_info_completed_not_rated(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
        ]);

        $response = $this->getJson("{$this->baseUrl}/{$ticket->code}/rating");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', $ticket->code)
            ->assertJsonPath('data.subject', $ticket->subject)
            ->assertJsonPath('data.is_ratable', true)
            ->assertJsonPath('data.rating', null);
    }

    public function test_get_rating_info_not_found(): void
    {
        $response = $this->getJson("{$this->baseUrl}/TK-9999-999/rating");

        $response->assertStatus(404);
    }

    public function test_get_rating_info_not_completed(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::InProgress,
        ]);

        $response = $this->getJson("{$this->baseUrl}/{$ticket->code}/rating");

        $response->assertOk()
            ->assertJsonPath('data.is_ratable', false)
            ->assertJsonPath('data.rating', null);
    }

    public function test_get_rating_info_already_rated(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
            'resident_rating' => 4,
            'resident_rating_comment' => 'Tốt lắm',
            'resident_rated_at' => now(),
        ]);

        $response = $this->getJson("{$this->baseUrl}/{$ticket->code}/rating");

        $response->assertOk()
            ->assertJsonPath('data.is_ratable', false)
            ->assertJsonPath('data.rating.rating', 4)
            ->assertJsonPath('data.rating.comment', 'Tốt lắm');
    }

    // ==================== SUBMIT RATING ====================

    public function test_submit_rating_success(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 5,
            'resident_rating_comment' => 'Rất hài lòng!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Cảm ơn bạn đã đánh giá!');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'resident_rating' => 5,
            'resident_rating_comment' => 'Rất hài lòng!',
        ]);
    }

    public function test_submit_rating_without_comment(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 3,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'resident_rating' => 3,
            'resident_rating_comment' => null,
        ]);
    }

    public function test_submit_rating_syncs_to_og_ticket(): void
    {
        $org = Organization::first();

        if (! $org) {
            $this->markTestSkipped('No tenant available for sync test.');
        }

        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
            'claimed_by_org_id' => $org->id,
            'is_from_pool' => false,
        ]);

        $org->run(function () use ($ticket): void {
            OgTicket::factory()->create([
                'ticket_id' => $ticket->id,
                'status' => OgTicketStatus::Completed,
            ]);
        });

        $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 4,
            'resident_rating_comment' => 'Phản hồi nhanh',
        ])->assertOk();

        $org->run(function () use ($ticket): void {
            $ogTicket = OgTicket::where('ticket_id', $ticket->id)->first();
            $this->assertEquals(4, $ogTicket->resident_rating);
            $this->assertEquals('Phản hồi nhanh', $ogTicket->resident_rating_comment);
            $this->assertNotNull($ogTicket->resident_rated_at);
        });
    }

    public function test_submit_rating_not_completed(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::InProgress,
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_submit_rating_already_rated(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
            'resident_rating' => 3,
            'resident_rated_at' => now(),
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_submit_rating_ticket_not_found(): void
    {
        $response = $this->postJson("{$this->baseUrl}/TK-9999-999/rating", [
            'resident_rating' => 5,
        ]);

        $response->assertStatus(404);
    }

    public function test_submit_rating_validation_invalid_rating(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
        ]);

        $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 0,
        ])->assertStatus(422);

        $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 6,
        ])->assertStatus(422);

        $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 'abc',
        ])->assertStatus(422);

        $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [])->assertStatus(422);
    }

    public function test_submit_rating_validation_comment_too_long(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 4,
            'resident_rating_comment' => str_repeat('a', 1001),
        ]);

        $response->assertStatus(422);
    }

    public function test_submit_rating_no_org_skips_sync(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
            'claimed_by_org_id' => null,
        ]);

        $response = $this->postJson("{$this->baseUrl}/{$ticket->code}/rating", [
            'resident_rating' => 5,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'resident_rating' => 5,
        ]);
    }
}
