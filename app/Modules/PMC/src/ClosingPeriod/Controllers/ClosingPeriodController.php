<?php

namespace App\Modules\PMC\ClosingPeriod\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\ClosingPeriod\Contracts\ClosingPeriodServiceInterface;
use App\Modules\PMC\ClosingPeriod\Requests\AddOrdersRequest;
use App\Modules\PMC\ClosingPeriod\Requests\CloseClosingPeriodRequest;
use App\Modules\PMC\ClosingPeriod\Requests\CreateClosingPeriodRequest;
use App\Modules\PMC\ClosingPeriod\Requests\ListClosingPeriodRequest;
use App\Modules\PMC\ClosingPeriod\Requests\ReopenClosingPeriodRequest;
use App\Modules\PMC\ClosingPeriod\Resources\ClosingPeriodDetailResource;
use App\Modules\PMC\ClosingPeriod\Resources\ClosingPeriodListResource;
use App\Modules\PMC\ClosingPeriod\Resources\EligibleOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Closing Periods
 */
class ClosingPeriodController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected ClosingPeriodServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:closing-periods.view', only: ['index', 'show', 'eligibleOrders']),
            new Middleware('permission:closing-periods.store', only: ['store', 'addOrders']),
            new Middleware('permission:closing-periods.update', only: ['close', 'reopen']),
            new Middleware('permission:closing-periods.destroy', only: ['destroy', 'removeOrder']),
        ];
    }

    /**
     * List all closing periods.
     */
    public function index(ListClosingPeriodRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return ClosingPeriodListResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a closing period by ID.
     */
    public function show(int $id): ClosingPeriodDetailResource
    {
        return new ClosingPeriodDetailResource($this->service->findById($id));
    }

    /**
     * Create a new closing period.
     */
    public function store(CreateClosingPeriodRequest $request): JsonResponse
    {
        $period = $this->service->create($request->validated());

        return (new ClosingPeriodDetailResource($this->service->findById($period->id)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get eligible orders for a closing period.
     */
    public function eligibleOrders(int $id): AnonymousResourceCollection
    {
        $orders = $this->service->getEligibleOrders($id);

        return EligibleOrderResource::collection($orders)->additional(['success' => true]);
    }

    /**
     * Add orders to a closing period.
     */
    public function addOrders(AddOrdersRequest $request, int $id): ClosingPeriodDetailResource
    {
        return new ClosingPeriodDetailResource(
            $this->service->addOrders($id, $request->validated('order_ids'))
        );
    }

    /**
     * Remove an order from a closing period.
     */
    public function removeOrder(int $id, int $orderId): ClosingPeriodDetailResource
    {
        return new ClosingPeriodDetailResource(
            $this->service->removeOrder($id, $orderId)
        );
    }

    /**
     * Close a closing period.
     */
    public function close(CloseClosingPeriodRequest $request, int $id): ClosingPeriodDetailResource
    {
        return new ClosingPeriodDetailResource(
            $this->service->close($id, $request->validated())
        );
    }

    /**
     * Reopen a closed closing period.
     */
    public function reopen(ReopenClosingPeriodRequest $request, int $id): ClosingPeriodDetailResource
    {
        return new ClosingPeriodDetailResource(
            $this->service->reopen($id, $request->validated())
        );
    }

    /**
     * Delete a closing period.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['message' => 'Deleted successfully']);
    }
}
