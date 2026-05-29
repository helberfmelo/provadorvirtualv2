<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFitProfileRequest;
use App\Http\Requests\UpdateFitProfileRequest;
use App\Http\Resources\FitProfileResource;
use App\Http\Resources\ProductResource;
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

    public function diagnostics(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $profiles = $this->profilesForDiagnostics($merchant, $company);
        $products = $this->productsForDiagnostics($merchant, $company, 1200);
        $issues = $products
            ->map(fn (Product $product): ?array => $this->productModelingIssue($product, $profiles))
            ->filter()
            ->values();
        $groups = $this->diagnosticGroups($issues);

        return response()->json([
            'summary' => [
                'products_analyzed' => $products->count(),
                'issues' => $issues->count(),
                'without_modeling' => $issues->where('code', 'without_modeling')->count(),
                'modeling_not_found' => $issues->where('code', 'modeling_not_found')->count(),
                'modeling_inactive' => $issues->where('code', 'modeling_inactive')->count(),
                'modeling_incompatible' => $issues->whereIn('code', ['modeling_gender_mismatch', 'modeling_category_mismatch'])->count(),
                'groups' => $groups->count(),
            ],
            'groups' => $groups->values()->all(),
            'issues' => $issues->take(160)->values()->all(),
        ]);
    }

    public function applyDiagnostics(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer'],
            'profile_id' => ['nullable', 'integer'],
            'profile' => ['nullable', 'array'],
            'profile.name' => ['required_without:profile_id', 'string', 'max:120'],
            'profile.code' => ['nullable', 'string', 'max:80'],
            'profile.description' => ['nullable', 'string', 'max:600'],
            'profile.product_type' => ['nullable', 'string', 'max:80'],
            'profile.gender' => ['nullable', 'in:female,male,unisex,kids'],
            'profile.fit_intensity' => ['nullable', 'in:very_slim,slim,regular,relaxed,oversized,custom'],
            'profile.stretch_level' => ['nullable', 'in:none,low,medium,high'],
        ]);

        if (blank($data['profile_id'] ?? null) && blank($data['profile'] ?? null)) {
            throw ValidationException::withMessages([
                'profile_id' => ['Informe uma modelagem existente ou os dados para criar uma modelagem.'],
            ]);
        }

        $productIds = collect($data['product_ids'])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $batchId = (string) Str::uuid();
        $created = false;

        $profile = DB::transaction(function () use ($request, $merchant, $company, $data, $productIds, $batchId, &$created): FitProfile {
            $profile = $this->resolveDiagnosticProfile($merchant, $company, $data, $created);
            $products = Product::query()
                ->where('merchant_id', $merchant->id)
                ->tap(fn ($query) => $this->scopeCompany($query, $company))
                ->whereIn('id', $productIds)
                ->get();
            $updatedIds = [];
            $skippedIds = [];

            foreach ($products as $product) {
                if ((string) $product->fit_profile === (string) $profile->code) {
                    $skippedIds[] = $product->id;

                    continue;
                }

                $metadata = $product->metadata ?? [];
                $metadata['field_sources'] = is_array($metadata['field_sources'] ?? null) ? $metadata['field_sources'] : [];
                $metadata['field_sources']['fit_profile'] = 'diagnostic';
                $metadata['fit_profile_diagnostic']['last_action'] = [
                    'batch_id' => $batchId,
                    'previous_fit_profile' => $product->fit_profile,
                    'fit_profile' => $profile->code,
                    'fit_profile_id' => $profile->id,
                    'source' => $created ? 'diagnostic_create_and_apply' : 'diagnostic_apply',
                    'created_at' => now()->toISOString(),
                ];
                $metadata = $this->appendProductHistory($metadata, 'fit_profile.diagnostic_applied', [
                    'batch_id' => $batchId,
                    'from' => $product->fit_profile,
                    'to' => $profile->code,
                    'fit_profile_id' => $profile->id,
                ]);

                $product->update([
                    'fit_profile' => $profile->code,
                    'metadata' => $metadata,
                ]);
                $updatedIds[] = $product->id;
            }

            app(AuditLogger::class)->log($request, $merchant, 'fit_profile.diagnostic_applied', 'measurement_tables', 'info', [
                'module' => 'measurement_tables',
                'action' => $created ? 'create_and_apply_fit_profile' : 'apply_fit_profile',
                'merchant_company_id' => $company?->id,
                'fit_profile_id' => $profile->id,
                'code' => $profile->code,
                'batch_id' => $batchId,
                'product_ids' => $productIds->all(),
                'updated_product_ids' => $updatedIds,
                'skipped_product_ids' => $skippedIds,
            ], $profile);

            $profile->setAttribute('diagnostic_summary', [
                'batch_id' => $batchId,
                'requested' => $productIds->count(),
                'updated' => count($updatedIds),
                'skipped' => count($skippedIds),
                'created' => $created,
            ]);

            return $profile;
        });

        $updatedProducts = Product::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereIn('id', $productIds)
            ->with(['company', 'measurementTable'])
            ->withCount('variants')
            ->orderByDesc('id')
            ->get();

        $this->attachUsageCounts(new Collection([$profile]), $merchant, $company);

        return ProductResource::collection($updatedProducts)->additional([
            'summary' => $profile->getAttribute('diagnostic_summary'),
            'profile' => (new FitProfileResource($profile->load('company')))->resolve($request),
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
            'metadata' => $this->fitProfileMetadata($data, 'merchant_portal'),
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
        $data['metadata'] = array_merge(
            $fitProfile->metadata ?? [],
            $this->fitProfileMetadata([
                'product_type' => $data['product_type'] ?? $fitProfile->product_type,
                'gender' => $data['gender'] ?? $fitProfile->gender,
                'fit_intensity' => $data['fit_intensity'] ?? $fitProfile->fit_intensity ?? 'regular',
                'stretch_level' => $data['stretch_level'] ?? $fitProfile->stretch_level ?? 'medium',
            ], data_get($fitProfile->metadata ?? [], 'source', 'merchant_portal'))
        );

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

    private function profilesForDiagnostics(Merchant $merchant, ?MerchantCompany $company): Collection
    {
        return FitProfile::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'draft' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();
    }

    private function productsForDiagnostics(Merchant $merchant, ?MerchantCompany $company, int $limit): Collection
    {
        return Product::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->with(['variants' => fn ($query) => $query->select(['id', 'product_id', 'size_label', 'is_active'])->orderBy('id')])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function productModelingIssue(Product $product, Collection $profiles): ?array
    {
        $fitProfile = trim((string) $product->fit_profile);
        $profile = filled($fitProfile) ? $this->profileByCode($profiles, $fitProfile) : null;
        $code = null;
        $severity = 'warning';
        $title = '';
        $cause = '';
        $action = '';

        if (blank($fitProfile)) {
            $code = 'without_modeling';
            $title = 'Modelagem ausente';
            $cause = 'O produto veio sem caimento, então o provador usa apenas tabela e medidas.';
            $action = 'Aplicar uma modelagem existente ou criar uma a partir deste grupo de produtos.';
        } elseif (! $profile) {
            $code = 'modeling_not_found';
            $severity = 'danger';
            $title = 'Modelagem não encontrada';
            $cause = 'O produto referencia "'.$fitProfile.'", mas não existe modelagem ativa ou cadastrada com esse código.';
            $action = 'Criar a modelagem ausente ou trocar o produto para uma modelagem cadastrada.';
        } elseif ($profile->status !== 'active') {
            $code = 'modeling_inactive';
            $title = 'Modelagem inativa';
            $cause = 'A modelagem existe, mas não está ativa para orientar regras e revisão.';
            $action = 'Ativar a modelagem ou aplicar uma modelagem ativa equivalente.';
        } elseif ($this->genderMismatch($product, $profile)) {
            $code = 'modeling_gender_mismatch';
            $title = 'Gênero incompatível';
            $cause = 'O gênero do produto não combina com o público cadastrado na modelagem.';
            $action = 'Trocar para uma modelagem compatível ou ajustar o cadastro da modelagem.';
        } elseif ($this->categoryMismatch($product, $profile)) {
            $code = 'modeling_category_mismatch';
            $title = 'Categoria fora da regra da modelagem';
            $cause = 'O tipo de produto da modelagem não parece combinar com a categoria do produto.';
            $action = 'Revisar o tipo da modelagem ou aplicar uma modelagem específica para esta categoria.';
        }

        if (! $code) {
            return null;
        }

        $suggestion = $this->suggestFitProfile($product, $profiles, $fitProfile, $code);

        return [
            'code' => $code,
            'severity' => $severity,
            'title' => $title,
            'cause' => $cause,
            'action' => $action,
            'product' => $this->diagnosticProductPayload($product),
            'current_profile' => $profile ? $this->profilePayload($profile) : [
                'code' => $fitProfile ?: null,
                'name' => $fitProfile ?: null,
                'status' => blank($fitProfile) ? 'missing' : 'unknown',
            ],
            'suggested_profile' => $suggestion,
            'group_key' => $code.'|'.($suggestion['code'] ?? 'review').'|'.$this->normalizedText($product->category).'|'.$this->normalizedText(data_get($product->metadata ?? [], 'brand')),
        ];
    }

    private function diagnosticGroups($issues)
    {
        return $issues
            ->groupBy('group_key')
            ->map(function ($group): array {
                $first = $group->first();
                $suggestion = $first['suggested_profile'];
                $products = $group->pluck('product');

                return [
                    'key' => $first['group_key'],
                    'code' => $first['code'],
                    'severity' => $first['severity'],
                    'title' => $first['title'],
                    'cause' => $first['cause'],
                    'action' => $first['action'],
                    'suggested_profile' => $suggestion,
                    'products_count' => $group->count(),
                    'product_ids' => $products->pluck('id')->values()->all(),
                    'sample_products' => $products->take(5)->values()->all(),
                    'category' => $products->pluck('category')->filter()->countBy()->sortDesc()->keys()->first(),
                    'brand' => $products->pluck('brand')->filter()->countBy()->sortDesc()->keys()->first(),
                    'gender' => $products->pluck('gender')->filter()->countBy()->sortDesc()->keys()->first(),
                    'age_group' => $products->pluck('age_group')->filter()->countBy()->sortDesc()->keys()->first(),
                ];
            })
            ->sortByDesc(fn (array $group): int => $group['products_count'])
            ->values();
    }

    private function suggestFitProfile(Product $product, Collection $profiles, string $currentFit, string $issueCode): array
    {
        $activeProfiles = $profiles->where('status', 'active')->values();
        $normalizedCurrent = $this->normalizedText($currentFit);
        $productText = $this->normalizedText(implode(' ', [
            $product->name,
            $product->category,
            data_get($product->metadata ?? [], 'brand'),
            $currentFit,
        ]));

        $ranked = $activeProfiles
            ->map(function (FitProfile $profile) use ($product, $normalizedCurrent, $productText): array {
                $score = 0;
                $reasons = [];
                $profileCode = $this->normalizedText($profile->code);
                $profileName = $this->normalizedText($profile->name);
                $profileType = $this->normalizedText($profile->product_type);
                $productCategory = $this->normalizedText($product->category);
                $profileGender = $this->normalizedGender($profile->gender);
                $productGender = $this->normalizedGender($product->gender);

                if ($normalizedCurrent && ($normalizedCurrent === $profileCode || $normalizedCurrent === $profileName)) {
                    $score += 8;
                    $reasons[] = 'código igual ao recebido';
                }

                if ($profileType && $productCategory && $this->textOverlaps($profileType, $productCategory)) {
                    $score += 4;
                    $reasons[] = 'categoria compatível';
                }

                if ($productGender && ($profileGender === $productGender || $profileGender === 'unisex')) {
                    $score += 3;
                    $reasons[] = 'público compatível';
                }

                if ($this->textContainsIntensity($productText, (string) $profile->fit_intensity, (string) $profile->code)) {
                    $score += 3;
                    $reasons[] = 'nome sugere o mesmo caimento';
                }

                if ($profile->code === 'regular') {
                    $score += 1;
                    $reasons[] = 'fallback seguro';
                }

                return [
                    'profile' => $profile,
                    'score' => $score,
                    'reasons' => $reasons,
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] > 0)
            ->sortByDesc('score')
            ->values();

        if ($ranked->isNotEmpty() && $issueCode !== 'modeling_not_found') {
            $candidate = $ranked->first();

            return [
                ...$this->profilePayload($candidate['profile']),
                'mode' => 'existing',
                'confidence' => $this->confidenceFromScore($candidate['score']),
                'reasons' => $candidate['reasons'],
            ];
        }

        if ($ranked->isNotEmpty() && $normalizedCurrent === '') {
            $candidate = $ranked->first();

            return [
                ...$this->profilePayload($candidate['profile']),
                'mode' => 'existing',
                'confidence' => $this->confidenceFromScore($candidate['score']),
                'reasons' => $candidate['reasons'],
            ];
        }

        $payload = $this->newProfileSuggestionPayload($product, $currentFit);

        return [
            ...$payload,
            'mode' => 'create',
            'confidence' => $issueCode === 'modeling_not_found' ? 'high' : 'medium',
            'reasons' => [
                $issueCode === 'modeling_not_found'
                    ? 'cria exatamente a modelagem ausente recebida na sincronização'
                    : 'grupo sem modelagem clara; cria cadastro revisável antes de aplicar',
            ],
            'profile' => $payload,
        ];
    }

    private function resolveDiagnosticProfile(Merchant $merchant, ?MerchantCompany $company, array $data, bool &$created): FitProfile
    {
        if (filled($data['profile_id'] ?? null)) {
            $profile = FitProfile::query()
                ->where('merchant_id', $merchant->id)
                ->tap(fn ($query) => $this->scopeCompany($query, $company))
                ->whereKey((int) $data['profile_id'])
                ->firstOrFail();

            if ($profile->status !== 'active') {
                throw ValidationException::withMessages([
                    'profile_id' => ['A modelagem precisa estar ativa para aplicação em massa.'],
                ]);
            }

            return $profile;
        }

        $profileData = $data['profile'] ?? [];
        $targetCompany = array_key_exists('merchant_company_id', $profileData)
            ? ($profileData['merchant_company_id'] ? $this->merchantCompany($merchant, $profileData['merchant_company_id']) : null)
            : $company;
        $code = $this->codeFor($profileData['code'] ?? $profileData['name']);

        $this->ensureUniqueCode($merchant, $targetCompany, $code);
        $created = true;

        $profile = FitProfile::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $targetCompany?->id,
            'name' => $profileData['name'],
            'code' => $code,
            'description' => $profileData['description'] ?? 'Criada pelo diagnóstico guiado de modelagens.',
            'product_type' => $profileData['product_type'] ?? null,
            'gender' => $profileData['gender'] ?? null,
            'fit_intensity' => $profileData['fit_intensity'] ?? 'regular',
            'stretch_level' => $profileData['stretch_level'] ?? 'medium',
            'status' => 'active',
            'metadata' => $this->fitProfileMetadata($profileData, 'diagnostic'),
        ]);

        app(AuditLogger::class)->log(request(), $merchant, 'fit_profile.created_from_diagnostic', 'measurement_tables', 'info', [
            'fit_profile_id' => $profile->id,
            'merchant_company_id' => $targetCompany?->id,
            'module' => 'measurement_tables',
            'action' => 'create_from_diagnostic',
            'code' => $profile->code,
        ], $profile);

        return $profile;
    }

    private function diagnosticProductPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku ?: $product->external_product_id,
            'category' => $product->category,
            'brand' => data_get($product->metadata ?? [], 'brand'),
            'gender' => $product->gender,
            'age_group' => data_get($product->metadata ?? [], 'age_group'),
            'fit_profile' => $product->fit_profile,
            'sizes' => $product->relationLoaded('variants')
                ? $product->variants->pluck('size_label')->filter()->unique()->values()->all()
                : [],
        ];
    }

    private function profilePayload(FitProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'code' => $profile->code,
            'product_type' => $profile->product_type,
            'gender' => $profile->gender,
            'fit_intensity' => $profile->fit_intensity ?: 'regular',
            'stretch_level' => $profile->stretch_level ?: 'medium',
            'status' => $profile->status,
        ];
    }

    private function newProfileSuggestionPayload(Product $product, string $currentFit): array
    {
        $codeSource = filled($currentFit) ? $currentFit : implode(' ', array_filter([
            $product->category,
            data_get($product->metadata ?? [], 'brand'),
            'regular',
        ]));
        $code = $this->codeFor($codeSource);
        $name = filled($currentFit)
            ? Str::headline(str_replace(['_', '-'], ' ', $currentFit))
            : 'Modelagem '.$this->humanContextName($product);

        return [
            'name' => Str::limit($name, 120, ''),
            'code' => Str::limit($code, 80, ''),
            'description' => 'Sugerida pelo diagnóstico de produtos sem modelagem consistente.',
            'product_type' => $product->category ? Str::limit($product->category, 80, '') : null,
            'gender' => $this->normalizedGender($product->gender) ?: 'unisex',
            'fit_intensity' => $this->intensityFromText($currentFit.' '.$product->name.' '.$product->category),
            'stretch_level' => 'medium',
        ];
    }

    private function humanContextName(Product $product): string
    {
        $parts = array_filter([
            $product->category,
            data_get($product->metadata ?? [], 'brand'),
            $this->normalizedGender($product->gender),
        ]);

        return Str::limit(implode(' · ', $parts) ?: 'regular', 96, '');
    }

    private function genderMismatch(Product $product, FitProfile $profile): bool
    {
        $productGender = $this->normalizedGender($product->gender);
        $profileGender = $this->normalizedGender($profile->gender);

        return filled($productGender)
            && filled($profileGender)
            && $profileGender !== 'unisex'
            && $productGender !== $profileGender;
    }

    private function categoryMismatch(Product $product, FitProfile $profile): bool
    {
        $category = $this->normalizedText($product->category);
        $productType = $this->normalizedText($profile->product_type);

        return filled($category)
            && filled($productType)
            && ! $this->textOverlaps($category, $productType);
    }

    private function profileByCode(Collection $profiles, string $code): ?FitProfile
    {
        $normalizedCode = $this->normalizedText($code);

        return $profiles->first(fn (FitProfile $profile): bool => $this->normalizedText($profile->code) === $normalizedCode);
    }

    private function textOverlaps(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        return $left === $right || str_contains($left, $right) || str_contains($right, $left);
    }

    private function textContainsIntensity(string $text, string $intensity, string $code): bool
    {
        $needles = match ($intensity) {
            'very_slim' => ['very slim', 'super slim', 'muito ajustada'],
            'slim' => ['slim', 'skinny', 'ajustada'],
            'relaxed' => ['relaxed', 'loose', 'solta', 'conforto'],
            'oversized' => ['oversized', 'ampla', 'over'],
            'regular' => ['regular', 'reta', 'tradicional'],
            default => [$code],
        };

        foreach ($needles as $needle) {
            if ($this->normalizedText($needle) !== '' && str_contains($text, $this->normalizedText($needle))) {
                return true;
            }
        }

        return $code !== '' && str_contains($text, $this->normalizedText($code));
    }

    private function intensityFromText(string $value): string
    {
        $text = $this->normalizedText($value);

        return match (true) {
            str_contains($text, 'very slim') || str_contains($text, 'super slim') || str_contains($text, 'muito ajust') => 'very_slim',
            str_contains($text, 'slim') || str_contains($text, 'skinny') || str_contains($text, 'ajust') => 'slim',
            str_contains($text, 'oversized') || str_contains($text, 'over') || str_contains($text, 'ampl') => 'oversized',
            str_contains($text, 'relax') || str_contains($text, 'loose') || str_contains($text, 'solt') || str_contains($text, 'comfort') || str_contains($text, 'confort') => 'relaxed',
            default => 'regular',
        };
    }

    private function normalizedGender(mixed $value): ?string
    {
        $text = $this->normalizedText($value);

        return match ($text) {
            'f', 'fem', 'female', 'feminino', 'mulher' => 'female',
            'm', 'masc', 'male', 'masculino', 'homem' => 'male',
            'kids', 'kid', 'infantil', 'crianca', 'criancas' => 'kids',
            'unisex', 'unissex', 'all', 'todos' => 'unisex',
            default => $text ?: null,
        };
    }

    private function confidenceFromScore(int $score): string
    {
        return match (true) {
            $score >= 9 => 'high',
            $score >= 5 => 'medium',
            default => 'low',
        };
    }

    private function normalizedText(mixed $value): string
    {
        $normalized = Str::ascii(Str::lower((string) $value));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: '');
    }

    private function appendProductHistory(array $metadata, string $event, array $details): array
    {
        $history = is_array($metadata['history'] ?? null) ? $metadata['history'] : [];
        array_unshift($history, [
            'event' => $event,
            'source' => 'diagnostic',
            'details' => $details,
            'created_at' => now()->toISOString(),
        ]);

        $metadata['history'] = array_slice($history, 0, 25);

        return $metadata;
    }

    private function fitProfileMetadata(array $data, string $source): array
    {
        return [
            'source' => $source,
            'rules_context' => [
                'product_type' => $data['product_type'] ?? null,
                'gender' => $data['gender'] ?? null,
                'fit_intensity' => $data['fit_intensity'] ?? 'regular',
                'stretch_level' => $data['stretch_level'] ?? 'medium',
            ],
            'ai_context' => [
                'use_for_table_suggestions' => true,
                'use_for_product_diagnostics' => true,
                'review_required' => true,
            ],
            'recommendation_impact' => $this->recommendationImpact(
                $data['fit_intensity'] ?? 'regular',
                $data['stretch_level'] ?? 'medium'
            ),
        ];
    }

    private function recommendationImpact(string $intensity, string $stretch): array
    {
        return [
            'summary' => match ($intensity) {
                'very_slim', 'slim' => 'Caimento ajustado: revisar confiança quando houver medidas no limite superior.',
                'relaxed', 'oversized' => 'Caimento amplo: revisar alertas de peça solta e preferência do consumidor.',
                default => 'Caimento regular: base neutra para revisar recomendações e feedback.',
            },
            'confidence_hint' => match ($stretch) {
                'high' => 'Elasticidade alta pode tolerar pequenas variações de medida.',
                'none', 'low' => 'Baixa elasticidade exige maior cuidado com medidas no limite.',
                default => 'Elasticidade média mantém tolerância padrão.',
            },
        ];
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
