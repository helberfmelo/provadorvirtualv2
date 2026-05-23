<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgetShopperProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_id' => ['nullable', 'integer'],
            'store_id' => ['nullable', 'integer'],
            'profile_id' => ['required', 'uuid'],
            'profile_token' => ['required', 'string', 'max:120'],
        ];
    }
}
