<?php

namespace App\Modules\PMC\Receivable\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Receivable\Contracts\ReceivableServiceInterface;
use App\Modules\PMC\Receivable\Enums\PaymentMethod;
use App\Modules\PMC\Receivable\Enums\PaymentReceiptType;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use App\Modules\PMC\Receivable\Models\Receivable;
use App\Modules\PMC\Receivable\Repositories\ReceivableRepository;
use App\Modules\PMC\Reconciliation\Contracts\ReconciliationServiceInterface;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class ReceivableService extends BaseService implements ReceivableServiceInterface
{
    public function __construct(
        protected ReceivableRepository $repository,
        protected ReconciliationServiceInterface $reconciliationService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Receivable
    {
        /** @var Receivable */
        return $this->repository->findById($id, ['*'], [
            'order:id,code,status,quote_id',
            'order.quote:id,og_ticket_id',
            'order.quote.ogTicket:id,subject,requester_name,requester_phone,apartment_name,customer_id',
            'order.quote.ogTicket.customer:id,code,full_name,phone,email',
            'project:id,name',
            'payments.collectedBy:id,name',
            'payments.reconciliation',
            'reconciliations',
        ]);
    }

    /**
     * @return array{kpi: array<string, mixed>, aging: list<array<string, mixed>>}
     */
    public function summary(?int $projectId = null): array
    {
        return $this->repository->getSummary($projectId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(int $receivableId, array $data): Receivable
    {
        return $this->executeInTransaction(function () use ($receivableId, $data): Receivable {
            $receivable = $this->findById($receivableId);

            if (! in_array($receivable->status, ReceivableStatus::payable())) {
                throw new BusinessException(
                    message: 'Không thể thu tiền cho khoản công nợ ở trạng thái "'.$receivable->status->label().'".',
                    errorCode: 'RECEIVABLE_NOT_PAYABLE',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $paymentAmount = (float) $data['amount'];

            /** @var PaymentReceipt $paymentReceipt */
            $paymentReceipt = $receivable->payments()->create([
                'type' => PaymentReceiptType::Collection->value,
                'amount' => $paymentAmount,
                'payment_method' => $data['payment_method'],
                'collected_by_id' => auth()->id(),
                'note' => $data['note'] ?? null,
                'paid_at' => $data['paid_at'],
            ]);

            $this->reconciliationService->createFromPaymentReceipt($receivable, $paymentReceipt);

            $newPaidAmount = (float) $receivable->paid_amount + $paymentAmount;
            $newStatus = $this->calculateStatus($newPaidAmount, (float) $receivable->amount, $receivable->status);

            $receivable->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus->value,
            ]);

            return $this->findById($receivableId);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePayment(int $receivableId, int $paymentId, array $data): Receivable
    {
        return $this->executeInTransaction(function () use ($receivableId, $paymentId, $data): Receivable {
            $receivable = $this->findById($receivableId);

            if (in_array($receivable->status, [ReceivableStatus::WrittenOff, ReceivableStatus::Completed])) {
                throw new BusinessException(
                    message: 'Không thể chỉnh sửa phiếu thu cho khoản công nợ ở trạng thái "'.$receivable->status->label().'".',
                    errorCode: 'RECEIVABLE_CANNOT_EDIT_PAYMENT',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            /** @var PaymentReceipt $payment */
            $payment = $receivable->payments()->findOrFail($paymentId);

            $payment->update([
                'amount' => (float) $data['amount'],
                'payment_method' => $data['payment_method'],
                'note' => $data['note'] ?? null,
                'paid_at' => $data['paid_at'],
            ]);

            // Reset reconciliation for this payment
            $this->reconciliationService->resetForPaymentReceipt($payment);

            // Recalculate from collection payments only (refunds subtract)
            $totalCollected = (float) $receivable->payments()
                ->where('type', PaymentReceiptType::Collection->value)
                ->sum('amount');
            $totalRefunded = (float) $receivable->payments()
                ->where('type', PaymentReceiptType::Refund->value)
                ->sum('amount');
            $totalPaid = $totalCollected - $totalRefunded;

            $newStatus = $this->calculateStatus($totalPaid, (float) $receivable->amount, $receivable->status);

            $receivable->update([
                'paid_amount' => $totalPaid,
                'status' => $newStatus->value,
            ]);

            return $this->findById($receivableId);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordRefund(int $receivableId, array $data): Receivable
    {
        return $this->executeInTransaction(function () use ($receivableId, $data): Receivable {
            $receivable = $this->findById($receivableId);

            if (! in_array($receivable->status, ReceivableStatus::refundable())) {
                throw new BusinessException(
                    message: 'Chỉ có thể hoàn trả khi trạng thái là "Thu thừa".',
                    errorCode: 'RECEIVABLE_NOT_REFUNDABLE',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $overpaidAmount = (float) $receivable->paid_amount - (float) $receivable->amount;
            $refundAmount = (float) $data['amount'];

            if ($refundAmount > $overpaidAmount) {
                throw new BusinessException(
                    message: 'Số tiền hoàn trả ('.number_format($refundAmount, 0, '.', ',').') vượt quá số tiền thừa ('.number_format($overpaidAmount, 0, '.', ',').').',
                    errorCode: 'RECEIVABLE_REFUND_EXCEEDS_OVERPAID',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            /** @var PaymentReceipt $paymentReceipt */
            $paymentReceipt = $receivable->payments()->create([
                'type' => PaymentReceiptType::Refund->value,
                'amount' => $refundAmount,
                'payment_method' => $data['payment_method'],
                'collected_by_id' => auth()->id(),
                'note' => $data['note'] ?? null,
                'paid_at' => $data['paid_at'],
            ]);

            $this->reconciliationService->createFromPaymentReceipt($receivable, $paymentReceipt);

            $newPaidAmount = (float) $receivable->paid_amount - $refundAmount;
            $newStatus = $this->calculateStatus($newPaidAmount, (float) $receivable->amount, $receivable->status);

            $receivable->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus->value,
            ]);

            return $this->findById($receivableId);
        });
    }

    public function markCompleted(int $receivableId): Receivable
    {
        $receivable = $this->findById($receivableId);

        if ($receivable->status !== ReceivableStatus::Paid) {
            throw new BusinessException(
                message: 'Chỉ có thể hoàn thành khi đã thu đủ.',
                errorCode: 'RECEIVABLE_NOT_COMPLETABLE',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $totalPayments = $receivable->payments()->count();
        $reconciledCount = $receivable->reconciliations()
            ->where('status', ReconciliationStatus::Reconciled->value)
            ->count();

        if ($reconciledCount < $totalPayments) {
            $pending = $totalPayments - $reconciledCount;

            throw new BusinessException(
                message: "Còn {$pending} dòng tiền chưa đối soát.",
                errorCode: 'RECEIVABLE_RECONCILIATION_INCOMPLETE',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $receivable->update([
            'status' => ReceivableStatus::Completed->value,
        ]);

        return $this->findById($receivableId);
    }

    public function autoCompleteIfReady(int $receivableId): bool
    {
        /** @var Receivable $receivable */
        $receivable = $this->repository->findById($receivableId);

        if ($receivable->status !== ReceivableStatus::Paid) {
            return false;
        }

        $totalPayments = $receivable->payments()->count();

        if ($totalPayments === 0) {
            return false;
        }

        $reconciledCount = $receivable->reconciliations()
            ->where('status', ReconciliationStatus::Reconciled->value)
            ->count();

        if ($reconciledCount < $totalPayments) {
            return false;
        }

        $receivable->update([
            'status' => ReceivableStatus::Completed->value,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function writeOff(int $receivableId, array $data): Receivable
    {
        $receivable = $this->findById($receivableId);

        if (! in_array($receivable->status, ReceivableStatus::writableOff())) {
            throw new BusinessException(
                message: 'Không thể xóa nợ cho khoản công nợ ở trạng thái "'.$receivable->status->label().'".',
                errorCode: 'RECEIVABLE_CANNOT_WRITE_OFF',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $receivable->update([
            'status' => ReceivableStatus::WrittenOff->value,
        ]);

        return $this->findById($receivableId);
    }

    public function createFromOrder(Order $order): Receivable
    {
        $existing = $this->repository->findByOrderId($order->id);
        if ($existing) {
            return $existing;
        }

        $ogTicket = $order->quote?->ogTicket;
        $projectId = $ogTicket?->project_id;

        /** @var Receivable */
        return $this->repository->create([
            'order_id' => $order->id,
            'project_id' => $projectId,
            'amount' => $order->total_amount,
            'paid_amount' => 0,
            'status' => ReceivableStatus::Unpaid->value,
            'due_date' => now()->addDays(config('pmc.receivable_due_days', 30)),
            'issued_at' => now(),
        ]);
    }

    public function handleOrderCancelled(Order $order): void
    {
        $receivable = $this->repository->findByOrderId($order->id);

        if (! $receivable) {
            return;
        }

        // Auto write-off only if no payments collected
        if ((float) $receivable->paid_amount === 0.0) {
            $receivable->update([
                'status' => ReceivableStatus::WrittenOff->value,
            ]);
        }
        // If paid_amount > 0, do nothing — accountant handles manually
    }

    public function syncAmountFromOrder(Order $order): void
    {
        $receivable = $this->repository->findByOrderId($order->id);

        if (! $receivable) {
            return;
        }

        $newAmount = (float) $order->total_amount;
        $currentAmount = (float) $receivable->amount;

        if (abs($newAmount - $currentAmount) < 0.01) {
            return;
        }

        // A Completed receivable regresses when the underlying order amount
        // shifts — reconciliation records stay intact, the status only reflects
        // the new money position (Paid / Partial / Overpaid).
        $baseStatus = $receivable->status === ReceivableStatus::Completed
            ? ReceivableStatus::Paid
            : $receivable->status;

        $newStatus = $this->calculateStatus(
            (float) $receivable->paid_amount,
            $newAmount,
            $baseStatus,
        );

        $this->repository->update($receivable->id, [
            'amount' => $newAmount,
            'status' => $newStatus->value,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAudits(int $id): array
    {
        $receivable = $this->findById($id);

        // Get receivable audits
        $receivableAudits = $receivable->audits()
            ->with('user')
            ->latest()
            ->get()
            ->map(fn ($audit) => [
                'id' => $audit->id,
                'event' => $audit->event,
                'auditable_type' => 'receivable',
                'old_values' => $this->resolveReceivableAuditValues($audit->old_values),
                'new_values' => $this->resolveReceivableAuditValues($audit->new_values),
                'user' => $audit->user ? ['id' => $audit->user->id, 'name' => $audit->user->name] : null,
                'created_at' => $audit->created_at?->toIso8601String(),
            ]);

        // Get payment receipt audits
        $paymentIds = $receivable->payments()->pluck('id');
        $paymentAudits = $this->repository->getPaymentReceiptAudits($paymentIds)
            ->map(fn ($audit) => [
                'id' => $audit->id,
                'event' => $audit->event,
                'auditable_type' => 'payment_receipt',
                'old_values' => $this->resolvePaymentAuditValues($audit->old_values),
                'new_values' => $this->resolvePaymentAuditValues($audit->new_values),
                'user' => $audit->user ? ['id' => $audit->user->id, 'name' => $audit->user->name] : null,
                'created_at' => $audit->created_at?->toIso8601String(),
            ]);

        return $receivableAudits->merge($paymentAudits)
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    private function calculateStatus(float $paidAmount, float $amount, ReceivableStatus $currentStatus): ReceivableStatus
    {
        return match (true) {
            $paidAmount > $amount => ReceivableStatus::Overpaid,
            $paidAmount == $amount => ReceivableStatus::Paid,
            $paidAmount > 0 && $currentStatus === ReceivableStatus::Overdue => ReceivableStatus::Overdue,
            $paidAmount > 0 => ReceivableStatus::Partial,
            default => ReceivableStatus::Unpaid,
        };
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function resolveReceivableAuditValues(?array $values): ?array
    {
        if (! $values) {
            return null;
        }

        $resolved = $values;

        if (isset($resolved['status'])) {
            $enum = ReceivableStatus::tryFrom($resolved['status']);
            $resolved['status'] = $enum?->label() ?? $resolved['status'];
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function resolvePaymentAuditValues(?array $values): ?array
    {
        if (! $values) {
            return null;
        }

        $resolved = $values;

        if (isset($resolved['payment_method'])) {
            $enum = PaymentMethod::tryFrom($resolved['payment_method']);
            $resolved['payment_method'] = $enum?->label() ?? $resolved['payment_method'];
        }

        return $resolved;
    }
}
