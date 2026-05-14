<?php

namespace App\Modules\PMC\Customer\Contracts;

use App\Modules\PMC\Customer\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Customer;

    /**
     * @return array{customer: Customer, aggregates: array<string, mixed>}
     */
    public function getDetailWithAggregates(int $id): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Customer;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Customer;

    public function delete(int $id): void;

    /**
     * @return array{can_delete: bool, message: string, ticket_count: int, order_count: int}
     */
    public function checkDelete(int $id): array;

    public function findOrCreateByPhone(string $phone, string $fullName): Customer;

    public function markContacted(Customer $customer): void;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listTickets(int $customerId, array $filters): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listOrders(int $customerId, array $filters): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPayments(int $customerId, array $filters): LengthAwarePaginator;
}
