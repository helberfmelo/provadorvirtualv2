<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportMeasurementTablesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(['csv', 'xlsx'])],
            'filename' => ['nullable', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:4194304'],
        ];
    }
}
