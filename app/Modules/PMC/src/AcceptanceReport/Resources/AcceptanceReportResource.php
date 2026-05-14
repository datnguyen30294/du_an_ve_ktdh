<?php

namespace App\Modules\PMC\AcceptanceReport\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\AcceptanceReport\Models\AcceptanceReport;
use Illuminate\Http\Request;

/**
 * @mixin AcceptanceReport
 */
class AcceptanceReportResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'content_html' => $this->content_html,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'note' => $this->note,
            'share_token' => $this->share_token,
            'created_by_account_id' => $this->created_by_account_id,
            'confirmed_at' => optional($this->confirmed_at)->toIso8601String(),
            'confirmed_signature_name' => $this->confirmed_signature_name,
            'confirmed_note' => $this->confirmed_note,
            'is_confirmed' => $this->confirmed_at !== null,
            'signed_file_url' => $this->signed_file_url,
            'signed_file_original_name' => $this->signed_file_original_name,
            'signed_file_mime' => $this->signed_file_mime,
            'signed_file_size' => $this->signed_file_size,
            'signed_uploaded_at' => optional($this->signed_uploaded_at)->toIso8601String(),
            'has_signed_file' => $this->signed_file_path !== null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
