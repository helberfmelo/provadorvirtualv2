<?php

namespace App\Http\Requests;

class StoreRecommendationRequest extends RecommendationConfigCheckRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'measurements' => ['required', 'array'],
            'measurements.bust' => ['nullable', 'numeric', 'min:30', 'max:220'],
            'measurements.waist' => ['nullable', 'numeric', 'min:30', 'max:220'],
            'measurements.hip' => ['nullable', 'numeric', 'min:30', 'max:240'],
            'measurements.height' => ['nullable', 'numeric', 'min:80', 'max:260'],
            'measurements.weight' => ['nullable', 'numeric', 'min:20', 'max:260'],
            'measurements.length' => ['nullable', 'numeric', 'min:10', 'max:220'],
            'measurements.shoulder' => ['nullable', 'numeric', 'min:10', 'max:120'],
            'shopper_profile' => ['nullable', 'array'],
            'shopper_profile.gender' => ['nullable', 'string', 'max:40'],
            'shopper_profile.body_shape' => ['nullable', 'string', 'max:60'],
            'shopper_profile.fit_preference' => ['nullable', 'in:tight,regular,loose'],
        ]);
    }
}
