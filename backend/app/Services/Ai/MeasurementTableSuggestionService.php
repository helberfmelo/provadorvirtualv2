<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
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
            'prompt_version' => 'measurement-table-suggestion-v1',
        ];
    }

    public function suggest(Merchant $merchant, User $user, array $input, ?MerchantCompany $company = null): array
    {
        $sourceType = $input['source_type'];
        $content = (string) ($input['content'] ?? '');
        $warnings = [];
        $learningContext = $this->tableInsights->contextForSuggestion($merchant, $company, $input);
        $provider = 'local_parser';
        $model = 'local-table-parser-v1';
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

        $warnings = array_merge($warnings, $learningContext['warnings']);

        $suggestion = $this->buildSuggestion($input, $rows, $warnings);
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
            ],
        ]);

        return [
            'log_id' => $log->id,
            'provider' => $provider,
            'model' => $model,
            'status' => $status,
            'review_required' => true,
            'confidence' => $this->confidence($rows),
            'warnings' => array_values(array_unique($warnings)),
            'usage' => $usage,
            'learning_context' => $learningContext,
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

    private function buildSuggestion(array $input, array $rows, array $warnings): array
    {
        $name = trim((string) ($input['name'] ?? ''));

        return [
            'name' => $name !== '' ? $name : 'Tabela sugerida '.now()->format('d/m H:i'),
            'product_type' => $input['product_type'] ?? 'shirt',
            'gender' => $input['gender'] ?? 'unisex',
            'fit_profile' => $input['fit_profile'] ?? 'regular',
            'unit' => $input['unit'] ?? 'cm',
            'status' => 'draft',
            'source' => 'ai',
            'notes' => 'Tabela sugerida pelo assistente. Revise todas as medidas antes de ativar.',
            'rows' => $rows,
            'warnings' => array_values(array_unique($warnings)),
        ];
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

    private function confidence(array $rows): float
    {
        if (! $rows) {
            return 0;
        }

        $filled = collect($rows)->sum(fn (array $row) => count(Arr::except($row, ['size_label', 'sort_order'])));

        return round(min(0.92, 0.48 + (count($rows) * 0.08) + min(0.2, $filled / 80)), 2);
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
