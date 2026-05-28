<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_company_id' => ['sometimes', 'nullable', 'integer'],
            'measurement_table_id' => ['sometimes', 'nullable', 'integer'],
            'external_product_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:120'],
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:40'],
            'fit_profile' => ['sometimes', 'nullable', 'string', 'max:80'],
            'status' => ['sometimes', 'nullable', 'in:active,draft,inactive'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
