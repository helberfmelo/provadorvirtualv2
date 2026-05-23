<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WidgetInstallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scriptUrl = url('/widget/v1/provador-virtual.js');
        $cssUrl = url('/widget/v1/provador-virtual.css');
        $product = $this->sampleProduct();

        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'merchant_company_id' => $this->merchant_company_id,
            'public_key' => $this->public_key,
            'platform' => $this->platform,
            'allowed_domains' => $this->allowed_domains ?? [],
            'theme' => $this->theme ?? [],
            'is_active' => $this->is_active,
            'script_url' => $scriptUrl,
            'css_url' => $cssUrl,
            'container_id' => 'provador-virtual-container',
            'snippet' => $this->snippet($scriptUrl, $cssUrl, $product),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'domain' => $this->company?->domain,
                'platform' => $this->company?->platform,
                'external_store_id' => $this->company?->external_store_id,
            ]),
            'sample_product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'external_product_id' => $product->external_product_id,
            ] : null,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function sampleProduct(): ?Product
    {
        return Product::query()
            ->where('merchant_id', $this->merchant_id)
            ->where('status', 'active')
            ->orderByDesc('measurement_table_id')
            ->orderBy('id')
            ->first();
    }

    private function snippet(string $scriptUrl, string $cssUrl, ?Product $product): string
    {
        $theme = htmlspecialchars(json_encode($this->theme ?? [], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

        return implode("\n", [
            '<div id="provador-virtual-container"></div>',
            '<script',
            '  src="'.$scriptUrl.'"',
            '  data-public-key="'.$this->public_key.'"',
            '  data-merchant-id="'.$this->merchant_id.'"',
            '  data-store-id="'.$this->merchant_company_id.'"',
            '  data-product-id="'.($product?->external_product_id ?? $product?->id ?? 'ID_DO_PRODUTO').'"',
            '  data-sku="'.($product?->sku ?? 'SKU_DO_PRODUTO').'"',
            '  data-platform="'.$this->platform.'"',
            '  data-container-id="provador-virtual-container"',
            '  data-css-url="'.$cssUrl.'"',
            '  data-theme="'.$theme.'"',
            '  async>',
            '</script>',
        ]);
    }
}
