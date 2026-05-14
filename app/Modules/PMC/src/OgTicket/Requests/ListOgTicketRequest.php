<?php

namespace App\Modules\PMC\OgTicket\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use Illuminate\Validation\Rule;

class ListOgTicketRequest extends BaseFormRequest
{
    /**
     * Normalize string "true"/"false" (from query string) to real booleans
     * so Laravel's `boolean` rule accepts them.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('has_warranty_request')) {
            $raw = $this->input('has_warranty_request');
            if (is_string($raw)) {
                $normalized = strtolower($raw);
                if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
                    $this->merge(['has_warranty_request' => true]);
                } elseif (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
                    $this->merge(['has_warranty_request' => false]);
                } elseif ($normalized === '' || $normalized === 'null') {
                    $this->merge(['has_warranty_request' => null]);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(OgTicketStatus::values())],
            'priority' => ['nullable', 'string', Rule::in(OgTicketPriority::values())],
            'assignee_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', Rule::exists('og_ticket_categories', 'id')],
            'has_warranty_request' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:subject,status,priority,received_at,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
