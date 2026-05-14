<?php

namespace App\Modules\PMC\ClosingPeriod\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus;
use Illuminate\Validation\Rule;

class UpdatePayoutStatusRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'snapshot_ids' => ['required', 'array', 'min:1'],
            'snapshot_ids.*' => ['required', 'integer', Rule::exists('order_commission_snapshots', 'id')],
            'payout_status' => ['required', 'string', Rule::in(PayoutStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'snapshot_ids.required' => 'Vui lòng chọn ít nhất một dòng hoa hồng.',
            'snapshot_ids.min' => 'Vui lòng chọn ít nhất một dòng hoa hồng.',
            'snapshot_ids.*.exists' => 'Dòng hoa hồng không tồn tại.',
            'payout_status.required' => 'Vui lòng chọn trạng thái thanh toán.',
            'payout_status.in' => 'Trạng thái thanh toán không hợp lệ.',
        ];
    }
}
