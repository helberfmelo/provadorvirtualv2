<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFitProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_company_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:600'],
            'product_type' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', 'in:female,male,unisex,kids'],
            'fit_intensity' => ['nullable', 'in:very_slim,slim,regular,relaxed,oversized,custom'],
            'stretch_level' => ['nullable', 'in:none,low,medium,high'],
            'status' => ['nullable', 'in:active,draft,inactive'],
        ];
    }
}
