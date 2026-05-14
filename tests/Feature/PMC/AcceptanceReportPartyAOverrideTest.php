<?php

namespace Tests\Feature\PMC;

use App\Modules\PMC\AcceptanceReport\Contracts\AcceptanceReportServiceInterface;
use App\Modules\PMC\AcceptanceReport\Models\AcceptanceReport;
use App\Modules\PMC\AcceptanceReport\Services\AcceptanceReportService;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Setting\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AcceptanceReportPartyAOverrideTest extends TestCase
{
    use RefreshDatabase;

    private AcceptanceReportServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();

        SystemSetting::query()->updateOrCreate(
            [
                'group' => AcceptanceReportService::SETTING_GROUP,
                'key' => AcceptanceReportService::SETTING_KEY_TEMPLATE,
            ],
            ['value' => 'Khách hàng: {{customer_name}} — {{customer_phone}}'],
        );

        $this->service = app(AcceptanceReportServiceInterface::class);
    }

    private function makeOrderWithTicket(string $requesterName, string $requesterPhone): Order
    {
        $ogTicket = OgTicket::factory()->create([
            'requester_name' => $requesterName,
            'requester_phone' => $requesterPhone,
        ]);
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);

        return Order::factory()->create(['quote_id' => $quote->id]);
    }

    #[Test]
    public function test_initial_render_falls_back_to_ogticket_when_report_has_no_overrides(): void
    {
        $order = $this->makeOrderWithTicket('Nguyễn Văn A', '0911000001');

        $report = $this->service->getOrCreateForOrder($order->id);

        $this->assertStringContainsString('Nguyễn Văn A', $report->content_html);
        $this->assertStringContainsString('0911000001', $report->content_html);
    }

    #[Test]
    public function test_update_rerenders_content_when_party_a_changes_and_content_not_edited(): void
    {
        $order = $this->makeOrderWithTicket('Nguyễn Văn A', '0911000001');
        $report = $this->service->getOrCreateForOrder($order->id);

        $updated = $this->service->update($report->id, [
            'content_html' => $report->content_html,
            'customer_name' => 'Trần Thị B',
            'customer_phone' => '0922000002',
            'note' => null,
        ]);

        $this->assertStringContainsString('Trần Thị B', $updated->content_html);
        $this->assertStringContainsString('0922000002', $updated->content_html);
        $this->assertStringNotContainsString('Nguyễn Văn A', $updated->content_html);
    }

    #[Test]
    public function test_update_preserves_hand_edited_content_even_when_party_a_changes(): void
    {
        $order = $this->makeOrderWithTicket('Nguyễn Văn A', '0911000001');
        $report = $this->service->getOrCreateForOrder($order->id);

        $customContent = '<p>Nội dung do người dùng tự soạn</p>';

        $updated = $this->service->update($report->id, [
            'content_html' => $customContent,
            'customer_name' => 'Trần Thị B',
            'customer_phone' => '0922000002',
            'note' => null,
        ]);

        $this->assertSame($customContent, $updated->content_html);
        $this->assertSame('Trần Thị B', $updated->customer_name);
        $this->assertSame('0922000002', $updated->customer_phone);
    }

    #[Test]
    public function test_regenerate_uses_report_overrides_over_ogticket_fallback(): void
    {
        $order = $this->makeOrderWithTicket('Nguyễn Văn A', '0911000001');
        $report = $this->service->getOrCreateForOrder($order->id);

        AcceptanceReport::query()->where('id', $report->id)->update([
            'customer_name' => 'Lê Văn C',
            'customer_phone' => '0933000003',
        ]);

        $regenerated = $this->service->regenerate($report->id);

        $this->assertStringContainsString('Lê Văn C', $regenerated->content_html);
        $this->assertStringContainsString('0933000003', $regenerated->content_html);
    }
}
