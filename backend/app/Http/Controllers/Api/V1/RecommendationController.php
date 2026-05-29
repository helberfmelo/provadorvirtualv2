<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgetShopperProfileRequest;
use App\Http\Requests\RecommendationConfigCheckRequest;
use App\Http\Requests\StoreRecommendationFeedbackRequest;
use App\Http\Requests\StoreRecommendationRequest;
use App\Http\Requests\StoreRecommendationSignalRequest;
use App\Models\FitProfile;
use App\Models\MeasurementTable;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLog;
use App\Models\RecommendationSession;
use App\Models\WidgetInstall;
use App\Services\Recommendation\LearningSignalService;
use App\Services\Recommendation\RecommendationEngine;
use App\Services\Recommendation\ShopperProfileService;
use Illuminate\Support\Str;

class RecommendationController extends Controller
{
    public function configCheck(RecommendationConfigCheckRequest $request)
    {
        $product = $this->resolveProduct($request->validated());
        $configurationIssue = $this->configurationIssue($product, false);

        if ($configurationIssue) {
            return response()->json([
                'configured' => false,
                'reason' => $configurationIssue['reason'],
                'message' => $configurationIssue['message'],
            ]);
        }

        $product->load(['measurementTable.rows', 'variants']);
        $virtualTryOnEnabled = $this->measurementTableVirtualTryOnEnabled($product->measurementTable);
        $modelingContext = $this->modelingContext($product);

        return response()->json([
            'configured' => true,
            'product_id' => $product->id,
            'measurement_table_id' => $product->measurement_table_id,
            'virtual_try_on_enabled' => $virtualTryOnEnabled,
            'measurement_table_enabled' => true,
            'available_sizes' => $product->measurementTable->rows->pluck('size_label')->values(),
            'measurement_table' => [
                'id' => $product->measurementTable->id,
                'name' => $product->measurementTable->name,
                'unit' => $product->measurementTable->unit,
                'measurement_target' => $product->measurementTable->measurement_target ?: 'body',
                'size_system' => $product->measurementTable->size_system ?: 'br_alpha',
                'range_mode' => $product->measurementTable->range_mode ?: 'min_max',
                'custom_variations' => data_get($product->measurementTable->metadata ?? [], 'custom_variations', []),
                'rows' => $product->measurementTable->rows->map(fn ($row): array => [
                    'size_label' => $row->size_label,
                    'bust' => [$row->bust_min, $row->bust_max],
                    'waist' => [$row->waist_min, $row->waist_max],
                    'hip' => [$row->hip_min, $row->hip_max],
                    'height' => [$row->height_min, $row->height_max],
                    'weight' => [$row->weight_min, $row->weight_max],
                    'length' => [$row->length_min, $row->length_max],
                    'shoulder' => [$row->shoulder_min, $row->shoulder_max],
                    'measurements' => $row->measurements ?? [],
                    'composite_measurements' => $row->composite_measurements ?? [],
                ])->values(),
            ],
            'modeling_context' => $modelingContext,
            'theme' => WidgetInstall::query()
                ->where('merchant_id', $product->merchant_id)
                ->when($product->merchant_company_id, fn ($query, $companyId) => $query->where('merchant_company_id', $companyId))
                ->where('is_active', true)
                ->first()?->theme ?? [],
        ]);
    }

