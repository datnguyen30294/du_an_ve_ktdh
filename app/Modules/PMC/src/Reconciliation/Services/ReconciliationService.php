<?php

namespace App\Modules\PMC\Reconciliation\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use App\Modules\PMC\Receivable\Models\Receivable;
use App\Modules\PMC\Reconciliation\Contracts\ReconciliationServiceInterface;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use App\Modules\PMC\Reconciliation\Repositories\ReconciliationRepository;
use App\Modules\PMC\Treasury\Events\FinancialReconciliationApproved;
use App\Modules\PMC\Treasury\Events\FinancialReconciliationReset;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class ReconciliationService extends BaseService implements ReconciliationServiceInterface
{
    public function __construct(protected ReconciliationRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): FinancialReconciliation
    {
        /** @var FinancialReconciliation */
        return $this->repository->findById($id, ['*'], [
            'receivable:id,order_id,project_id,amount,paid_amount,status',
            'receivable.order:id,code,quote_id',
            'receivable.order.quote:id,og_ticket_id',
            'receivable.order.quote.ogTicket:id,subject,requester_name,requester_phone,apartment_name,customer_id',
            'receivable.order.quote.ogTicket.customer:id,code,full_name,phone',
            'receivable.project:id,name',
            'paymentReceipt:id,type,amount,payment_method,collected_by_id,note,paid_at',
            'paymentReceipt.collectedBy:id,name',
            'sourceCashTransaction:id,code,category,direction,amount,transaction_date,note,created_by_id',
            'sourceCashTransaction.createdBy:id,name',
            'reconciledBy:id,name',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total_count: int, pending_count: int, reconciled_count: int, rejected_count: int, pending_amount: string, reconciled_amount: string, rejected_amount: string}
     */
    public function summary(array $filters = []): array
    {
        return $this->repository->getSummary($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reconcile(int $reconciliationId, array $data): FinancialReconciliation
    {
        $reconciliation = $this->findById($reconciliationId);

        if ($reconciliation->status !== ReconciliationStatus::Pending) {
            throw new BusinessException(
                message: 'Bản ghi đã được đối soát.',
                errorCode: 'RECONCILIATION_ALREADY_RECONCILED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $reconciliation->update([
            'status' => ReconciliationStatus::Reconciled->value,
            'reconciled_at' => now(),
            'reconciled_by_id' => auth()->id(),
            'note' => $data['note'] ?? null,
        ]);

        // Only the receivable flow needs to generate a cash transaction on approval.
        // Manual-sourced reconciliations point to a cash tx that already exists.
        if ($reconciliation->isReceivableSource()) {
            FinancialReconciliationApproved::dispatch(
                $reconciliation->fresh(['paymentReceipt', 'receivable'])
            );
        }

        return $this->findById($reconciliationId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reject(int $reconciliationId, array $data): FinancialReconciliation
    {
        $reconciliation = $this->findById($reconciliationId);

        if ($reconciliation->status !== ReconciliationStatus::Pending) {
            throw new BusinessException(
                message: 'Chỉ có thể từ chối bản ghi đang chờ đối soát.',
                errorCode: 'RECONCILIATION_NOT_PENDING',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $reconciliation->update([
            'status' => ReconciliationStatus::Rejected->value,
            'reconciled_at' => null,
            'reconciled_by_id' => null,
            'note' => $data['reason'] ?? null,
        ]);

        return $this->findById($reconciliationId);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $data
     * @return array{reconciled_count: int, skipped_count: int}
     */
    public function batchReconcile(array $ids, array $data): array
    {
        return $this->executeInTransaction(function () use ($ids, $data): array {
            $reconciliations = $this->repository->findByIds($ids);

            $reconciledCount = 0;
            $skippedCount = 0;

            foreach ($reconciliations as $reconciliation) {
                if ($reconciliation->status === ReconciliationStatus::Pending) {
                    $reconciliation->update([
                        'status' => ReconciliationStatus::Reconciled->value,
                        'reconciled_at' => now(),
                        'reconciled_by_id' => auth()->id(),
                        'note' => $data['note'] ?? null,
                    ]);

                    if ($reconciliation->isReceivableSource()) {
                        FinancialReconciliationApproved::dispatch(
                            $reconciliation->fresh(['paymentReceipt', 'receivable'])
                        );
                    }

                    $reconciledCount++;
                } else {
                    $skippedCount++;
                }
            }

            return [
                'reconciled_count' => $reconciledCount,
                'skipped_count' => $skippedCount,
            ];
        });
    }

    public function createFromPaymentReceipt(Receivable $receivable, PaymentReceipt $paymentReceipt): FinancialReconciliation
    {
        /** @var FinancialReconciliation */
        return $this->repository->create([
            'receivable_id' => $receivable->id,
            'payment_receipt_id' => $paymentReceipt->id,
            'amount' => $paymentReceipt->amount,
            'status' => ReconciliationStatus::Pending->value,
        ]);
    }

    public function createFromManualCashTransaction(CashTransaction $cashTransaction): FinancialReconciliation
    {
        /** @var FinancialReconciliation */
        return $this->repository->create([
            'cash_transaction_id' => $cashTransaction->id,
            'amount' => $cashTransaction->amount,
            'status' => ReconciliationStatus::Pending->value,
        ]);
    }

    public function resetForPaymentReceipt(PaymentReceipt $paymentReceipt): void
    {
        $reconciliation = $paymentReceipt->reconciliation;

        if ($reconciliation && in_array($reconciliation->status, [ReconciliationStatus::Reconciled, ReconciliationStatus::Rejected])) {
            $wasReconciled = $reconciliation->status === ReconciliationStatus::Reconciled;

            $reconciliation->update([
                'status' => ReconciliationStatus::Pending->value,
                'reconciled_at' => null,
                'reconciled_by_id' => null,
                'note' => 'Đối soát bị reset do chỉnh sửa dòng tiền.',
            ]);

            // Only fire the reset event when a matching cash transaction could
            // exist (previous state was Reconciled). Resetting from Rejected
            // never produced a tx in the first place.
            if ($wasReconciled) {
                FinancialReconciliationReset::dispatch($reconciliation->fresh());
            }
        }
    }
}
