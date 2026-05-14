<?php

namespace App\Modules\PMC\Treasury\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Treasury\Enums\CashAccountType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class CashAccount extends BaseModel implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'type',
        'bank_id',
        'bank_account_number',
        'bank_account_name',
        'opening_balance',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashAccountType::class,
            'opening_balance' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CashTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'cash_account_id');
    }

    protected static function newFactory(): \Database\Factories\Tenant\CashAccountFactory
    {
        return \Database\Factories\Tenant\CashAccountFactory::new();
    }
}
