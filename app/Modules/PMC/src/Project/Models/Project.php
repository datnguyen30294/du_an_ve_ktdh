<?php

namespace App\Modules\PMC\Project\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Commission\Models\ProjectCommissionConfig;
use App\Modules\PMC\Project\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends BaseModel
{
    protected $fillable = [
        'code',
        'name',
        'address',
        'status',
        'bqt_bank_bin',
        'bqt_bank_name',
        'bqt_account_number',
        'bqt_account_holder',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
        ];
    }

    public function hasBqtBankInfo(): bool
    {
        return ! empty($this->bqt_bank_bin)
            && ! empty($this->bqt_account_number)
            && ! empty($this->bqt_account_holder);
    }

    /**
     * Returns bank info in the same shape as Account::bankInfo() so that
     * commission-summary can render the VietQR regardless of recipient type.
     *
     * @return array{bin: string, label: string, account_number: string, account_name: string}|null
     */
    public function bqtBankInfo(): ?array
    {
        if (! $this->hasBqtBankInfo()) {
            return null;
        }

        return [
            'bin' => (string) $this->bqt_bank_bin,
            'label' => (string) ($this->bqt_bank_name ?? ''),
            'account_number' => (string) $this->bqt_account_number,
            'account_name' => (string) $this->bqt_account_holder,
        ];
    }

    /**
     * @return BelongsToMany<Account, $this>
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_project', 'project_id', 'account_id');
    }

    /**
     * @return HasOne<ProjectCommissionConfig, $this>
     */
    public function commissionConfig(): HasOne
    {
        return $this->hasOne(ProjectCommissionConfig::class);
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, ProjectStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    protected static function newFactory(): \Database\Factories\Tenant\ProjectFactory
    {
        return \Database\Factories\Tenant\ProjectFactory::new();
    }
}
