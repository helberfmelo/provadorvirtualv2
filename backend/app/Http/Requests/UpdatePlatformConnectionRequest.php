<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_company_id' => ['nullable', 'integer'],
            'external_store_id' => ['nullable', 'string', 'max:120'],
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'feed_url' => ['nullable', 'string', 'max:255'],
            'feed_format' => ['nullable', 'string', Rule::in(['google_xml'])],
            'status' => ['nullable', 'string', Rule::in(['draft', 'configured', 'connected', 'disabled', 'error'])],
            'access_token' => ['nullable', 'string', 'max:4000'],
            'webhook_secret' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
