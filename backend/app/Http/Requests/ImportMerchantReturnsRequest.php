<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportMerchantReturnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(['csv', 'xlsx', 'json'])],
            'filename' => ['nullable', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:6291456'],
            'commit' => ['nullable', 'boolean'],
            'mapping' => ['nullable', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:120'],
        ];
    }
}
