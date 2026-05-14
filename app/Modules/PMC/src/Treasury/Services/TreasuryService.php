<?php

namespace App\Modules\PMC\Treasury\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord;
use App\Modules\PMC\Receivable\Enums\PaymentReceiptType;
use App\Modules\PMC\Reconciliation\Contracts\ReconciliationServiceInterface;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Enums\CashTransactionCategory;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use App\Modules\PMC\Treasury\Models\CashAccount;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use App\Modules\PMC\Treasury\Repositories\CashAccountRepository;
use App\Modules\PMC\Treasury\Repositories\CashTransactionRepository;
use App\Modules\PMC\Treasury\Support\CashTransactionCodeGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class TreasuryService extends BaseService implements TreasuryServiceInterface
{
    private const PG_UNIQUE_VIOLATION = '23505';

    public function __construct(
        protected CashAccountRepository $accountRepository,
        protected CashTransactionRepository $transactionRepository,
        protected CashTransactionCodeGenerator $codeGenerator,
        protected ReconciliationServiceInterface $reconciliationService,
    ) {}

    // =========================================================================
    // CashAccount
    // =========================================================================

    public function listCashAccounts(): Collection
    {
        return $this->accountRepository->listActive();
    }

    public function findCashAccountById(int $id): CashAccount
    {
        /** @var CashAccount */
        return $this->accountRepository->findById($id);
    }

    public function getDefaultCashAccount(): CashAccount
    {
        $account = $this->accountRepository->findDefault();

        if (! $account) {
            throw new BusinessException(
                message: 'Chưa có quỹ mặc định. Vui lòng liên hệ quản trị viên.',
                errorCode: 'CASH_ACCOUNT_DEFAULT_MISSING',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $account;
    }

    public function getCurrentBalance(CashAccount $account): float
    {
        return $this->transactionRepository->computeBalance($account);
    }

    // =========================================================================
    // CashTransaction list/detail
    // =========================================================================

    public function listTransactions(array $filters): LengthAwarePaginator
    {
        return $this->transactionRepository->list($filters);
    }

    public function findTransactionById(int $id): CashTransaction
    {
        /** @var CashTransaction */
        return $this->transactionRepository->findById($id);
    }

    // =========================================================================
    // Manual CRUD
    // =========================================================================

    public function recordManualTopup(array $data): CashTransaction
    {
        return $this->recordManual($data, CashTransactionCategory::ManualTopup);
    }

    public function recordManualWithdraw(array $data): CashTransaction
    {
        return $this->recordManual($data, CashTransactionCategory::ManualWithdraw);
    }

    public function softDeleteManual(int $transactionId, string $reason): void
    {
        /** @var CashTransaction $tx */
        $tx = $this->transactionRepository->findById($transactionId);

        if ($tx->trashed()) {
            throw new BusinessException(
                message: 'Giao dịch đã bị xóa trước đó.',
                errorCode: 'CASH_TRANSACTION_ALREADY_DELETED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (! $tx->isManual()) {
            throw new BusinessException(
                message: 'Chỉ có thể xóa giao dịch nạp/rút thủ công.',
                errorCode: 'CASH_TRANSACTION_NOT_DELETABLE',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->executeInTransaction(function () use ($tx, $reason): void {
            $tx->fill([
                'delete_reason' => $reason,
                'auto_deleted' => false,
                'deleted_by_id' => auth()->id(),
            ])->save();

            // Hard-delete the paired reconciliation audit record so it stops
            // appearing on the reconciliation page. The cash tx itself retains
            // the soft-delete history for audit.
            $tx->manualReconciliation()->delete();

            $tx->delete();
        });
    }

    // =========================================================================
    // Auto-sourced (listeners)
    // =========================================================================

    public function recordFromReconciliation(FinancialReconciliation $reconciliation): ?CashTransaction
    {
        // Application-layer idempotency check (DB partial unique is the fallback).
        if ($this->transactionRepository->findActiveByReconciliationId($reconciliation->id)) {
            return null;
        }

        $paymentReceipt = $reconciliation->paymentReceipt;

        if (! $paymentReceipt) {
            throw new BusinessException(
                message: 'Đối soát thiếu phiếu thu/hoàn trả liên kết.',
                errorCode: 'RECONCILIATION_MISSING_PAYMENT_RECEIPT',
            );
        }

        $category = $paymentReceipt->type === PaymentReceiptType::Collection
            ? CashTransactionCategory::ReceivableCollection
            : CashTransactionCategory::CustomerRefund;

        $transactionDate = $paymentReceipt->paid_at
            ? Carbon::parse($paymentReceipt->paid_at)
            : Carbon::parse($reconciliation->reconciled_at ?? now());

        $orderId = $reconciliation->receivable?->order_id;

        return $this->createAutoTransaction(
            category: $category,
            amount: (float) $paymentReceipt->amount,
            transactionDate: $transactionDate,
            note: null,
            extras: [
                'financial_reconciliation_id' => $reconciliation->id,
                'order_id' => $orderId,
            ],
        );
    }

    public function recordFromCommissionSnapshot(OrderCommissionSnapshot $snapshot): ?CashTransaction
    {
        if ($this->transactionRepository->findActiveByCommissionSnapshotId($snapshot->id)) {
            return null;
        }

        $transactionDate = $snapshot->paid_out_at
            ? Carbon::parse($snapshot->paid_out_at)
            : Carbon::now();

        return $this->createAutoTransaction(
            category: CashTransactionCategory::CommissionPayout,
            amount: (float) $snapshot->amount,
            transactionDate: $transactionDate,
            note: null,
            extras: [
                'commission_snapshot_id' => $snapshot->id,
                'order_id' => $snapshot->order_id,
            ],
        );
    }

    public function recordFromAdvancePayment(AdvancePaymentRecord $record): ?CashTransaction
    {
        if ($this->transactionRepository->findActiveByAdvancePaymentRecordId($record->id)) {
            return null;
        }

        $transactionDate = $record->paid_at
            ? Carbon::parse($record->paid_at)
            : Carbon::now();

        $note = $record->note !== null && $record->note !== ''
            ? $record->note
            : sprintf('Chi tiền ứng vật tư cho đơn hàng #%d', $record->order_id ?? 0);

        return $this->createAutoTransaction(
            category: CashTransactionCategory::AdvancePaymentPayout,
            amount: (float) $record->amount,
            transactionDate: $transactionDate,
            note: $note,
            extras: [
                'advance_payment_record_id' => $record->id,
                'order_id' => $record->order_id,
            ],
        );
    }

    public function softDeleteFromReconciliationReset(FinancialReconciliation $reconciliation): void
    {
        $tx = $this->transactionRepository->findActiveByReconciliationId($reconciliation->id);

        if (! $tx) {
            return;
        }

        $this->autoSoftDelete($tx, 'Đối soát bị reset do chỉnh sửa dòng tiền');
    }

    public function softDeleteFromCommissionUnpaid(OrderCommissionSnapshot $snapshot): void
    {
        $tx = $this->transactionRepository->findActiveByCommissionSnapshotId($snapshot->id);

        if (! $tx) {
            return;
        }

        $this->autoSoftDelete($tx, 'Snapshot hoa hồng chuyển về chưa thanh toán');
    }

    public function softDeleteFromAdvancePaymentDeleted(AdvancePaymentRecord $record): void
    {
        $tx = $this->transactionRepository->findActiveByAdvancePaymentRecordId($record->id);

        if (! $tx) {
            return;
        }

        $this->autoSoftDelete($tx, 'Bản ghi tiền ứng vật tư đã bị xóa');
    }

    // =========================================================================
    // Reporting
    // =========================================================================

    public function getSummary(array $filters): array
    {
        $account = ! empty($filters['cash_account_id'])
            ? $this->findCashAccountById((int) $filters['cash_account_id'])
            : $this->getDefaultCashAccount();

        $summary = $this->transactionRepository->getSummary($account, $filters);

        $inflowByCategory = array_map(fn (array $row) => [
            'category' => $this->categoryPayload(CashTransactionCategory::from($row['category'])),
            'amount' => number_format($row['amount'], 2, '.', ''),
            'count' => $row['count'],
        ], $summary['inflow_by_category']);

        $outflowByCategory = array_map(fn (array $row) => [
            'category' => $this->categoryPayload(CashTransactionCategory::from($row['category'])),
            'amount' => number_format($row['amount'], 2, '.', ''),
            'count' => $row['count'],
        ], $summary['outflow_by_category']);

        return [
            'cash_account_id' => $account->id,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'current_balance' => number_format($this->getCurrentBalance($account), 2, '.', ''),
            'total_inflow' => number_format($summary['total_inflow'], 2, '.', ''),
            'total_outflow' => number_format($summary['total_outflow'], 2, '.', ''),
            'net_flow' => number_format($summary['total_inflow'] - $summary['total_outflow'], 2, '.', ''),
            'transaction_count' => $summary['transaction_count'],
            'inflow_by_category' => $inflowByCategory,
            'outflow_by_category' => $outflowByCategory,
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordManual(array $data, CashTransactionCategory $category): CashTransaction
    {
        return $this->executeInTransaction(function () use ($data, $category): CashTransaction {
            // Lock the account row so concurrent withdrawals serialize on it;
            // the balance check below is otherwise racy at READ COMMITTED.
            $account = $this->accountRepository->findByIdForUpdate((int) $data['cash_account_id']);

            if (! $account->is_active) {
                throw new BusinessException(
                    message: 'Tài khoản quỹ đã bị vô hiệu hóa.',
                    errorCode: 'CASH_ACCOUNT_INACTIVE',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $direction = $category->direction();
            $transactionDate = Carbon::parse($data['transaction_date']);
            $amount = (float) $data['amount'];

            if ($direction === CashTransactionDirection::Outflow) {
                $currentBalance = $this->transactionRepository->computeBalance($account);

                if ($currentBalance < $amount) {
                    throw new BusinessException(
                        message: sprintf(
                            'Số dư quỹ không đủ để rút. Số dư hiện tại: %s đ, số tiền rút: %s đ.',
                            number_format($currentBalance, 0, ',', '.'),
                            number_format($amount, 0, ',', '.'),
                        ),
                        errorCode: 'CASH_ACCOUNT_INSUFFICIENT_BALANCE',
                        httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                    );
                }
            }

            /** @var CashTransaction $tx */
            $tx = $this->transactionRepository->create([
                'code' => $this->codeGenerator->generate($direction, $transactionDate),
                'cash_account_id' => $account->id,
                'direction' => $direction->value,
                'amount' => $data['amount'],
                'category' => $category->value,
                'transaction_date' => $transactionDate->toDateString(),
                'note' => $data['note'] ?? null,
                'created_by_id' => auth()->id(),
            ]);

            // Attach a pending reconciliation record as an audit gate. The cash tx
            // already affects the balance; reconciliation here is a verify-or-not
            // marker that the accounting lead flips later.
            $this->reconciliationService->createFromManualCashTransaction($tx);

            return $tx;
        });
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    private function createAutoTransaction(
        CashTransactionCategory $category,
        float $amount,
        Carbon $transactionDate,
        ?string $note,
        array $extras,
    ): ?CashTransaction {
        $this->ensureCategoryMatchesDirection($category, $category->direction());

        try {
            return $this->executeInTransaction(function () use ($category, $amount, $transactionDate, $note, $extras): CashTransaction {
                $account = $this->getDefaultCashAccount();
                $direction = $category->direction();

                /** @var CashTransaction */
                return $this->transactionRepository->create(array_merge([
                    'code' => $this->codeGenerator->generate($direction, $transactionDate),
                    'cash_account_id' => $account->id,
                    'direction' => $direction->value,
                    'amount' => $amount,
                    'category' => $category->value,
                    'transaction_date' => $transactionDate->toDateString(),
                    'note' => $note,
                    'created_by_id' => auth()->id(),
                ], $extras));
            });
        } catch (BusinessException $e) {
            // Rewrap QueryException that BaseService converted: only swallow for unique violations.
            $previous = $e->getPrevious();

            if ($previous instanceof QueryException && $this->isUniqueViolation($previous)) {
                return null;
            }

            throw $e;
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return null;
            }

            throw $e;
        }
    }

    private function autoSoftDelete(CashTransaction $tx, string $reason): void
    {
        $this->executeInTransaction(function () use ($tx, $reason): void {
            $tx->fill([
                'auto_deleted' => true,
                'delete_reason' => $reason,
                'deleted_by_id' => auth()->id(),
            ])->save();

            $tx->delete();
        });
    }

    private function ensureCategoryMatchesDirection(
        CashTransactionCategory $category,
        CashTransactionDirection $direction,
    ): void {
        if ($category->direction() !== $direction) {
            throw new BusinessException(
                message: 'Danh mục giao dịch và hướng tiền không khớp.',
                errorCode: 'CASH_TRANSACTION_CATEGORY_DIRECTION_MISMATCH',
            );
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        // PostgreSQL: 23505 unique_violation. SQLite: reports "23000" and "HY000"
        // with driverCode 19; we match on the message as a last resort so tests
        // on SQLite behave the same as production PostgreSQL.
        if ($sqlState === self::PG_UNIQUE_VIOLATION || $sqlState === '23000') {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }

    /**
     * @return array{value: string, label: string}
     */
    private function categoryPayload(CashTransactionCategory $category): array
    {
        return [
            'value' => $category->value,
            'label' => $category->label(),
        ];
    }
}
