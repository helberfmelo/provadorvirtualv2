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
        ];
    }
}
