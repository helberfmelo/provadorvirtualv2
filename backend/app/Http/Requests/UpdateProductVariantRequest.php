<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_variant_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:120'],
            'size_label' => ['sometimes', 'required', 'string', 'max:40'],
            'color' => ['sometimes', 'nullable', 'string', 'max:80'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'stock_quantity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
