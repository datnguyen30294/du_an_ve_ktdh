<?php

namespace App\Modules\PMC\Treasury\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Treasury\Models\CashAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CashAccountRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new CashAccount);
    }

    /**
     * @return Collection<int, CashAccount>
     */
    public function listActive(): Collection
    {
        /** @var Collection<int, CashAccount> */
        return $this->newQuery()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function findDefault(): ?CashAccount
    {
        /** @var CashAccount|null */
        return $this->newQuery()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public function findById(int|string $id, array $columns = ['*'], array $relations = []): Model
    {
        return parent::findById($id, $columns, $relations);
    }

    /**
     * Fetch a cash account with a row-level lock so concurrent withdrawals
     * serialize on the account row. The caller MUST be inside a DB transaction.
     */
    public function findByIdForUpdate(int $id): CashAccount
    {
        /** @var CashAccount */
        return $this->newQuery()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
