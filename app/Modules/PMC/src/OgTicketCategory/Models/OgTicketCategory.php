<?php

namespace App\Modules\PMC\OgTicketCategory\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OgTicketCategory extends BaseModel
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<OgTicket, $this>
     */
    public function ogTickets(): BelongsToMany
    {
        return $this->belongsToMany(OgTicket::class, 'og_ticket_category_links')
            ->withTimestamps();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('name', static::likeOperator(), "%{$keyword}%")
                ->orWhere('code', static::likeOperator(), "%{$keyword}%");
        });
    }
}
