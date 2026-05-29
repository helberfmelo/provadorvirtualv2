<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxonomyMappingSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_company_id' => $this->merchant_company_id,
            'taxonomy_version_id' => $this->taxonomy_version_id,
            'suggestion_type' => $this->suggestion_type,
            'source' => $this->source,
            'original_value' => $this->original_value,
            'suggested_target_type' => $this->suggested_target_type,
            'suggested_name' => $this->suggested_name,
            'confidence_score' => round((float) $this->confidence_score, 4),
            'confidence_level' => $this->confidence_level,
            'status' => $this->status,
            'review_required' => $this->confidence_level === 'low',
            'can_auto_apply' => (bool) data_get($this->impact ?? [], 'can_auto_apply'),
            'reasons' => $this->reasons ?? [],
            'impact' => $this->impact ?? [],
            'context' => $this->context ?? [],
            'version' => new TaxonomyVersionResource($this->whenLoaded('version')),
            'merchant_category' => new MerchantCategoryResource($this->whenLoaded('merchantCategory')),
            'merchant_brand' => new MerchantBrandResource($this->whenLoaded('merchantBrand')),
            'taxonomy_category' => new TaxonomyCategoryResource($this->whenLoaded('taxonomyCategory')),
            'normalized_brand' => new NormalizedBrandResource($this->whenLoaded('normalizedBrand')),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'applied_at' => $this->applied_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
