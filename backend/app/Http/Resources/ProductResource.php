<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $readinessIssues = $this->readinessIssues();
        $source = $this->dataSource();

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
            'brand' => data_get($this->metadata ?? [], 'brand'),
            'age_group' => data_get($this->metadata ?? [], 'age_group'),
            'data_source' => $source,
            'source_label' => $this->sourceLabel($source),
            'status' => $this->status,
            'has_sync_error' => $this->hasSyncError(),
            'readiness_status' => $readinessIssues === [] && $this->status === 'active' ? 'ready' : 'pending',
            'readiness_issues' => $readinessIssues,
            'size_labels' => $this->whenLoaded('variants', fn () => $this->variants
                ->pluck('size_label')
                ->filter()
                ->unique()
                ->values()
                ->all()),
            'image_url' => $this->image_url,
            'variants_count' => $this->whenCounted('variants'),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'platform' => $this->company?->platform,
            ]),
            'measurement_table' => new MeasurementTableResource($this->whenLoaded('measurementTable')),
            'variants' => $this->when(
                ! $request->routeIs('products.index') && $this->relationLoaded('variants'),
                fn () => ProductVariantResource::collection($this->variants)
            ),
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

    private function dataSource(): string
    {
        $metadata = $this->metadata ?? [];
        $source = data_get($metadata, 'source') ?: data_get($metadata, 'data_source');

        if ($source) {
            return (string) $source;
        }

        if (filled(data_get($metadata, 'bigshop_last_sync_at'))) {
            return 'bigshop';
        }

        if (filled(data_get($metadata, 'last_imported_at'))) {
            return 'import';
        }

        return 'manual';
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'bigshop' => 'BigShop',
            'import' => 'Importação',
            'api' => 'API',
            'ai' => 'IA',
            'manual' => 'Manual',
            default => ucfirst($source),
        };
    }
}
