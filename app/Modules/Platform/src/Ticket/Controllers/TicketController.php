<?php

namespace App\Modules\Platform\Ticket\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\Ticket\Contracts\TicketServiceInterface;
use App\Modules\Platform\Ticket\Requests\ListTicketRequest;
use App\Modules\Platform\Ticket\Requests\SubmitTicketRequest;
use App\Modules\Platform\Ticket\Resources\TicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Tickets
 */
class TicketController extends BaseController
{
    public function __construct(
        protected TicketServiceInterface $service,
    ) {}

    /**
     * List tickets (requires requester auth).
     */
    public function index(ListTicketRequest $request): AnonymousResourceCollection
    {
        return TicketResource::collection($this->service->list($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Get a single ticket (requires requester auth).
     */
    public function show(int $id): TicketResource
    {
        return new TicketResource($this->service->findById($id));
    }

    /**
     * Lookup organization and project names (public, no auth).
     */
    public function lookup(Request $request): JsonResponse
    {
        $result = $this->service->lookup(
            $request->query('org_id'),
            $request->filled('project_id') ? (int) $request->query('project_id') : null,
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Submit a new ticket (public, no auth).
     */
    public function submit(SubmitTicketRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['attachments'] = $request->file('attachments', []);

        return (new TicketResource($this->service->submit($data)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
