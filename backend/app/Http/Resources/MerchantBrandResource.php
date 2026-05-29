<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantBrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_company_id' => $this->merchant_company_id,
            'normalized_brand_id' => $this->normalized_brand_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'source' => $this->source,
            'status' => $this->status,
            'product_count' => (int) ($this->product_count ?? 0),
            'normalized_product_count' => (int) ($this->normalized_product_count ?? 0),
            'aliases' => $this->aliases ?? [],
            'suggestion' => $this->suggestion ?? null,
            'metadata' => $this->metadata ?? [],
            'normalized_brand' => new NormalizedBrandResource($this->whenLoaded('normalizedBrand')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
