<?php

namespace App\Modules\PMC\AcceptanceReport\Models;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Models\BaseModel;
use App\Modules\PMC\Order\Models\Order;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcceptanceReport extends BaseModel
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'content_html',
        'customer_name',
        'customer_phone',
        'note',
        'share_token',
        'created_by_account_id',
        'confirmed_at',
        'confirmed_signature_name',
        'confirmed_note',
        'signed_file_path',
        'signed_file_original_name',
        'signed_file_mime',
        'signed_file_size',
        'signed_uploaded_at',
        'signed_uploaded_by_account_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'signed_uploaded_at' => 'datetime',
            'signed_file_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function signedFileUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->signed_file_path
                ? app(StorageServiceInterface::class)->getUrl($this->signed_file_path)
                : null,
        );
    }
}
