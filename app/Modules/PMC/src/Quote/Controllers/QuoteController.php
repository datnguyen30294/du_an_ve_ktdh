<?php

namespace App\Modules\PMC\Quote\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Quote\Contracts\QuoteServiceInterface;
use App\Modules\PMC\Quote\Requests\CheckActiveQuoteRequest;
use App\Modules\PMC\Quote\Requests\CreateQuoteRequest;
use App\Modules\PMC\Quote\Requests\ListQuoteRequest;
use App\Modules\PMC\Quote\Requests\TransitionQuoteRequest;
use App\Modules\PMC\Quote\Requests\UpdateQuoteRequest;
use App\Modules\PMC\Quote\Resources\CheckActiveQuoteResource;
use App\Modules\PMC\Quote\Resources\QuoteDetailResource;
use App\Modules\PMC\Quote\Resources\QuoteListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Quotes
 */
class QuoteController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected QuoteServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:quotes.view', only: ['index', 'show', 'checkActive', 'audits', 'versions']),
            new Middleware('permission:quotes.store', only: ['store']),
            new Middleware('permission:quotes.update', only: ['update', 'transition']),
            new Middleware('permission:quotes.destroy', only: ['destroy', 'checkDelete']),
        ];
    }

    /**
     * List quotes with filters and pagination.
     */
    public function index(ListQuoteRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return QuoteListResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get quote detail with lines.
     */
    public function show(int $id): QuoteDetailResource
    {
        return new QuoteDetailResource($this->service->findById($id));
    }

    /**
     * Check if an OgTicket has an active quote. Also returns commission fixed total.
     */
    public function checkActive(CheckActiveQuoteRequest $request): CheckActiveQuoteResource
    {
        $ogTicketId = (int) $request->validated('og_ticket_id');
        $activeQuote = $this->service->checkActive($ogTicketId);
        $fixedTotal = $this->service->getCommissionFixedTotal($ogTicketId);

        return new CheckActiveQuoteResource($activeQuote, $fixedTotal);
    }

    /**
     * Get all quote versions for an OgTicket (active first, then by created_at desc).
     */
    public function versions(int $ogTicketId): AnonymousResourceCollection
    {
        $quotes = $this->service->getVersionsByTicket($ogTicketId);

        return QuoteDetailResource::collection($quotes)->additional(['success' => true]);
    }

    /**
     * Create a new quote with lines.
     */
    public function store(CreateQuoteRequest $request): JsonResponse
    {
        $quote = $this->service->create($request->validated());

        return (new QuoteDetailResource($quote))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a draft + active quote.
     */
    public function update(UpdateQuoteRequest $request, int $id): QuoteDetailResource
    {
        return new QuoteDetailResource($this->service->update($id, $request->validated()));
    }

    /**
     * Transition quote status. State machine validates allowed transitions.
     */
    public function transition(TransitionQuoteRequest $request, int $id): QuoteDetailResource
    {
        return new QuoteDetailResource($this->service->transition($id, $request->validated()));
    }

    /**
     * Deactivate a quote (set is_active = false).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }

    /**
     * Check if a quote can be deleted.
     */
    public function checkDelete(int $id): JsonResponse
    {
        return response()->json($this->service->checkDelete($id));
    }

    /**
     * Get audit history for a quote.
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
}
