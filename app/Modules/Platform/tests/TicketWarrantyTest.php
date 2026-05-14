<?php

namespace Tests\Modules\Platform;

use App\Modules\Platform\Tenant\Models\Organization;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Models\OgTicketWarrantyRequest;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketWarrantyTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/tickets';

    private function setUpCompletedTicket(?Organization $org = null, ?\Carbon\Carbon $completedAt = null): Ticket
    {
        $org ??= Organization::first() ?? Organization::withoutEvents(fn () => Organization::factory()->create());

        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Completed,
            'claimed_by_org_id' => $org->id,
            'is_from_pool' => false,
        ]);

        $org->run(function () use ($ticket, $completedAt): void {
            $ogTicket = OgTicket::factory()->create([
                'ticket_id' => $ticket->id,
                'status' => OgTicketStatus::Completed,
            ]);

            $quote = Quote::factory()->approved()->create([
                'og_ticket_id' => $ogTicket->id,
                'is_active' => true,
            ]);

            Order::factory()->completed()->create([
                'quote_id' => $quote->id,
                'completed_at' => $completedAt ?? now(),
            ]);
        });

        return $ticket;
    }

    public function test_submit_warranty_request_success(): void
    {
        Storage::fake('public');

        $org = Organization::first() ?? Organization::withoutEvents(fn () => Organization::factory()->create());
        $ticket = $this->setUpCompletedTicket($org);

        $response = $this->postJson("{$this->baseUrl}/{$ticket->code}/warranty", [
            'subject' => 'Mái bị thấm nước',
            'description' => 'Sau cơn mưa lớn, trần nhà bị thấm và nhỏ nước ở vị trí bếp.',
            'attachments' => [UploadedFile::fake()->image('leak.jpg')],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $org->run(function () use ($ticket): void {
            $ogTicket = OgTicket::query()->where('ticket_id', $ticket->id)->first();
            $this->assertNotNull($ogTicket);

            $warranties = OgTicketWarrantyRequest::query()->where('og_ticket_id', $ogTicket->id)->get();
            $this->assertCount(1, $warranties);
            $this->assertSame('Mái bị thấm nước', $warranties->first()->subject);
            $this->assertSame($ogTicket->requester_name, $warranties->first()->requester_name);
            $this->assertCount(1, $warranties->first()->attachments);
        });
    }

    public function test_submit_warranty_request_rejected_when_order_not_completed(): void
    {
        $org = Organization::first() ?? Organization::withoutEvents(fn () => Organization::factory()->create());

        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::InProgress,
            'claimed_by_org_id' => $org->id,
            'is_from_pool' => false,
        ]);

        $org->run(function () use ($ticket): void {
            $ogTicket = OgTicket::factory()->create([
                'ticket_id' => $ticket->id,
                'status' => OgTicketStatus::InProgress,
            ]);

            $quote = Quote::factory()->approved()->create([
                'og_ticket_id' => $ogTicket->id,
                'is_active' => true,
            ]);

            Order::factory()->create([
                'quote_id' => $quote->id,
                'status' => OrderStatus::InProgress,
            ]);
        });

        $response = $this->postJson("{$this->baseUrl}/{$ticket->code}/warranty", [
            'subject' => 'Không được',
            'description' => 'Chưa hoàn thành mà xin bảo hành.',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ORDER_NOT_COMPLETED');
    }

    public function test_submit_warranty_request_rejected_when_beyond_warranty_period(): void
    {
        $org = Organization::first() ?? Organization::withoutEvents(fn () => Organization::factory()->create());
        $ticket = $this->setUpCompletedTicket($org, now()->subMonths(13));

        $response = $this->postJson("{$this->baseUrl}/{$ticket->code}/warranty", [
            'subject' => 'Quá hạn bảo hành',
            'description' => 'Đã hơn 12 tháng rồi.',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WARRANTY_EXPIRED');
    }

    public function test_can_submit_multiple_warranty_requests(): void
    {
        $org = Organization::first() ?? Organization::withoutEvents(fn () => Organization::factory()->create());
        $ticket = $this->setUpCompletedTicket($org);

        $this->postJson("{$this->baseUrl}/{$ticket->code}/warranty", [
            'subject' => 'Yêu cầu 1',
            'description' => 'Lần đầu.',
        ])->assertOk();

        $this->postJson("{$this->baseUrl}/{$ticket->code}/warranty", [
            'subject' => 'Yêu cầu 2',
            'description' => 'Lần hai.',
        ])->assertOk();

        $org->run(function () use ($ticket): void {
            $ogTicket = OgTicket::query()->where('ticket_id', $ticket->id)->first();
            $this->assertEquals(2, OgTicketWarrantyRequest::query()->where('og_ticket_id', $ogTicket->id)->count());
        });
    }

    public function test_rating_endpoint_includes_warranty_requests_and_flag(): void
    {
        $org = Organization::first() ?? Organization::withoutEvents(fn () => Organization::factory()->create());
        $ticket = $this->setUpCompletedTicket($org);

        $this->postJson("{$this->baseUrl}/{$ticket->code}/warranty", [
            'subject' => 'Test subject',
            'description' => 'Test description content.',
        ])->assertOk();

        $response = $this->getJson("{$this->baseUrl}/{$ticket->code}/rating");

        $response->assertOk()
            ->assertJsonPath('data.can_request_warranty', true)
            ->assertJsonCount(1, 'data.warranty_requests')
            ->assertJsonPath('data.warranty_requests.0.subject', 'Test subject');
    }

    public function test_submit_warranty_requires_subject_and_description(): void
    {
        $org = Organization::first() ?? Organization::withoutEvents(fn () => Organization::factory()->create());
        $ticket = $this->setUpCompletedTicket($org);

        $this->postJson("{$this->baseUrl}/{$ticket->code}/warranty", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'description']);
    }
}
