<?php

namespace App\Modules\PMC\AcceptanceReport\Services;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\AcceptanceReport\Contracts\AcceptanceReportServiceInterface;
use App\Modules\PMC\AcceptanceReport\Models\AcceptanceReport;
use App\Modules\PMC\AcceptanceReport\Repositories\AcceptanceReportRepository;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Repositories\OrderRepository;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AcceptanceReportService extends BaseService implements AcceptanceReportServiceInterface
{
    public const SETTING_GROUP = 'acceptance_report';

    public const SETTING_KEY_TEMPLATE = 'template_html';

    public const SETTING_KEY_TITLE = 'template_title';

    public const SIGNED_FILE_DIRECTORY = 'acceptance-reports/signed';

    public const SIGNED_FILE_MAX_BYTES = 20 * 1024 * 1024;

    /** @var list<string> */
    public const SIGNED_FILE_ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    public function __construct(
        private AcceptanceReportRepository $repository,
        private OrderRepository $orderRepository,
        private SystemSettingServiceInterface $settingService,
        private StorageServiceInterface $storageService,
    ) {}

    public function getOrCreateForOrder(int $orderId): AcceptanceReport
    {
        $existing = $this->repository->findByOrderId($orderId);

        if ($existing) {
            return $existing;
        }

        return $this->executeInTransaction(function () use ($orderId): AcceptanceReport {
            $rendered = $this->renderTemplate($orderId, null);

            /** @var AcceptanceReport */
            return $this->repository->create([
                'order_id' => $orderId,
                'content_html' => $rendered,
                'share_token' => Str::random(40),
                'created_by_account_id' => auth()->id(),
            ]);
        });
    }

    public function update(int $id, array $data): AcceptanceReport
    {
        /** @var AcceptanceReport|null $report */
        $report = $this->repository->findById($id);

        if (! $report) {
            throw new BusinessException(
                message: 'Không tìm thấy biên bản.',
                errorCode: 'ACCEPTANCE_REPORT_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->applyUpdate($report, $data);
    }

    public function updateByToken(string $token, array $data): AcceptanceReport
    {
        $report = $this->repository->findByToken($token);

        if (! $report) {
            throw new BusinessException(
                message: 'Không tìm thấy biên bản.',
                errorCode: 'ACCEPTANCE_REPORT_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->applyUpdate($report, $data);
    }

    public function findByOrderId(int $orderId): ?AcceptanceReport
    {
        return $this->repository->findByOrderId($orderId);
    }

    public function findByToken(string $token): ?AcceptanceReport
    {
        return $this->repository->findByToken($token);
    }

    public function delete(int $id): void
    {
        $this->repository->delete($id);
    }

    public function confirmByToken(string $token, array $data): AcceptanceReport
    {
        $report = $this->repository->findByToken($token);

        if (! $report) {
            throw new BusinessException(
                message: 'Không tìm thấy biên bản.',
                errorCode: 'ACCEPTANCE_REPORT_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $this->assertOrderAllowsAcceptance($report->order_id);

        if ($report->confirmed_at !== null) {
            throw new BusinessException(
                message: 'Biên bản đã được xác nhận trước đó.',
                errorCode: 'ACCEPTANCE_REPORT_ALREADY_CONFIRMED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->repository->update($report->id, [
            'confirmed_at' => now(),
            'confirmed_signature_name' => trim((string) $data['signature_name']),
            'confirmed_note' => isset($data['note']) ? (string) $data['note'] : null,
        ]);

        /** @var AcceptanceReport */
        return $this->repository->findById($report->id);
    }

    public function uploadSignedFile(int $orderId, UploadedFile $file): AcceptanceReport
    {
        $report = $this->getOrCreateForOrder($orderId);

        $this->assertOrderAllowsAcceptance($orderId);

        return $this->executeInTransaction(function () use ($report, $file): AcceptanceReport {
            if ($report->signed_file_path) {
                $this->storageService->delete($report->signed_file_path);
            }

            $path = $this->storageService->upload($file, self::SIGNED_FILE_DIRECTORY);

            $this->repository->update($report->id, [
                'signed_file_path' => $path,
                'signed_file_original_name' => $file->getClientOriginalName(),
                'signed_file_mime' => $file->getClientMimeType(),
                'signed_file_size' => $file->getSize(),
                'signed_uploaded_at' => now(),
                'signed_uploaded_by_account_id' => auth()->id(),
            ]);

            /** @var AcceptanceReport */
            return $this->repository->findById($report->id);
        });
    }

    public function deleteSignedFile(int $orderId): AcceptanceReport
    {
        $report = $this->repository->findByOrderId($orderId);

        if (! $report) {
            throw new BusinessException(
                message: 'Không tìm thấy biên bản.',
                errorCode: 'ACCEPTANCE_REPORT_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        if ($report->signed_file_path) {
            $this->storageService->delete($report->signed_file_path);
        }

        $this->repository->update($report->id, [
            'signed_file_path' => null,
            'signed_file_original_name' => null,
            'signed_file_mime' => null,
            'signed_file_size' => null,
            'signed_uploaded_at' => null,
            'signed_uploaded_by_account_id' => null,
        ]);

        /** @var AcceptanceReport */
        return $this->repository->findById($report->id);
    }

    /**
     * Verify the order is in a status that allows acceptance actions
     * (confirm / upload signed file): accepted or completed.
     */
    private function assertOrderAllowsAcceptance(int $orderId): void
    {
        /** @var Order|null $order */
        $order = $this->orderRepository->findById($orderId);

        if (! $order) {
            throw new BusinessException(
                message: 'Không tìm thấy đơn hàng.',
                errorCode: 'ORDER_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        if (! in_array($order->status, [OrderStatus::Accepted, OrderStatus::Completed], true)) {
            throw new BusinessException(
                message: 'Đơn hàng chưa ở trạng thái nghiệm thu hoặc hoàn thành.',
                errorCode: 'ORDER_NOT_ACCEPTABLE',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    public function regenerate(int $id): AcceptanceReport
    {
        /** @var AcceptanceReport|null $report */
        $report = $this->repository->findById($id);

        if (! $report) {
            throw new BusinessException(
                message: 'Không tìm thấy biên bản.',
                errorCode: 'ACCEPTANCE_REPORT_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $this->repository->update($id, [
            'content_html' => $this->renderTemplate($report->order_id, $report),
        ]);

        /** @var AcceptanceReport */
        return $this->repository->findById($id);
    }

    public function renderTemplate(int $orderId, ?AcceptanceReport $report = null): string
    {
        $template = (string) $this->settingService->get(
            self::SETTING_GROUP,
            self::SETTING_KEY_TEMPLATE,
            '',
        );

        if ($template === '') {
            return '<p><em>Chưa có template biên bản nghiệm thu. Vui lòng cấu hình trong Cài đặt.</em></p>';
        }

        $context = $this->buildContext($orderId, $report);

        return strtr($template, $context);
    }

    /**
     * Apply an update payload to a report.
     * If party-A fields (customer_name/phone) change AND the user didn't hand-edit
     * content_html in the same request, re-render content_html so the printable
     * biên bản reflects the new party-A info.
     *
     * @param  array{content_html?: string, customer_name?: ?string, customer_phone?: ?string, note?: ?string}  $data
     */
    private function applyUpdate(AcceptanceReport $report, array $data): AcceptanceReport
    {
        $filtered = $this->filterUpdatableFields($data);

        $nameChanged = array_key_exists('customer_name', $filtered)
            && ($filtered['customer_name'] ?: null) !== ($report->customer_name ?: null);
        $phoneChanged = array_key_exists('customer_phone', $filtered)
            && ($filtered['customer_phone'] ?: null) !== ($report->customer_phone ?: null);
        $contentHandEdited = array_key_exists('content_html', $filtered)
            && (string) $filtered['content_html'] !== (string) $report->content_html;

        $this->repository->update($report->id, $filtered);

        if (($nameChanged || $phoneChanged) && ! $contentHandEdited) {
            /** @var AcceptanceReport $refreshed */
            $refreshed = $this->repository->findById($report->id);

            $this->repository->update($report->id, [
                'content_html' => $this->renderTemplate($refreshed->order_id, $refreshed),
            ]);
        }

        /** @var AcceptanceReport */
        return $this->repository->findById($report->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterUpdatableFields(array $data): array
    {
        return array_intersect_key(
            $data,
            array_flip(['content_html', 'customer_name', 'customer_phone', 'note']),
        );
    }

    /**
     * Build mustache-style token → value map for the order.
     * If a report is provided, its party-A fields (customer_name/phone) override
     * the fallback values pulled from the originating ogTicket.
     *
     * @return array<string, string>
     */
    private function buildContext(int $orderId, ?AcceptanceReport $report = null): array
    {
        /** @var Order|null $order */
        $order = $this->orderRepository->findById($orderId);

        if (! $order) {
            return [];
        }

        $order->load(['quote.ogTicket.project', 'lines']);

        $ogTicket = $order->quote?->ogTicket;
        $project = $ogTicket?->project;

        $customerName = $report?->customer_name ?: ($ogTicket->requester_name ?? '');
        $customerPhone = $report?->customer_phone ?: ($ogTicket->requester_phone ?? '');

        return [
            '{{order_code}}' => (string) ($order->code ?? ''),
            '{{order_total}}' => number_format((float) ($order->total_amount ?? 0), 0, ',', '.').' VNĐ',
            '{{order_date}}' => optional($order->created_at)->format('d/m/Y') ?? '',
            '{{today}}' => now()->format('d/m/Y'),
            '{{note}}' => (string) ($order->note ?? ''),
            '{{customer_name}}' => (string) $customerName,
            '{{customer_phone}}' => (string) $customerPhone,
            '{{customer_address}}' => (string) ($ogTicket->address ?? ''),
            '{{ticket_subject}}' => (string) ($ogTicket->subject ?? ''),
            '{{project_name}}' => (string) ($project->name ?? ''),
            '{{organization_name}}' => (string) (tenant()?->name ?? ''),
            '{{order_lines_table}}' => $this->renderLinesTable($order),
        ];
    }

    private function renderLinesTable(Order $order): string
    {
        $rows = '';
        $idx = 0;
        foreach ($order->lines as $line) {
            $idx++;
            $name = htmlspecialchars((string) $line->name, ENT_QUOTES);
            $qty = htmlspecialchars((string) $line->quantity, ENT_QUOTES);
            $unit = htmlspecialchars((string) $line->unit, ENT_QUOTES);
            $unitPrice = number_format((float) $line->unit_price, 0, ',', '.');
            $lineAmount = number_format((float) $line->line_amount, 0, ',', '.');
            $rows .= "<tr><td style=\"text-align:center\">{$idx}</td><td>{$name}</td><td style=\"text-align:right\">{$qty}</td><td style=\"text-align:center\">{$unit}</td><td style=\"text-align:right\">{$unitPrice}</td><td style=\"text-align:right\">{$lineAmount}</td></tr>";
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" style="text-align:center;font-style:italic">Không có dòng.</td></tr>';
        }

        return <<<HTML
<table style="width:100%;border-collapse:collapse" border="1" cellpadding="6">
  <thead>
    <tr>
      <th style="width:36px">STT</th>
      <th>Nội dung</th>
      <th style="width:80px">SL</th>
      <th style="width:60px">ĐVT</th>
      <th style="width:120px">Đơn giá</th>
      <th style="width:140px">Thành tiền</th>
    </tr>
  </thead>
  <tbody>{$rows}</tbody>
</table>
HTML;
    }
}
