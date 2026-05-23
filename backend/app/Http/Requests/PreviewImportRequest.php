<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_company_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', Rule::in(['products', 'measurement_tables'])],
            'source_format' => ['required', 'string', Rule::in(['csv', 'google_xml'])],
            'filename' => ['nullable', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:1048576'],
        ];
    }
}
