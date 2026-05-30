<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuggestMeasurementTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', 'string', Rule::in(['text', 'csv', 'image'])],
            'content' => ['required_without:image_data', 'nullable', 'string', 'max:60000'],
            'image_data' => ['required_without:content', 'nullable', 'string', 'max:1600000'],
            'filename' => ['nullable', 'string', 'max:180'],
            'name' => ['nullable', 'string', 'max:180'],
            'product_type' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', 'string', 'max:40'],
            'fit_profile' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'measurement_target' => ['nullable', Rule::in(['body', 'garment', 'mixed'])],
            'size_system' => ['nullable', Rule::in(['br_alpha', 'br_numeric', 'international', 'custom'])],
            'range_mode' => ['nullable', Rule::in(['min_max', 'exact', 'tolerance'])],
            'compare_table_id' => ['nullable', 'integer', 'min:1'],
            'explain_for_merchant' => ['nullable', 'boolean'],
            'unit' => ['nullable', Rule::in(['cm'])],
        ];
    }
}
