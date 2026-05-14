<?php

namespace App\Modules\Platform\Ticket\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\Ticket\Contracts\TicketServiceInterface;
use App\Modules\Platform\Ticket\Requests\SubmitWarrantyRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Ticket Warranty
 */
class TicketWarrantyController extends BaseController
{
    public function __construct(
        protected TicketServiceInterface $service,
    ) {}

    /**
     * Submit a warranty request for a completed order (public, no auth).
     */
    public function submit(SubmitWarrantyRequest $request, string $code): JsonResponse
    {
        $this->service->submitWarrantyRequest(
            $code,
            $request->only(['subject', 'description']),
            $request->file('attachments', []),
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu bảo hành. Chúng tôi sẽ liên hệ với bạn sớm.',
        ]);
    }
}
