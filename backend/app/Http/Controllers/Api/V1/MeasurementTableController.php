<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeasurementTableRequest;
use App\Http\Requests\UpdateMeasurementTableRequest;
use App\Http\Resources\MeasurementTableResource;
use App\Models\MeasurementTable;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeasurementTableController extends Controller
{
    use ResolvesMerchant;

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        $tables = MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->with('company')
            ->withCount(['rows', 'products'])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('product_type', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->get();

        return MeasurementTableResource::collection($tables)->additional([
            'summary' => [
                'total' => $tables->count(),
                'active' => $tables->where('status', 'active')->count(),
            ],
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
