<?php

namespace App\Modules\PMC\OgTicketCategory\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\OgTicketCategory\Models\OgTicketCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OgTicketCategoryRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new OgTicketCategory);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()->withCount('ogTickets');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $this->applySorting($query, $filters, 'sort_order', 'asc');

        return $query->paginate($this->getPerPage($filters));
    }

    /**
     * @return Collection<int, OgTicketCategory>
     */
    public function listAll(): Collection
    {
        /** @var Collection<int, OgTicketCategory> */
        return $this->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function countLinks(int $id): int
    {
        return OgTicketCategory::query()
            ->whereKey($id)
            ->withCount('ogTickets')
            ->value('og_tickets_count') ?? 0;
    }

    /**
     * Find or create category by name (case-insensitive, Unicode-safe). Auto-generate unique code.
     * Uses PHP-side mb_strtolower to avoid DB LOWER() Unicode limitations (SQLite).
     */
    public function firstOrCreateByName(string $name): OgTicketCategory
    {
        $name = trim($name);
        $lower = mb_strtolower($name);

        /** @var OgTicketCategory|null $existing */
        $existing = $this->newQuery()
            ->get(['id', 'name'])
            ->first(fn (OgTicketCategory $c) => mb_strtolower((string) $c->name) === $lower);

        if ($existing) {
            return $this->findByIdOrFail($existing->id);
        }

        return $this->newQuery()->create([
            'name' => $name,
            'code' => $this->generateUniqueCode($name),
        ]);
    }

    private function findByIdOrFail(int $id): OgTicketCategory
    {
        /** @var OgTicketCategory */
        return $this->newQuery()->findOrFail($id);
    }

    private function generateUniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $code = $base;
        $counter = 1;

        while ($this->newQuery()->where('code', $code)->exists()) {
            $code = "{$base}-{$counter}";
            $counter++;
        }

        return $code;
    }
}
