<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use App\Services\Recommendation\MeasurementTableInsightService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MeasurementTableSuggestionService
{
    private const MEASURES = ['bust', 'waist', 'hip', 'height', 'weight', 'length', 'shoulder'];

    public function __construct(private readonly MeasurementTableInsightService $tableInsights) {}

    public function status(): array
    {
        $provider = $this->provider();
        $missingSecret = $this->missingSecret($provider);

        return [
            'provider' => $provider,
            'model' => $this->model($provider),
            'configured' => $provider === 'local' || $missingSecret === null,
            'text_extraction' => true,
            'image_ocr' => false,
            'external_provider_ready' => $provider !== 'local' && $missingSecret === null,
            'review_required' => true,
            'missing_secret' => $missingSecret,
            'prompt_version' => 'measurement-table-suggestion-v2',
        ];
    }

    public function suggest(Merchant $merchant, User $user, array $input, ?MerchantCompany $company = null): array
    {
        $sourceType = $input['source_type'];
        $content = (string) ($input['content'] ?? '');
        $warnings = [];
        $learningContext = $this->tableInsights->contextForSuggestion($merchant, $company, $input);
        $comparisonTable = $this->comparisonTable($merchant, $company, $input);
        $provider = 'local_parser';
        $model = 'local-table-parser-v2';
        $status = 'completed';
        $rows = [];

        if ($content !== '') {
            [$rows, $warnings] = $this->parseRows($content);
        }

        if ($sourceType === 'image' && ! $this->status()['image_ocr']) {
            $status = $rows ? 'completed_with_warnings' : 'needs_provider';
            $warnings[] = 'OCR de imagem ainda não está ativo. Use texto colado por enquanto ou cadastre uma chave de IA para a próxima etapa.';
        }

        if (! $rows && $sourceType !== 'image') {
            $status = 'failed';
            $warnings[] = 'Não encontrei linhas de medida suficientes. Cole uma tabela com cabeçalho e tamanhos.';
        }

        $warnings = array_values(array_unique(array_merge($warnings, $learningContext['warnings'])));
        $reviewContext = $this->buildReviewContext($input, $rows, $warnings, $learningContext, $comparisonTable, $status);
        $suggestion = $this->buildSuggestion($input, $rows, $warnings, $reviewContext);
        $usage = $this->estimatedUsage($content, $suggestion);
        $log = $this->logUsage($merchant, $user, [
            'feature' => 'measurement_table_suggestion',
            'provider' => $provider,
            'model' => $model,
            'status' => $status,
            'input_type' => $sourceType,
            'input_fingerprint' => $this->fingerprint($input),
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'estimated_cost' => 0,
            'summary' => [
                'rows_detected' => count($rows),
                'warnings_count' => count($warnings),
                'review_required' => true,
                'source_type' => $sourceType,
                'filename' => $input['filename'] ?? null,
                'learning_context_used' => $learningContext['has_signals'],
                'learning_context_count' => count($learningContext['matching_insights']),
                'comparison_table_id' => $comparisonTable?->id,
                'category' => $input['category'] ?? null,
                'brand' => $input['brand'] ?? null,
                'measurement_target' => $reviewContext['data_used']['measurement_target'],
                'size_system' => $reviewContext['data_used']['size_system'],
                'range_mode' => $reviewContext['data_used']['range_mode'],
                'risk_level' => $reviewContext['risk_level'],
            ],
        ]);

        return [
            'log_id' => $log->id,
            'provider' => $provider,
            'model' => $model,
            'status' => $status,
            'review_required' => true,
            'confidence' => $reviewContext['confidence_breakdown']['final'],
            'risk_level' => $reviewContext['risk_level'],
            'warnings' => $warnings,
            'usage' => $usage,
            'learning_context' => $learningContext,
            'review_context' => $reviewContext,
            'suggestion' => $suggestion,
        ];
    }

    private function parseRows(string $content): array
    {
        $lines = collect(preg_split('/\R/u', trim($content)) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();

        if (count($lines) < 2) {
            return [[], ['A tabela precisa ter cabeçalho e pelo menos uma linha de tamanho.']];
        }

        $delimiter = $this->delimiter($lines[0]);
        $matrix = array_map(fn (string $line) => $this->columns($line, $delimiter), $lines);
        $headers = array_map(fn (string $header) => $this->canonicalHeader($header), $matrix[0]);
        $hasHeader = in_array('size_label', $headers, true) || count(array_filter($headers)) >= 2;

        if (! $hasHeader) {
            $headers = ['size_label', 'bust', 'waist', 'hip'];
        } else {
            array_shift($matrix);
        }

        $rows = [];
        $warnings = [];

        foreach ($matrix as $index => $columns) {
            $row = $this->parseMeasurementRow($headers, $columns, $index);

            if (! $row['size_label']) {
                $warnings[] = 'Linha '.($index + 1).' ignorada sem tamanho.';

                continue;
            }

            if (! $this->hasMeasurement($row)) {
                $warnings[] = 'Linha '.($index + 1).' ignorada sem medida numerica.';

                continue;
            }

            $rows[] = $row;
        }

        if (! $rows) {
            $warnings[] = 'Nenhuma linha válida foi detectada.';
        }

        return [$rows, $warnings];
    }

    private function parseMeasurementRow(array $headers, array $columns, int $sortOrder): array
    {
        $row = [
            'size_label' => '',
            'sort_order' => $sortOrder,
        ];

        foreach ($headers as $index => $header) {
            if (! $header || ! array_key_exists($index, $columns)) {
                continue;
            }

            $value = trim((string) $columns[$index]);

            if ($header === 'size_label') {
                $row['size_label'] = Str::upper($value);

                continue;
            }

            if (Str::endsWith($header, ['_min', '_max'])) {
                $number = $this->number($value);

                if ($number !== null) {
                    $row[$header] = $number;
                }

                continue;
            }

            if (in_array($header, self::MEASURES, true)) {
                [$min, $max] = $this->range($value);

                if ($min !== null) {
                    $row[$header.'_min'] = $min;
                    $row[$header.'_max'] = $max ?? $min;
                }
            }
        }

        return $row;
    }

    private function canonicalHeader(string $header): ?string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '_', Str::ascii(Str::lower(trim($header)))) ?: '';
        $normalized = trim($normalized, '_');

        if (in_array($normalized, ['tam', 'tamanho', 'size', 'size_label'], true)) {
            return 'size_label';
        }

        foreach ([
            'bust' => ['busto', 'bust', 'torax', 'peito', 'chest'],
            'waist' => ['cintura', 'waist'],
            'hip' => ['quadril', 'hip'],
            'height' => ['altura', 'height'],
            'weight' => ['peso', 'weight'],
            'length' => ['comprimento', 'length'],
            'shoulder' => ['ombro', 'ombros', 'shoulder'],
        ] as $measure => $aliases) {
            foreach ($aliases as $alias) {
                if (! Str::contains($normalized, $alias)) {
                    continue;
                }

                if (Str::contains($normalized, ['min', 'de', 'inicio'])) {
                    return $measure.'_min';
                }

                if (Str::contains($normalized, ['max', 'ate', 'fim'])) {
                    return $measure.'_max';
                }

                return $measure;
            }
        }

        return null;
    }

    private function buildSuggestion(array $input, array $rows, array $warnings, array $reviewContext): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $brand = trim((string) ($input['brand'] ?? ''));
        $notes = [
            'Tabela sugerida pelo assistente. Revise todas as medidas antes de ativar.',
            $category !== '' ? 'Categoria de referência: '.$category.'.' : null,
            $brand !== '' ? 'Marca/contexto: '.$brand.'.' : null,
            'Base: '.$this->measurementTargetLabel($reviewContext['data_used']['measurement_target']).'.',
            'Sistema de tamanho: '.$this->sizeSystemLabel($reviewContext['data_used']['size_system']).'.',
            $reviewContext['merchant_explanation'] ?? null,
        ];

        return [
            'name' => $name !== '' ? $name : 'Tabela sugerida '.now()->format('d/m H:i'),
            'product_type' => $input['product_type'] ?? 'shirt',
            'gender' => $input['gender'] ?? 'unisex',
            'fit_profile' => $input['fit_profile'] ?? 'regular',
            'measurement_target' => $reviewContext['data_used']['measurement_target'],
            'size_system' => $reviewContext['data_used']['size_system'],
            'range_mode' => $reviewContext['data_used']['range_mode'],
            'unit' => $input['unit'] ?? 'cm',
            'status' => 'draft',
            'source' => 'ai',
            'notes' => collect($notes)->filter()->implode(' '),
            'rows' => $rows,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function buildReviewContext(
        array $input,
        array $rows,
        array $warnings,
        array $learningContext,
        ?MeasurementTable $comparisonTable,
        string $status,
    ): array {
        $measurementTarget = (string) ($input['measurement_target'] ?? $this->detectMeasurementTarget($rows));
        $sizeSystem = (string) ($input['size_system'] ?? $this->detectSizeSystem($rows));
        $rangeMode = (string) ($input['range_mode'] ?? $this->detectRangeMode($rows));
        $comparisonRows = $this->comparisonRows($comparisonTable, $rows);
        $risks = $this->buildRisks($input, $rows, $warnings, $learningContext, $comparisonTable, $comparisonRows, $status);
        $confidenceBreakdown = $this->confidenceBreakdown($rows, $learningContext, $comparisonTable, $comparisonRows, $risks);
        $merchantExplanation = ! array_key_exists('explain_for_merchant', $input) || (bool) $input['explain_for_merchant']
            ? $this->merchantExplanation($input, $measurementTarget, $sizeSystem, $comparisonTable, $learningContext, $risks, $comparisonRows)
            : null;

        return [
            'data_used' => [
                'source_type' => $input['source_type'],
                'filename' => $input['filename'] ?? null,
                'category' => $input['category'] ?? null,
                'brand' => $input['brand'] ?? null,
                'measurement_target' => $measurementTarget,
                'size_system' => $sizeSystem,
                'range_mode' => $rangeMode,
                'rows_detected' => count($rows),
                'learning_signals' => count($learningContext['matching_insights'] ?? []),
                'comparison_table' => $comparisonTable ? [
                    'id' => $comparisonTable->id,
                    'name' => $comparisonTable->name,
                ] : null,
            ],
            'confidence_breakdown' => $confidenceBreakdown,
            'risk_level' => $this->riskLevel($risks),
            'risks' => $risks,
            'merchant_explanation' => $merchantExplanation,
            'comparison' => [
                'current_table' => $comparisonTable ? [
                    'id' => $comparisonTable->id,
                    'name' => $comparisonTable->name,
                    'measurement_target' => $comparisonTable->measurement_target ?: 'body',
                    'size_system' => $comparisonTable->size_system ?: 'br_alpha',
                    'range_mode' => $comparisonTable->range_mode ?: 'min_max',
                    'rows_count' => $comparisonTable->rows->count(),
                ] : null,
                'suggested_table' => [
                    'measurement_target' => $measurementTarget,
                    'size_system' => $sizeSystem,
                    'range_mode' => $rangeMode,
                    'rows_count' => count($rows),
                ],
                'overview' => [
                    'changed_sizes' => count($comparisonRows),
                    'changed_fields' => collect($comparisonRows)->sum(fn (array $row): int => count($row['changes'] ?? [])),
                    'same_size_system' => ! $comparisonTable || ($comparisonTable->size_system ?: 'br_alpha') === $sizeSystem,
                ],
                'rows' => $comparisonRows,
            ],
            'action_plan' => [
                'Confira a explicação simples e os riscos antes de criar ou revisar a tabela.',
                'Compare a tabela sugerida com a atual nos tamanhos e campos que mais mudaram.',
                'Salve como rascunho e publique somente depois da revisão operacional.',
            ],
        ];
    }

    private function comparisonTable(Merchant $merchant, ?MerchantCompany $company, array $input): ?MeasurementTable
    {
        $query = MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($builder) use ($company): void {
                $builder->where(function ($inner) use ($company): void {
                    $inner->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->with('rows');

        if (! empty($input['compare_table_id'])) {
            return (clone $query)->find((int) $input['compare_table_id']);
        }

        return (clone $query)
            ->when(filled($input['product_type'] ?? null), fn ($builder) => $builder->where('product_type', (string) $input['product_type']))
            ->when(filled($input['fit_profile'] ?? null), fn ($builder) => $builder->where('fit_profile', (string) $input['fit_profile']))
            ->when(filled($input['gender'] ?? null), function ($builder) use ($input): void {
                $builder->where(function ($inner) use ($input): void {
                    $inner->where('gender', (string) $input['gender'])
                        ->orWhere('gender', 'unisex')
                        ->orWhereNull('gender');
                });
            })
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();
    }

    private function comparisonRows(?MeasurementTable $comparisonTable, array $suggestedRows): array
    {
        if (! $comparisonTable) {
            return [];
        }

        $currentRows = $comparisonTable->rows
            ->keyBy(fn ($row) => mb_strtolower((string) $row->size_label));
        $seenCurrent = [];

        $rows = collect($suggestedRows)
            ->map(function (array $row) use ($currentRows, &$seenCurrent): ?array {
                $current = $currentRows->get(mb_strtolower((string) ($row['size_label'] ?? '')));
                $currentKey = $current ? mb_strtolower((string) $current->size_label) : null;

                if ($currentKey !== null) {
                    $seenCurrent[$currentKey] = true;
                }

                if (! $current) {
                    return [
                        'size_label' => $row['size_label'] ?? '-',
                        'status' => 'new_size',
                        'changes' => [],
                    ];
                }

                $changes = collect(self::MEASURES)
                    ->map(function (string $field) use ($current, $row): ?array {
                        $currentMin = $current->{$field.'_min'};
                        $currentMax = $current->{$field.'_max'};
                        $suggestedMin = $row[$field.'_min'] ?? null;
                        $suggestedMax = $row[$field.'_max'] ?? null;

                        if ($currentMin === null && $currentMax === null && $suggestedMin === null && $suggestedMax === null) {
                            return null;
                        }

                        if ((float) ($currentMin ?? 0) === (float) ($suggestedMin ?? 0)
                            && (float) ($currentMax ?? 0) === (float) ($suggestedMax ?? 0)) {
                            return null;
                        }

                        return [
                            'field' => $field,
                            'current' => $this->formatRange($currentMin, $currentMax),
                            'suggested' => $this->formatRange($suggestedMin, $suggestedMax),
                            'delta_min' => $this->numericDelta($currentMin, $suggestedMin),
                            'delta_max' => $this->numericDelta($currentMax, $suggestedMax),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if ($changes === []) {
                    return null;
                }

                return [
                    'size_label' => $row['size_label'] ?? '-',
                    'status' => 'changed',
                    'changes' => $changes,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $removedRows = $currentRows
            ->reject(fn ($row, string $key): bool => isset($seenCurrent[$key]))
            ->map(fn ($row): array => [
                'size_label' => $row->size_label ?: '-',
                'status' => 'missing_in_suggestion',
                'changes' => [],
            ])
            ->values()
            ->all();

        return collect([...$rows, ...$removedRows])
            ->take(10)
            ->values()
            ->all();
    }

    private function buildRisks(
        array $input,
        array $rows,
        array $warnings,
        array $learningContext,
        ?MeasurementTable $comparisonTable,
        array $comparisonRows,
        string $status,
    ): array {
        $risks = [];

        if ($status === 'needs_provider') {
            $risks[] = [
                'level' => 'high',
                'label' => 'OCR pendente',
                'message' => 'A leitura por imagem ainda depende de provider externo. Use texto ou CSV quando a imagem vier sozinha.',
            ];
        }

        if (count($rows) < 2) {
            $risks[] = [
                'level' => 'medium',
                'label' => 'Poucas linhas detectadas',
                'message' => 'Revise se todos os tamanhos relevantes foram capturados antes de criar o rascunho.',
            ];
        }

        if (! $comparisonTable) {
            $risks[] = [
                'level' => 'medium',
                'label' => 'Sem tabela atual para comparar',
                'message' => 'O assistente não encontrou uma tabela atual compatível. Compare manualmente antes de publicar.',
            ];
        } elseif (count($comparisonRows) >= 4) {
            $risks[] = [
                'level' => 'medium',
                'label' => 'Diferença ampla na comparação',
                'message' => 'A sugestão mudou vários tamanhos ou campos em relação à tabela atual. Revise a grade inteira.',
            ];
        }

        foreach ($learningContext['matching_insights'] ?? [] as $insight) {
            if (in_array($insight['suggested_action'] ?? null, ['stable', 'collect_more_data'], true)) {
                continue;
            }

            $risks[] = [
                'level' => 'high',
                'label' => 'Dados reais pedem revisão',
                'message' => $insight['reason'] ?? 'Há sinais reais pedindo revisão antes de qualquer mudança crítica.',
            ];
        }

        if ($warnings !== []) {
            $risks[] = [
                'level' => 'low',
                'label' => 'Avisos estruturais',
                'message' => 'Foram gerados avisos de parsing ou contexto. Confira o resumo antes de seguir.',
            ];
        }

        if (filled($input['brand'] ?? null) || filled($input['category'] ?? null)) {
            $risks[] = [
                'level' => 'low',
                'label' => 'Contexto de marca e categoria',
                'message' => 'Marca e categoria ajudam a explicar a sugestão, mas ainda não substituem a revisão operacional da grade.',
            ];
        }

        return collect($risks)
            ->unique(fn (array $risk): string => $risk['label'].'|'.$risk['message'])
            ->values()
            ->all();
    }

    private function confidenceBreakdown(
        array $rows,
        array $learningContext,
        ?MeasurementTable $comparisonTable,
        array $comparisonRows,
        array $risks,
    ): array {
        $filled = collect($rows)->sum(fn (array $row): int => count(array_filter(Arr::except($row, ['size_label', 'sort_order']))));
        $parser = $rows === [] ? 0.0 : min(0.62, 0.3 + (count($rows) * 0.08));
        $structure = min(0.18, $filled / 90);
        $learning = ($learningContext['has_signals'] ?? false) ? min(0.1, count($learningContext['matching_insights'] ?? []) * 0.03) : 0.0;
        $comparison = $comparisonTable ? min(0.08, 0.04 + (count($comparisonRows) === 0 ? 0.04 : 0.02)) : 0.0;
        $riskDiscount = min(0.22, count($risks) * 0.04);
        $final = max(0.18, min(0.97, $parser + $structure + $learning + $comparison - $riskDiscount));

        return [
            'parser' => round($parser, 2),
            'structure' => round($structure, 2),
            'learning' => round($learning, 2),
            'comparison' => round($comparison, 2),
            'risk_discount' => round($riskDiscount, 2),
            'final' => round($final, 2),
        ];
    }

    private function merchantExplanation(
        array $input,
        string $measurementTarget,
        string $sizeSystem,
        ?MeasurementTable $comparisonTable,
        array $learningContext,
        array $risks,
        array $comparisonRows,
    ): string {
        $parts = [
            'Esta sugestao serve como ponto de partida e sai sempre como rascunho.',
            filled($input['category'] ?? null) ? 'Ela foi preparada para a categoria '.trim((string) $input['category']).'.' : null,
            filled($input['brand'] ?? null) ? 'O contexto de marca informado foi '.trim((string) $input['brand']).'.' : null,
            'A base principal desta tabela e '.mb_strtolower($this->measurementTargetLabel($measurementTarget)).' com sistema '.mb_strtolower($this->sizeSystemLabel($sizeSystem)).'.',
            $comparisonTable ? 'A comparacao usou a tabela atual '.$comparisonTable->name.' para mostrar o que mudou.' : 'Nao havia tabela atual compatível para comparar automaticamente.',
            ($learningContext['has_signals'] ?? false) ? 'Os dados reais encontrados foram usados como contexto de revisao, nunca como publicacao automatica.' : 'Ainda ha pouco dado real compatível para reforcar a sugestao.',
            count($comparisonRows) > 0 ? 'Revise primeiro os tamanhos destacados na comparacao antes de salvar o rascunho.' : null,
            $risks !== [] ? 'Os riscos listados mostram onde vale revisar com mais cuidado antes da publicacao.' : null,
        ];

        return collect($parts)->filter()->implode(' ');
    }

    private function detectMeasurementTarget(array $rows): string
    {
        $bodyFields = ['bust', 'waist', 'hip', 'height', 'weight'];
        $garmentFields = ['length', 'shoulder'];
        $hasBody = $this->rowsUseAnyField($rows, $bodyFields);
        $hasGarment = $this->rowsUseAnyField($rows, $garmentFields);

        if ($hasBody && $hasGarment) {
            return 'mixed';
        }

        return $hasGarment ? 'garment' : 'body';
    }

    private function detectSizeSystem(array $rows): string
    {
        $labels = collect($rows)
            ->pluck('size_label')
            ->filter()
            ->map(fn (string $label): string => Str::upper(trim($label)))
            ->values();

        if ($labels->isEmpty()) {
            return 'br_alpha';
        }

        if ($labels->every(fn (string $label): bool => preg_match('/^\d{2,3}$/', $label) === 1)) {
            return 'br_numeric';
        }

        $international = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        if ($labels->every(fn (string $label): bool => in_array($label, $international, true))) {
            return 'international';
        }

        $brAlpha = ['PP', 'P', 'M', 'G', 'GG', 'XGG', 'EXG'];
        if ($labels->every(fn (string $label): bool => in_array($label, $brAlpha, true))) {
            return 'br_alpha';
        }

        return 'custom';
    }

    private function detectRangeMode(array $rows): string
    {
        $pairs = collect($rows)
            ->flatMap(function (array $row): array {
                return collect(self::MEASURES)
                    ->map(fn (string $field): array => [
                        'min' => $row[$field.'_min'] ?? null,
                        'max' => $row[$field.'_max'] ?? null,
                    ])
                    ->all();
            })
            ->filter(fn (array $pair): bool => $pair['min'] !== null && $pair['max'] !== null)
            ->values();

        if ($pairs->isEmpty()) {
            return 'min_max';
        }

        $exactPairs = $pairs->filter(fn (array $pair): bool => (float) $pair['min'] === (float) $pair['max'])->count();

        return ($exactPairs / max(1, $pairs->count())) >= 0.7 ? 'exact' : 'min_max';
    }

    private function rowsUseAnyField(array $rows, array $fields): bool
    {
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if (isset($row[$field.'_min']) || isset($row[$field.'_max'])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function riskLevel(array $risks): string
    {
        if (collect($risks)->contains(fn (array $risk): bool => ($risk['level'] ?? null) === 'high')) {
            return 'high';
        }

        if (collect($risks)->contains(fn (array $risk): bool => ($risk['level'] ?? null) === 'medium')) {
            return 'medium';
        }

        return 'low';
    }

    private function formatRange(mixed $min, mixed $max): ?string
    {
        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null && (float) $min === (float) $max) {
            return (string) $min;
        }

        return trim(($min !== null ? $min : '').($max !== null ? ' - '.$max : ''));
    }

    private function numericDelta(mixed $current, mixed $suggested): ?float
    {
        if ($current === null || $suggested === null) {
            return null;
        }

        return round(((float) $suggested) - ((float) $current), 2);
    }

    private function measurementTargetLabel(string $value): string
    {
        return match ($value) {
            'garment' => 'Peça',
            'mixed' => 'Corpo + peça',
            default => 'Corpo',
        };
    }

    private function sizeSystemLabel(string $value): string
    {
        return match ($value) {
            'br_numeric' => 'BR numérico',
            'international' => 'Internacional',
            'custom' => 'Personalizado',
            default => 'BR letras',
        };
    }

    private function delimiter(string $line): ?string
    {
        $candidates = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($candidates);
        $delimiter = array_key_first($candidates);

        return $candidates[$delimiter] > 0 ? $delimiter : null;
    }

    private function columns(string $line, ?string $delimiter): array
    {
        if ($delimiter !== null) {
            return array_map('trim', str_getcsv($line, $delimiter));
        }

        return preg_split('/\s{2,}|\t|\s+/', trim($line)) ?: [];
    }

    private function range(string $value): array
    {
        preg_match_all('/\d+(?:[,.]\d+)?/', $value, $matches);
        $numbers = array_map(fn (string $number) => (float) str_replace(',', '.', $number), $matches[0] ?? []);

        if (! $numbers) {
            return [null, null];
        }

        if (count($numbers) === 1) {
            return [$numbers[0], $numbers[0]];
        }

        return [min($numbers[0], $numbers[1]), max($numbers[0], $numbers[1])];
    }

    private function number(string $value): ?float
    {
        [$number] = $this->range($value);

        return $number;
    }

    private function hasMeasurement(array $row): bool
    {
        return collect(Arr::except($row, ['size_label', 'sort_order']))
            ->filter(fn ($value) => is_numeric($value))
            ->isNotEmpty();
    }

    private function estimatedUsage(string $content, array $suggestion): array
    {
        $inputTokens = (int) ceil(max(1, str_word_count($content)) * 1.35);
        $outputTokens = (int) ceil(strlen(json_encode($suggestion)) / 4);

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost' => 0,
        ];
    }

    private function fingerprint(array $input): string
    {
        return hash('sha256', implode('|', [
            $input['source_type'] ?? '',
            $input['content'] ?? '',
            $input['image_data'] ?? '',
        ]));
    }

    private function logUsage(Merchant $merchant, User $user, array $data): AiUsageLog
    {
        return AiUsageLog::query()->create([
            'merchant_id' => $merchant->id,
            'user_id' => $user->id,
            ...$data,
        ]);
    }

    private function provider(): string
    {
        return Str::lower((string) config('services.ai.provider', 'local')) ?: 'local';
    }

    private function model(string $provider): string
    {
        return (string) config('services.ai.model', $provider === 'openai' ? 'gpt-5-mini' : 'local-table-parser-v1');
    }

    private function missingSecret(string $provider): ?string
    {
        return match ($provider) {
            'openai' => filled(config('services.ai.openai_api_key')) ? null : 'OPENAI_API_KEY',
            'gemini' => filled(config('services.ai.gemini_api_key')) ? null : 'GEMINI_API_KEY',
            default => null,
        };
    }
}
