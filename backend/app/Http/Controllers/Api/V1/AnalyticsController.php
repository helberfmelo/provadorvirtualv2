<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Models\IntegrationEvent;
use App\Models\Product;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLearningEvent;
use App\Models\RecommendationLog;
use App\Models\ShopperProfile;
use App\Services\Recommendation\MeasurementTableInsightService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ResolvesMerchant;

    public function recommendations(Request $request, MeasurementTableInsightService $tableInsights): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $logs = RecommendationLog::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->with('product')
            ->get();

        $feedbackQuery = RecommendationFeedback::query()
            ->whereHas('recommendationLog', function ($query) use ($merchant, $company): void {
                $query->where('merchant_id', $merchant->id);
                $this->scopeCompany($query, $company);
            });

        $feedbackTotal = (clone $feedbackQuery)->count();
        $positiveFeedback = (clone $feedbackQuery)
            ->where(function ($query): void {
                $query->where('was_helpful', true)
                    ->orWhere('rating', '>=', 4);
            })
            ->count();

        $productsWithoutTable = Product::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereNull('measurement_table_id')
            ->orderByDesc('id')
            ->get(['id', 'name', 'sku', 'category']);

        $failedIntegrationEvents = IntegrationEvent::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $learningEvents = RecommendationLearningEvent::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->get();

        $shopperProfiles = ShopperProfile::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->where('status', 'active')
            ->get();
        $commercePurchases = $learningEvents->where('event_type', 'purchase')->count();
        $commerceReturns = $learningEvents->where('event_type', 'return')->count();
        $commerceExchanges = $learningEvents->where('event_type', 'exchange')->count();
        $measurementTableInsights = $tableInsights->insights($merchant, $company);

        return [
            'data' => [
                'summary' => [
                    'recommendations_total' => $logs->count(),
                    'recommendations_today' => $logs->where('created_at', '>=', now()->startOfDay())->count(),
                    'recommendations_7d' => $logs->where('created_at', '>=', now()->subDays(7))->count(),
                    'average_confidence' => round((float) $logs->avg('confidence'), 2),
                    'feedback_total' => $feedbackTotal,
                    'positive_feedback_rate' => $feedbackTotal > 0 ? round(($positiveFeedback / $feedbackTotal) * 100, 1) : null,
                    'products_without_measurement_table' => $productsWithoutTable->count(),
                    'widget_attention_items' => $logs->where('status', 'needs_more_data')->count() + $failedIntegrationEvents,
                    'shopper_profiles_total' => $shopperProfiles->count(),
                    'shopper_profiles_known' => $shopperProfiles->where('profile_type', 'known')->count(),
                    'average_profile_quality' => round((float) $shopperProfiles->avg('quality_score'), 1),
                    'learning_events_total' => $learningEvents->count(),
                    'learning_accepted' => $learningEvents->where('status', 'accepted')->count(),
                    'learning_review' => $learningEvents->where('status', 'review')->count(),
                    'learning_blocked_outliers' => $learningEvents->where('status', 'blocked_outlier')->count(),
                    'average_outlier_score' => round((float) $learningEvents->avg('outlier_score'), 2),
                    'commerce_purchases' => $commercePurchases,
                    'commerce_returns' => $commerceReturns,
                    'commerce_exchanges' => $commerceExchanges,
                    'commerce_return_rate' => $commercePurchases > 0 ? round((($commerceReturns + $commerceExchanges) / $commercePurchases) * 100, 1) : null,
                    'measurement_table_insights_review' => collect($measurementTableInsights)
                        ->whereNotIn('suggested_action', ['stable', 'collect_more_data'])
                        ->count(),
                ],
                'daily' => $this->dailySeries($logs),
                'sizes' => $logs
                    ->whereNotNull('recommended_size')
                    ->groupBy('recommended_size')
                    ->map(fn ($group, string $size): array => ['size' => $size, 'count' => $group->count()])
                    ->values()
                    ->all(),
                'products' => $logs
                    ->whereNotNull('product_id')
                    ->groupBy('product_id')
                    ->map(fn ($group): array => [
                        'product_id' => $group->first()->product_id,
                        'name' => $group->first()->product?->name,
                        'brand' => data_get($group->first()->product?->metadata ?? [], 'brand'),
                        'normalized_brand' => data_get($group->first()->product?->metadata ?? [], 'normalized_brand.name')
                            ?: data_get($group->first()->product?->metadata ?? [], 'normalized_brand_name'),
                        'normalized_category' => data_get($group->first()->product?->metadata ?? [], 'normalized_category.name')
                            ?: data_get($group->first()->product?->metadata ?? [], 'normalized_category_name'),
                        'recommendations' => $group->count(),
                        'average_confidence' => round((float) $group->avg('confidence'), 2),
                        'average_outlier_score' => round((float) $group->avg('outlier_score'), 2),
                    ])
                    ->sortByDesc('recommendations')
                    ->values()
                    ->all(),
                'brands' => $this->brandSeries($logs),
                'categories' => $this->categorySeries($logs),
                'products_without_measurement_table' => $productsWithoutTable
                    ->take(8)
                    ->map(fn (Product $product): array => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'category' => $product->category,
                    ])
                    ->values()
                    ->all(),
                'learning_statuses' => $learningEvents
                    ->groupBy('status')
                    ->map(fn ($group, string $status): array => ['status' => $status, 'count' => $group->count()])
                    ->values()
                    ->all(),
                'commerce_signals' => $learningEvents
                    ->whereIn('event_type', ['add_to_cart', 'purchase', 'return', 'exchange'])
                    ->groupBy('event_type')
                    ->map(fn ($group, string $signal): array => ['signal' => $signal, 'count' => $group->count()])
                    ->values()
                    ->all(),
                'measurement_table_insights' => $measurementTableInsights,
                'outliers' => $learningEvents
                    ->where('status', 'blocked_outlier')
                    ->sortByDesc('occurred_at')
                    ->take(8)
                    ->map(fn (RecommendationLearningEvent $event): array => [
                        'id' => $event->id,
                        'event_type' => $event->event_type,
                        'recommended_size' => $event->recommended_size,
                        'selected_size' => $event->selected_size,
                        'outlier_score' => (float) $event->outlier_score,
                        'reason' => $event->reason,
                        'occurred_at' => $event->occurred_at?->toISOString(),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function dailySeries($logs): array
    {
        $start = now()->subDays(6)->startOfDay();
        $period = CarbonPeriod::create($start, now()->startOfDay());

        return collect($period)->map(function ($date) use ($logs): array {
            return [
                'date' => $date->toDateString(),
                'count' => $logs->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->count(),
            ];
        })->values()->all();
    }

    private function brandSeries($logs): array
    {
        return $logs
            ->whereNotNull('product_id')
            ->groupBy(function (RecommendationLog $log): string {
                return data_get($log->product?->metadata ?? [], 'normalized_brand.name')
                    ?: data_get($log->product?->metadata ?? [], 'normalized_brand_name')
                    ?: data_get($log->product?->metadata ?? [], 'brand')
                    ?: 'Sem marca';
            })
            ->map(fn ($group, string $brand): array => [
                'brand' => $brand,
                'normalized' => (bool) (
                    data_get($group->first()->product?->metadata ?? [], 'normalized_brand.name')
                    ?: data_get($group->first()->product?->metadata ?? [], 'normalized_brand_name')
                ),
                'recommendations' => $group->count(),
                'average_confidence' => round((float) $group->avg('confidence'), 2),
            ])
            ->sortByDesc('recommendations')
            ->values()
            ->all();
    }

    private function categorySeries($logs): array
    {
        return $logs
            ->whereNotNull('product_id')
            ->groupBy(function (RecommendationLog $log): string {
                return data_get($log->product?->metadata ?? [], 'normalized_category.name')
                    ?: data_get($log->product?->metadata ?? [], 'normalized_category_name')
                    ?: $log->product?->category
                    ?: 'Sem categoria';
            })
            ->map(fn ($group, string $category): array => [
                'category' => $category,
                'normalized' => (bool) (
                    data_get($group->first()->product?->metadata ?? [], 'normalized_category.name')
                    ?: data_get($group->first()->product?->metadata ?? [], 'normalized_category_name')
                ),
                'category_type' => data_get($group->first()->product?->metadata ?? [], 'normalized_category.type')
                    ?: data_get($group->first()->product?->metadata ?? [], 'category_mapping.category_type'),
                'recommendations' => $group->count(),
                'average_confidence' => round((float) $group->avg('confidence'), 2),
            ])
            ->sortByDesc('recommendations')
            ->values()
            ->all();
    }
}
