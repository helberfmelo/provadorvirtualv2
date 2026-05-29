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
            'measurement_target' => $this->measurement_target ?: 'body',
            'size_system' => $this->size_system ?: 'br_alpha',
            'range_mode' => $this->range_mode ?: 'min_max',
            'unit' => $this->unit,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'metadata' => $this->metadata ?? [],
            'activation' => [
                'virtual_try_on_enabled' => $this->booleanFlag(data_get($this->metadata ?? [], 'activation.virtual_try_on_enabled', true)),
                'virtual_try_on_updated_at' => data_get($this->metadata ?? [], 'activation.virtual_try_on_updated_at'),
            ],
            'custom_variations' => data_get($this->metadata ?? [], 'custom_variations', []),
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

    private function booleanFlag(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
