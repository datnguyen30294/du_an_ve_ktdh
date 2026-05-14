<?php

namespace App\Modules\Platform\Ticket\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\Platform\Ticket\Models\Ticket;
use Illuminate\Http\Request;

/**
 * Public ticket info for resident rating page.
 *
 * @mixin Ticket
 */
class TicketRatingInfoResource extends BaseResource
{
    /**
     * @return array{
     *     code: string,
     *     subject: string,
     *     requester_name: string,
     *     requester_phone: string|null,
     *     description: string|null,
     *     address: string|null,
     *     status: array{value: string, label: string},
     *     channel: array{value: string, label: string},
     *     created_at: string|null,
     *     is_ratable: bool,
     *     rating: array{rating: int, comment: string|null, rated_at: string|null}|null,
     *     order: array{code: string, status: array{value: string, label: string}, total_amount: string, lines: list<array{name: string, quantity: int, unit: string, unit_price: string, line_amount: string, line_type: array{value: string, label: string}}>}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string */
            'code' => $this->code,
            /** @var array{id: int, name: string, phone: string, address: string|null}|null */
            'customer' => $this->relationLoaded('customer') && $this->customer
                ? [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'phone' => $this->customer->phone,
                    'address' => $this->customer->address,
                ]
                : null,
            /** @var string */
            'subject' => $this->subject,
            /** @var string */
            'requester_name' => $this->requester_name,
            /** @var string|null */
            'requester_phone' => $this->requester_phone,
            /** @var string|null */
            'description' => $this->description,
            /** @var string|null */
            'address' => $this->address,
            /** @var array{value: string, label: string} */
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var array{value: string, label: string} */
            'channel' => [
                'value' => $this->channel->value,
                'label' => $this->channel->label(),
            ],
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
            /** @var bool */
            'is_ratable' => (bool) $this->getAttribute('is_ratable'),
            /** @var array{rating: int, comment: string|null, rated_at: string|null}|null */
            'rating' => $this->resident_rating !== null ? [
                'rating' => $this->resident_rating,
                'comment' => $this->resident_rating_comment,
                'rated_at' => $this->resident_rated_at?->toIso8601String(),
            ] : null,
            /** @var array{code: string, status: array{value: string, label: string}, total_amount: string, lines: list<array{name: string, quantity: int, unit: string, unit_price: string, line_amount: string, line_type: array{value: string, label: string}}>}|null */
            'order' => $this->getAttribute('order_info'),
            /** @var array{code: string, status: array{value: string, label: string}, total_amount: string, lines: list<array{name: string, quantity: int, unit: string, unit_price: string, line_amount: string, line_type: array{value: string, label: string}}>, is_resident_actionable: bool, manager_approved_at: string|null, note: string|null}|null */
            'quote' => $this->getAttribute('quote_info'),
            /** @var list<array{id: int, subject: string, description: string, requester_name: string, created_at: string|null, attachments: list<array{id: int, url: string|null, original_name: string, mime_type: string, size_bytes: int}>}> */
            'warranty_requests' => $this->getAttribute('warranty_requests') ?? [],
            /** @var bool */
            'can_request_warranty' => (bool) $this->getAttribute('can_request_warranty'),
            /** @var array{share_token: string, public_url: string, is_confirmed: bool, confirmed_at: string|null, confirmed_signature_name: string|null, is_confirmable: bool, has_signed_file: bool, signed_file_url: string|null, signed_file_original_name: string|null, signed_uploaded_at: string|null}|null */
            'acceptance_report' => $this->getAttribute('acceptance_report_info'),
        ];
    }
}
