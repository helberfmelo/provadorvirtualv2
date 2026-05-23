<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_company_id' => $this->merchant_company_id,
            'name' => $this->name,
            'product_type' => $this->product_type,
            'gender' => $this->gender,
            'fit_profile' => $this->fit_profile,
            'unit' => $this->unit,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'rows_count' => $this->whenCounted('rows'),
            'products_count' => $this->whenCounted('products'),
            'rows' => MeasurementTableRowResource::collection($this->whenLoaded('rows')),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'platform' => $this->company?->platform,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
