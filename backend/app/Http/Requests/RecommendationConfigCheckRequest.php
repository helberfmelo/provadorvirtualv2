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
            'merchant_id' => ['nullable', 'integer'],
            'store_id' => ['nullable', 'integer'],
            'product_id' => ['nullable'],
            'variant_id' => ['nullable'],
            'sku' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $platform = mb_strtolower((string) $this->input('platform'));
            $hasMerchantId = is_numeric($this->input('merchant_id')) && (int) $this->input('merchant_id') > 0;
            $hasBigShopStore = $platform === 'bigshop'
                && is_numeric($this->input('store_id'))
                && (int) $this->input('store_id') > 0;

            if (! $hasMerchantId && ! $hasBigShopStore) {
                $validator->errors()->add('merchant_id', 'Informe merchant_id ou store_id BigShop.');
            }
        });
    }
}
