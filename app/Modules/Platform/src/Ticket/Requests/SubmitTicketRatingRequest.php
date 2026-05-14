<?php

namespace App\Modules\Platform\Ticket\Requests;

use App\Common\Requests\BaseFormRequest;

class SubmitTicketRatingRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resident_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'resident_rating_comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resident_rating.required' => 'Vui lòng chọn điểm đánh giá.',
            'resident_rating.integer' => 'Điểm đánh giá phải là số nguyên.',
            'resident_rating.min' => 'Điểm đánh giá tối thiểu là 1.',
            'resident_rating.max' => 'Điểm đánh giá tối đa là 5.',
            'resident_rating_comment.max' => 'Nhận xét không được vượt quá 1000 ký tự.',
        ];
    }
}
