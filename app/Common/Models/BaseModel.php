<?php

namespace App\Common\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class BaseModel extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Serialize dates to ISO 8601 format with timezone.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * Return the appropriate LIKE operator for the current database driver.
     * PostgreSQL uses ILIKE for case-insensitive matching; others use LIKE.
     */
    public static function likeOperator(): string
    {
        $driver = config('database.connections.'.config('database.default').'.driver');

        return $driver === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Scope to get records created recently (default: last 7 days).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to order by latest creation date.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }
}
