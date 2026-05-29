<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkLinkProductMeasurementTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['integer', 'distinct'],
            'measurement_table_id' => ['required', 'integer'],
        ];
    }
}
