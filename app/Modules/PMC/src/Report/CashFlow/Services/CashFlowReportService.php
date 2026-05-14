<?php

namespace App\Modules\PMC\Report\CashFlow\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Report\CashFlow\Contracts\CashFlowReportServiceInterface;
use App\Modules\PMC\Report\CashFlow\Repositories\CashFlowReportRepository;
use App\Modules\PMC\Treasury\Enums\CashTransactionCategory;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use App\Modules\PMC\Treasury\Repositories\CashAccountRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class CashFlowReportService extends BaseService implements CashFlowReportServiceInterface
{
    public function __construct(
        protected CashFlowReportRepository $repository,
        protected CashAccountRepository $accountRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array
    {
        $account = $this->accountRepository->findDefault();

        if (! $account) {
            throw new BusinessException(
                message: 'Chưa có quỹ mặc định. Vui lòng liên hệ quản trị viên.',
                errorCode: 'CASH_ACCOUNT_DEFAULT_MISSING',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $summary = $this->repository->getSummary($account->id, $filters);
        $currentBalance = $this->repository->computeCurrentBalance($account->id, (float) $account->opening_balance);

        return [
            'period_label' => $this->repository->getPeriodLabel($filters),
            'current_balance' => number_format($currentBalance, 2, '.', ''),
            'total_inflow' => number_format($summary['total_inflow'], 2, '.', ''),
            'total_outflow' => number_format($summary['total_outflow'], 2, '.', ''),
            'net_flow' => number_format($summary['total_inflow'] - $summary['total_outflow'], 2, '.', ''),
            'transaction_count' => $summary['transaction_count'],
            'inflow_by_category' => array_map(fn (array $row) => [
                'category' => $this->categoryPayload(CashTransactionCategory::from($row['category'])),
                'amount' => number_format($row['amount'], 2, '.', ''),
                'count' => $row['count'],
            ], $summary['inflow_by_category']),
            'outflow_by_category' => array_map(fn (array $row) => [
                'category' => $this->categoryPayload(CashTransactionCategory::from($row['category'])),
                'amount' => number_format($row['amount'], 2, '.', ''),
                'count' => $row['count'],
            ], $summary['outflow_by_category']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getDaily(array $filters): array
    {
        $account = $this->accountRepository->findDefault();

        if (! $account) {
            throw new BusinessException(
                message: 'Chưa có quỹ mặc định. Vui lòng liên hệ quản trị viên.',
                errorCode: 'CASH_ACCOUNT_DEFAULT_MISSING',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $daily = $this->repository->getDaily($account->id, $filters);

        return array_map(fn (array $row) => [
            'date' => $row['date'],
            'total_inflow' => number_format($row['total_inflow'], 2, '.', ''),
            'total_outflow' => number_format($row['total_outflow'], 2, '.', ''),
            'net' => number_format($row['total_inflow'] - $row['total_outflow'], 2, '.', ''),
        ], $daily);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, mixed>
     */
    public function getTransactions(array $filters): LengthAwarePaginator
    {
        $account = $this->accountRepository->findDefault();

        if (! $account) {
            throw new BusinessException(
                message: 'Chưa có quỹ mặc định. Vui lòng liên hệ quản trị viên.',
                errorCode: 'CASH_ACCOUNT_DEFAULT_MISSING',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->repository->getTransactions($account->id, $filters);
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

    /**
     * @return array{value: string, label: string}
     */
    public static function directionPayload(string $value): array
    {
        $enum = CashTransactionDirection::from($value);

        return [
            'value' => $enum->value,
            'label' => $enum->label(),
        ];
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function categoryPayloadStatic(string $value): array
    {
        $enum = CashTransactionCategory::from($value);

        return [
            'value' => $enum->value,
            'label' => $enum->label(),
        ];
    }
}
