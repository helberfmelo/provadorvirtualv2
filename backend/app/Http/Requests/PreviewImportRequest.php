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
        $isMigration = $this->input('type') === 'sizebay_migration';
        $format = (string) $this->input('source_format');

        return [
            'merchant_company_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', Rule::in(['products', 'measurement_tables', 'sizebay_migration'])],
            'source_format' => ['required', 'string', Rule::in($isMigration ? ['csv', 'json', 'xlsx', 'zip'] : ['csv', 'google_xml'])],
            'filename' => ['nullable', 'string', 'max:180'],
            'section' => [
                Rule::requiredIf($isMigration && in_array($format, ['csv', 'xlsx'], true)),
                'nullable',
                'string',
                Rule::in(['measurement_tables', 'products', 'brands', 'categories', 'fit_profiles', 'import_rules', 'reports']),
            ],
            'compare_with_bigshop' => ['nullable', 'boolean'],
            'content' => [
                Rule::requiredIf(! $isMigration || in_array($format, ['csv', 'json', 'google_xml'], true)),
                'nullable',
                'string',
                'max:7340032',
            ],
            'content_base64' => [
                Rule::requiredIf($isMigration && in_array($format, ['xlsx', 'zip'], true)),
                'nullable',
                'string',
                'max:15728640',
            ],
        ];
    }
}
