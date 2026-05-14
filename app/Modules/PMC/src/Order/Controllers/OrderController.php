<?php

namespace App\Modules\PMC\Order\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Account\Resources\AccountResource;
use App\Modules\PMC\Order\Contracts\OrderServiceInterface;
use App\Modules\PMC\Order\Requests\CreateOrderRequest;
use App\Modules\PMC\Order\Requests\ListActiveAccountsRequest;
use App\Modules\PMC\Order\Requests\ListOrderRequest;
use App\Modules\PMC\Order\Requests\SetAdvancePayerRequest;
use App\Modules\PMC\Order\Requests\TransitionOrderRequest;
use App\Modules\PMC\Order\Requests\UpdateOrderLinePricesRequest;
use App\Modules\PMC\Order\Requests\UpdateOrderRequest;
use App\Modules\PMC\Order\Resources\OrderDetailResource;
use App\Modules\PMC\Order\Resources\OrderListResource;
use App\Modules\PMC\Quote\Resources\QuoteListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Orders
 */
class OrderController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected OrderServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:orders.view', only: ['index', 'show', 'availableQuotes', 'checkDelete']),
            new Middleware('permission:orders.store', only: ['store']),
            new Middleware('permission:orders.update', only: ['update', 'transition']),
            new Middleware('permission:orders.destroy', only: ['destroy']),
        ];
    }

    /**
     * List orders with filters and pagination.
     */
    public function index(ListOrderRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return OrderListResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get order detail with lines.
     */
    public function show(int $id): OrderDetailResource
    {
        return new OrderDetailResource($this->service->findById($id));
    }

    /**
     * List available quotes for creating an order.
     */
    public function availableQuotes(): AnonymousResourceCollection
    {
        $quotes = $this->service->availableQuotes();

        return QuoteListResource::collection($quotes)->additional(['success' => true]);
    }

    /**
     * Create a new order from an approved quote.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->service->create($request->validated());

        return (new OrderDetailResource($order))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a draft order (replace lines).
     */
    public function update(UpdateOrderRequest $request, int $id): OrderDetailResource
    {
        return new OrderDetailResource($this->service->update($id, $request->validated()));
    }

    /**
     * Transition order status. State machine validates allowed transitions.
     */
    public function transition(TransitionOrderRequest $request, int $id): OrderDetailResource
    {
        return new OrderDetailResource($this->service->transition($id, $request->validated()));
    }

    /**
     * Delete a draft order (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }

    /**
     * Check if an order can be deleted.
     */
    public function checkDelete(int $id): JsonResponse
    {
        return response()->json($this->service->checkDelete($id));
    }

    /**
     * List active accounts (candidates for advance payer selection) with optional search query.
     */
    public function activeAccounts(ListActiveAccountsRequest $request): AnonymousResourceCollection
    {
        $search = $request->validated('search');
        $accounts = $this->service->listActiveAccounts($search);

        return AccountResource::collection($accounts)->additional(['success' => true]);
    }

    /**
     * Set (or clear) the advance payer on an order line.
     */
    public function setAdvancePayer(SetAdvancePayerRequest $request, int $id, int $lineId): OrderDetailResource
    {
        $advancePayerId = $request->validated('advance_payer_id');
        $order = $this->service->setAdvancePayer($id, $lineId, $advancePayerId !== null ? (int) $advancePayerId : null);

        return new OrderDetailResource($order);
    }

    /**
     * Update unit_price and purchase_price on an order line.
     * Recalculates the line amount, order total, and syncs receivable.
     */
    public function updateLinePrices(UpdateOrderLinePricesRequest $request, int $id, int $lineId): OrderDetailResource
    {
        $order = $this->service->updateLinePrices($id, $lineId, $request->validated());

        return new OrderDetailResource($order);
    }
}
