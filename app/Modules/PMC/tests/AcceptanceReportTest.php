<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\AcceptanceReport\Models\AcceptanceReport;
use App\Modules\PMC\AcceptanceReport\Services\AcceptanceReportService;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AcceptanceReportTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = '/api/v1/pmc/orders';

    private string $publicBaseUrl = '/api/v1/public/acceptance-reports';

    protected function setUp(): void
    {
        parent::setUp();

        app(SystemSettingServiceInterface::class)->updateGroup(
            AcceptanceReportService::SETTING_GROUP,
            [AcceptanceReportService::SETTING_KEY_TEMPLATE => '<p>Biên bản nghiệm thu cho đơn {{order_code}}.</p>'],
        );
    }

    private function createOrder(OrderStatus $status = OrderStatus::Accepted): Order
    {
        $ogTicket = OgTicket::factory()->create();
        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ogTicket->id,
            'is_active' => true,
        ]);

        /** @var Order $order */
        $order = Order::factory()->create([
            'quote_id' => $quote->id,
            'status' => $status,
            'completed_at' => $status === OrderStatus::Completed ? now() : null,
        ]);

        return $order;
    }

    public function test_admin_show_creates_report_from_template(): void
    {
        $this->actingAsAdmin();
        $order = $this->createOrder();

        $response = $this->getJson($this->adminBaseUrl."/{$order->id}/acceptance-report");

        $response->assertSuccessful()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.is_confirmed', false)
            ->assertJsonPath('data.has_signed_file', false);

        $this->assertDatabaseHas('acceptance_reports', ['order_id' => $order->id]);
    }

    public function test_public_confirm_sets_signature_when_order_accepted(): void
    {
        $this->actingAsAdmin();
        $order = $this->createOrder(OrderStatus::Accepted);
        $this->getJson($this->adminBaseUrl."/{$order->id}/acceptance-report");

        /** @var AcceptanceReport $report */
        $report = AcceptanceReport::query()->where('order_id', $order->id)->firstOrFail();

        $response = $this->postJson($this->publicBaseUrl."/{$report->share_token}/confirm", [
            'signature_name' => 'Nguyễn Văn A',
            'note' => 'Đã kiểm tra đủ hạng mục',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_confirmed', true)
            ->assertJsonPath('data.confirmed_signature_name', 'Nguyễn Văn A');

        $this->assertNotNull($report->fresh()->confirmed_at);
    }

    public function test_public_confirm_rejects_when_order_still_in_progress(): void
    {
        $this->actingAsAdmin();
        $order = $this->createOrder(OrderStatus::InProgress);
        $this->getJson($this->adminBaseUrl."/{$order->id}/acceptance-report");

        /** @var AcceptanceReport $report */
        $report = AcceptanceReport::query()->where('order_id', $order->id)->firstOrFail();

        $response = $this->postJson($this->publicBaseUrl."/{$report->share_token}/confirm", [
            'signature_name' => 'Nguyễn Văn A',
        ]);

        $response->assertUnprocessable();
        $this->assertNull($report->fresh()->confirmed_at);
    }

    public function test_public_confirm_rejects_signature_name_too_short(): void
    {
        $this->actingAsAdmin();
        $order = $this->createOrder();
        $this->getJson($this->adminBaseUrl."/{$order->id}/acceptance-report");

        /** @var AcceptanceReport $report */
        $report = AcceptanceReport::query()->where('order_id', $order->id)->firstOrFail();

        $response = $this->postJson($this->publicBaseUrl."/{$report->share_token}/confirm", [
            'signature_name' => 'A',
        ]);

        $response->assertUnprocessable();
    }

    public function test_public_confirm_rejects_when_already_confirmed(): void
    {
        $this->actingAsAdmin();
        $order = $this->createOrder();
        $this->getJson($this->adminBaseUrl."/{$order->id}/acceptance-report");

        /** @var AcceptanceReport $report */
        $report = AcceptanceReport::query()->where('order_id', $order->id)->firstOrFail();
        $report->update(['confirmed_at' => now(), 'confirmed_signature_name' => 'A B']);

        $response = $this->postJson($this->publicBaseUrl."/{$report->share_token}/confirm", [
            'signature_name' => 'Nguyễn Văn A',
        ]);

        $response->assertUnprocessable();
    }

    public function test_admin_uploads_signed_pdf_file(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $order = $this->createOrder();

        $file = UploadedFile::fake()->create('signed.pdf', 500, 'application/pdf');

        $response = $this->postJson(
            $this->adminBaseUrl."/{$order->id}/acceptance-report/signed",
            ['file' => $file],
        );

        $response->assertOk()
            ->assertJsonPath('data.has_signed_file', true)
            ->assertJsonPath('data.signed_file_original_name', 'signed.pdf');

        $report = AcceptanceReport::query()->where('order_id', $order->id)->firstOrFail();
        Storage::assertExists($report->signed_file_path);
    }

    public function test_admin_upload_rejects_invalid_mime(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $order = $this->createOrder();

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $response = $this->postJson(
            $this->adminBaseUrl."/{$order->id}/acceptance-report/signed",
            ['file' => $file],
        );

        $response->assertUnprocessable();
    }

    public function test_admin_upload_rejects_file_over_20mb(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $order = $this->createOrder();

        $file = UploadedFile::fake()->create('big.pdf', 21 * 1024, 'application/pdf');

        $response = $this->postJson(
            $this->adminBaseUrl."/{$order->id}/acceptance-report/signed",
            ['file' => $file],
        );

        $response->assertUnprocessable();
    }

    public function test_admin_upload_rejects_when_order_not_accepted(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $order = $this->createOrder(OrderStatus::Draft);

        $file = UploadedFile::fake()->create('signed.pdf', 500, 'application/pdf');

        $response = $this->postJson(
            $this->adminBaseUrl."/{$order->id}/acceptance-report/signed",
            ['file' => $file],
        );

        $response->assertUnprocessable();
    }

    public function test_admin_deletes_signed_file(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $order = $this->createOrder();

        $this->postJson(
            $this->adminBaseUrl."/{$order->id}/acceptance-report/signed",
            ['file' => UploadedFile::fake()->create('signed.pdf', 200, 'application/pdf')],
        )->assertOk();

        $report = AcceptanceReport::query()->where('order_id', $order->id)->firstOrFail();
        $storedPath = $report->signed_file_path;

        $response = $this->deleteJson($this->adminBaseUrl."/{$order->id}/acceptance-report/signed");

        $response->assertOk()->assertJsonPath('data.has_signed_file', false);

        Storage::assertMissing($storedPath);
        $this->assertNull($report->fresh()->signed_file_path);
    }
}
