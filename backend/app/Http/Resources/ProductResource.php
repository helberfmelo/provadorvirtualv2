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
            'has_sync_error' => $this->hasSyncError(),
            'readiness_status' => $this->readinessIssues() === [] && $this->status === 'active' ? 'ready' : 'pending',
            'readiness_issues' => $this->readinessIssues(),
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

    private function readinessIssues(): array
    {
        $issues = [];

        if ($this->status !== 'active') {
            $issues[] = 'inactive';
        }

        if (blank($this->measurement_table_id)) {
            $issues[] = 'without_measurement_table';
        }

        if (blank($this->fit_profile)) {
            $issues[] = 'without_modeling';
        }

        if (blank($this->category)) {
            $issues[] = 'without_category';
        }

        if ($this->hasSyncError()) {
            $issues[] = 'sync_error';
        }

        return $issues;
    }

    private function hasSyncError(): bool
    {
        $metadata = $this->metadata ?? [];

        return filled(data_get($metadata, 'sync_error'))
            || filled(data_get($metadata, 'last_sync_error'))
            || filled(data_get($metadata, 'import_error'))
            || data_get($metadata, 'sync.status') === 'error'
            || data_get($metadata, 'last_sync.status') === 'error';
    }
}
