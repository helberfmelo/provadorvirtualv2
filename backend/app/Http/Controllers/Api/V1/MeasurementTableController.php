<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeasurementTableRequest;
use App\Http\Requests\UpdateMeasurementTableRequest;
use App\Http\Resources\MeasurementTableResource;
use App\Models\MeasurementTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeasurementTableController extends Controller
{
    use ResolvesMerchant;

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);

        $tables = MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
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
        $data = $request->validated();
        $company = $this->merchantCompany($merchant, $data['merchant_company_id'] ?? null);

        $table = DB::transaction(function () use ($merchant, $company, $data): MeasurementTable {
            $table = MeasurementTable::query()->create([
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company?->id,
                'name' => $data['name'],
                'product_type' => $data['product_type'],
                'gender' => $data['gender'] ?? null,
                'fit_profile' => $data['fit_profile'] ?? null,
                'unit' => $data['unit'] ?? 'cm',
                'status' => $data['status'] ?? 'active',
                'source' => $data['source'] ?? 'manual',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncRows($table, $data['rows'] ?? []);

            return $table;
        });

        return (new MeasurementTableResource($table->load(['company', 'rows'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, MeasurementTable $measurementTable)
    {
        $merchant = $this->currentMerchant($request);
        $this->scopedMeasurementTable($merchant, $measurementTable);

        return new MeasurementTableResource($measurementTable->load(['company', 'rows']));
    }

    public function update(UpdateMeasurementTableRequest $request, MeasurementTable $measurementTable)
    {
        $merchant = $this->currentMerchant($request);
        $this->scopedMeasurementTable($merchant, $measurementTable);
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

        return new MeasurementTableResource($measurementTable->refresh()->load(['company', 'rows']));
    }

    public function destroy(Request $request, MeasurementTable $measurementTable)
    {
        $merchant = $this->currentMerchant($request);
        $this->scopedMeasurementTable($merchant, $measurementTable);

        DB::transaction(function () use ($measurementTable): void {
            $measurementTable->products()->update(['measurement_table_id' => null]);
            $measurementTable->delete();
        });

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
            ]);
        }
    }
}