    public function store(
        StoreRecommendationRequest $request,
        RecommendationEngine $engine,
        ShopperProfileService $profiles,
        LearningSignalService $learning,
    ) {
        $data = $request->validated();
        $product = $this->resolveProduct($data);
        $configurationIssue = $this->configurationIssue($product);

        if ($configurationIssue) {
            return response()->json([
                'configured' => false,
                'reason' => $configurationIssue['reason'],
                'message' => $configurationIssue['message'],
            ], 422);
        }

        $product->load(['measurementTable.rows', 'variants']);
        $result = $engine->recommend($product->measurementTable, $data['measurements']);
        $modelingContext = $this->modelingContext($product);
        $fitNotes = $this->fitNotesWithModeling($result->fitNotes, $modelingContext);
        $warnings = $this->warningsWithModeling($result->warnings, $modelingContext);
        $recommendedVariant = $this->variantForRecommendation($product, $result->recommendedSize);
        $profileState = $profiles->resolve($product, $data['measurements'], $data['shopper_profile'] ?? []);
        $rawWidgetPayload = $this->rawWidgetPayload($request->input('shopper_profile.raw_widget_data'));

        $session = RecommendationSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $product->merchant_id,
            'merchant_company_id' => $product->merchant_company_id,
            'product_id' => $product->id,
            'product_variant_id' => $recommendedVariant?->id,
            'shopper_profile_id' => $profileState['profile']?->id,
            'shopper_profile_uuid' => $profileState['profile']?->uuid,
            'consent_given' => $profileState['consent_given'],
            'shopper_profile' => $data['shopper_profile'] ?? null,
            'profile_snapshot' => $profileState['snapshot'],
            'user_agent_hash' => $this->hashValue($request->userAgent()),
            'ip_hash' => $this->hashValue($request->ip()),
            'expires_at' => now()->addDays(30),
        ]);

        $log = RecommendationLog::query()->create([
            'recommendation_session_id' => $session->id,
            'merchant_id' => $product->merchant_id,
            'merchant_company_id' => $product->merchant_company_id,
            'product_id' => $product->id,
            'product_variant_id' => $recommendedVariant?->id,
            'recommended_size' => $result->recommendedSize,
            'confidence' => $result->confidence,
            'input_measurements' => $data['measurements'],
            'raw_widget_payload' => $rawWidgetPayload,
            'score_breakdown' => $result->scoreBreakdown,
            'fit_notes' => $fitNotes,
            'warnings' => $warnings,
            'status' => $result->needsMoreData ? 'needs_more_data' : 'recommended',
        ]);

        $learningEvent = $learning->recordRecommendation(
            $log,
            $product->measurementTable,
            $data['measurements'],
            $profileState['profile'],
        );

        return response()->json([
            'configured' => true,
            'recommendation_id' => $log->id,
            'session_id' => $session->uuid,
            'product_id' => $product->id,
            'variant_id' => $recommendedVariant?->id,
            'shopper_profile' => $profileState['response'],
            'learning' => [
                'status' => $learningEvent->status,
                'outlier_score' => (float) $learningEvent->outlier_score,
                'weight' => (float) $learningEvent->learning_weight,
            ],
            ...[
                ...$result->toArray(),
                'fit_notes' => $fitNotes,
                'warnings' => $warnings,
                'modeling_context' => $modelingContext,
            ],
        ], 201);
    }

    public function feedback(
        StoreRecommendationFeedbackRequest $request,
        RecommendationLog $recommendationLog,
        LearningSignalService $learning,
    ) {
        $feedback = RecommendationFeedback::query()->create([
            'recommendation_log_id' => $recommendationLog->id,
            ...$request->validated(),
        ]);

        $learningEvent = $learning->recordFeedback($feedback);

        return response()->json([
            'message' => 'Feedback registrado com sucesso.',
            'feedback_id' => $feedback->id,
            'learning_status' => $learningEvent?->status,
        ], 201);
    }

    public function signal(
        StoreRecommendationSignalRequest $request,
        RecommendationLog $recommendationLog,
        LearningSignalService $learning,
    ) {
        $event = $learning->recordCommerceSignal($recommendationLog, $request->validated());

        if (! $event) {
            return response()->json([
                'message' => 'Não foi possível registrar este sinal para aprendizado.',
            ], 422);
        }

        return response()->json([
            'message' => 'Sinal registrado com sucesso.',
            'learning_event_id' => $event->id,
            'learning_status' => $event->status,
            'outlier_score' => (float) $event->outlier_score,
        ], 201);
    }

    public function forgetProfile(ForgetShopperProfileRequest $request, ShopperProfileService $profiles)
    {
        $forgotten = $profiles->forget($request->validated());

        return response()->json([
            'forgotten' => $forgotten,
            'message' => $forgotten ? 'Perfil removido deste provador.' : 'Perfil não encontrado.',
        ], $forgotten ? 200 : 404);
    }

    private function resolveProduct(array $data): ?Product
    {
        $context = $this->resolveMerchantContext($data);
        $merchantId = $context['merchant_id'];
        $company = $context['company'];

        if (! $merchantId) {
            return null;
        }

        $query = Product::query()
            ->where('merchant_id', $merchantId)
            ->when($company, fn ($query) => $query->where('merchant_company_id', $company->id))
            ->when(! $company && ! empty($data['store_id']), fn ($query) => $query->where('merchant_company_id', $data['store_id']));

        if (! empty($data['product_id'])) {
            $query->where(function ($subQuery) use ($data): void {
                $subQuery->whereKey($data['product_id'])
                    ->orWhere('external_product_id', (string) $data['product_id']);
            });
        } elseif (! empty($data['variant_id'])) {
            $variantId = $data['variant_id'];
            $query->whereHas('variants', function ($variantQuery) use ($variantId): void {
                $variantQuery->whereKey($variantId)
                    ->orWhere('external_variant_id', (string) $variantId);
            });
        } elseif (! empty($data['sku'])) {
            $sku = $data['sku'];
            $query->where(function ($subQuery) use ($sku): void {
                $subQuery->where('sku', $sku)
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('sku', $sku));
            });
        }

        return $query->first();
    }

    private function configurationIssue(?Product $product, bool $requireVirtualTryOn = true): ?array
    {
        if (! $product) {
            return [
                'reason' => 'measurement_table_missing',
                'message' => 'Produto sem tabela de medidas configurada.',
            ];
        }

        if ($product->status !== 'active') {
            return [
                'reason' => 'product_inactive',
                'message' => 'Produto inativo no Provador Virtual.',
            ];
        }

        if (! $this->productFlagEnabled($product, 'virtual_try_on_enabled')) {
            return [
                'reason' => 'virtual_try_on_disabled',
                'message' => 'Provador Virtual desativado para este produto.',
            ];
        }

        if (! $this->productFlagEnabled($product, 'measurement_table_enabled')) {
            return [
                'reason' => 'measurement_table_disabled',
                'message' => 'Tabela de medidas desativada para este produto.',
            ];
        }

        $product->loadMissing('measurementTable');

        if (! $product->measurementTable) {
            return [
                'reason' => 'measurement_table_missing',
                'message' => 'Produto sem tabela de medidas configurada.',
            ];
        }

        if ($requireVirtualTryOn && ! $this->measurementTableVirtualTryOnEnabled($product->measurementTable)) {
            return [
                'reason' => 'table_virtual_try_on_disabled',
                'message' => 'Provador Virtual desativado para a tabela vinculada a este produto.',
            ];
        }

        return null;
    }

    private function measurementTableVirtualTryOnEnabled(?MeasurementTable $table): bool
    {
        $value = data_get($table?->metadata ?? [], 'activation.virtual_try_on_enabled', true);

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function modelingContext(Product $product): array
    {
        $code = trim((string) $product->fit_profile);

        if ($code === '') {
            return [
                'status' => 'missing',
                'code' => null,
                'message' => 'Produto sem modelagem cadastrada; a recomendação usa apenas a tabela de medidas.',
                'impact' => 'Sem contexto de caimento para revisar confiança e feedback.',
            ];
        }

        $profile = FitProfile::query()
            ->where('merchant_id', $product->merchant_id)
            ->where('code', $code)
            ->when($product->merchant_company_id, function ($query, int $companyId): void {
                $query->where(function ($innerQuery) use ($companyId): void {
                    $innerQuery->where('merchant_company_id', $companyId)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->orderByRaw('merchant_company_id is null')
            ->first();

        if (! $profile) {
            return [
                'status' => 'unknown',
                'code' => $code,
                'message' => 'Produto referencia uma modelagem que ainda não existe no cadastro.',
                'impact' => 'A recomendação foi calculada, mas o caimento precisa ser corrigido no diagnóstico.',
            ];
        }

        if ($profile->status !== 'active') {
            return [
                'status' => 'inactive',
                'code' => $code,
                'profile_id' => $profile->id,
                'name' => $profile->name,
                'message' => 'Modelagem cadastrada, porém inativa.',
                'impact' => 'Ative ou substitua a modelagem antes de usar sinais de feedback para aprendizado.',
            ];
        }

        $impact = data_get($profile->metadata ?? [], 'recommendation_impact', []);

        return [
            'status' => 'active',
            'code' => $profile->code,
            'profile_id' => $profile->id,
            'name' => $profile->name,
            'fit_intensity' => $profile->fit_intensity ?: 'regular',
            'stretch_level' => $profile->stretch_level ?: 'medium',
            'message' => 'Modelagem ativa usada como contexto operacional da recomendação.',
            'impact' => $impact['summary'] ?? 'Modelagem ativa para revisar caimento, feedback e sinais comerciais.',
            'confidence_hint' => $impact['confidence_hint'] ?? 'Sem ajuste automático sem revisão humana.',
        ];
    }

    private function fitNotesWithModeling(array $fitNotes, array $modelingContext): array
    {
        if (($modelingContext['status'] ?? null) !== 'active') {
            return $fitNotes;
        }

        $fitNotes[] = 'Modelagem '.$modelingContext['name'].': '.$modelingContext['impact'];

        return array_slice($fitNotes, 0, 5);
    }

    private function warningsWithModeling(array $warnings, array $modelingContext): array
    {
        if (($modelingContext['status'] ?? null) === 'active') {
            return $warnings;
        }

        $warnings[] = $modelingContext['message'] ?? 'Revise a modelagem do produto.';

        return array_values(array_unique($warnings));
    }

    private function productFlagEnabled(Product $product, string $flag): bool
    {
        $value = data_get($product->metadata ?? [], "activation.{$flag}", true);

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function resolveMerchantContext(array $data): array
    {
        $merchantId = ! empty($data['merchant_id']) ? (int) $data['merchant_id'] : null;
        $company = $this->companyForRequest($data, $merchantId);

        if (! $merchantId && $company) {
            $merchantId = (int) $company->merchant_id;
        }

        return [
            'merchant_id' => $merchantId,
            'company' => $company,
        ];
    }

    private function companyForRequest(array $data, ?int $merchantId): ?MerchantCompany
    {
        $storeId = $data['store_id'] ?? null;

        if (! $storeId) {
            return null;
        }

        if (mb_strtolower((string) ($data['platform'] ?? '')) === 'bigshop') {
            $company = MerchantCompany::query()
                ->where('platform', 'bigshop')
                ->where('external_store_id', (string) $storeId)
                ->when($merchantId, fn ($query) => $query->where('merchant_id', $merchantId))
                ->first();

            if ($company) {
                return $company;
            }

            $connectionCompany = PlatformConnection::query()
                ->with('company')
                ->where('platform', 'bigshop')
                ->where('external_store_id', (string) $storeId)
                ->whereNotNull('merchant_company_id')
                ->when($merchantId, fn ($query) => $query->where('merchant_id', $merchantId))
                ->first()?->company;

            if ($connectionCompany) {
                return $connectionCompany;
            }
        }

        if (! $merchantId || ! is_numeric($storeId)) {
            return null;
        }

        return MerchantCompany::query()
            ->whereKey((int) $storeId)
            ->where('merchant_id', $merchantId)
            ->first();
    }

    private function variantForRecommendation(Product $product, ?string $recommendedSize): ?ProductVariant
    {
        if (! $recommendedSize) {
            return null;
        }

        return $product->variants
            ->first(fn (ProductVariant $variant) => mb_strtolower($variant->size_label) === mb_strtolower($recommendedSize));
    }

    private function rawWidgetPayload(mixed $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $json = json_encode($payload);

        if ($json === false) {
            return null;
        }

        if (strlen($json) > 12000) {
            return [
                'truncated' => true,
                'original_size' => strlen($json),
                'version' => $payload['version'] ?? null,
                'source' => $payload['source'] ?? null,
                'precision' => $payload['precision'] ?? null,
                'steps_completed' => $payload['steps_completed'] ?? null,
            ];
        }

        return $payload;
    }

    private function hashValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return hash('sha256', $value.config('app.key'));
    }
}
