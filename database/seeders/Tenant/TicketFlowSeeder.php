<?php

namespace Database\Seeders\Tenant;

use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Catalog\Enums\CatalogItemType;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\OgTicket\Contracts\OgTicketServiceInterface;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicketCategory\Models\OgTicketCategory;
use App\Modules\PMC\Order\Contracts\OrderServiceInterface;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Contracts\QuoteServiceInterface;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TicketFlowSeeder extends Seeder
{
    public function __construct(
        private OgTicketServiceInterface $ogTicketService,
        private QuoteServiceInterface $quoteService,
        private OrderServiceInterface $orderService,
    ) {}

    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $account = Account::query()->first();
        $project = Project::query()->first();
        $materials = CatalogItem::query()->where('type', CatalogItemType::Material->value)->limit(2)->get();
        $services = CatalogItem::query()->where('type', CatalogItemType::Service->value)->limit(2)->get();

        if (! $account || ! $project || $materials->isEmpty() || $services->isEmpty()) {
            $this->command?->warn('Cần Account, Project, CatalogItem (material + service) trước khi seed TicketFlow.');

            return;
        }

        $catalogItems = $materials->merge($services);

        Auth::guard('web')->setUser($account);

        $categoryIdByCode = OgTicketCategory::query()->pluck('id', 'code');

        try {
            $this->runPoolClaimScenario(
                account: $account,
                project: $project,
                catalogItems: $catalogItems,
                categoryIds: [$categoryIdByCode['ELEC'], $categoryIdByCode['HVAC']],
            );
            $this->runFormSubmitScenario(
                account: $account,
                project: $project,
                catalogItems: $catalogItems,
                ticketCode: 'TK-FORM-001',
                requesterName: 'Hoàng Thị Lan',
                requesterPhone: '0967891234',
                subject: 'Điều hoà phòng ngủ chạy không mát',
                description: 'Điều hoà phòng ngủ chính chạy liên tục nhưng không hạ được nhiệt độ. Cư dân đã vệ sinh lọc gió nhưng vẫn không cải thiện.',
                address: 'A-0705, 123 Đường Nguyễn Văn Linh, Phường Tân Phong, Quận 7, TP.HCM',
                finalOrderStatus: OrderStatus::InProgress,
                categoryIds: [$categoryIdByCode['HVAC']],
            );
            $this->runFormSubmitScenario(
                account: $account,
                project: $project,
                catalogItems: $catalogItems,
                ticketCode: 'TK-FORM-002',
                requesterName: 'Bùi Minh Tuấn',
                requesterPhone: '0953334455',
                subject: 'Khoá cửa chính bị kẹt',
                description: 'Ổ khoá cửa chính bị kẹt, không mở được bằng chìa khoá. Cư dân phải dùng cửa phụ tạm thời.',
                address: 'B-1105, 456 Đường Phạm Hùng, Phường Bình Thuận, Quận 7, TP.HCM',
                finalOrderStatus: OrderStatus::Completed,
                categoryIds: [$categoryIdByCode['FURN']],
            );
        } finally {
            Auth::guard('web')->logout();
        }
    }

    /**
     * Scenario A: PMC claim 1 ticket từ pool → full business flow → Order Completed.
     *
     * @param  array<int, int>  $categoryIds
     */
    private function runPoolClaimScenario(
        Account $account,
        Project $project,
        Collection $catalogItems,
        array $categoryIds,
    ): void {
        $ticket = Ticket::query()
            ->where('status', TicketStatus::Pending)
            ->whereNull('claimed_by_org_id')
            ->where('is_from_pool', true)
            ->orderBy('id')
            ->first();

        if (! $ticket) {
            return;
        }

        $ogTicket = $this->ogTicketService->claim(['ticket_id' => $ticket->id]);
        $ogTicket->categories()->sync($categoryIds);
        $this->runBusinessFlow($ogTicket, $account, $catalogItems, OrderStatus::Completed);
    }

    /**
     * Scenario B/C: Giả lập cư dân submit form có org → tạo Ticket + OgTicket qua claim flow.
     *
     * @param  array<int, int>  $categoryIds
     */
    private function runFormSubmitScenario(
        Account $account,
        Project $project,
        Collection $catalogItems,
        string $ticketCode,
        string $requesterName,
        string $requesterPhone,
        string $subject,
        string $description,
        string $address,
        OrderStatus $finalOrderStatus,
        array $categoryIds,
    ): void {
        $ticket = Ticket::firstOrCreate(
            ['code' => $ticketCode],
            [
                'requester_name' => $requesterName,
                'requester_phone' => $requesterPhone,
                'subject' => $subject,
                'description' => $description,
                'address' => $address,
                'project_id' => $project->id,
                'status' => TicketStatus::Pending,
                'channel' => TicketChannel::App,
                'is_from_pool' => false,
            ],
        );

        $ogTicket = $this->ogTicketService->claim(['ticket_id' => $ticket->id]);
        $ogTicket->categories()->sync($categoryIds);
        $this->runBusinessFlow($ogTicket, $account, $catalogItems, $finalOrderStatus);
    }

    /**
     * Drive an OgTicket through the full happy path: assign → survey → quote
     * (draft → sent → manager_approved → approved) → order (draft → confirmed
     * → in_progress → optional completed).
     */
    private function runBusinessFlow(
        OgTicket $ogTicket,
        Account $account,
        Collection $catalogItems,
        OrderStatus $finalOrderStatus,
    ): void {
        $this->ogTicketService->update($ogTicket->id, [
            'assigned_to_ids' => [$account->id],
            'priority' => OgTicketPriority::Normal->value,
        ]);

        $this->ogTicketService->manualTransition($ogTicket->id, [
            'target_status' => OgTicketStatus::Surveying->value,
        ]);

        $lines = $this->buildQuoteLines($catalogItems);
        $quote = $this->quoteService->create([
            'og_ticket_id' => $ogTicket->id,
            'status' => QuoteStatus::Draft->value,
            'lines' => $lines,
        ]);

        $this->quoteService->transition($quote->id, ['status' => QuoteStatus::Sent->value]);
        $this->quoteService->transition($quote->id, ['status' => QuoteStatus::ManagerApproved->value]);
        $this->quoteService->transition($quote->id, ['status' => QuoteStatus::Approved->value]);

        $order = $this->orderService->create(['quote_id' => $quote->id]);

        $this->orderService->transition($order->id, ['status' => OrderStatus::Confirmed->value]);
        $this->orderService->transition($order->id, ['status' => OrderStatus::InProgress->value]);

        if (\in_array($finalOrderStatus, [OrderStatus::Accepted, OrderStatus::Completed], true)) {
            $this->orderService->transition($order->id, ['status' => OrderStatus::Accepted->value]);
        }

        if ($finalOrderStatus === OrderStatus::Completed) {
            $this->orderService->transition($order->id, ['status' => OrderStatus::Completed->value]);
        }
    }

    /**
     * Mỗi báo giá phải có ít nhất 1 vật tư + 1 dịch vụ để tính được hoa hồng.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildQuoteLines(Collection $catalogItems): array
    {
        $materials = $catalogItems->where('type', CatalogItemType::Material)->values();
        $services = $catalogItems->where('type', CatalogItemType::Service)->values();

        $lines = [];
        $qtyCycle = [2, 1, 3, 1];
        $index = 0;

        foreach ($materials->take(2) as $item) {
            $lines[] = $this->buildLine($item, $qtyCycle[$index % \count($qtyCycle)]);
            $index++;
        }

        foreach ($services->take(2) as $item) {
            $lines[] = $this->buildLine($item, $qtyCycle[$index % \count($qtyCycle)]);
            $index++;
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLine(CatalogItem $item, int $quantity): array
    {
        return [
            'line_type' => $item->type->value,
            'reference_id' => $item->id,
            'name' => $item->name,
            'quantity' => $quantity,
            'unit_price' => (float) $item->unit_price,
            'purchase_price' => (float) ($item->purchase_price ?? $item->unit_price * 0.7),
        ];
    }
}
