<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxonomyCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category_type' => $this->category_type,
            'gender' => $this->gender,
            'age_group' => $this->age_group,
            'translations' => $this->translations ?? [],
            'status' => $this->status,
            'metadata' => $this->metadata ?? [],
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent?->id,
                'name' => $this->parent?->name,
                'slug' => $this->parent?->slug,
                'category_type' => $this->parent?->category_type,
            ]),
            'children' => TaxonomyCategoryResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
