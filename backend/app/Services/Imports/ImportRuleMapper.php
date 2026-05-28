<?php

namespace App\Services\Imports;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ImportRuleMapper
{
    public const FIELDS = ['category', 'brand', 'gender', 'age_group', 'status', 'fit_profile'];

    private const LABELS = [
        'category' => 'Categoria',
        'brand' => 'Marca',
        'gender' => 'Gênero',
        'age_group' => 'Faixa etária',
        'status' => 'Status',
        'fit_profile' => 'Modelagem',
    ];

    private const SOURCE_CANDIDATES = [
        'category' => ['category', 'categoria', 'product_type', 'tipo', 'google_product_category'],
        'brand' => ['brand', 'marca', 'manufacturer', 'fabricante'],
        'gender' => ['gender', 'genero', 'sexo'],
        'age_group' => ['age_group', 'faixa_etaria', 'faixa_etária', 'idade'],
        'status' => ['status', 'availability', 'disponibilidade', 'ativo', 'active'],
        'fit_profile' => ['fit_profile', 'modelagem', 'fit', 'caimento'],
    ];

    public function defaults(): array
    {
        return [
            'category' => $this->rule('category', required: true),
            'brand' => $this->rule('brand'),
            'gender' => $this->rule('gender', fallback: 'unisex', required: true, aliases: [
                'feminino' => 'female',
                'female' => 'female',
                'masculino' => 'male',
                'male' => 'male',
                'infantil' => 'kids',
                'kids' => 'kids',
                'unissex' => 'unisex',
                'unisex' => 'unisex',
            ]),
            'age_group' => $this->rule('age_group', fallback: 'adult', aliases: [
                'adulto' => 'adult',
                'adult' => 'adult',
                'infantil' => 'kids',
                'criança' => 'kids',
                'kids' => 'kids',
                'bebê' => 'baby',
                'baby' => 'baby',
                'teen' => 'teen',
                'adolescente' => 'teen',
            ]),
            'status' => $this->rule('status', fallback: 'active', required: true, aliases: [
                'ativo' => 'active',
                'active' => 'active',
                'em estoque' => 'active',
                'in stock' => 'active',
                'disponivel' => 'active',
                'inativo' => 'inactive',
                'inactive' => 'inactive',
                'sem estoque' => 'inactive',
                'out of stock' => 'inactive',
                'indisponivel' => 'inactive',
                'rascunho' => 'draft',
                'draft' => 'draft',
            ]),
            'fit_profile' => $this->rule('fit_profile', fallback: 'regular', required: true, aliases: [
                'regular' => 'regular',
                'padrão' => 'regular',
                'tradicional' => 'regular',
                'reta' => 'regular',
                'slim' => 'slim',
                'ajustada' => 'slim',
                'ampla' => 'loose',
                'solta' => 'loose',
                'loose' => 'loose',
                'oversized' => 'oversized',
                'conforto' => 'comfort',
                'comfort' => 'comfort',
            ]),
        ];
    }

    public function normalize(?array $rules): array
    {
        $rules = is_array($rules) ? $rules : [];
        $normalized = [];

        foreach ($this->defaults() as $key => $default) {
            $incoming = Arr::get($rules, $key, []);
            $incoming = is_array($incoming) ? $incoming : [];

            $normalized[$key] = [
                'label' => self::LABELS[$key],
                'enabled' => (bool) Arr::get($incoming, 'enabled', $default['enabled']),
                'required' => (bool) Arr::get($incoming, 'required', $default['required']),
                'source_field' => $this->cleanString(Arr::get($incoming, 'source_field', $default['source_field'])) ?: $default['source_field'],
                'fallback' => $this->cleanString(Arr::get($incoming, 'fallback', $default['fallback'])),
                'aliases' => $this->normalizeAliases(Arr::get($incoming, 'aliases', $default['aliases'])),
            ];
        }

        return $normalized;
    }

    public function summarize(array $rules): array
    {
        $rules = $this->normalize($rules);
        $active = collect($rules)->where('enabled', true);

        return [
            'active' => $active->count(),
            'required' => $active->where('required', true)->count(),
            'with_fallback' => $active->filter(fn (array $rule): bool => filled($rule['fallback']))->count(),
        ];
    }

