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
            'metadata' => $this->metadata ?? [],
            'guidance' => [
                'rules_context' => data_get($this->metadata ?? [], 'rules_context', [
                    'product_type' => $this->product_type,
                    'gender' => $this->gender,
                    'fit_intensity' => $this->fit_intensity ?: 'regular',
                    'stretch_level' => $this->stretch_level ?: 'medium',
                ]),
                'ai_context' => data_get($this->metadata ?? [], 'ai_context', [
                    'use_for_table_suggestions' => true,
                    'use_for_product_diagnostics' => true,
                    'review_required' => true,
                ]),
                'recommendation_impact' => data_get($this->metadata ?? [], 'recommendation_impact', [
                    'summary' => 'Modelagem usada como contexto para revisar caimento e qualidade das recomendações.',
                    'confidence_hint' => 'Sem ajuste automático sem revisão humana.',
                ]),
            ],
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
