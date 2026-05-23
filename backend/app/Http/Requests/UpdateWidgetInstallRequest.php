<?php

namespace App\Http\Requests;

use App\Support\PlatformCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWidgetInstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_company_id' => ['nullable', 'integer'],
            'platform' => ['sometimes', 'string', Rule::in(PlatformCatalog::keys())],
            'allowed_domains' => ['sometimes', 'array', 'max:12'],
            'allowed_domains.*' => ['required', 'string', 'max:120'],
            'theme' => ['sometimes', 'array'],
            'theme.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.secondary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.background' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.font_family' => ['nullable', 'string', 'max:120'],
            'theme.font_size' => ['nullable', 'numeric', 'min:11', 'max:22'],
            'theme.font_weight' => ['nullable', 'integer', 'min:400', 'max:900'],
            'theme.button_radius' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
