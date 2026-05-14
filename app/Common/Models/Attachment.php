<?php

namespace App\Common\Models;

use App\Common\Contracts\StorageServiceInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Attachment extends Model
{
    use CentralConnection;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected $appends = ['url'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->file_path
                ? app(StorageServiceInterface::class)->getUrl($this->file_path)
                : null,
        );
    }
}
