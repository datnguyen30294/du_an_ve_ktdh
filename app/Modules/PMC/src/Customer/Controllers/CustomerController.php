<?php

namespace App\Modules\PMC\Customer\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Customer\Contracts\CustomerServiceInterface;
use App\Modules\PMC\Customer\Requests\CreateCustomerRequest;
use App\Modules\PMC\Customer\Requests\ListCustomerOrdersRequest;
use App\Modules\PMC\Customer\Requests\ListCustomerPaymentsRequest;
use App\Modules\PMC\Customer\Requests\ListCustomerRequest;
use App\Modules\PMC\Customer\Requests\ListCustomerTicketsRequest;
use App\Modules\PMC\Customer\Requests\UpdateCustomerRequest;
use App\Modules\PMC\Customer\Resources\CustomerDetailResource;
use App\Modules\PMC\Customer\Resources\CustomerListResource;
use App\Modules\PMC\Customer\Resources\CustomerOrderItemResource;
use App\Modules\PMC\Customer\Resources\CustomerPaymentItemResource;
use App\Modules\PMC\Customer\Resources\CustomerTicketItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Customers
 */
class CustomerController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected CustomerServiceInterface $service
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.view', only: ['index', 'show', 'tickets', 'orders', 'payments']),
            new Middleware('permission:customers.store', only: ['store']),
            new Middleware('permission:customers.update', only: ['update']),
            new Middleware('permission:customers.destroy', only: ['destroy', 'checkDelete']),
        ];
    }

    /**
     * List all customers.
     */
    public function index(ListCustomerRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return CustomerListResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a customer by ID with aggregates.
     */
    public function show(int $id): CustomerDetailResource
    {
        return new CustomerDetailResource($this->service->getDetailWithAggregates($id));
    }

    /**
     * Create a new customer.
     */
    public function store(CreateCustomerRequest $request): JsonResponse
    {
        $customer = $this->service->create($request->validated());

        return (new CustomerDetailResource([
            'customer' => $customer->refresh(),
            'aggregates' => [
                'ticket_count' => 0,
                'ticket_by_status' => [],
                'avg_rating' => null,
                'rating_count' => 0,
                'order_count' => 0,
                'total_paid' => '0.00',
                'total_outstanding' => '0.00',
            ],
        ]))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing customer.
     */
    public function update(UpdateCustomerRequest $request, int $id): CustomerDetailResource
    {
        $customer = $this->service->update($id, $request->validated());

        return new CustomerDetailResource([
            'customer' => $customer,
            'aggregates' => $this->service->getDetailWithAggregates($id)['aggregates'],
        ]);
    }

    /**
     * Check if a customer can be deleted.
     */
    public function checkDelete(int $id): JsonResponse
    {
        return response()->json($this->service->checkDelete($id));
    }

    /**
     * Delete a customer (soft delete, blocked when tickets/orders exist).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }

    /**
     * List tickets of a customer.
     */
    public function tickets(ListCustomerTicketsRequest $request, int $id): AnonymousResourceCollection
    {
        $paginator = $this->service->listTickets($id, $request->validated());

        return CustomerTicketItemResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * List orders of a customer.
     */
    public function orders(ListCustomerOrdersRequest $request, int $id): AnonymousResourceCollection
    {
        $paginator = $this->service->listOrders($id, $request->validated());

        return CustomerOrderItemResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * List payment receipts of a customer.
     */
    public function payments(ListCustomerPaymentsRequest $request, int $id): AnonymousResourceCollection
    {
        $paginator = $this->service->listPayments($id, $request->validated());

        return CustomerPaymentItemResource::collection($paginator)->additional(['success' => true]);
    }
}
