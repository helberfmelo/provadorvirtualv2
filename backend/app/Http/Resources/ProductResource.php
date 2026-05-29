<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activation = $this->activation();
        $readinessIssues = $this->readinessIssues($activation);
        $source = $this->dataSource();
        $includeDetails = ! $request->routeIs('products.index');

        return [
            'id' => $this->id,
            'merchant_company_id' => $this->merchant_company_id,
            'measurement_table_id' => $this->measurement_table_id,
            'external_product_id' => $this->external_product_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'normalized_category' => $this->normalizedCategoryPayload(),
            'gender' => $this->gender,
            'fit_profile' => $this->fit_profile,
            'brand' => data_get($this->metadata ?? [], 'brand'),
            'normalized_brand' => $this->normalizedBrandPayload(),
            'age_group' => data_get($this->metadata ?? [], 'age_group'),
            'data_source' => $source,
            'source_label' => $this->sourceLabel($source),
            'status' => $this->status,
            'activation' => $activation,
            'has_sync_error' => $this->hasSyncError(),
            'readiness_status' => $readinessIssues === [] && $this->status === 'active' ? 'ready' : 'pending',
            'readiness_issues' => $readinessIssues,
            'diagnostics' => $this->diagnostics($readinessIssues, $activation),
            'size_labels' => $this->whenLoaded('variants', fn () => $this->variants
                ->pluck('size_label')
                ->filter()
                ->unique()
                ->values()
                ->all()),
            'image_url' => $this->image_url,
            'variants_count' => $this->whenCounted('variants'),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'platform' => $this->company?->platform,
            ]),
            'measurement_table' => new MeasurementTableResource($this->whenLoaded('measurementTable')),
            'variants' => $this->when(
                ! $request->routeIs('products.index') && $this->relationLoaded('variants'),
                fn () => ProductVariantResource::collection($this->variants)
            ),
            'origin_fields' => $this->when($includeDetails, fn () => $this->originFields()),
            'imported_snapshot' => $this->when($includeDetails, fn () => $this->importedSnapshot()),
            'manual_overrides' => $this->when($includeDetails, fn () => $this->manualOverrides()),
            'history' => $this->when($includeDetails, fn () => $this->history()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function readinessIssues(array $activation): array
    {
        $issues = [];

        if ($this->status !== 'active') {
            $issues[] = 'inactive';
        }

        if (! $activation['virtual_try_on_enabled']) {
            $issues[] = 'virtual_try_on_disabled';
        }

        if (! $activation['measurement_table_enabled']) {
            $issues[] = 'measurement_table_disabled';
        }

        if (blank($this->measurement_table_id)) {
            $issues[] = 'without_measurement_table';
        }

        if (blank($this->fit_profile)) {
            $issues[] = 'without_modeling';
        }

        if (blank($this->category)) {
            $issues[] = 'without_category';
        }

        if ($this->hasSyncError()) {
            $issues[] = 'sync_error';
        }

        return $issues;
    }

    private function hasSyncError(): bool
    {
        $metadata = $this->metadata ?? [];

        return filled(data_get($metadata, 'sync_error'))
            || filled(data_get($metadata, 'last_sync_error'))
            || filled(data_get($metadata, 'import_error'))
            || data_get($metadata, 'sync.status') === 'error'
            || data_get($metadata, 'last_sync.status') === 'error';
    }

    private function dataSource(): string
    {
        $metadata = $this->metadata ?? [];
        $source = data_get($metadata, 'source') ?: data_get($metadata, 'data_source');

        if ($source) {
            return (string) $source;
        }

        if (filled(data_get($metadata, 'bigshop_last_sync_at'))) {
            return 'bigshop';
        }

        if (filled(data_get($metadata, 'last_imported_at'))) {
            return 'import';
        }

        return 'manual';
    }

    private function normalizedBrandPayload(): ?array
    {
        $metadata = $this->metadata ?? [];
        $normalized = data_get($metadata, 'normalized_brand');

        if (is_array($normalized) && filled($normalized['name'] ?? null)) {
            return [
                'id' => $normalized['id'] ?? data_get($metadata, 'normalized_brand_id'),
                'name' => $normalized['name'],
                'slug' => $normalized['slug'] ?? null,
                'original_name' => $normalized['original_name'] ?? data_get($metadata, 'brand'),
                'merchant_brand_id' => $normalized['merchant_brand_id'] ?? null,
                'source' => $normalized['source'] ?? data_get($metadata, 'brand_mapping.source'),
                'applied_at' => $normalized['applied_at'] ?? data_get($metadata, 'brand_mapping.updated_at'),
            ];
        }

        $name = data_get($metadata, 'normalized_brand_name');

        if (blank($name)) {
            return null;
        }

        return [
            'id' => data_get($metadata, 'normalized_brand_id'),
            'name' => $name,
            'slug' => null,
            'original_name' => data_get($metadata, 'brand'),
            'merchant_brand_id' => data_get($metadata, 'brand_mapping.local_brand_id'),
            'source' => data_get($metadata, 'brand_mapping.source'),
            'applied_at' => data_get($metadata, 'brand_mapping.updated_at'),
        ];
    }

    private function normalizedCategoryPayload(): ?array
    {
        $metadata = $this->metadata ?? [];
        $normalized = data_get($metadata, 'normalized_category');

        if (is_array($normalized) && filled($normalized['name'] ?? null)) {
            return [
                'id' => $normalized['id'] ?? data_get($metadata, 'normalized_category_id'),
                'name' => $normalized['name'],
                'slug' => $normalized['slug'] ?? null,
                'type' => $normalized['type'] ?? data_get($metadata, 'category_mapping.category_type'),
                'parent_id' => $normalized['parent_id'] ?? null,
                'parent_name' => $normalized['parent_name'] ?? null,
                'gender' => $normalized['gender'] ?? null,
                'age_group' => $normalized['age_group'] ?? null,
                'original_name' => $normalized['original_name'] ?? $this->category,
                'merchant_category_id' => $normalized['merchant_category_id'] ?? null,
                'source' => $normalized['source'] ?? data_get($metadata, 'category_mapping.source'),
                'applied_at' => $normalized['applied_at'] ?? data_get($metadata, 'category_mapping.updated_at'),
            ];
        }

        $name = data_get($metadata, 'normalized_category_name');

        if (blank($name)) {
            return null;
        }

        return [
            'id' => data_get($metadata, 'normalized_category_id'),
            'name' => $name,
            'slug' => null,
            'type' => data_get($metadata, 'category_mapping.category_type'),
            'parent_id' => null,
            'parent_name' => null,
            'gender' => null,
            'age_group' => null,
            'original_name' => $this->category,
            'merchant_category_id' => data_get($metadata, 'category_mapping.local_category_id'),
            'source' => data_get($metadata, 'category_mapping.source'),
            'applied_at' => data_get($metadata, 'category_mapping.updated_at'),
        ];
    }

    private function activation(): array
    {
        $metadata = $this->metadata ?? [];

        return [
            'virtual_try_on_enabled' => $this->booleanFlag(data_get($metadata, 'activation.virtual_try_on_enabled', true)),
            'measurement_table_enabled' => $this->booleanFlag(data_get($metadata, 'activation.measurement_table_enabled', true)),
            'updated_at' => data_get($metadata, 'activation.updated_at'),
            'virtual_try_on_updated_at' => data_get($metadata, 'activation.virtual_try_on_enabled_updated_at'),
            'measurement_table_updated_at' => data_get($metadata, 'activation.measurement_table_enabled_updated_at'),
        ];
    }

    private function booleanFlag(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function diagnostics(array $readinessIssues, array $activation): array
    {
        if ($readinessIssues === []) {
            return [[
                'severity' => 'ok',
                'code' => 'ready',
                'title' => 'Produto pronto para o widget',
                'cause' => 'O produto está ativo, com tabela, modelagem e sem erro de sincronização.',
                'action' => 'Manter a revisão antes de publicar alterações de catálogo.',
            ]];
        }

        $diagnostics = [];

        foreach ($readinessIssues as $issue) {
            $diagnostics[] = match ($issue) {
                'inactive' => [
                    'severity' => 'warning',
                    'code' => 'inactive',
                    'title' => 'Produto inativo',
                    'cause' => 'O status do produto impede exibição operacional.',
                    'action' => 'Altere o status para ativo quando o produto puder aparecer no widget.',
                ],
                'virtual_try_on_disabled' => [
                    'severity' => 'warning',
                    'code' => 'virtual_try_on_disabled',
                    'title' => 'Provador Virtual desligado neste produto',
                    'cause' => 'A ativação individual do provador está desabilitada.',
                    'action' => 'Ative o Provador Virtual neste detalhe quando a tabela e os dados estiverem revisados.',
                ],
                'measurement_table_disabled' => [
                    'severity' => 'warning',
                    'code' => 'measurement_table_disabled',
                    'title' => 'Tabela de medidas desligada neste produto',
                    'cause' => 'A tabela foi bloqueada para este produto mesmo havendo vínculo possível.',
                    'action' => 'Reative a tabela por produto ou revise o vínculo antes de publicar.',
                ],
                'without_measurement_table' => [
                    'severity' => 'danger',
                    'code' => 'without_measurement_table',
                    'title' => 'Sem tabela de medidas',
                    'cause' => 'O produto não tem uma tabela vinculada.',
                    'action' => 'Vincule uma tabela revisada para liberar recomendação e tabela pública.',
                ],
                'without_modeling' => [
                    'severity' => 'warning',
                    'code' => 'without_modeling',
                    'title' => 'Modelagem ausente',
                    'cause' => 'A modelagem ajuda a calibrar a recomendação.',
                    'action' => 'Defina slim, regular, oversized ou uma modelagem cadastrada.',
                ],
                'without_category' => [
                    'severity' => 'warning',
                    'code' => 'without_category',
                    'title' => 'Categoria ausente',
                    'cause' => 'A categoria é usada para filtro, vínculo e diagnóstico.',
                    'action' => 'Preencha a categoria do produto ou ajuste a regra de importação.',
                ],
                'sync_error' => [
                    'severity' => 'danger',
                    'code' => 'sync_error',
                    'title' => 'Erro de sincronização',
                    'cause' => $this->syncErrorMessage(),
                    'action' => 'Revise a origem dos dados e reexecute a sincronização quando a causa externa estiver resolvida.',
                ],
                default => [
                    'severity' => 'warning',
                    'code' => $issue,
                    'title' => 'Ponto de atenção',
                    'cause' => 'Há uma pendência operacional neste produto.',
                    'action' => 'Revise os dados do detalhe antes de liberar no widget.',
                ],
            };
        }

        if ($activation['virtual_try_on_enabled'] && $activation['measurement_table_enabled'] && $this->measurement_table_id) {
            $diagnostics[] = [
                'severity' => 'info',
                'code' => 'activation_ready',
                'title' => 'Ativação individual liberada',
                'cause' => 'As chaves do produto permitem o uso público assim que as demais pendências forem resolvidas.',
                'action' => 'Use o config-check público para confirmar o comportamento na página do produto.',
            ];
        }

        return $diagnostics;
    }

    private function syncErrorMessage(): string
    {
        $metadata = $this->metadata ?? [];

        return (string) (
            data_get($metadata, 'sync_error')
            ?: data_get($metadata, 'last_sync_error')
            ?: data_get($metadata, 'import_error')
            ?: data_get($metadata, 'sync.message')
            ?: data_get($metadata, 'last_sync.message')
            ?: 'A última sincronização marcou o produto com erro.'
        );
    }

    private function originFields(): array
    {
        $fields = [
            'external_product_id' => 'ID externo',
            'sku' => 'SKU base',
            'name' => 'Nome',
            'description' => 'Descrição',
            'category' => 'Categoria',
            'gender' => 'Gênero',
            'fit_profile' => 'Modelagem',
            'brand' => 'Marca',
            'age_group' => 'Faixa etária',
            'image_url' => 'Imagem',
            'measurement_table_id' => 'Tabela',
        ];

        return collect($fields)->map(function (string $label, string $field): array {
            $source = $this->fieldSource($field);

            return [
                'field' => $field,
                'label' => $label,
                'value' => $this->fieldValue($field),
                'imported_value' => data_get($this->metadata ?? [], "imported_snapshot.{$field}"),
                'source' => $source,
                'source_label' => $this->sourceLabel($source),
                'manual_override' => data_get($this->metadata ?? [], "manual_overrides.{$field}"),
            ];
        })->values()->all();
    }

    private function fieldValue(string $field): mixed
    {
        $metadata = $this->metadata ?? [];

        return match ($field) {
            'brand', 'age_group' => data_get($metadata, $field),
            default => $this->{$field},
        };
    }

    private function fieldSource(string $field): string
    {
        $metadata = $this->metadata ?? [];

        if (filled(data_get($metadata, "manual_overrides.{$field}"))) {
            return 'manual';
        }

        return (string) (data_get($metadata, "field_sources.{$field}") ?: $this->dataSource());
    }

    private function importedSnapshot(): array
    {
        $snapshot = data_get($this->metadata ?? [], 'imported_snapshot', []);

        return is_array($snapshot) ? $snapshot : [];
    }

    private function manualOverrides(): array
    {
        $overrides = data_get($this->metadata ?? [], 'manual_overrides', []);

        return is_array($overrides) ? $overrides : [];
    }

    private function history(): array
    {
        $metadataHistory = collect(data_get($this->metadata ?? [], 'history', []))
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(fn (array $entry): array => [
                'event' => $entry['event'] ?? 'product.metadata',
                'category' => 'metadata',
                'severity' => 'info',
                'source' => $entry['source'] ?? 'manual',
                'details' => $entry['details'] ?? [],
                'created_at' => $entry['created_at'] ?? null,
            ]);

        $auditHistory = $this->relationLoaded('auditLogs')
            ? $this->auditLogs->map(fn ($log): array => [
                'event' => $log->event,
                'category' => $log->category,
                'severity' => $log->severity,
                'source' => 'audit',
                'details' => $log->metadata ?? [],
                'created_at' => $log->created_at?->toISOString(),
            ])
            : collect();

        return $auditHistory
            ->concat($metadataHistory)
            ->sortByDesc('created_at')
            ->take(12)
            ->values()
            ->all();
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'bigshop' => 'BigShop',
            'import' => 'Importação',
            'xml', 'feed' => 'XML/feed',
            'rule' => 'Regra',
            'api' => 'API',
            'ai' => 'IA',
            'manual' => 'Manual',
            default => ucfirst($source),
        };
    }
}
