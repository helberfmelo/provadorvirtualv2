<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeasurementTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_company_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'product_type' => ['required', 'string', 'max:80'],
            'gender' => ['nullable', 'string', 'max:40'],
            'fit_profile' => ['nullable', 'string', 'max:80'],
            'measurement_target' => ['nullable', 'in:body,garment,mixed'],
            'size_system' => ['nullable', 'in:br_alpha,br_numeric,international,custom'],
            'range_mode' => ['nullable', 'in:min_max,exact,tolerance'],
            'unit' => ['nullable', 'in:cm'],
            'status' => ['nullable', 'in:active,draft,inactive'],
            'source' => ['nullable', 'in:manual,template,import,bigshop,ai'],
            'notes' => ['nullable', 'string'],
            'rows' => ['nullable', 'array', 'min:1'],
            'rows.*.size_label' => ['required_with:rows', 'string', 'max:40'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'rows.*.bust_min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.bust_max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.waist_min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.waist_max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.hip_min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.hip_max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.height_min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.height_max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.weight_min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.weight_max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.length_min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.length_max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.shoulder_min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.shoulder_max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.measurements' => ['nullable', 'array'],
            'rows.*.measurements.*' => ['nullable', 'array'],
            'rows.*.measurements.*.label' => ['nullable', 'string', 'max:80'],
            'rows.*.measurements.*.min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.measurements.*.max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.measurements.*.value' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.composite_measurements' => ['nullable', 'array'],
            'rows.*.composite_measurements.*' => ['nullable', 'array'],
            'rows.*.composite_measurements.*.label' => ['nullable', 'string', 'max:80'],
            'rows.*.composite_measurements.*.formula' => ['nullable', 'string', 'max:160'],
            'rows.*.composite_measurements.*.min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.composite_measurements.*.max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'rows.*.composite_measurements.*.value' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ];
    }
}
