<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_company_id' => $this->merchant_company_id,
            'measurement_table_id' => $this->measurement_table_id,
            'external_product_id' => $this->external_product_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'gender' => $this->gender,
            'fit_profile' => $this->fit_profile,
            'status' => $this->status,
            'image_url' => $this->image_url,
            'variants_count' => $this->whenCounted('variants'),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'platform' => $this->company?->platform,
            ]),
            'measurement_table' => new MeasurementTableResource($this->whenLoaded('measurementTable')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
