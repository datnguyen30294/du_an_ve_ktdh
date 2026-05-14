<?php

namespace App\Modules\PMC\Quote\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use Illuminate\Validation\Rule;

class CreateQuoteRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'og_ticket_id' => ['required', 'integer', Rule::exists('og_tickets', 'id')->whereNull('deleted_at')],
            'status' => ['required', 'string', Rule::in([QuoteStatus::Draft->value, QuoteStatus::Sent->value])],
            'note' => ['nullable', 'string', 'max:1000'],
            'replace_active' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_type' => ['required', 'string', Rule::in(QuoteLineType::values())],
            'lines.*.reference_id' => ['required', 'integer', Rule::exists('catalog_items', 'id')->whereNull('deleted_at')],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'og_ticket_id.required' => 'Vui lòng chọn ticket.',
            'og_ticket_id.exists' => 'Ticket không tồn tại.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'lines.required' => 'Báo giá phải có ít nhất 1 dòng.',
            'lines.min' => 'Báo giá phải có ít nhất 1 dòng.',
            'lines.*.line_type.in' => 'Loại hạng mục không hợp lệ.',
            'lines.*.reference_id.exists' => 'Hạng mục không tồn tại trong danh mục.',
            'lines.*.name.required' => 'Tên hạng mục không được để trống.',
            'lines.*.quantity.min' => 'Số lượng phải lớn hơn 0.',
            'lines.*.unit_price.min' => 'Đơn giá không được âm.',
            'lines.*.purchase_price.min' => 'Giá nhập không được âm.',
        ];
    }
}
