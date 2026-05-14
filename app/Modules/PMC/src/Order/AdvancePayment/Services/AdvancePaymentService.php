<?php

namespace App\Modules\PMC\Order\AdvancePayment\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord;
use App\Modules\PMC\Order\AdvancePayment\Repositories\AdvancePaymentRecordRepository;
use App\Modules\PMC\Order\Models\OrderLine;
use App\Modules\PMC\Order\Repositories\OrderLineRepository;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use App\Modules\PMC\Treasury\Events\AdvancePaymentDeleted;
use App\Modules\PMC\Treasury\Events\AdvancePaymentRecorded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AdvancePaymentService extends BaseService
{
    public function __construct(
        protected AdvancePaymentRecordRepository $repository,
        protected OrderLineRepository $orderLineRepository,
    ) {}

    /**
     * List all rows — material lines with an advance payer — for the
     * "Tiền ứng vật tư" screen. Each row is a derived object with status
     * (pending/paid) and the line's current advance_amount.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters): array
    {
        $lines = $this->orderLineRepository->listAdvanceCandidates($filters);

        if ($lines->isEmpty()) {
            return [];
        }

        $paidMap = $this->repository->paidAtByLine($lines->pluck('id')->all());

        $rows = $lines->map(function (OrderLine $line) use ($paidMap) {
            $paidAt = $paidMap->get($line->id);
            $isPaid = $paidAt !== null;
            $payer = $line->advancePayer;

            return [
                'key' => $line->id,
                'order_line_id' => $line->id,
                'order_id' => $line->order_id,
                'order_code' => $line->order?->code ?? '',
                'line_name' => $line->name,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'purchase_price' => $line->purchase_price,
                'advance_amount' => number_format($line->advanceAmount(), 2, '.', ''),
                'project_id' => $line->order?->quote?->ogTicket?->project_id,
                'is_paid' => $isPaid,
                'paid_at' => $paidAt,
                'advance_payer' => $payer ? [
                    'id' => $payer->id,
                    'name' => $payer->name,
                    'employee_code' => $payer->employee_code,
                    'bank_info' => $payer->bankInfo(),
                ] : null,
            ];
        });

        // Apply post-query status filter (pending/paid) since pagination is off.
        $status = $filters['status'] ?? null;
        if ($status === 'pending') {
            $rows = $rows->filter(fn ($r) => ! $r['is_paid']);
        } elseif ($status === 'paid') {
            $rows = $rows->filter(fn ($r) => $r['is_paid']);
        }

        return $rows->values()->all();
    }

    /**
     * KPI stats across all rows.
     *
     * @return array{total_advanced: string, total_pending: string, total_paid: string, account_count: int}
     */
    public function stats(): array
    {
        $lines = $this->orderLineRepository->listAdvanceCandidates([]);
        $paidMap = $this->repository->paidAtByLine($lines->pluck('id')->all());

        $totalAdvanced = 0.0;
        $totalPending = 0.0;
        $totalPaid = 0.0;
        $accountIds = [];

        foreach ($lines as $line) {
            $amount = $line->advanceAmount();
            $totalAdvanced += $amount;

            if ($paidMap->has($line->id)) {
                $totalPaid += $amount;
            } else {
                $totalPending += $amount;
            }

            if ($line->advance_payer_id !== null) {
                $accountIds[$line->advance_payer_id] = true;
            }
        }

        return [
            'total_advanced' => number_format($totalAdvanced, 2, '.', ''),
            'total_pending' => number_format($totalPending, 2, '.', ''),
            'total_paid' => number_format($totalPaid, 2, '.', ''),
            'account_count' => count($accountIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function history(array $filters): LengthAwarePaginator
    {
        return $this->repository->history($filters);
    }

    /**
     * Record payment for a single order line.
     *
     * @param  array<string, mixed>  $data  { note?: string, paid_at?: string }
     */
    public function recordSingle(int $orderLineId, array $data): AdvancePaymentRecord
    {
        $record = $this->executeInTransaction(function () use ($orderLineId, $data): AdvancePaymentRecord {
            $line = $this->orderLineRepository->findById($orderLineId);
            $this->ensurePayable($line);

            /** @var AdvancePaymentRecord $record */
            $record = $this->repository->create([
                'account_id' => $line->advance_payer_id,
                'order_id' => $line->order_id,
                'order_line_id' => $line->id,
                'amount' => $line->advanceAmount(),
                'note' => $data['note'] ?? null,
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'paid_by_id' => auth()->id(),
                'batch_id' => null,
            ]);

            return $record;
        });

        AdvancePaymentRecorded::dispatch($record);

        return $record;
    }

    /**
     * Record payment for multiple order lines in a single batch.
     * Each line gets its own record but shares the same batch_id.
     *
     * @param  array<int>  $orderLineIds
     * @param  array<string, mixed>  $data
     * @return Collection<int, AdvancePaymentRecord>
     */
    public function recordBatch(array $orderLineIds, array $data): Collection
    {
        $records = $this->executeInTransaction(function () use ($orderLineIds, $data): Collection {
            if (empty($orderLineIds)) {
                throw new BusinessException(
                    message: 'Cần chọn ít nhất 1 dòng để hoàn tiền ứng.',
                    errorCode: 'ADVANCE_BATCH_EMPTY',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $lines = $this->orderLineRepository->findManyByIds($orderLineIds);

            if ($lines->count() !== count($orderLineIds)) {
                throw new BusinessException(
                    message: 'Một hoặc nhiều dòng đơn hàng không tồn tại.',
                    errorCode: 'ADVANCE_LINE_NOT_FOUND',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $batchId = (string) Str::uuid();
            $paidAt = $data['paid_at'] ?? now()->toDateString();
            $note = $data['note'] ?? null;
            $paidById = auth()->id();

            $created = collect();

            foreach ($lines as $line) {
                $this->ensurePayable($line);

                $created->push($this->repository->create([
                    'account_id' => $line->advance_payer_id,
                    'order_id' => $line->order_id,
                    'order_line_id' => $line->id,
                    'amount' => $line->advanceAmount(),
                    'note' => $note,
                    'paid_at' => $paidAt,
                    'paid_by_id' => $paidById,
                    'batch_id' => $batchId,
                ]));
            }

            /** @var Collection<int, AdvancePaymentRecord> */
            return Collection::make($created->all());
        });

        foreach ($records as $record) {
            AdvancePaymentRecorded::dispatch($record);
        }

        return $records;
    }

    /**
     * Soft-delete a payment record (in case of wrong entry).
     */
    public function delete(int $id): void
    {
        /** @var AdvancePaymentRecord $record */
        $record = $this->repository->findById($id);
        $this->repository->delete($id);

        AdvancePaymentDeleted::dispatch($record);
    }

    private function ensurePayable(OrderLine $line): void
    {
        if ($line->line_type !== QuoteLineType::Material) {
            throw new BusinessException(
                message: 'Chỉ có thể hoàn tiền ứng cho dòng vật tư.',
                errorCode: 'ADVANCE_NOT_MATERIAL_LINE',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($line->advance_payer_id === null) {
            throw new BusinessException(
                message: 'Dòng đơn hàng chưa gán người ứng tiền.',
                errorCode: 'ADVANCE_NO_PAYER',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($line->purchase_price === null || $line->advanceAmount() <= 0) {
            throw new BusinessException(
                message: 'Dòng đơn hàng không có giá nhập.',
                errorCode: 'ADVANCE_NO_PURCHASE_PRICE',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($this->repository->existsForLine($line->id)) {
            throw new BusinessException(
                message: "Dòng {$line->name} đã được hoàn tiền ứng trước đó.",
                errorCode: 'ADVANCE_ALREADY_PAID',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
