<?php

namespace App\Services\Imports;

use App\Models\ImportJob;
use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

class ImportService
{
    public function preview(Merchant $merchant, ?MerchantCompany $company, array $input): array
    {
        $rows = $this->parse($input['type'], $input['source_format'], $input['content']);
        $preview = $input['type'] === 'measurement_tables'
            ? $this->previewMeasurementTables($rows)
            : $this->previewProducts($merchant, $rows);

        return [
            'type' => $input['type'],
            'source_format' => $input['source_format'],
            'filename' => $input['filename'] ?? null,
            'total_rows' => count($rows),
            'valid_rows' => collect($preview['rows'])->where('valid', true)->count(),
            'failed_rows' => collect($preview['rows'])->where('valid', false)->count(),
            'summary' => $preview['summary'],
            'rows' => array_slice($preview['rows'], 0, 50),
        ];
    }

    public function commit(Merchant $merchant, ?MerchantCompany $company, array $input): ImportJob
    {
        $preview = $this->preview($merchant, $company, $input);
        $job = ImportJob::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'type' => $input['type'],
            'source_format' => $input['source_format'],
            'filename' => $input['filename'] ?? null,
            'status' => 'running',
            'total_rows' => $preview['total_rows'],
            'failed_rows' => $preview['failed_rows'],
            'summary' => $preview['summary'],
            'errors' => collect($preview['rows'])
                ->where('valid', false)
                ->map(fn (array $row): array => Arr::only($row, ['line', 'errors']))
                ->values()
                ->all(),
            'started_at' => now(),
        ]);

        try {
            $importedRows = DB::transaction(function () use ($merchant, $company, $input, $preview): int {
                return $input['type'] === 'measurement_tables'
                    ? $this->commitMeasurementTables($merchant, $company, $preview['rows'])
                    : $this->commitProducts($merchant, $company, $preview['rows']);
            });

            $job->update([
                'status' => $preview['failed_rows'] > 0 ? 'completed_with_warnings' : 'completed',
                'imported_rows' => $importedRows,
                'finished_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            $job->update([
                'status' => 'failed',
                'errors' => array_merge($job->errors ?? [], [['line' => null, 'errors' => [$exception->getMessage()]]]),
                'finished_at' => now(),
            ]);
        }

        return $job->refresh();
    }

    private function parse(string $type, string $format, string $content): array
    {
        if ($format === 'google_xml') {
            if ($type !== 'products') {
                throw new RuntimeException('Google XML esta disponivel apenas para produtos.');
            }

            return $this->parseGoogleXml($content);
        }

        return $this->parseCsv($content);
    }

    private function parseCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', trim($content));
        if ($content === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $content);
        $delimiter = $this->detectDelimiter($lines[0] ?? '');
        $headers = array_map(fn (string $header): string => $this->normalizeHeader($header), str_getcsv(array_shift($lines), $delimiter));
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            $row = [];

            foreach ($headers as $position => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($values[$position] ?? ''));
                }
            }

            $row['_line'] = $index + 2;
            $rows[] = $row;
        }

        return $rows;
    }

    private function parseGoogleXml(string $content): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(trim($content));

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('XML invalido.');
        }

        $namespaces = $xml->getNamespaces(true);
        $items = $xml->xpath('//item') ?: $xml->xpath('//entry') ?: [];
        $rows = [];

        foreach ($items as $index => $item) {
            $google = isset($namespaces['g']) ? $item->children($namespaces['g']) : null;
            $rows[] = [
                '_line' => $index + 1,
                'external_product_id' => $this->xmlText($google?->id) ?: $this->xmlText($item->id),
                'sku' => $this->xmlText($google?->mpn) ?: $this->xmlText($google?->id) ?: $this->xmlText($item->id),
                'name' => $this->xmlText($item->title) ?: $this->xmlText($google?->title),
                'description' => $this->xmlText($item->description),
                'category' => $this->xmlText($google?->product_type) ?: $this->xmlText($google?->google_product_category),
                'image_url' => $this->xmlText($google?->image_link) ?: $this->xmlText($item->link),
                'price' => preg_replace('/[^0-9.,]/', '', $this->xmlText($google?->price)),
            ];
        }

        return $rows;
    }

    private function previewProducts(Merchant $merchant, array $rows): array
    {
        $previewRows = [];
        $products = [];
        $variants = 0;

        foreach ($rows as $row) {
            $sku = $this->value($row, ['sku', 'product_sku', 'id', 'external_product_id']);
            $name = $this->value($row, ['name', 'title', 'product_name']);
            $size = $this->value($row, ['size_label', 'size', 'tamanho']);
            $errors = [];

            if (! $sku && ! $this->value($row, ['external_product_id'])) {
                $errors[] = 'Informe SKU ou ID externo.';
            }

            if (! $name) {
                $errors[] = 'Informe o nome do produto.';
            }

            if ($size) {
                $variants++;
            }

            if ($sku) {
                $products[$sku] = true;
            }

            $previewRows[] = [
                'line' => $row['_line'] ?? null,
                'valid' => $errors === [],
                'errors' => $errors,
                'action' => $this->productExists($merchant, $sku) ? 'update' : 'create',
                'data' => [
                    'sku' => $sku,
                    'external_product_id' => $this->value($row, ['external_product_id', 'id']),
                    'name' => $name,
                    'category' => $this->value($row, ['category', 'product_type']),
                    'gender' => $this->value($row, ['gender', 'genero']),
                    'fit_profile' => $this->value($row, ['fit_profile', 'modelagem']),
                    'size_label' => $size,
                    'variant_sku' => $this->value($row, ['variant_sku', 'grade_sku']) ?: ($size ? trim($sku.'-'.$size, '-') : null),
                    'price' => $this->decimal($this->value($row, ['price', 'preco'])),
                    'stock_quantity' => $this->integer($this->value($row, ['stock_quantity', 'stock', 'estoque'])),
                    'measurement_table' => $this->value($row, ['measurement_table', 'table_name', 'tabela']),
                ],
            ];
        }

        return [
            'summary' => [
                'products' => count($products),
                'variants' => $variants,
            ],
            'rows' => $previewRows,
        ];
    }

    private function previewMeasurementTables(array $rows): array
    {
        $previewRows = [];
        $tables = [];

        foreach ($rows as $row) {
            $name = $this->value($row, ['table_name', 'name', 'tabela']);
            $size = $this->value($row, ['size_label', 'size', 'tamanho']);
            $errors = [];

            if (! $name) {
                $errors[] = 'Informe o nome da tabela.';
            }

            if (! $size) {
                $errors[] = 'Informe o tamanho.';
            }

            if ($name) {
                $tables[$name] = true;
            }

            $previewRows[] = [
                'line' => $row['_line'] ?? null,
                'valid' => $errors === [],
                'errors' => $errors,
                'action' => 'upsert',
                'data' => [
                    'table_name' => $name,
                    'product_type' => $this->value($row, ['product_type', 'tipo']) ?: 'dress',
                    'gender' => $this->value($row, ['gender', 'genero']) ?: 'female',
                    'fit_profile' => $this->value($row, ['fit_profile', 'modelagem']) ?: 'regular',
                    'size_label' => $size,
                    'bust_min' => $this->decimal($this->value($row, ['bust_min', 'busto_min'])),
                    'bust_max' => $this->decimal($this->value($row, ['bust_max', 'busto_max'])),
                    'waist_min' => $this->decimal($this->value($row, ['waist_min', 'cintura_min'])),
                    'waist_max' => $this->decimal($this->value($row, ['waist_max', 'cintura_max'])),
                    'hip_min' => $this->decimal($this->value($row, ['hip_min', 'quadril_min'])),
                    'hip_max' => $this->decimal($this->value($row, ['hip_max', 'quadril_max'])),
                    'height_min' => $this->decimal($this->value($row, ['height_min', 'altura_min'])),
                    'height_max' => $this->decimal($this->value($row, ['height_max', 'altura_max'])),
                    'weight_min' => $this->decimal($this->value($row, ['weight_min', 'peso_min'])),
                    'weight_max' => $this->decimal($this->value($row, ['weight_max', 'peso_max'])),
                ],
            ];
        }

        return [
            'summary' => [
                'measurement_tables' => count($tables),
                'rows' => count($rows),
            ],
            'rows' => $previewRows,
        ];
    }

    private function commitProducts(Merchant $merchant, ?MerchantCompany $company, array $rows): int
    {
        $imported = 0;

        foreach ($rows as $row) {
            if (! $row['valid']) {
                continue;
            }

            $data = $row['data'];
            $table = $this->measurementTableByName($merchant, $data['measurement_table'] ?? null);
            $product = Product::query()
                ->where('merchant_id', $merchant->id)
                ->where(function ($query) use ($data): void {
                    $query->when($data['sku'] ?? null, fn ($subQuery, string $sku) => $subQuery->orWhere('sku', $sku))
                        ->when($data['external_product_id'] ?? null, fn ($subQuery, string $id) => $subQuery->orWhere('external_product_id', $id));
                })
                ->first();

            if (! $product) {
                $product = new Product([
                    'merchant_id' => $merchant->id,
                    'slug' => Str::slug($data['name']) ?: Str::random(10),
                ]);
            }

            $product->fill([
                'merchant_company_id' => $company?->id,
                'measurement_table_id' => $table?->id,
                'external_product_id' => $data['external_product_id'] ?? $product->external_product_id,
                'sku' => $data['sku'] ?? $product->sku,
                'name' => $data['name'],
                'category' => $data['category'] ?? $product->category,
                'gender' => $data['gender'] ?? $product->gender ?? 'unisex',
                'fit_profile' => $data['fit_profile'] ?? $product->fit_profile ?? 'regular',
                'status' => 'active',
                'metadata' => array_merge($product->metadata ?? [], ['last_imported_at' => now()->toISOString()]),
            ]);
            $product->save();
            $imported++;

            if ($data['size_label'] ?? null) {
                $product->variants()->updateOrCreate(
                    ['size_label' => $data['size_label']],
                    [
                        'merchant_id' => $merchant->id,
                        'merchant_company_id' => $company?->id,
                        'sku' => $data['variant_sku'] ?? null,
                        'price' => $data['price'] ?? null,
                        'stock_quantity' => $data['stock_quantity'] ?? null,
                        'is_active' => true,
                    ]
                );
            }
        }

        return $imported;
    }

    private function commitMeasurementTables(Merchant $merchant, ?MerchantCompany $company, array $rows): int
    {
        $validRows = collect($rows)->where('valid', true)->groupBy('data.table_name');
        $imported = 0;

        foreach ($validRows as $tableName => $tableRows) {
            $first = $tableRows->first()['data'];
            $table = MeasurementTable::query()->updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'name' => $tableName,
                ],
                [
                    'merchant_company_id' => $company?->id,
                    'product_type' => $first['product_type'],
                    'gender' => $first['gender'],
                    'fit_profile' => $first['fit_profile'],
                    'unit' => 'cm',
                    'status' => 'active',
                    'source' => 'import',
                ]
            );

            $table->rows()->delete();

            foreach ($tableRows->values() as $sortOrder => $row) {
                $data = $row['data'];
                $table->rows()->create([
                    'size_label' => $data['size_label'],
                    'sort_order' => $sortOrder,
                    'bust_min' => $data['bust_min'],
                    'bust_max' => $data['bust_max'],
                    'waist_min' => $data['waist_min'],
                    'waist_max' => $data['waist_max'],
                    'hip_min' => $data['hip_min'],
                    'hip_max' => $data['hip_max'],
                    'height_min' => $data['height_min'],
                    'height_max' => $data['height_max'],
                    'weight_min' => $data['weight_min'],
                    'weight_max' => $data['weight_max'],
                ]);
                $imported++;
            }
        }

        return $imported;
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t"];

        return collect($delimiters)
            ->sortByDesc(fn (string $delimiter): int => substr_count($line, $delimiter))
            ->first();
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->trim()->lower()->replace([' ', '-', '.'], '_')->ascii()->toString();
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function decimal(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $value));
    }

    private function integer(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) preg_replace('/[^0-9-]/', '', $value);
    }

    private function xmlText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function productExists(Merchant $merchant, ?string $sku): bool
    {
        if (! $sku) {
            return false;
        }

        return Product::query()
            ->where('merchant_id', $merchant->id)
            ->where('sku', $sku)
            ->exists();
    }

    private function measurementTableByName(Merchant $merchant, ?string $name): ?MeasurementTable
    {
        if (! $name) {
            return null;
        }

        return MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->where('name', $name)
            ->first();
    }
}
