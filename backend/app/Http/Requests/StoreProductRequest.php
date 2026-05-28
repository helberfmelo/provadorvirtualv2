<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_company_id' => ['nullable', 'integer'],
            'measurement_table_id' => ['nullable', 'integer'],
            'external_product_id' => ['nullable', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'string', 'max:40'],
            'fit_profile' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:active,draft,inactive'],
            'image_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
