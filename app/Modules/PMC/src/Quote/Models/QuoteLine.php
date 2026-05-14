<?php

namespace App\Modules\PMC\Quote\Models;

use App\Modules\PMC\Quote\Enums\QuoteLineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLine extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'quote_id',
        'line_type',
        'reference_id',
        'name',
        'quantity',
        'unit',
        'unit_price',
        'purchase_price',
        'line_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_type' => QuoteLineType::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'line_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    protected static function newFactory(): \Database\Factories\Tenant\QuoteLineFactory
    {
        return \Database\Factories\Tenant\QuoteLineFactory::new();
    }
}
