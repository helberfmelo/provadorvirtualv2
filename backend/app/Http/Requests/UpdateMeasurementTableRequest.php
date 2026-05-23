<?php

namespace App\Http\Requests;

class UpdateMeasurementTableRequest extends StoreMeasurementTableRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['sometimes', 'required', 'string', 'max:180'];
        $rules['product_type'] = ['sometimes', 'required', 'string', 'max:80'];

        return $rules;
    }
}
