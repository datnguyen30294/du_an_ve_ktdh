<?php

namespace App\Modules\PMC\OgTicket\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\OgTicket\Contracts\OgTicketServiceInterface;
use App\Modules\PMC\OgTicket\Requests\ClaimTicketRequest;
use App\Modules\PMC\OgTicket\Requests\CreateOgTicketRequest;
use App\Modules\PMC\OgTicket\Requests\ListOgTicketRequest;
use App\Modules\PMC\OgTicket\Requests\ListPoolRequest;
use App\Modules\PMC\OgTicket\Requests\ReleaseOgTicketRequest;
use App\Modules\PMC\OgTicket\Requests\SyncOgTicketCategoriesRequest;
use App\Modules\PMC\OgTicket\Requests\TransitionOgTicketRequest;
use App\Modules\PMC\OgTicket\Requests\UpdateOgTicketRequest;
use App\Modules\PMC\OgTicket\Resources\OgTicketDetailResource;
use App\Modules\PMC\OgTicket\Resources\OgTicketListResource;
use App\Modules\PMC\OgTicket\Resources\PoolTicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags OG Tickets
 */
class OgTicketController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected OgTicketServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ticket-pool.view', only: ['pool']),
            new Middleware('permission:ticket-pool.store', only: ['claim']),
            new Middleware('permission:og-tickets.view', only: ['index', 'show', 'audits']),
            new Middleware('permission:og-tickets.store', only: ['store']),
            new Middleware('permission:og-tickets.update', only: ['update', 'transition', 'release', 'syncCategories']),
            new Middleware('permission:og-tickets.destroy', only: ['destroy', 'checkDelete']),
        ];
    }

    /**
     * List available tickets from pool.
     */
    public function pool(ListPoolRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->getPool($request->validated());

        return PoolTicketResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Claim a ticket from pool — creates og_ticket.
     */
    public function claim(ClaimTicketRequest $request): JsonResponse
    {
        $ogTicket = $this->service->claim($request->validated());

        return (new OgTicketListResource($ogTicket))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Admin manual-create an og_ticket (also creates the central Ticket).
     */
    public function store(CreateOgTicketRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['attachments'] = $request->file('attachments', []);

        $ogTicket = $this->service->create($data);

        return (new OgTicketDetailResource($ogTicket))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * List og_tickets for the current tenant.
     */
    public function index(ListOgTicketRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return OgTicketListResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get og_ticket detail (with ticket snapshot + relationships).
     */
    public function show(int $id): OgTicketDetailResource
    {
        return new OgTicketDetailResource($this->service->findById($id));
    }

    /**
     * Update og_ticket processing fields.
     */
    public function update(UpdateOgTicketRequest $request, int $id): OgTicketDetailResource
    {
        $data = $request->validated();
        $data['attachments'] = $request->file('attachments', []);

        return new OgTicketDetailResource($this->service->update($id, $data));
    }

    /**
     * Manually transition og_ticket status.
     */
    public function transition(TransitionOgTicketRequest $request, int $id): OgTicketDetailResource
    {
        return new OgTicketDetailResource($this->service->manualTransition($id, $request->validated()));
    }

    /**
     * Delete an og_ticket.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }

    /**
     * Check if an og_ticket can be deleted.
     */
    public function checkDelete(int $id): JsonResponse
    {
        return response()->json($this->service->checkDelete($id));
    }

    /**
     * Cancel og_ticket and release ticket back to pool.
     */
    public function release(ReleaseOgTicketRequest $request, int $id): OgTicketListResource
    {
        return new OgTicketListResource($this->service->release($id, $request->validated()));
    }

    /**
     * Get audit history for an og_ticket.
     *
     * @return \Illuminate\Http\JsonResponse<array{
     *     success: bool,
     *     data: array<int, array{
     *         id: int,
     *         event: string,
     *         old_values: array<string, mixed>|null,
     *         new_values: array<string, mixed>|null,
     *         user: array{id: int, name: string}|null,
     *         created_at: string|null,
     *     }>,
     * }>
     */
    public function audits(int $id): JsonResponse
    {
        $audits = $this->service->getAudits($id);

        return response()->json(['success' => true, 'data' => $audits]);
    }

    /**
     * Sync og_ticket_categories for an og_ticket.
     */
    public function syncCategories(SyncOgTicketCategoriesRequest $request, int $id): OgTicketDetailResource
    {
        /** @var array<int, int> $ids */
        $ids = $request->validated('category_ids', []);

        return new OgTicketDetailResource($this->service->syncCategories($id, $ids));
    }
}
