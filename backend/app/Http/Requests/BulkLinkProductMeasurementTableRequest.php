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
            'action' => ['nullable', 'in:preview,apply,undo'],
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['integer', 'distinct'],
            'measurement_table_id' => ['required_unless:action,undo', 'nullable', 'integer'],
            'confirm_conflicts' => ['nullable', 'boolean'],
            'batch_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
