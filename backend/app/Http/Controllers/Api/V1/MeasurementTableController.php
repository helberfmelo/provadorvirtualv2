<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportMeasurementTablesRequest;
use App\Http\Requests\StoreMeasurementTableRequest;
use App\Http\Requests\UpdateMeasurementTableRequest;
use App\Http\Resources\MeasurementTableResource;
use App\Models\MeasurementTable;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class MeasurementTableController extends Controller
{
    use ResolvesMerchant;

    private const SPREADSHEET_COLUMNS = [
        'table_name',
        'product_type',
        'gender',
        'fit_profile',
        'measurement_target',
        'size_system',
        'range_mode',
        'status',
        'table_notes',
        'size_label',
        'size_note',
        'bust_min',
        'bust_max',
        'bust_note',
        'waist_min',
        'waist_max',
        'waist_note',
        'hip_min',
        'hip_max',
        'hip_note',
        'height_min',
        'height_max',
        'height_note',
        'weight_min',
        'weight_max',
        'weight_note',
        'length_min',
        'length_max',
        'length_note',
        'shoulder_min',
        'shoulder_max',
        'shoulder_note',
        'composite_min',
        'composite_max',
        'composite_note',
    ];

    private const RANGE_FIELDS = ['bust', 'waist', 'hip', 'height', 'weight', 'length', 'shoulder', 'composite'];

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        $baseQuery = MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company));

        $tables = $this->applyTableFilters(clone $baseQuery, $request)
            ->with('company')
            ->withCount(['rows', 'products'])
            ->orderByDesc('id')
            ->get();

        return MeasurementTableResource::collection($tables)->additional([
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'active' => (clone $baseQuery)->where('status', 'active')->count(),
                'filtered' => $tables->count(),
                'filters' => $this->tableFilterOptions($baseQuery),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $format = $request->string('format')->lower()->toString() === 'xlsx' ? 'xlsx' : 'csv';
        $tables = $this->applyTableFilters(
            MeasurementTable::query()
                ->where('merchant_id', $merchant->id)
                ->tap(fn ($query) => $this->scopeCompany($query, $company)),
            $request
        )
            ->with(['rows', 'company'])
            ->withCount(['rows', 'products'])
            ->orderBy('name')
            ->get();
        $rows = $this->spreadsheetRowsForTables($tables);
        $filename = 'tabelas-medidas-'.now()->format('Ymd-His').'.'.$format;

        return $this->spreadsheetResponse($rows, $filename, $format);
    }

    public function template(Request $request)
    {
        $format = $request->string('format')->lower()->toString() === 'xlsx' ? 'xlsx' : 'csv';
        $target = $request->string('target')->lower()->toString();
        $target = in_array($target, ['body', 'garment', 'mixed'], true) ? $target : 'body';
        $filename = 'modelo-tabelas-'.$target.'.'.$format;

        return $this->spreadsheetResponse($this->templateRows($target), $filename, $format);
    }

    public function previewImport(ImportMeasurementTablesRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validated();

        try {
            return response()->json($this->previewImportPayload($merchant, $company, $data));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'failed_rows' => 1,
                'rows' => [],
            ], 422);
        }
    }

    public function import(ImportMeasurementTablesRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validated();
        try {
            $preview = $this->previewImportPayload($merchant, $company, $data);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'failed_rows' => 1,
                'rows' => [],
            ], 422);
        }

        if (($preview['failed_rows'] ?? 0) > 0) {
            return response()->json([
                ...$preview,
                'message' => 'Corrija os erros da prévia antes de importar a tabela.',
            ], 422);
        }

        $imported = 0;
        $tableIds = [];

        DB::transaction(function () use ($merchant, $company, $preview, &$imported, &$tableIds): void {
            collect($preview['rows'])
                ->where('valid', true)
                ->groupBy('data.table_name')
                ->each(function (Collection $rows, string $tableName) use ($merchant, $company, &$imported, &$tableIds): void {
                    $first = $rows->first()['data'];
                    $table = MeasurementTable::query()->updateOrCreate(
                        [
                            'merchant_id' => $merchant->id,
                            'merchant_company_id' => $company?->id,
                            'name' => $tableName,
                        ],
                        [
                            'product_type' => $first['product_type'],
                            'gender' => $first['gender'],
                            'fit_profile' => $first['fit_profile'],
                            'measurement_target' => $first['measurement_target'],
                            'size_system' => $first['size_system'],
                            'range_mode' => $first['range_mode'],
                            'unit' => 'cm',
                            'status' => $first['status'],
                            'source' => 'import',
                            'notes' => $first['table_notes'],
                        ]
                    );

                    $table->rows()->delete();

                    foreach ($rows->values() as $sortOrder => $row) {
                        $table->rows()->create([
                            ...$this->rowPayload($row['data']),
                            'sort_order' => $sortOrder,
                        ]);
                        $imported++;
                    }

                    $tableIds[] = $table->id;
                });
        });

        app(AuditLogger::class)->log($request, $merchant, 'measurement_table.imported', 'measurement_tables', 'info', [
            'module' => 'measurement_tables',
            'action' => 'import',
            'merchant_company_id' => $company?->id,
            'measurement_table_ids' => array_values(array_unique($tableIds)),
            'imported_rows' => $imported,
            'filename' => $data['filename'] ?? null,
            'format' => $data['format'],
        ]);

        return response()->json([
            ...$preview,
            'imported_rows' => $imported,
            'measurement_table_ids' => array_values(array_unique($tableIds)),
            'message' => 'Tabelas importadas com sucesso.',
        ]);
    }

    public function store(StoreMeasurementTableRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $activeCompany = $this->currentCompany($request, $merchant);
        $data = $request->validated();
        $company = array_key_exists('merchant_company_id', $data)
            ? $this->merchantCompany($merchant, $data['merchant_company_id'])
            : $activeCompany;

        $table = DB::transaction(function () use ($merchant, $company, $data): MeasurementTable {
            $table = MeasurementTable::query()->create([
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company?->id,
                'name' => $data['name'],
                'product_type' => $data['product_type'],
                'gender' => $data['gender'] ?? null,
                'fit_profile' => $data['fit_profile'] ?? null,
                'measurement_target' => $data['measurement_target'] ?? 'body',
                'size_system' => $data['size_system'] ?? 'br_alpha',
                'range_mode' => $data['range_mode'] ?? 'min_max',
                'unit' => $data['unit'] ?? 'cm',
                'status' => $data['status'] ?? 'active',
                'source' => $data['source'] ?? 'manual',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncRows($table, $data['rows'] ?? []);

            return $table;
        });

        app(AuditLogger::class)->log($request, $merchant, 'measurement_table.created', 'measurement_tables', 'info', [
            'measurement_table_id' => $table->id,
            'merchant_company_id' => $company?->id,
            'module' => 'measurement_tables',
            'action' => 'create',
            'rows_count' => $table->rows()->count(),
        ], $table);

        return (new MeasurementTableResource($table->load(['company', 'rows'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, MeasurementTable $measurementTable)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedMeasurementTable($merchant, $measurementTable, $company);

        return new MeasurementTableResource($measurementTable->load(['company', 'rows']));
    }

    public function update(UpdateMeasurementTableRequest $request, MeasurementTable $measurementTable)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedMeasurementTable($merchant, $measurementTable, $company);
        $data = $request->validated();

        DB::transaction(function () use ($merchant, $measurementTable, $data): void {
            if (array_key_exists('merchant_company_id', $data)) {
                $data['merchant_company_id'] = $this->merchantCompany($merchant, $data['merchant_company_id'])?->id;
            }

            $rows = $data['rows'] ?? null;
            unset($data['rows']);

            $measurementTable->update($data);

            if (is_array($rows)) {
                $this->syncRows($measurementTable, $rows);
            }
        });

        app(AuditLogger::class)->log($request, $merchant, 'measurement_table.updated', 'measurement_tables', 'info', [
            'measurement_table_id' => $measurementTable->id,
            'merchant_company_id' => $measurementTable->merchant_company_id,
            'module' => 'measurement_tables',
            'action' => 'update',
            'rows_count' => $measurementTable->rows()->count(),
        ], $measurementTable);

        return new MeasurementTableResource($measurementTable->refresh()->load(['company', 'rows']));
    }

    public function destroy(Request $request, MeasurementTable $measurementTable)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedMeasurementTable($merchant, $measurementTable, $company);

        DB::transaction(function () use ($measurementTable): void {
            $measurementTable->products()->update(['measurement_table_id' => null]);
            $measurementTable->delete();
        });

        app(AuditLogger::class)->log($request, $merchant, 'measurement_table.deleted', 'measurement_tables', 'warning', [
            'measurement_table_id' => $measurementTable->id,
            'merchant_company_id' => $measurementTable->merchant_company_id,
            'module' => 'measurement_tables',
            'action' => 'delete',
        ], $measurementTable);

        return response()->json([
            'message' => 'Tabela de medidas removida com sucesso.',
        ]);
    }

    private function syncRows(MeasurementTable $table, array $rows): void
    {
        $table->rows()->delete();

        foreach (array_values($rows) as $index => $row) {
            $table->rows()->create([
                'size_label' => $row['size_label'],
                'sort_order' => $row['sort_order'] ?? $index,
                'bust_min' => $row['bust_min'] ?? null,
                'bust_max' => $row['bust_max'] ?? null,
                'waist_min' => $row['waist_min'] ?? null,
                'waist_max' => $row['waist_max'] ?? null,
                'hip_min' => $row['hip_min'] ?? null,
                'hip_max' => $row['hip_max'] ?? null,
                'height_min' => $row['height_min'] ?? null,
                'height_max' => $row['height_max'] ?? null,
                'weight_min' => $row['weight_min'] ?? null,
                'weight_max' => $row['weight_max'] ?? null,
                'length_min' => $row['length_min'] ?? null,
                'length_max' => $row['length_max'] ?? null,
                'shoulder_min' => $row['shoulder_min'] ?? null,
                'shoulder_max' => $row['shoulder_max'] ?? null,
                'measurements' => $this->measurementsPayload($row),
                'composite_measurements' => $this->compositeMeasurementsPayload($row),
                'metadata' => $this->rowMetadataPayload($row),
            ]);
        }
    }

    private function measurementsPayload(array $row): array
    {
        $measurements = is_array($row['measurements'] ?? null) ? $row['measurements'] : [];

        foreach ($this->legacyMeasurementFields() as $key => $label) {
            $min = $row[$key.'_min'] ?? null;
            $max = $row[$key.'_max'] ?? null;

            if ($min === null && $max === null && isset($measurements[$key])) {
                continue;
            }

            if ($min !== null || $max !== null) {
                $measurements[$key] = array_filter([
                    'label' => $label,
                    'min' => $min,
                    'max' => $max,
                ], fn ($value): bool => $value !== null && $value !== '');
            }
        }

        return $this->filterMeasurementMap($measurements);
    }

    private function compositeMeasurementsPayload(array $row): array
    {
        $composite = is_array($row['composite_measurements'] ?? null) ? $row['composite_measurements'] : [];

        return $this->filterMeasurementMap($composite);
    }

    private function filterMeasurementMap(array $measurements): array
    {
        return collect($measurements)
            ->filter(fn (mixed $value): bool => is_array($value))
            ->map(fn (array $value): array => array_filter($value, fn (mixed $item): bool => $item !== null && $item !== ''))
            ->filter()
            ->all();
    }

    private function rowMetadataPayload(array $row): array
    {
        return array_filter([
            'note' => $row['note'] ?? $row['size_note'] ?? null,
            'measurement_notes' => $row['measurement_notes'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function applyTableFilters($query, Request $request)
    {
        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('product_type', 'like', "%{$search}%")
                    ->orWhere('gender', 'like', "%{$search}%")
                    ->orWhere('fit_profile', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        foreach (['status', 'measurement_target', 'product_type', 'fit_profile'] as $field) {
            if ($value = trim($request->string($field)->toString())) {
                $query->where($field, $value);
            }
        }

        if ($usage = trim($request->string('usage')->toString())) {
            match ($usage) {
                'with_products' => $query->has('products'),
                'without_products' => $query->doesntHave('products'),
                default => null,
            };
        }

        return $query;
    }

    private function tableFilterOptions($baseQuery): array
    {
        $tables = (clone $baseQuery)
            ->select(['product_type', 'fit_profile', 'status', 'measurement_target'])
            ->latest('id')
            ->limit(2000)
            ->get();

        return [
            'product_types' => $this->optionValues($tables->pluck('product_type')),
            'fit_profiles' => $this->optionValues($tables->pluck('fit_profile')),
            'statuses' => $this->optionValues($tables->pluck('status')),
            'measurement_targets' => $this->optionValues($tables->pluck('measurement_target')),
        ];
    }

    private function optionValues(Collection $values): array
    {
        return $values
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sortBy(fn (string $value): string => Str::lower($value))
            ->values()
            ->all();
    }

    private function spreadsheetRowsForTables(Collection $tables): array
    {
        $rows = [self::SPREADSHEET_COLUMNS];

        foreach ($tables as $table) {
            foreach ($table->rows as $row) {
                $notes = $row->metadata['measurement_notes'] ?? [];
                $rows[] = [
                    $table->name,
                    $table->product_type,
                    $table->gender,
                    $table->fit_profile,
                    $table->measurement_target ?: 'body',
                    $table->size_system ?: 'br_alpha',
                    $table->range_mode ?: 'min_max',
                    $table->status,
                    $table->notes,
                    $row->size_label,
                    $row->metadata['note'] ?? '',
                    $row->bust_min,
                    $row->bust_max,
                    $notes['bust'] ?? '',
                    $row->waist_min,
                    $row->waist_max,
                    $notes['waist'] ?? '',
                    $row->hip_min,
                    $row->hip_max,
                    $notes['hip'] ?? '',
                    $row->height_min,
                    $row->height_max,
                    $notes['height'] ?? '',
                    $row->weight_min,
                    $row->weight_max,
                    $notes['weight'] ?? '',
                    $row->length_min,
                    $row->length_max,
                    $notes['length'] ?? '',
                    $row->shoulder_min,
                    $row->shoulder_max,
                    $notes['shoulder'] ?? '',
                    data_get($row->composite_measurements ?? [], 'fit_balance.min'),
                    data_get($row->composite_measurements ?? [], 'fit_balance.max'),
                    $notes['composite'] ?? '',
                ];
            }
        }

        return $rows;
    }

    private function templateRows(string $target): array
    {
        $rows = [self::SPREADSHEET_COLUMNS];
        $base = [
            'Camisas masculinas regular',
            'shirt',
            'male',
            'regular',
            $target,
            'br_alpha',
            'min_max',
            'active',
            'Medidas em cm. Revise antes de publicar.',
        ];

        foreach ([
            ['P', 'Entrada do modelo', 88, 94, 76, 82, 90, 96],
            ['M', 'Tamanho central', 94, 100, 82, 88, 96, 102],
            ['G', 'Conferir grade real', 100, 106, 88, 94, 102, 108],
        ] as $size) {
            $rows[] = [
                ...$base,
                $size[0],
                $size[1],
                $size[2],
                $size[3],
                $target === 'garment' ? 'Medida da peça na altura do busto' : 'Medida corporal de busto',
                $size[4],
                $size[5],
                'Cintura natural',
                $size[6],
                $size[7],
                'Quadril no ponto mais largo',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ];
        }

        return $rows;
    }

    private function spreadsheetResponse(array $rows, string $filename, string $format)
    {
        if ($format === 'xlsx') {
            $content = $this->xlsxContent($rows);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return response($this->csvContent($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function csvContent(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (mixed $value): string => (string) $value, $row), ';');
        }

        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }

    private function xlsxContent(array $rows): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extensão ZIP indisponível para gerar XLSX.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'pv-xlsx-');
        $zip = new ZipArchive;
        $zip->open($temp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRels());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheet($rows));
        $zip->close();
        $content = file_get_contents($temp) ?: '';
        @unlink($temp);

        return $content;
    }

    private function xlsxSheet(array $rows): string
    {
        $xmlRows = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = [];

            foreach (array_values($row) as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $cells[] = '<c r="'.$cell.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
            }

            $xmlRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData></worksheet>';
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function xlsxRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Tabelas" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function previewImportPayload($merchant, $company, array $data): array
    {
        $rawRows = $this->parseSpreadsheet($data['format'], $data['content']);
        $rows = [];
        $tableNames = [];
        $seenSizes = [];

        foreach ($rawRows as $row) {
            $preview = $this->previewImportRow($merchant, $company, $row);

            if ($preview['valid']) {
                $sizeKey = Str::lower($preview['data']['table_name'].'|'.$preview['data']['size_label']);

                if (isset($seenSizes[$sizeKey])) {
                    $preview['valid'] = false;
                    $preview['errors'][] = $this->importError($row, 'size_label', 'Tamanho duplicado na mesma tabela.', 'Mantenha apenas uma linha por tamanho ou altere o size_label.');
                } else {
                    $seenSizes[$sizeKey] = true;
                    $tableNames[$preview['data']['table_name']] = true;
                }
            }

            $rows[] = $preview;
        }

        $validRows = collect($rows)->where('valid', true);

        return [
            'type' => 'measurement_tables',
            'format' => $data['format'],
            'filename' => $data['filename'] ?? null,
            'total_rows' => count($rawRows),
            'valid_rows' => $validRows->count(),
            'failed_rows' => collect($rows)->where('valid', false)->count(),
            'summary' => [
                'measurement_tables' => count($tableNames),
                'rows' => $validRows->count(),
                'creates' => $validRows->where('action', 'create')->count(),
                'updates' => $validRows->where('action', 'update')->count(),
            ],
            'rows' => array_slice($rows, 0, 80),
        ];
    }

    private function previewImportRow($merchant, $company, array $row): array
    {
        $errors = [];
        $tableName = $this->spreadsheetValue($row, ['table_name', 'name', 'tabela']);
        $sizeLabel = $this->spreadsheetValue($row, ['size_label', 'size', 'tamanho']);
        $data = [
            'table_name' => $tableName,
            'product_type' => $this->spreadsheetValue($row, ['product_type', 'tipo', 'categoria']) ?: 'custom',
            'gender' => $this->spreadsheetValue($row, ['gender', 'genero']) ?: null,
            'fit_profile' => $this->spreadsheetValue($row, ['fit_profile', 'modelagem']) ?: null,
            'measurement_target' => $this->spreadsheetValue($row, ['measurement_target', 'base']) ?: 'body',
            'size_system' => $this->spreadsheetValue($row, ['size_system', 'sistema']) ?: 'br_alpha',
            'range_mode' => $this->spreadsheetValue($row, ['range_mode', 'ranges']) ?: 'min_max',
            'status' => $this->spreadsheetValue($row, ['status']) ?: 'active',
            'table_notes' => $this->spreadsheetValue($row, ['table_notes', 'notes', 'observacoes']),
            'size_label' => $sizeLabel,
            'size_note' => $this->spreadsheetValue($row, ['size_note', 'observacao_tamanho']),
            'measurement_notes' => [],
        ];

        if (! $tableName) {
            $errors[] = $this->importError($row, 'table_name', 'Informe o nome da tabela.', 'Preencha table_name com o nome usado na listagem.');
        }

        if (! $sizeLabel) {
            $errors[] = $this->importError($row, 'size_label', 'Informe o tamanho.', 'Preencha size_label, por exemplo P, M, G ou 40.');
        }

        foreach ([
            'measurement_target' => ['body', 'garment', 'mixed'],
            'size_system' => ['br_alpha', 'br_numeric', 'international', 'custom'],
            'range_mode' => ['min_max', 'exact', 'tolerance'],
            'status' => ['active', 'draft', 'inactive'],
        ] as $field => $allowed) {
            if (! in_array($data[$field], $allowed, true)) {
                $errors[] = $this->importError($row, $field, "Valor inválido para {$field}.", 'Use: '.implode(', ', $allowed).'.');
            }
        }

        foreach (self::RANGE_FIELDS as $field) {
            $min = $this->spreadsheetDecimal($row, $field.'_min', $errors);
            $max = $this->spreadsheetDecimal($row, $field.'_max', $errors);
            $data[$field.'_min'] = $min;
            $data[$field.'_max'] = $max;

            if ($min !== null && $max !== null && $min > $max) {
                $errors[] = $this->importError($row, $field.'_max', 'Máximo menor que o mínimo.', 'Ajuste o máximo para ser maior ou igual ao mínimo.');
            }

            if ($note = $this->spreadsheetValue($row, [$field.'_note'])) {
                $data['measurement_notes'][$field] = $note;
            }
        }

        $exists = $tableName
            ? MeasurementTable::query()
                ->where('merchant_id', $merchant->id)
                ->tap(fn ($query) => $this->scopeCompany($query, $company))
                ->where('name', $tableName)
                ->exists()
            : false;

        return [
            'line' => $row['_line'] ?? null,
            'valid' => $errors === [],
            'errors' => $errors,
            'action' => $exists ? 'update' : 'create',
            'data' => $data,
        ];
    }

    private function parseSpreadsheet(string $format, string $content): array
    {
        return $format === 'xlsx'
            ? $this->parseXlsx(base64_decode($content, true) ?: '')
            : $this->parseCsv($content);
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
            $row = ['_line' => $index + 2, '_columns' => []];

            foreach ($headers as $position => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = trim((string) ($values[$position] ?? ''));
                $row['_columns'][$header] = $position + 1;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function parseXlsx(string $content): array
    {
        if ($content === '' || ! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Arquivo XLSX inválido ou extensão ZIP indisponível.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'pv-import-xlsx-');
        file_put_contents($temp, $content);
        $zip = new ZipArchive;

        if ($zip->open($temp) !== true) {
            @unlink($temp);
            throw new RuntimeException('Não foi possível abrir o XLSX.');
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($temp);

        if (! $sheet) {
            throw new RuntimeException('A primeira planilha do XLSX não foi encontrada.');
        }

        return $this->parseXlsxSheet($sheet, $sharedStrings);
    }

    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! $xml) {
            return [];
        }

        $sheet = simplexml_load_string($xml);

        if (! $sheet instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];
        $namespaces = $sheet->getNamespaces(true);
        $sheet->registerXPathNamespace('m', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($sheet->xpath('//m:si') ?: [] as $item) {
            $item->registerXPathNamespace('m', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $strings[] = trim(implode('', array_map('strval', $item->xpath('.//m:t') ?: [])));
        }

        return $strings;
    }

    private function parseXlsxSheet(string $xml, array $sharedStrings): array
    {
        $sheet = simplexml_load_string($xml);

        if (! $sheet instanceof SimpleXMLElement) {
            throw new RuntimeException('Planilha XLSX inválida.');
        }

        $rows = [];
        $headers = [];
        $namespaces = $sheet->getNamespaces(true);
        $namespace = $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sheetRows = $sheet->children($namespace)->sheetData->children($namespace)->row;
        $rowNumber = 0;

        foreach ($sheetRows as $row) {
            $cells = [];

            foreach ($row->children($namespace)->c as $cell) {
                $reference = (string) $cell->attributes()['r'];
                $columnIndex = $this->columnIndex(preg_replace('/\d+/', '', $reference));
                $cells[$columnIndex] = trim($this->xlsxCellValue($cell, $sharedStrings));
            }

            if ($rowNumber === 0) {
                $headers = [];

                foreach ($cells as $columnIndex => $header) {
                    $headers[$columnIndex] = $this->normalizeHeader($header);
                }

                $rowNumber++;

                continue;
            }

            if (collect($cells)->filter()->isEmpty()) {
                $rowNumber++;

                continue;
            }

            $normalized = ['_line' => $rowNumber + 1, '_columns' => []];

            foreach ($headers as $columnIndex => $header) {
                if ($header === '') {
                    continue;
                }

                $normalized[$header] = $cells[$columnIndex] ?? '';
                $normalized['_columns'][$header] = $columnIndex;
            }

            $rows[] = $normalized;
            $rowNumber++;
        }

        return $rows;
    }

    private function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell->attributes()['t'];

        if ($type === 's') {
            return (string) ($sharedStrings[(int) $cell->v] ?? '');
        }

        if ($type === 'inlineStr') {
            $namespaces = $cell->getNamespaces(true);
            $namespace = $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $inline = $cell->children($namespace)->is;

            if ($inline) {
                return trim(implode('', array_map('strval', $inline->xpath('.//*[local-name()="t"]') ?: [])));
            }

            return '';
        }

        return (string) ($cell->v ?? '');
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split(Str::upper($letters)) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index;
    }

    private function detectDelimiter(string $line): string
    {
        return collect([',', ';', "\t"])
            ->sortByDesc(fn (string $delimiter): int => substr_count($line, $delimiter))
            ->first();
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->trim()->lower()->replace([' ', '-', '.', '/'], '_')->ascii()->toString();
    }

    private function spreadsheetValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function spreadsheetDecimal(array $row, string $key, array &$errors): ?float
    {
        $value = $this->spreadsheetValue($row, [$key]);

        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            $errors[] = $this->importError($row, $key, 'Valor numérico inválido.', 'Use número em centímetros, por exemplo 88 ou 88,5.');

            return null;
        }

        $number = (float) $normalized;

        if ($number < 0 || $number > 999.99) {
            $errors[] = $this->importError($row, $key, 'Valor fora do intervalo permitido.', 'Use um número entre 0 e 999,99.');

            return null;
        }

        return round($number, 2);
    }

    private function importError(array $row, string $field, string $message, string $suggestion): array
    {
        return [
            'line' => $row['_line'] ?? null,
            'column' => data_get($row, "_columns.{$field}"),
            'field' => $field,
            'message' => $message,
            'suggestion' => $suggestion,
        ];
    }

    private function rowPayload(array $data): array
    {
        return [
            'size_label' => $data['size_label'],
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
            'length_min' => $data['length_min'],
            'length_max' => $data['length_max'],
            'shoulder_min' => $data['shoulder_min'],
            'shoulder_max' => $data['shoulder_max'],
            'measurements' => $this->measurementsPayload($data),
            'composite_measurements' => $data['composite_min'] !== null || $data['composite_max'] !== null
                ? ['fit_balance' => [
                    'label' => 'Busto + cintura + quadril',
                    'formula' => 'bust+waist+hip',
                    'min' => $data['composite_min'],
                    'max' => $data['composite_max'],
                ]]
                : [],
            'metadata' => $this->rowMetadataPayload([
                'size_note' => $data['size_note'],
                'measurement_notes' => $data['measurement_notes'],
            ]),
        ];
    }

    private function legacyMeasurementFields(): array
    {
        return [
            'bust' => 'Busto',
            'waist' => 'Cintura',
            'hip' => 'Quadril',
            'height' => 'Altura',
            'weight' => 'Peso',
            'length' => 'Comprimento',
            'shoulder' => 'Ombro',
        ];
    }
}
