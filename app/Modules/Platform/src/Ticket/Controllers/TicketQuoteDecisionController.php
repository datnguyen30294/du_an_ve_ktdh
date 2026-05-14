<?php

namespace App\Modules\Platform\Ticket\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\Ticket\Contracts\TicketServiceInterface;
use App\Modules\Platform\Ticket\Requests\SubmitQuoteDecisionRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Ticket Quote Decision
 */
class TicketQuoteDecisionController extends BaseController
{
    public function __construct(
        protected TicketServiceInterface $service,
    ) {}

    /**
     * Submit resident quote decision — approve or reject (public, no auth).
     */
    public function submit(SubmitQuoteDecisionRequest $request, string $code): JsonResponse
    {
        $validated = $request->validated();

        $this->service->submitQuoteDecision(
            $code,
            $validated['action'],
            $validated['reason'] ?? null,
        );

        $message = $validated['action'] === 'approve'
            ? 'Bạn đã chấp thuận báo giá.'
            : 'Bạn đã từ chối báo giá.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
