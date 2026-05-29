<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'virtual_try_on_enabled' => ['nullable', 'boolean'],
            'custom_variations' => ['nullable', 'array', 'max:8'],
            'custom_variations.*.field' => ['required_with:custom_variations', 'in:bust,waist,hip,height,weight,length,shoulder,composite'],
            'custom_variations.*.mode' => ['nullable', 'in:restricted,wide'],
            'custom_variations.*.min' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'custom_variations.*.max' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'custom_variations.*.note' => ['nullable', 'string', 'max:500'],
            'rows' => ['nullable', 'array', 'min:1'],
            'rows.*.size_label' => ['required_with:rows', 'string', 'max:40'],
            'rows.*.note' => ['nullable', 'string', 'max:500'],
            'rows.*.size_note' => ['nullable', 'string', 'max:500'],
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
            'rows.*.measurement_notes' => ['nullable', 'array'],
            'rows.*.measurement_notes.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('custom_variations', []) as $index => $variation) {
                $mode = $variation['mode'] ?? 'restricted';
                $min = $variation['min'] ?? null;
                $max = $variation['max'] ?? null;

                if ($mode === 'restricted' && ($min === null || $min === '' || $max === null || $max === '')) {
                    $validator->errors()->add("custom_variations.{$index}.min", 'Informe mínimo e máximo para variação restrita.');
                }

                if ($min !== null && $min !== '' && $max !== null && $max !== '' && (float) $min > (float) $max) {
                    $validator->errors()->add("custom_variations.{$index}.max", 'O máximo da variação precisa ser maior ou igual ao mínimo.');
                }
            }
        });
    }
}
