<?php

namespace App\Modules\PMC\AcceptanceReport\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\AcceptanceReport\Models\AcceptanceReport;

class AcceptanceReportRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new AcceptanceReport);
    }

    public function findByOrderId(int $orderId): ?AcceptanceReport
    {
        /** @var AcceptanceReport|null */
        return $this->newQuery()
            ->where('order_id', $orderId)
            ->first();
    }

    public function findByToken(string $token): ?AcceptanceReport
    {
        /** @var AcceptanceReport|null */
        return $this->newQuery()
            ->where('share_token', $token)
            ->first();
    }
}
