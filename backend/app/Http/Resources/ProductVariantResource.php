<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'external_variant_id' => $this->external_variant_id,
            'sku' => $this->sku,
            'size_label' => $this->size_label,
            'color' => $this->color,
            'price' => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
