<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Measurement\StandardMeasurementCatalog;

class MeasurementTemplateController extends Controller
{
    public function index(StandardMeasurementCatalog $catalog)
    {
        $templates = $catalog->templates();

        return response()->json([
            'data' => $templates ?: [
                [
                    'key' => 'female_dress_regular',
                    'name' => 'Vestido feminino regular',
                    'product_type' => 'dress',
                    'gender' => 'female',
                    'fit_profile' => 'regular',
                    'rows' => [
                        ['size_label' => 'PP', 'bust_min' => 80, 'bust_max' => 84, 'waist_min' => 62, 'waist_max' => 66, 'hip_min' => 88, 'hip_max' => 92],
                        ['size_label' => 'P', 'bust_min' => 84, 'bust_max' => 90, 'waist_min' => 66, 'waist_max' => 72, 'hip_min' => 92, 'hip_max' => 98],
                        ['size_label' => 'M', 'bust_min' => 90, 'bust_max' => 96, 'waist_min' => 72, 'waist_max' => 78, 'hip_min' => 98, 'hip_max' => 104],
                        ['size_label' => 'G', 'bust_min' => 96, 'bust_max' => 104, 'waist_min' => 78, 'waist_max' => 86, 'hip_min' => 104, 'hip_max' => 112],
                        ['size_label' => 'GG', 'bust_min' => 104, 'bust_max' => 112, 'waist_min' => 86, 'waist_max' => 96, 'hip_min' => 112, 'hip_max' => 120],
                    ],
                ],
                [
                    'key' => 'unisex_tshirt_regular',
                    'name' => 'Camiseta unissex regular',
                    'product_type' => 'shirt',
                    'gender' => 'unisex',
                    'fit_profile' => 'regular',
                    'rows' => [
                        ['size_label' => 'P', 'bust_min' => 88, 'bust_max' => 94, 'length_min' => 64, 'length_max' => 68, 'shoulder_min' => 38, 'shoulder_max' => 42],
                        ['size_label' => 'M', 'bust_min' => 94, 'bust_max' => 102, 'length_min' => 68, 'length_max' => 72, 'shoulder_min' => 42, 'shoulder_max' => 46],
                        ['size_label' => 'G', 'bust_min' => 102, 'bust_max' => 110, 'length_min' => 72, 'length_max' => 76, 'shoulder_min' => 46, 'shoulder_max' => 50],
                        ['size_label' => 'GG', 'bust_min' => 110, 'bust_max' => 120, 'length_min' => 76, 'length_max' => 80, 'shoulder_min' => 50, 'shoulder_max' => 54],
                    ],
                ],
            ],
            'meta' => $catalog->metadata(),
        ]);
    }
}
