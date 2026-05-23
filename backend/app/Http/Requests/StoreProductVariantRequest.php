<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_variant_id' => ['nullable', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:120'],
            'size_label' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
