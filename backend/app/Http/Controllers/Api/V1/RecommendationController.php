<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecommendationConfigCheckRequest;
use App\Http\Requests\StoreRecommendationFeedbackRequest;
use App\Http\Requests\StoreRecommendationRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLog;
use App\Models\RecommendationSession;
use App\Models\WidgetInstall;
use App\Services\Recommendation\RecommendationEngine;
use Illuminate\Support\Str;

class RecommendationController extends Controller
{
    public function configCheck(RecommendationConfigCheckRequest $request)
    {
        $product = $this->resolveProduct($request->validated());

        if (! $product || ! $product->measurementTable()->exists()) {
            return response()->json([
                'configured' => false,
                'reason' => 'measurement_table_missing',
            ]);
        }

        $product->load(['measurementTable.rows', 'variants']);

        return response()->json([
            'configured' => true,
            'product_id' => $product->id,
            'measurement_table_id' => $product->measurement_table_id,
            'available_sizes' => $product->measurementTable->rows->pluck('size_label')->values(),
            'measurement_table' => [
                'id' => $product->measurementTable->id,
                'name' => $product->measurementTable->name,
                'unit' => $product->measurementTable->unit,
                'rows' => $product->measurementTable->rows->map(fn ($row): array => [
                    'size_label' => $row->size_label,
                    'bust' => [$row->bust_min, $row->bust_max],
                    'waist' => [$row->waist_min, $row->waist_max],
                    'hip' => [$row->hip_min, $row->hip_max],
                    'height' => [$row->height_min, $row->height_max],
                    'weight' => [$row->weight_min, $row->weight_max],
                    'length' => [$row->length_min, $row->length_max],
                    'shoulder' => [$row->shoulder_min, $row->shoulder_max],
                ])->values(),
            ],
            'theme' => WidgetInstall::query()
                ->where('merchant_id', $product->merchant_id)
                ->when($product->merchant_company_id, fn ($query, $companyId) => $query->where('merchant_company_id', $companyId))
                ->where('is_active', true)
                ->first()?->theme ?? [],
        ]);
    }

    public function store(StoreRecommendationRequest $request, RecommendationEngine $engine)
    {
        $data = $request->validated();
        $product = $this->resolveProduct($data);

        if (! $product || ! $product->measurementTable()->exists()) {
            return response()->json([
                'configured' => false,
                'message' => 'Produto sem tabela de medidas configurada.',
            ], 422);
        }

        $product->load(['measurementTable.rows', 'variants']);
        $result = $engine->recommend($product->measurementTable, $data['measurements']);
        $recommendedVariant = $this->variantForRecommendation($product, $result->recommendedSize);

        $session = RecommendationSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $product->merchant_id,
            'merchant_company_id' => $product->merchant_company_id,
            'product_id' => $product->id,
            'product_variant_id' => $recommendedVariant?->id,
            'shopper_profile' => $data['shopper_profile'] ?? null,
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
            'score_breakdown' => $result->scoreBreakdown,
            'fit_notes' => $result->fitNotes,
            'warnings' => $result->warnings,
            'status' => $result->needsMoreData ? 'needs_more_data' : 'recommended',
        ]);

        return response()->json([
            'configured' => true,
            'recommendation_id' => $log->id,
            'session_id' => $session->uuid,
            'product_id' => $product->id,
            'variant_id' => $recommendedVariant?->id,
            ...$result->toArray(),
        ], 201);
    }

    public function feedback(StoreRecommendationFeedbackRequest $request, RecommendationLog $recommendationLog)
    {
        $feedback = RecommendationFeedback::query()->create([
            'recommendation_log_id' => $recommendationLog->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'message' => 'Feedback registrado com sucesso.',
            'feedback_id' => $feedback->id,
        ], 201);
    }

    private function resolveProduct(array $data): ?Product
    {
        $query = Product::query()
            ->where('merchant_id', $data['merchant_id'])
            ->when($data['store_id'] ?? null, fn ($query, $storeId) => $query->where('merchant_company_id', $storeId));

        if (! empty($data['product_id'])) {
            $query->where(function ($subQuery) use ($data): void {
                $subQuery->whereKey($data['product_id'])
                    ->orWhere('external_product_id', (string) $data['product_id']);
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

    private function variantForRecommendation(Product $product, ?string $recommendedSize): ?ProductVariant
    {
        if (! $recommendedSize) {
            return null;
        }

        return $product->variants
            ->first(fn (ProductVariant $variant) => mb_strtolower($variant->size_label) === mb_strtolower($recommendedSize));
    }

    private function hashValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return hash('sha256', $value.config('app.key'));
    }
}
