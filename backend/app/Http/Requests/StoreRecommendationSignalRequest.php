<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecommendationSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signal' => ['required', 'string', Rule::in(['add_to_cart', 'purchase', 'return', 'exchange'])],
            'selected_size' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:80'],
            'order_reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
