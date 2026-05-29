<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementTableRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'size_label' => $this->size_label,
            'sort_order' => $this->sort_order,
            'bust_min' => $this->bust_min,
            'bust_max' => $this->bust_max,
            'waist_min' => $this->waist_min,
            'waist_max' => $this->waist_max,
            'hip_min' => $this->hip_min,
            'hip_max' => $this->hip_max,
            'height_min' => $this->height_min,
            'height_max' => $this->height_max,
            'weight_min' => $this->weight_min,
            'weight_max' => $this->weight_max,
            'length_min' => $this->length_min,
            'length_max' => $this->length_max,
            'shoulder_min' => $this->shoulder_min,
            'shoulder_max' => $this->shoulder_max,
            'measurements' => $this->measurements ?? $this->legacyMeasurements(),
            'composite_measurements' => $this->composite_measurements ?? [],
            'note' => data_get($this->metadata ?? [], 'note'),
            'measurement_notes' => data_get($this->metadata ?? [], 'measurement_notes', []),
            'metadata' => $this->metadata ?? [],
        ];
    }

    private function legacyMeasurements(): array
    {
        return collect([
            'bust' => ['label' => 'Busto', 'min' => $this->bust_min, 'max' => $this->bust_max],
            'waist' => ['label' => 'Cintura', 'min' => $this->waist_min, 'max' => $this->waist_max],
            'hip' => ['label' => 'Quadril', 'min' => $this->hip_min, 'max' => $this->hip_max],
            'height' => ['label' => 'Altura', 'min' => $this->height_min, 'max' => $this->height_max],
            'weight' => ['label' => 'Peso', 'min' => $this->weight_min, 'max' => $this->weight_max],
            'length' => ['label' => 'Comprimento', 'min' => $this->length_min, 'max' => $this->length_max],
            'shoulder' => ['label' => 'Ombro', 'min' => $this->shoulder_min, 'max' => $this->shoulder_max],
        ])->filter(fn (array $range): bool => $range['min'] !== null || $range['max'] !== null)->all();
    }
}
