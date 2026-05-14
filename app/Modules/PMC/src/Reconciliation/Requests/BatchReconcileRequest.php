<?php

namespace App\Modules\PMC\Reconciliation\Requests;

use App\Common\Requests\BaseFormRequest;

class BatchReconcileRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:financial_reconciliations,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách đối soát là bắt buộc.',
            'ids.min' => 'Phải chọn ít nhất 1 bản ghi.',
            'ids.*.exists' => 'Bản ghi đối soát không tồn tại.',
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự.',
        ];
    }
}
