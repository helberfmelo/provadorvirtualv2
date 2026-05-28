<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FitProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_company_id' => $this->merchant_company_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'product_type' => $this->product_type,
            'gender' => $this->gender,
            'fit_intensity' => $this->fit_intensity ?: 'regular',
            'stretch_level' => $this->stretch_level ?: 'medium',
            'status' => $this->status,
            'products_count' => (int) ($this->products_count ?? 0),
            'measurement_tables_count' => (int) ($this->measurement_tables_count ?? 0),
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
