<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFitProfileRequest;
use App\Http\Requests\UpdateFitProfileRequest;
use App\Http\Resources\FitProfileResource;
use App\Models\FitProfile;
use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FitProfileController extends Controller
{
    use ResolvesMerchant;

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        $profiles = FitProfile::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->with('company')
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('product_type', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'draft' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        $this->attachUsageCounts($profiles, $merchant, $company);

        return FitProfileResource::collection($profiles)->additional([
            'summary' => [
                'total' => $profiles->count(),
                'active' => $profiles->where('status', 'active')->count(),
                'used' => $profiles->filter(fn (FitProfile $profile): bool => $this->usageTotal($profile) > 0)->count(),
            ],
        ]);
    }

    public function store(StoreFitProfileRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $activeCompany = $this->currentCompany($request, $merchant);
        $data = $request->validated();
        $company = array_key_exists('merchant_company_id', $data)
            ? ($data['merchant_company_id'] ? $this->merchantCompany($merchant, $data['merchant_company_id']) : null)
            : $activeCompany;
        $code = $this->codeFor($data['code'] ?? $data['name']);

        $this->ensureUniqueCode($merchant, $company, $code);

        $profile = FitProfile::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'name' => $data['name'],
            'code' => $code,
            'description' => $data['description'] ?? null,
            'product_type' => $data['product_type'] ?? null,
            'gender' => $data['gender'] ?? null,
            'fit_intensity' => $data['fit_intensity'] ?? 'regular',
            'stretch_level' => $data['stretch_level'] ?? 'medium',
            'status' => $data['status'] ?? 'active',
            'metadata' => ['source' => 'merchant_portal'],
        ]);

        app(AuditLogger::class)->log($request, $merchant, 'fit_profile.created', 'measurement_tables', 'info', [
            'fit_profile_id' => $profile->id,
            'merchant_company_id' => $company?->id,
            'module' => 'measurement_tables',
            'action' => 'create',
            'code' => $profile->code,
        ], $profile);

        $this->attachUsageCounts(new Collection([$profile]), $merchant, $company);

        return (new FitProfileResource($profile->load('company')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, FitProfile $fitProfile)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedFitProfile($merchant, $fitProfile, $company);
        $this->attachUsageCounts(new Collection([$fitProfile]), $merchant, $company);

        return new FitProfileResource($fitProfile->load('company'));
    }

    public function update(UpdateFitProfileRequest $request, FitProfile $fitProfile)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedFitProfile($merchant, $fitProfile, $company);
        $data = $request->validated();

        if (array_key_exists('merchant_company_id', $data)) {
            $data['merchant_company_id'] = $data['merchant_company_id']
                ? $this->merchantCompany($merchant, $data['merchant_company_id'])?->id
                : null;
        }

        if (array_key_exists('code', $data) || array_key_exists('name', $data)) {
            $targetCompany = array_key_exists('merchant_company_id', $data)
                ? ($data['merchant_company_id'] ? $this->merchantCompany($merchant, $data['merchant_company_id']) : null)
                : ($fitProfile->merchant_company_id ? $this->merchantCompany($merchant, $fitProfile->merchant_company_id) : null);
            $data['code'] = $this->codeFor($data['code'] ?? $fitProfile->code ?? $data['name']);
            $this->ensureUniqueCode($merchant, $targetCompany, $data['code'], $fitProfile);
        }

        $oldCode = $fitProfile->code;

        DB::transaction(function () use ($fitProfile, $merchant, $data, $oldCode): void {
            $fitProfile->update($data);

            if (($data['code'] ?? $oldCode) !== $oldCode) {
                $this->retargetUsage($merchant, $fitProfile, $oldCode);
            }
        });

        app(AuditLogger::class)->log($request, $merchant, 'fit_profile.updated', 'measurement_tables', 'info', [
            'fit_profile_id' => $fitProfile->id,
            'merchant_company_id' => $fitProfile->merchant_company_id,
            'module' => 'measurement_tables',
            'action' => 'update',
            'code' => $fitProfile->code,
        ], $fitProfile);

        $fitProfile->refresh();
        $this->attachUsageCounts(new Collection([$fitProfile]), $merchant, $company);

        return new FitProfileResource($fitProfile->load('company'));
    }

    public function destroy(Request $request, FitProfile $fitProfile)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedFitProfile($merchant, $fitProfile, $company);
        $usage = $this->usageCountsForCode($merchant, $fitProfile->code, $fitProfile->merchant_company_id);

        if ($usage['products_count'] > 0 || $usage['measurement_tables_count'] > 0) {
            return response()->json([
                'message' => 'Modelagem em uso. Inative a modelagem ou desvincule produtos e tabelas antes de remover.',
                'usage' => $usage,
            ], 422);
        }

        $fitProfile->delete();

        app(AuditLogger::class)->log($request, $merchant, 'fit_profile.deleted', 'measurement_tables', 'warning', [
            'fit_profile_id' => $fitProfile->id,
            'merchant_company_id' => $fitProfile->merchant_company_id,
            'module' => 'measurement_tables',
            'action' => 'delete',
            'code' => $fitProfile->code,
        ], $fitProfile);

        return response()->json([
            'message' => 'Modelagem removida com sucesso.',
        ]);
    }

    private function scopedFitProfile(Merchant $merchant, FitProfile $fitProfile, ?MerchantCompany $company = null): FitProfile
    {
        if ((int) $fitProfile->merchant_id !== (int) $merchant->id) {
            throw new NotFoundHttpException('Modelagem não encontrada.');
        }

        if ($company && $fitProfile->merchant_company_id && (int) $fitProfile->merchant_company_id !== (int) $company->id) {
            throw new NotFoundHttpException('Modelagem não encontrada.');
        }

        return $fitProfile;
    }

    private function attachUsageCounts(Collection $profiles, Merchant $merchant, ?MerchantCompany $company): void
    {
        if ($profiles->isEmpty()) {
            return;
        }

        $codes = $profiles->pluck('code')->filter()->unique()->values();
        $productCounts = Product::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereIn('fit_profile', $codes)
            ->selectRaw('fit_profile, count(*) as aggregate')
            ->groupBy('fit_profile')
            ->pluck('aggregate', 'fit_profile');
        $tableCounts = MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereIn('fit_profile', $codes)
            ->selectRaw('fit_profile, count(*) as aggregate')
            ->groupBy('fit_profile')
            ->pluck('aggregate', 'fit_profile');

        $profiles->each(function (FitProfile $profile) use ($productCounts, $tableCounts): void {
            $profile->setAttribute('products_count', (int) ($productCounts[$profile->code] ?? 0));
            $profile->setAttribute('measurement_tables_count', (int) ($tableCounts[$profile->code] ?? 0));
        });
    }

    private function usageCountsForCode(Merchant $merchant, string $code, ?int $companyId): array
    {
        $products = Product::query()
            ->where('merchant_id', $merchant->id)
            ->where('fit_profile', $code)
            ->when($companyId, fn ($query) => $query->where('merchant_company_id', $companyId))
            ->count();
        $tables = MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->where('fit_profile', $code)
            ->when($companyId, fn ($query) => $query->where('merchant_company_id', $companyId))
            ->count();

        return [
            'products_count' => $products,
            'measurement_tables_count' => $tables,
        ];
    }

    private function retargetUsage(Merchant $merchant, FitProfile $profile, string $oldCode): void
    {
        Product::query()
            ->where('merchant_id', $merchant->id)
            ->where('fit_profile', $oldCode)
            ->when($profile->merchant_company_id, fn ($query) => $query->where('merchant_company_id', $profile->merchant_company_id))
            ->update(['fit_profile' => $profile->code]);
        MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->where('fit_profile', $oldCode)
            ->when($profile->merchant_company_id, fn ($query) => $query->where('merchant_company_id', $profile->merchant_company_id))
            ->update(['fit_profile' => $profile->code]);
    }

    private function usageTotal(FitProfile $profile): int
    {
        return (int) ($profile->products_count ?? 0) + (int) ($profile->measurement_tables_count ?? 0);
    }

    private function ensureUniqueCode(Merchant $merchant, ?MerchantCompany $company, string $code, ?FitProfile $ignore = null): void
    {
        $query = FitProfile::query()
            ->where('merchant_id', $merchant->id)
            ->where('code', $code)
            ->when($company, function ($innerQuery) use ($company): void {
                $innerQuery->where(function ($scopeQuery) use ($company): void {
                    $scopeQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->when($ignore, fn ($innerQuery) => $innerQuery->where('id', '!=', $ignore->id));

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => ['Já existe uma modelagem com este código.'],
            ]);
        }
    }

    private function codeFor(string $value): string
    {
        return Str::slug($value, '_') ?: Str::random(8);
    }
}
