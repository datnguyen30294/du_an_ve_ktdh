<?php

namespace App\Modules\PMC\Quote\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Http\Request;

/**
 * Wraps the check-active result: has_active_quote flag + optional active quote with lines.
 *
 * @mixin Quote
 */
class CheckActiveQuoteResource extends BaseResource
{
    /** @var Quote|null */
    public $resource;

    /**
     * Create a new resource from the active quote (or null).
     */
    public function __construct(
        ?Quote $activeQuote,
        protected float $commissionFixedTotal = 0,
    ) {
        parent::__construct($activeQuote);
    }

    /**
     * @return array{
     *     has_active_quote: bool,
     *     active_quote: array{
     *         id: int,
     *         code: string,
     *         status: array{value: string, label: string},
     *         lines: QuoteLineResource[],
     *     }|null,
     *     commission_fixed_total: float,
     * }
     */
    public function toArray(Request $request): array
    {
        $base = [
            /** @var float */
            'commission_fixed_total' => $this->commissionFixedTotal,
        ];

        if (! $this->resource) {
            return array_merge($base, [
                /** @var bool */
                'has_active_quote' => false,
                /** @var null */
                'active_quote' => null,
            ]);
        }

        return array_merge($base, [
            /** @var bool */
            'has_active_quote' => true,
            'active_quote' => [
                /** @var int */
                'id' => $this->resource->id,
                /** @var string */
                'code' => $this->resource->code,
                'status' => [
                    'value' => $this->resource->status->value,
                    'label' => $this->resource->status->label(),
                ],
                /** @var QuoteLineResource[] */
                'lines' => $this->whenLoaded('lines', fn () => QuoteLineResource::collection($this->resource->lines)),
            ],
        ]);
    }
}
