<?php

namespace App\Modules\Platform\Ticket\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\Ticket\Contracts\TicketServiceInterface;
use App\Modules\Platform\Ticket\Requests\SubmitTicketRatingRequest;
use App\Modules\Platform\Ticket\Resources\TicketRatingInfoResource;
use Illuminate\Http\JsonResponse;

/**
 * @tags Ticket Rating
 */
class TicketRatingController extends BaseController
{
    public function __construct(
        protected TicketServiceInterface $service,
    ) {}

    /**
     * Get public ticket info for rating page (public, no auth).
     */
    public function show(string $code): TicketRatingInfoResource
    {
        return new TicketRatingInfoResource(
            $this->service->getPublicTicketInfo($code),
        );
    }

    /**
     * Submit resident rating (public, no auth).
     */
    public function submit(SubmitTicketRatingRequest $request, string $code): JsonResponse
    {
        $this->service->submitRating($code, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cảm ơn bạn đã đánh giá!',
        ]);
    }
}
