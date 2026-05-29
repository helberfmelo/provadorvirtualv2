<?php

namespace App\Services\Imports;

use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ImportRuleImpactService
{
    public function __construct(private readonly ImportRuleMapper $ruleMapper) {}

    public function simulate(
        Merchant $merchant,
        ?MerchantCompany $company,
        string $platform,
        ?PlatformConnection $connection,
        array $proposedRules
    ): array {
        $currentRules = $this->ruleMapper->normalize($connection?->import_rules ?? []);
        $nextRules = $this->ruleMapper->normalize($proposedRules);
        $samples = $this->sampleProducts($merchant, $company);
        $sampleSource = $samples->isEmpty() ? 'synthetic' : 'real_catalog';

        if ($samples->isEmpty()) {
            $samples = collect($this->syntheticSamples($platform));
        }

        $impact = $this->emptyImpact($nextRules);
        $rows = [];
        $affectedProducts = [];

        foreach ($samples as $index => $sample) {
            $payload = $sample['payload'];
            $before = $this->ruleMapper->mapProduct($payload, $currentRules);
            $after = $this->ruleMapper->mapProduct($payload, $nextRules);
            $changes = [];
            $rowWarnings = [];

            foreach (ImportRuleMapper::FIELDS as $field) {
                $beforeValue = Arr::get($before, 'values.'.$field);
                $afterValue = Arr::get($after, 'values.'.$field);
                $afterDetail = Arr::get($after, 'details.'.$field, []);

                if (Arr::get($afterDetail, 'origin') === 'fallback') {
                    $impact[$field]['fallback_applied']++;
                }

                if (Arr::get($afterDetail, 'required') && $afterValue === null) {
                    $impact[$field]['missing_required']++;
                    $rowWarnings[] = [
                        'severity' => 'warning',
                        'code' => 'required_value_missing',
                        'message' => ($impact[$field]['label'] ?? $field).' obrigatório não foi encontrado nesta amostra.',
                    ];
                }

                if ($beforeValue !== $afterValue) {
                    $changes[] = [
                        'field' => $field,
                        'label' => $impact[$field]['label'] ?? $field,
                        'before' => $beforeValue,
                        'after' => $afterValue,
                        'source_field' => Arr::get($afterDetail, 'source_field'),
                        'source_value' => Arr::get($afterDetail, 'source_value'),
                        'origin' => Arr::get($afterDetail, 'origin'),
                    ];
                    $impact[$field]['affected_products']++;
                    $impact[$field]['changed_values'][] = $afterValue;
                }
            }

            if ($changes !== []) {
                $affectedProducts[$sample['id'] ?? 'sample-'.$index] = true;
            }

            $rows[] = [
                'id' => $sample['id'],
                'name' => $sample['name'],
                'sku' => $sample['sku'],
                'category' => $payload['category'] ?? null,
                'brand' => $payload['brand'] ?? null,
                'before' => $before['values'],
                'after' => $after['values'],
                'changes' => $changes,
                'warnings' => $rowWarnings,
                'status' => $changes === [] ? 'unchanged' : 'changed',
            ];
        }

        $sampleTotal = $samples->count();
        $impact = $this->finalizeImpact($impact, $sampleTotal);
        $warnings = $this->warnings($nextRules, $impact, $sampleTotal, $sampleSource);

        return [
            'platform' => $platform,
            'sample_source' => $sampleSource,
            'sample_total' => $sampleTotal,
            'affected_products' => count($affectedProducts),
            'affected_percentage' => $sampleTotal > 0 ? round((count($affectedProducts) / $sampleTotal) * 100, 1) : 0.0,
            'active_rules' => $this->ruleMapper->summarize($nextRules)['active'],
            'required_rules' => $this->ruleMapper->summarize($nextRules)['required'],
            'impact_by_rule' => array_values($impact),
            'warnings' => $warnings,
            'save_blocked' => collect($warnings)->contains(fn (array $warning): bool => $warning['severity'] === 'error'),
            'rows' => array_slice($rows, 0, 30),
        ];
    }

    private function sampleProducts(Merchant $merchant, ?MerchantCompany $company): Collection
    {
        return Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => (string) ($product->external_product_id ?: $product->id),
                'name' => $product->name,
                'sku' => $product->sku,
                'payload' => [
                    'external_product_id' => $product->external_product_id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category,
                    'categoria' => $product->category,
                    'brand' => data_get($product->metadata ?? [], 'brand'),
                    'marca' => data_get($product->metadata ?? [], 'brand'),
                    'gender' => $product->gender,
                    'genero' => $product->gender,
                    'age_group' => data_get($product->metadata ?? [], 'age_group'),
                    'faixa_etaria' => data_get($product->metadata ?? [], 'age_group'),
                    'status' => $product->status,
                    'availability' => $product->status,
                    'fit_profile' => $product->fit_profile,
                    'modelagem' => $product->fit_profile,
                    'public_url' => data_get($product->metadata ?? [], 'public_url'),
                ],
            ])
            ->values();
    }

    private function syntheticSamples(string $platform): array
    {
        return [
            [
                'id' => 'sample-vestido',
                'name' => 'Vestido de amostra '.$platform,
                'sku' => 'SKU-SAMPLE-1',
                'payload' => [
                    'sku' => 'SKU-SAMPLE-1',
                    'name' => 'Vestido de amostra',
                    'category' => 'Vestidos',
                    'categoria' => 'Vestidos',
                    'brand' => 'Marca exemplo',
                    'marca' => 'Marca exemplo',
                    'gender' => 'Feminino',
                    'genero' => 'Feminino',
                    'age_group' => 'Adulto',
                    'faixa_etaria' => 'Adulto',
                    'status' => 'Ativo',
                    'availability' => 'in stock',
                    'fit_profile' => 'Regular',
                    'modelagem' => 'Regular',
                ],
            ],
            [
                'id' => 'sample-camisa',
                'name' => 'Camisa de amostra '.$platform,
                'sku' => 'SKU-SAMPLE-2',
                'payload' => [
                    'sku' => 'SKU-SAMPLE-2',
                    'name' => 'Camisa de amostra',
                    'category' => 'Camisas',
                    'brand' => 'Marca exemplo',
                    'gender' => 'Unissex',
                    'age_group' => 'Adulto',
                    'availability' => 'out of stock',
                    'fit_profile' => 'Slim',
                ],
            ],
        ];
    }

    private function emptyImpact(array $rules): array
    {
        return collect($rules)
            ->mapWithKeys(fn (array $rule, string $field): array => [$field => [
                'key' => $field,
                'label' => $rule['label'],
                'enabled' => $rule['enabled'],
                'required' => $rule['required'],
                'source_field' => $rule['source_field'],
                'fallback' => $rule['fallback'],
                'affected_products' => 0,
                'missing_required' => 0,
                'fallback_applied' => 0,
                'changed_values' => [],
                'status' => $rule['enabled'] ? 'ok' : 'muted',
            ]])
            ->all();
    }

    private function finalizeImpact(array $impact, int $sampleTotal): array
    {
        foreach ($impact as $field => $item) {
            $changedValues = collect($item['changed_values'])
                ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
                ->map(fn (mixed $value): string => (string) $value)
                ->countBy()
                ->sortDesc()
                ->take(5)
                ->map(fn (int $count, string $value): array => [
                    'value' => $value,
                    'count' => $count,
                ])
                ->values()
                ->all();

            $impact[$field]['changed_values'] = $changedValues;
            $impact[$field]['affected_percentage'] = $sampleTotal > 0
                ? round(($item['affected_products'] / $sampleTotal) * 100, 1)
                : 0.0;

            if ($item['enabled'] && $item['missing_required'] > 0) {
                $impact[$field]['status'] = 'warning';
            }

            if ($item['enabled'] && $sampleTotal > 0 && $item['fallback_applied'] >= max(3, (int) ceil($sampleTotal * 0.7))) {
                $impact[$field]['status'] = 'warning';
            }
        }

        return $impact;
    }

    private function warnings(array $rules, array $impact, int $sampleTotal, string $sampleSource): array
    {
        $warnings = [];
        $sourceUsage = collect($rules)
            ->map(fn (array $rule, string $key): array => [...$rule, 'key' => $key])
            ->filter(fn (array $rule): bool => $rule['enabled'] && filled($rule['source_field']))
            ->groupBy(fn (array $rule): string => (string) $rule['source_field'])
            ->filter(fn ($group): bool => $group->count() > 1);

        foreach ($sourceUsage as $sourceField => $group) {
            $labels = $group->pluck('label')->values()->all();
            $warnings[] = [
                'severity' => $group->count() >= 3 ? 'error' : 'warning',
                'code' => 'source_field_conflict',
                'message' => 'O campo '.$sourceField.' alimenta '.implode(', ', $labels).'. Confira se essa condição não mistura significados.',
                'rule_keys' => $group->pluck('key')->values()->all(),
            ];
        }

        foreach ($impact as $item) {
            if (! $item['enabled']) {
                continue;
            }

            if ($item['required'] && $item['missing_required'] > 0) {
                $warnings[] = [
                    'severity' => 'warning',
                    'code' => 'required_value_missing',
                    'message' => $item['label'].' ficou vazio em '.$item['missing_required'].' produto(s) da amostra.',
                    'rule_keys' => [$item['key']],
                ];
            }

            if ($sampleTotal > 0 && $item['fallback_applied'] >= max(3, (int) ceil($sampleTotal * 0.7))) {
                $warnings[] = [
                    'severity' => 'warning',
                    'code' => 'fallback_too_broad',
                    'message' => $item['label'].' usou fallback em '.$item['fallback_applied'].' de '.$sampleTotal.' produto(s); a regra pode estar ampla demais.',
                    'rule_keys' => [$item['key']],
                ];
            }
        }

        if ($sampleSource === 'synthetic') {
            $warnings[] = [
                'severity' => 'info',
                'code' => 'synthetic_sample',
                'message' => 'Nenhum produto real foi encontrado para esta empresa; a simulação usou amostra técnica e deve ser refeita após importar catálogo.',
                'rule_keys' => [],
            ];
        }

        return collect($warnings)
            ->map(fn (array $warning): array => [
                ...$warning,
                'id' => substr(sha1(Str::lower($warning['code'].'|'.$warning['message'])), 0, 12),
            ])
            ->values()
            ->all();
    }
}
