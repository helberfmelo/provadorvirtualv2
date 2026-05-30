<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMerchantReturnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(['today', '7d', '30d', '90d', 'custom'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:32'],
            'assisted' => ['nullable', 'string', Rule::in(['all', 'yes', 'no'])],
            'source' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
