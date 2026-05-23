<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecommendationConfigCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_id' => ['required', 'integer'],
            'store_id' => ['nullable', 'integer'],
            'product_id' => ['nullable'],
            'variant_id' => ['nullable'],
            'sku' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:40'],
        ];
    }
}
