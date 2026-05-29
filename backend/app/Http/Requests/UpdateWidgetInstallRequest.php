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
            'theme.presentation_mode' => ['nullable', 'string', Rule::in(['drawer', 'modal'])],
            'theme.modal' => ['nullable', 'array'],
            'theme.modal.logo_text' => ['nullable', 'string', 'max:48'],
            'theme.modal.logo_url' => ['nullable', 'string', 'max:255'],
            'theme.modal.kicker' => ['nullable', 'string', 'max:64'],
            'theme.modal.title' => ['nullable', 'string', 'max:96'],
            'theme.modal.subtitle' => ['nullable', 'string', 'max:180'],
            'theme.modal.step_labels' => ['nullable', 'array', 'size:4'],
            'theme.modal.step_labels.*' => ['nullable', 'string', 'max:32'],
            'theme.modal.table_title' => ['nullable', 'string', 'max:64'],
            'theme.modal.table_unit_label' => ['nullable', 'string', 'max:16'],
            'theme.modal.footer_note' => ['nullable', 'string', 'max:120'],
            'theme.modal.background' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.modal.surface' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.modal.text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.modal.accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.modal.border' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.modal.radius' => ['nullable', 'numeric', 'min:0', 'max:28'],
            'theme.modal.font_family' => ['nullable', 'string', 'max:120'],
            'theme.modal.font_size' => ['nullable', 'numeric', 'min:12', 'max:20'],
            'theme.modal.font_weight' => ['nullable', 'integer', 'min:400', 'max:900'],
            'theme.modal.table_style' => ['nullable', 'string', Rule::in(['clean', 'compact', 'cards'])],
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
