<?php

namespace App\Modules\PMC\Catalog\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Catalog\Enums\CatalogItemType;
use App\Modules\PMC\Catalog\Enums\CatalogStatus;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use Illuminate\Validation\Rule;

class UpdateCatalogItemRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');
        $type = CatalogItem::query()->where('id', $id)->value('type');
        $isAdhoc = $type === CatalogItemType::Adhoc->value;

        return [
            'code' => $isAdhoc
                ? ['nullable', 'string', 'max:50']
                : [
                    'sometimes', 'required', 'string', 'max:50',
                    Rule::unique('catalog_items', 'code')
                        ->where('type', $type)
                        ->whereNull('deleted_at')
                        ->ignore($id),
                ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'supplier_id' => ['nullable', 'integer', 'exists:catalog_suppliers,id'],
            'service_category_id' => $type === CatalogItemType::Service->value
                ? ['sometimes', 'required', 'integer', 'exists:service_categories,id']
                : ['nullable'],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'price_note' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::enum(CatalogStatus::class)],
            'is_published' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Mã đã tồn tại.',
            'name.required' => 'Tên là bắt buộc.',
            'unit.required' => 'Đơn vị là bắt buộc.',
            'unit_price.required' => 'Đơn giá là bắt buộc.',
            'unit_price.min' => 'Giá bán không được âm.',
            'purchase_price.min' => 'Giá mua không được âm.',
            'commission_rate.min' => 'Tỷ lệ hoa hồng không được âm.',
            'commission_rate.max' => 'Tỷ lệ hoa hồng không được vượt quá 100%.',
            'supplier_id.exists' => 'Nhà cung cấp không tồn tại.',
            'service_category_id.required' => 'Danh mục dịch vụ là bắt buộc cho loại dịch vụ.',
            'service_category_id.exists' => 'Danh mục dịch vụ không tồn tại.',
            'status.enum' => 'Trạng thái không hợp lệ.',
        ];
    }
}
