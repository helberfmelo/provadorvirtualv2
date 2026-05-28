<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecommendationSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signal' => ['required', 'string', Rule::in(['add_to_cart', 'purchase', 'return', 'exchange'])],
            'selected_size' => ['nullable', 'string', 'max:40'],
            'ordered_size' => ['nullable', 'string', 'max:40'],
            'returned_size' => ['nullable', 'string', 'max:40'],
            'exchanged_to_size' => ['nullable', 'string', 'max:40'],
            'return_reason' => ['nullable', 'string', Rule::in(['size_too_small', 'size_too_large', 'fit_issue', 'changed_mind', 'defect', 'other', 'unknown'])],
            'order_status' => ['nullable', 'string', 'max:80'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'occurred_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:80'],
            'source_platform' => ['nullable', 'string', 'max:80'],
            'order_reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
