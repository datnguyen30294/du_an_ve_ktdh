<?php

namespace App\Modules\PMC\Customer\Models;

use App\Common\Models\BaseModel;
use App\Common\Support\PhoneNormalizer;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Customer extends BaseModel implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /**
     * Safe alphabet for customer codes — no O/0/I/1/L to avoid look-alike
     * ambiguity when a staff member reads the code aloud to a customer.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 6;

    /** @var string */
    protected $table = 'pmc_customers';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'full_name',
        'phone',
        'email',
        'note',
        'first_contacted_at',
        'last_contacted_at',
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
            'first_contacted_at' => 'datetime',
            'last_contacted_at' => 'datetime',
        ];
    }

    /**
     * Normalize phone via PhoneNormalizer before persisting.
     */
    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = PhoneNormalizer::normalize($value);
    }

    /**
     * Auto-generate a random non-sequential code before insert when missing.
     */
    protected static function booted(): void
    {
        static::creating(function (self $customer): void {
            if (! $customer->code) {
                $customer->code = self::generateCode();
            }
        });
    }

    private static function generateCode(): string
    {
        $out = '';
        $max = strlen(self::CODE_ALPHABET) - 1;

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $out .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return 'KH-'.$out;
    }

    /**
     * @return HasMany<OgTicket, $this>
     */
    public function ogTickets(): HasMany
    {
        return $this->hasMany(OgTicket::class, 'customer_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('full_name', static::likeOperator(), "%{$keyword}%")
                ->orWhere('phone', static::likeOperator(), "%{$keyword}%")
                ->orWhere('email', static::likeOperator(), "%{$keyword}%")
                ->orWhere('code', static::likeOperator(), "%{$keyword}%");
        });
    }

    protected static function newFactory(): \Database\Factories\Tenant\CustomerFactory
    {
        return \Database\Factories\Tenant\CustomerFactory::new();
    }
}
