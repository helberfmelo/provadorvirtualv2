<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WidgetUsageAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(['today', '7d', '30d', '90d', 'custom'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'product_id' => ['nullable', 'integer'],
            'measurement_table_id' => ['nullable', 'integer'],
            'platform' => ['nullable', 'string', 'max:40'],
            'device_type' => ['nullable', 'string', Rule::in(['desktop', 'mobile', 'tablet'])],
            'brand' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
        ];
    }
}
