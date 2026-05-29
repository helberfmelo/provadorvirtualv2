<?php

namespace App\Http\Requests;

use App\Support\PlatformCatalog;
use App\Support\WidgetButtonIconCatalog;
use App\Support\WidgetButtonStyleCatalog;
use App\Support\WidgetPlacementCatalog;
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
            'mode' => ['sometimes', 'string', Rule::in(['draft', 'publish', 'discard'])],
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
            'theme.button_style' => ['nullable', 'string', Rule::in(WidgetButtonStyleCatalog::keys())],
            'theme.button_background' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.button_text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.button_primary_icon' => ['nullable', 'string', Rule::in(WidgetButtonIconCatalog::keys())],
            'theme.button_secondary_icon' => ['nullable', 'string', Rule::in(WidgetButtonIconCatalog::keys())],
            'theme.button_icon_animation' => ['nullable', 'boolean'],
            'theme.confetti_enabled' => ['nullable', 'boolean'],
            'theme.presentation_mode' => ['nullable', 'string', Rule::in(['drawer', 'modal'])],
            'theme.placement' => ['nullable', 'array'],
            'theme.placement.mode' => ['nullable', 'string', Rule::in(WidgetPlacementCatalog::modes())],
            'theme.placement.selector' => ['nullable', 'string', 'max:180'],
            'theme.placement.container_id' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'theme.placement.validation' => ['nullable', 'array'],
            'theme.placement.validation.status' => ['nullable', 'string', Rule::in(['untested', 'passed', 'warning', 'failed'])],
            'theme.placement.validation.url' => ['nullable', 'string', 'max:255'],
            'theme.placement.validation.checked_at' => ['nullable', 'string', 'max:40'],
            'theme.placement.validation.message' => ['nullable', 'string', 'max:180'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