    public function mapProduct(array $payload, ?array $rules = null): array
    {
        $rules = $this->normalize($rules);
        $values = [];
        $details = [];
        $missing = [];

        foreach ($rules as $key => $rule) {
            if (! $rule['enabled']) {
                continue;
            }

            [$raw, $sourceField] = $this->valueForRule($payload, $rule['source_field'], self::SOURCE_CANDIDATES[$key] ?? []);
            $aliased = $this->applyAlias($raw, $rule['aliases']);
            $value = $this->normalizeValue($key, $aliased ?? $raw);
            $origin = $sourceField;

            if ($value === null && filled($rule['fallback'])) {
                $value = $this->normalizeValue($key, $rule['fallback']);
                $origin = 'fallback';
            }

            $values[$key] = $value;
            $details[$key] = [
                'label' => self::LABELS[$key],
                'source_field' => $rule['source_field'],
                'source_value' => $raw,
                'value' => $value,
                'origin' => $origin,
                'required' => $rule['required'],
            ];

            if ($rule['required'] && $value === null) {
                $missing[] = [
                    'field' => $key,
                    'label' => self::LABELS[$key],
                ];
            }
        }

        return [
            'values' => $values,
            'details' => $details,
            'missing' => $missing,
            'summary' => $this->summarize($rules),
        ];
    }

    public function labels(): array
    {
        return self::LABELS;
    }

    private function rule(string $sourceField, ?string $fallback = null, bool $required = false, array $aliases = []): array
    {
        return [
            'label' => self::LABELS[$sourceField],
            'enabled' => true,
            'required' => $required,
            'source_field' => $sourceField,
            'fallback' => $fallback,
            'aliases' => $aliases,
        ];
    }

    private function valueForRule(array $payload, string $preferredSource, array $candidates): array
    {
        foreach (array_values(array_unique(array_filter([$preferredSource, ...$candidates]))) as $field) {
            $value = Arr::get($payload, $field);

            if ($value !== null && $value !== '') {
                return [(string) $value, $field];
            }
        }

        return [null, null];
    }

    private function applyAlias(mixed $raw, array $aliases): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $needle = $this->key((string) $raw);

        foreach ($aliases as $alias => $target) {
            if ($this->key((string) $alias) === $needle) {
                return $this->cleanString($target);
            }
        }

        return null;
    }

    private function normalizeValue(string $field, mixed $value): ?string
    {
        $value = $this->cleanString($value);

        if ($value === null) {
            return null;
        }

        $key = $this->key($value);

        return match ($field) {
            'gender' => match ($key) {
                'f', 'fem', 'feminino', 'female' => 'female',
                'm', 'masc', 'masculino', 'male' => 'male',
                'infantil', 'kid', 'kids', 'crianca', 'criancas', 'child', 'children' => 'kids',
                'unissex', 'unisex' => 'unisex',
                default => $key,
            },
            'age_group' => match ($key) {
                'adulto', 'adulta', 'adult' => 'adult',
                'infantil', 'crianca', 'criancas', 'kid', 'kids', 'child', 'children' => 'kids',
                'bebe', 'baby' => 'baby',
                'teen', 'adolescente', 'juvenil' => 'teen',
                default => $key,
            },
            'status' => match ($key) {
                'ativo', 'active', 'enabled', 'publicado', 'published', 'em estoque', 'in stock', 'available', 'disponivel', 'true', '1', 'sim' => 'active',
                'inativo', 'inactive', 'disabled', 'pausado', 'paused', 'sem estoque', 'out of stock', 'unavailable', 'indisponivel', 'false', '0', 'nao' => 'inactive',
                'rascunho', 'draft' => 'draft',
                'arquivado', 'archived' => 'archived',
                default => $key,
            },
            'fit_profile' => match ($key) {
                'regular', 'padrao', 'tradicional', 'reta' => 'regular',
                'slim', 'ajustada', 'justa' => 'slim',
                'ampla', 'solta', 'loose', 'relaxed' => 'loose',
                'oversized', 'over' => 'oversized',
                'conforto', 'comfort' => 'comfort',
                default => $key,
            },
            default => $value,
        };
    }

    private function normalizeAliases(mixed $aliases): array
    {
        if (! is_array($aliases)) {
            return [];
        }

        $normalized = [];

        foreach ($aliases as $alias => $target) {
            $alias = $this->cleanString($alias);
            $target = $this->cleanString($target);

            if ($alias === null || $target === null) {
                continue;
            }

            $normalized[$alias] = $target;
        }

        return $normalized;
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 120, '');
    }

    private function key(string $value): string
    {
        return Str::of($value)->trim()->lower()->ascii()->replace(['_', '-'], ' ')->squish()->toString();
    }
}
