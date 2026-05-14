<?php

namespace App\Modules\PMC\Customer\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Customer\Contracts\CustomerServiceInterface;
use App\Modules\PMC\Customer\Models\Customer;
use App\Modules\PMC\Customer\Repositories\CustomerRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class CustomerService extends BaseService implements CustomerServiceInterface
{
    public function __construct(
        protected CustomerRepository $repository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Customer
    {
        /** @var Customer */
        return $this->repository->findById($id);
    }

    public function getDetailWithAggregates(int $id): array
    {
        $customer = $this->findById($id);
        $aggregates = $this->repository->getAggregates($id);

        return [
            'customer' => $customer,
            'aggregates' => $aggregates,
        ];
    }

    public function create(array $data): Customer
    {
        /** @var Customer */
        return $this->repository->create($data);
    }

    public function update(int $id, array $data): Customer
    {
        $customer = $this->findById($id);
        $customer->update($data);

        return $customer->refresh();
    }

    public function delete(int $id): void
    {
        $check = $this->checkDelete($id);

        if (! $check['can_delete']) {
            throw new BusinessException(
                message: $check['message'],
                errorCode: 'CUSTOMER_HAS_DEPENDENCIES',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $customer = $this->findById($id);
        $customer->delete();
    }

    public function checkDelete(int $id): array
    {
        $customer = $this->findById($id);

        $ticketCount = $this->repository->hasTickets($customer->id)
            ? $this->repository->getAggregates($customer->id)['ticket_count']
            : 0;

        $orderCount = $this->repository->hasOrders($customer->id)
            ? $this->repository->getAggregates($customer->id)['order_count']
            : 0;

        if ($ticketCount > 0 || $orderCount > 0) {
            $parts = [];
            if ($ticketCount > 0) {
                $parts[] = "{$ticketCount} ticket";
            }
            if ($orderCount > 0) {
                $parts[] = "{$orderCount} đơn hàng";
            }

            return [
                'can_delete' => false,
                'message' => 'Không thể xóa: khách còn '.implode(', ', $parts).'. Hãy xử lý các mục trên trước.',
                'ticket_count' => $ticketCount,
                'order_count' => $orderCount,
            ];
        }

        return [
            'can_delete' => true,
            'message' => '',
            'ticket_count' => 0,
            'order_count' => 0,
        ];
    }

    public function findOrCreateByPhone(string $phone, string $fullName): Customer
    {
        return $this->repository->findOrCreateByPhone($phone, $fullName);
    }

    public function markContacted(Customer $customer): void
    {
        $now = now();
        $data = ['last_contacted_at' => $now];

        if (! $customer->first_contacted_at) {
            $data['first_contacted_at'] = $now;
        }

        $customer->update($data);
    }

    public function listTickets(int $customerId, array $filters): LengthAwarePaginator
    {
        $this->findById($customerId); // ensure exists

        return $this->repository->listTickets($customerId, $filters);
    }

    public function listOrders(int $customerId, array $filters): LengthAwarePaginator
    {
        $this->findById($customerId);

        return $this->repository->listOrders($customerId, $filters);
    }

    public function listPayments(int $customerId, array $filters): LengthAwarePaginator
    {
        $this->findById($customerId);

        return $this->repository->listPayments($customerId, $filters);
    }
}
