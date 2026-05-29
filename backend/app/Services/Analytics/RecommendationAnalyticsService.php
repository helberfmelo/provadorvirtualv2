<?php

namespace App\Services\Analytics;

use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLearningEvent;
use App\Models\RecommendationLog;
use App\Models\ShopperProfile;
use App\Models\WidgetUsageEvent;
use App\Services\Recommendation\MeasurementTableInsightService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RecommendationAnalyticsService
{
    public function __construct(
        private readonly MeasurementTableInsightService $tableInsights,
    ) {}

    public function report(Merchant $merchant, ?MerchantCompany $company, array $filters): array
    {
        [$dateFrom, $dateTo, $normalizedFilters] = $this->normalizeFilters($filters);

        $summaryLogs = $this->baseLogQuery($merchant, $company, $normalizedFilters, $dateFrom, $dateTo)
            ->with(['product.measurementTable', 'recommendationUsageEvent'])
            ->get();

        $learningEvents = $this->learningEvents($merchant, $company, $normalizedFilters, $dateFrom, $dateTo)->get();
        $feedbackStats = $this->feedbackStats($summaryLogs);
        $productsWithoutTable = $this->productsWithoutTable($merchant, $company, $normalizedFilters);
        $measurementTableInsights = $this->tableInsights->insights($merchant, $company);
        $shopperProfiles = $this->shopperProfiles($merchant, $company, $normalizedFilters, $dateFrom, $dateTo)->get();
        $recommendationReport = $this->recommendationReport($merchant, $company, $normalizedFilters, $dateFrom, $dateTo);

        $commercePurchases = $learningEvents->where('event_type', 'purchase')->count();
        $commerceReturns = $learningEvents->where('event_type', 'return')->count();
        $commerceExchanges = $learningEvents->where('event_type', 'exchange')->count();

        return [
            'filters' => $normalizedFilters,
            'summary' => [
                'recommendations_total' => $summaryLogs->count(),
                'recommendations_today' => $summaryLogs->where('created_at', '>=', now()->startOfDay())->count(),
                'recommendations_7d' => $summaryLogs->where('created_at', '>=', now()->subDays(7))->count(),
                'average_confidence' => round((float) $summaryLogs->avg('confidence'), 2),
                'feedback_total' => $feedbackStats['feedback_total'],
                'positive_feedback_rate' => $feedbackStats['positive_feedback_rate'],
                'products_without_measurement_table' => $productsWithoutTable->count(),
                'widget_attention_items' => $summaryLogs->where('status', 'needs_more_data')->count()
                    + $learningEvents->where('status', 'blocked_outlier')->count(),
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
                'commerce_return_rate' => $commercePurchases > 0
                    ? round((($commerceReturns + $commerceExchanges) / $commercePurchases) * 100, 1)
                    : null,
                'measurement_table_insights_review' => collect($measurementTableInsights)
                    ->whereNotIn('suggested_action', ['stable', 'collect_more_data'])
                    ->count(),
            ],
            'daily' => $this->dailySeries($summaryLogs, $dateFrom, $dateTo),
            'sizes' => $summaryLogs
                ->whereNotNull('recommended_size')
                ->groupBy('recommended_size')
                ->map(fn (Collection $group, string $size): array => ['size' => $size, 'count' => $group->count()])
                ->values()
                ->all(),
            'products' => $summaryLogs
                ->whereNotNull('product_id')
                ->groupBy('product_id')
                ->map(fn (Collection $group): array => [
                    'product_id' => $group->first()->product_id,
                    'name' => $group->first()->product?->name,
                    'brand' => $this->productBrand($group->first()->product),
                    'normalized_brand' => data_get($group->first()->product?->metadata ?? [], 'normalized_brand.name')
                        ?: data_get($group->first()->product?->metadata ?? [], 'normalized_brand_name'),
                    'normalized_category' => $this->productCategory($group->first()->product),
                    'recommendations' => $group->count(),
                    'average_confidence' => round((float) $group->avg('confidence'), 2),
                    'average_outlier_score' => round((float) $group->avg('outlier_score'), 2),
                ])
                ->sortByDesc('recommendations')
                ->values()
                ->all(),
            'brands' => $this->brandSeries($summaryLogs),
            'categories' => $this->categorySeries($summaryLogs),
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
                ->map(fn (Collection $group, string $status): array => ['status' => $status, 'count' => $group->count()])
                ->values()
                ->all(),
            'commerce_signals' => $learningEvents
                ->whereIn('event_type', ['add_to_cart', 'purchase', 'return', 'exchange'])
                ->groupBy('event_type')
                ->map(fn (Collection $group, string $signal): array => ['signal' => $signal, 'count' => $group->count()])
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
            'product_ranking' => $this->productRanking($merchant, $company, $normalizedFilters, $dateFrom, $dateTo),
            'recommendation_report' => [
                'data' => collect($recommendationReport->items())
                    ->map(fn (RecommendationLog $log): array => $this->recommendationRow($log))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $recommendationReport->currentPage(),
                    'last_page' => $recommendationReport->lastPage(),
                    'per_page' => $recommendationReport->perPage(),
                    'total' => $recommendationReport->total(),
                ],
            ],
            'filter_options' => $this->filterOptions($merchant, $company),
        ];
    }

    public function exportCsv(Merchant $merchant, ?MerchantCompany $company, array $filters, string $report): string
    {
        return $report === 'ranking'
            ? $this->rankingCsv($merchant, $company, $filters)
            : $this->recommendationsCsv($merchant, $company, $filters);
    }

    private function normalizeFilters(array $filters): array
    {
        $period = in_array($filters['period'] ?? null, ['today', '7d', '30d', '90d', 'custom'], true)
            ? $filters['period']
            : '30d';

        if ($period === 'custom' && filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null)) {
            $dateFrom = Carbon::parse((string) $filters['date_from'])->startOfDay();
            $dateTo = Carbon::parse((string) $filters['date_to'])->endOfDay();
        } else {
            [$dateFrom, $dateTo] = match ($period) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                '7d' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
                '90d' => [now()->subDays(89)->startOfDay(), now()->endOfDay()],
                default => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            };
        }

        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [
            $dateFrom,
            $dateTo,
            [
                'period' => $period,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'product_id' => filled($filters['product_id'] ?? null) ? (int) $filters['product_id'] : null,
                'measurement_table_id' => filled($filters['measurement_table_id'] ?? null) ? (int) $filters['measurement_table_id'] : null,
                'platform' => $this->cleanString($filters['platform'] ?? null),
                'device_type' => $this->cleanString($filters['device_type'] ?? null),
                'brand' => $this->cleanString($filters['brand'] ?? null),
                'category' => $this->cleanString($filters['category'] ?? null),
                'page' => max(1, (int) ($filters['page'] ?? 1)),
                'per_page' => max(5, min(50, (int) ($filters['per_page'] ?? 12))),
            ],
        ];
    }

    private function baseLogQuery(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $filters,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): Builder {
        $query = RecommendationLog::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $this->applyLogFilters($query, $filters);

        return $query;
    }

    private function applyLogFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['product_id'], fn (Builder $builder, int $productId) => $builder->where('product_id', $productId))
            ->when($filters['measurement_table_id'], function (Builder $builder, int $tableId): void {
                $builder->whereHas('product', fn (Builder $productQuery) => $productQuery->where('measurement_table_id', $tableId));
            })
            ->when($filters['brand'], function (Builder $builder, string $brand): void {
                $builder->whereHas('product', fn (Builder $productQuery) => $this->applyBrandFilter($productQuery, $brand));
            })
            ->when($filters['category'], function (Builder $builder, string $category): void {
                $builder->whereHas('product', fn (Builder $productQuery) => $this->applyCategoryFilter($productQuery, $category));
            })
            ->when($filters['platform'], function (Builder $builder, string $platform): void {
                $builder->whereHas('widgetUsageEvents', function (Builder $usageQuery) use ($platform): void {
                    $usageQuery->where('event_name', 'recommendation_generated')
                        ->where('platform', $platform);
                });
            })
            ->when($filters['device_type'], function (Builder $builder, string $deviceType): void {
                $builder->whereHas('widgetUsageEvents', function (Builder $usageQuery) use ($deviceType): void {
                    $usageQuery->where('event_name', 'recommendation_generated')
                        ->where('device_type', $deviceType);
                });
            });
    }

    private function learningEvents(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $filters,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): Builder {
        $query = RecommendationLearningEvent::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->whereBetween('occurred_at', [$dateFrom, $dateTo]);

        $query
            ->when($filters['product_id'], fn (Builder $builder, int $productId) => $builder->where('product_id', $productId))
            ->when($filters['measurement_table_id'], function (Builder $builder, int $tableId): void {
                $builder->whereHas('product', fn (Builder $productQuery) => $productQuery->where('measurement_table_id', $tableId));
            })
            ->when($filters['brand'], function (Builder $builder, string $brand): void {
                $builder->whereHas('product', fn (Builder $productQuery) => $this->applyBrandFilter($productQuery, $brand));
            })
            ->when($filters['category'], function (Builder $builder, string $category): void {
                $builder->whereHas('product', fn (Builder $productQuery) => $this->applyCategoryFilter($productQuery, $category));
            })
            ->when($filters['platform'], function (Builder $builder, string $platform): void {
                $builder->whereHas('recommendationLog.widgetUsageEvents', function (Builder $usageQuery) use ($platform): void {
                    $usageQuery->where('event_name', 'recommendation_generated')
                        ->where('platform', $platform);
                });
            })
            ->when($filters['device_type'], function (Builder $builder, string $deviceType): void {
                $builder->whereHas('recommendationLog.widgetUsageEvents', function (Builder $usageQuery) use ($deviceType): void {
                    $usageQuery->where('event_name', 'recommendation_generated')
                        ->where('device_type', $deviceType);
                });
            });

        return $query;
    }

    private function shopperProfiles(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $filters,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): Builder {
        $query = ShopperProfile::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->whereBetween('updated_at', [$dateFrom, $dateTo]);

        if ($filters['platform'] || $filters['device_type'] || $filters['product_id']) {
            $query->whereHas('recommendationSessions.logs', function (Builder $logQuery) use ($filters, $dateFrom, $dateTo): void {
                $logQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
                $this->applyLogFilters($logQuery, $filters);
            });
        }

        return $query;
    }

    private function feedbackStats(Collection $logs): array
    {
        $logIds = $logs->pluck('id')->filter()->all();

        if ($logIds === []) {
            return [
                'feedback_total' => 0,
                'positive_feedback_rate' => null,
            ];
        }

        $feedbackQuery = RecommendationFeedback::query()->whereIn('recommendation_log_id', $logIds);
        $feedbackTotal = (clone $feedbackQuery)->count();
        $positiveFeedback = (clone $feedbackQuery)
            ->where(function (Builder $query): void {
                $query->where('was_helpful', true)
                    ->orWhere('rating', '>=', 4);
            })
            ->count();

        return [
            'feedback_total' => $feedbackTotal,
            'positive_feedback_rate' => $feedbackTotal > 0
                ? round(($positiveFeedback / $feedbackTotal) * 100, 1)
                : null,
        ];
    }

    private function productsWithoutTable(Merchant $merchant, ?MerchantCompany $company, array $filters): Collection
    {
        $query = Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->whereNull('measurement_table_id')
            ->orderByDesc('id');

        $query
            ->when($filters['product_id'], fn (Builder $builder, int $productId) => $builder->whereKey($productId))
            ->when($filters['brand'], fn (Builder $builder, string $brand) => $this->applyBrandFilter($builder, $brand))
            ->when($filters['category'], fn (Builder $builder, string $category) => $this->applyCategoryFilter($builder, $category));

        return $query->get(['id', 'name', 'sku', 'category']);
    }

    private function dailySeries(Collection $logs, Carbon $dateFrom, Carbon $dateTo): array
    {
        $period = CarbonPeriod::create($dateFrom->copy()->startOfDay(), $dateTo->copy()->startOfDay());

        return collect($period)->map(function (Carbon $date) use ($logs): array {
            return [
                'date' => $date->toDateString(),
                'count' => $logs->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->count(),
            ];
        })->values()->all();
    }

    private function brandSeries(Collection $logs): array
    {
        return $logs
            ->whereNotNull('product_id')
            ->groupBy(fn (RecommendationLog $log): string => $this->productBrand($log->product))
            ->map(fn (Collection $group, string $brand): array => [
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

    private function categorySeries(Collection $logs): array
    {
        return $logs
            ->whereNotNull('product_id')
            ->groupBy(fn (RecommendationLog $log): string => $this->productCategory($log->product))
            ->map(fn (Collection $group, string $category): array => [
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

    private function productRanking(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $filters,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): array {
        $products = Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->when($filters['product_id'], fn (Builder $builder, int $productId) => $builder->whereKey($productId))
            ->when($filters['measurement_table_id'], fn (Builder $builder, int $tableId) => $builder->where('measurement_table_id', $tableId))
            ->when($filters['brand'], fn (Builder $builder, string $brand) => $this->applyBrandFilter($builder, $brand))
            ->when($filters['category'], fn (Builder $builder, string $category) => $this->applyCategoryFilter($builder, $category))
            ->with('measurementTable:id,name')
            ->get(['id', 'name', 'sku', 'category', 'measurement_table_id', 'metadata']);

        $eventCounts = WidgetUsageEvent::query()
            ->selectRaw('product_id, event_name, COUNT(*) as aggregate')
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->whereBetween('occurred_at', [$dateFrom, $dateTo])
            ->when($filters['product_id'], fn (Builder $builder, int $productId) => $builder->where('product_id', $productId))
            ->when($filters['measurement_table_id'], fn (Builder $builder, int $tableId) => $builder->where('measurement_table_id', $tableId))
            ->when($filters['platform'], fn (Builder $builder, string $platform) => $builder->where('platform', $platform))
            ->when($filters['device_type'], fn (Builder $builder, string $deviceType) => $builder->where('device_type', $deviceType))
            ->when($filters['brand'], fn (Builder $builder, string $brand) => $builder->where('brand_label', $brand))
            ->when($filters['category'], fn (Builder $builder, string $category) => $builder->where('category_label', $category))
            ->groupBy('product_id', 'event_name')
            ->get()
            ->groupBy('product_id');

        $logsByProduct = $this->baseLogQuery($merchant, $company, $filters, $dateFrom, $dateTo)
            ->with('product')
            ->get()
            ->groupBy('product_id');

        $learningByProduct = $this->learningEvents($merchant, $company, $filters, $dateFrom, $dateTo)
            ->get()
            ->groupBy('product_id');

        $productIds = $products->pluck('id')
            ->merge($logsByProduct->keys())
            ->merge($eventCounts->keys())
            ->filter()
            ->unique()
            ->values();

        return $productIds
            ->map(function (int $productId) use ($products, $eventCounts, $logsByProduct, $learningByProduct): ?array {
                /** @var Product|null $product */
                $product = $products->firstWhere('id', $productId);
                $productEvents = collect($eventCounts->get($productId, []))->pluck('aggregate', 'event_name');
                $productLogs = collect($logsByProduct->get($productId, []));
                $productLearning = collect($learningByProduct->get($productId, []));
                $returns = $productLearning->whereIn('event_type', ['return', 'exchange'])->count();
                $purchases = $productLearning->where('event_type', 'purchase')->count();
                $needsMoreData = $productLogs->where('status', 'needs_more_data')->count();
                $blockedOutliers = $productLearning->where('status', 'blocked_outlier')->count();
                $impressions = (int) ($productEvents['button_impression'] ?? 0);
                $opens = (int) ($productEvents['virtual_try_on_open'] ?? 0);
                $recommendations = $productLogs->count();

                if (! $product && $impressions === 0 && $recommendations === 0) {
                    return null;
                }

                $attentionFlags = collect([
                    ! $product?->measurement_table_id && $impressions > 0 ? 'sem_tabela' : null,
                    $returns > 0 ? 'devolucao_ou_troca' : null,
                    $blockedOutliers > 0 ? 'outlier_bloqueado' : null,
                    $needsMoreData > 0 ? 'baixa_confianca' : null,
                ])->filter()->values()->all();

                return [
                    'product_id' => $productId,
                    'name' => $product?->name,
                    'sku' => $product?->sku,
                    'brand' => $this->productBrand($product),
                    'category' => $this->productCategory($product),
                    'measurement_table_id' => $product?->measurement_table_id,
                    'measurement_table_name' => $product?->measurementTable?->name,
                    'button_impressions' => $impressions,
                    'virtual_try_on_opens' => $opens,
                    'measurement_table_opens' => (int) ($productEvents['measurement_table_open'] ?? 0),
                    'recommendations_generated' => $recommendations,
                    'size_selections' => (int) ($productEvents['size_selected'] ?? 0),
                    'returns_exchanges' => $returns,
                    'blocked_outliers' => $blockedOutliers,
                    'needs_more_data' => $needsMoreData,
                    'usage_rate' => $this->rate($opens, $impressions),
                    'selection_rate' => $this->rate((int) ($productEvents['size_selected'] ?? 0), $recommendations),
                    'return_rate' => $this->rate($returns, $purchases > 0 ? $purchases : $recommendations),
                    'errors_total' => $returns + $blockedOutliers + $needsMoreData,
                    'attention_flags' => $attentionFlags,
                ];
            })
            ->filter()
            ->sortBy([
                ['recommendations_generated', 'desc'],
                ['button_impressions', 'desc'],
                ['errors_total', 'desc'],
            ])
            ->values()
            ->all();
    }

    private function recommendationReport(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $filters,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): LengthAwarePaginator {
        return $this->baseLogQuery($merchant, $company, $filters, $dateFrom, $dateTo)
            ->with(['product.measurementTable', 'feedbacks', 'learningEvents', 'recommendationUsageEvent'])
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);
    }

    private function recommendationRow(RecommendationLog $log): array
    {
        $usageEvent = $log->recommendationUsageEvent;
        $learningEvents = $log->learningEvents;
        $latestFeedback = $log->feedbacks->sortByDesc('id')->first();

        return [
            'recommendation_id' => $log->id,
            'created_at' => $log->created_at?->toISOString(),
            'product_id' => $log->product_id,
            'product_name' => $log->product?->name,
            'product_sku' => $log->product?->sku,
            'measurement_table_id' => $log->product?->measurement_table_id,
            'measurement_table_name' => $usageEvent?->measurement_table_name ?: $log->product?->measurementTable?->name,
            'recommended_size' => $log->recommended_size,
            'confidence' => round((float) $log->confidence, 2),
            'status' => $log->status,
            'platform' => $usageEvent?->platform,
            'device_type' => $usageEvent?->device_type,
            'origin' => data_get($usageEvent?->payload ?? [], 'source')
                ?: data_get($usageEvent?->payload ?? [], 'presentation_mode')
                ?: $usageEvent?->platform
                ?: 'widget',
            'feedback_helpful' => $latestFeedback?->was_helpful,
            'feedback_rating' => $latestFeedback?->rating,
            'purchases' => $learningEvents->where('event_type', 'purchase')->count(),
            'returns' => $learningEvents->where('event_type', 'return')->count(),
            'exchanges' => $learningEvents->where('event_type', 'exchange')->count(),
            'outlier_score' => round((float) $learningEvents->max('outlier_score'), 2),
            'brand' => $this->productBrand($log->product),
            'category' => $this->productCategory($log->product),
        ];
    }

    private function filterOptions(Merchant $merchant, ?MerchantCompany $company): array
    {
        $products = Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'metadata', 'category']);

        $tables = MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $usageEvents = WidgetUsageEvent::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->get(['platform', 'device_type']);

        return [
            'products' => $products
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ])
                ->values()
                ->all(),
            'measurement_tables' => $tables
                ->map(fn (MeasurementTable $table): array => [
                    'id' => $table->id,
                    'name' => $table->name,
                ])
                ->values()
                ->all(),
            'brands' => $products
                ->map(fn (Product $product): string => $this->productBrand($product))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'categories' => $products
                ->map(fn (Product $product): string => $this->productCategory($product))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'platforms' => $usageEvents->pluck('platform')->filter()->unique()->values()->all(),
            'device_types' => $usageEvents->pluck('device_type')->filter()->unique()->values()->all(),
        ];
    }

    private function rankingCsv(Merchant $merchant, ?MerchantCompany $company, array $filters): string
    {
        [$dateFrom, $dateTo, $normalizedFilters] = $this->normalizeFilters($filters);
        $rows = $this->productRanking($merchant, $company, $normalizedFilters, $dateFrom, $dateTo);

        return $this->csv(array_merge([
            [
                'Produto ID',
                'Produto',
                'SKU',
                'Marca',
                'Categoria',
                'Tabela',
                'Impressoes',
                'Aberturas',
                'Consultas tabela',
                'Recomendacoes',
                'Aplicacoes de tamanho',
                'Devolucoes/trocas',
                'Outliers bloqueados',
                'Taxa de uso (%)',
                'Taxa de aplicacao (%)',
                'Taxa de devolucao (%)',
                'Atencao',
            ],
        ], collect($rows)->map(fn (array $row): array => [
            $row['product_id'],
            $row['name'],
            $row['sku'],
            $row['brand'],
            $row['category'],
            $row['measurement_table_name'],
            $row['button_impressions'],
            $row['virtual_try_on_opens'],
            $row['measurement_table_opens'],
            $row['recommendations_generated'],
            $row['size_selections'],
            $row['returns_exchanges'],
            $row['blocked_outliers'],
            $row['usage_rate'],
            $row['selection_rate'],
            $row['return_rate'],
            implode('|', $row['attention_flags']),
        ])->all()));
    }

    private function recommendationsCsv(Merchant $merchant, ?MerchantCompany $company, array $filters): string
    {
        [$dateFrom, $dateTo, $normalizedFilters] = $this->normalizeFilters($filters);

        $rows = $this->baseLogQuery($merchant, $company, $normalizedFilters, $dateFrom, $dateTo)
            ->with(['product.measurementTable', 'feedbacks', 'learningEvents', 'recommendationUsageEvent'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (RecommendationLog $log): array => $this->recommendationRow($log))
            ->all();

        return $this->csv(array_merge([
            [
                'Recomendacao ID',
                'Data',
                'Produto ID',
                'Produto',
                'SKU',
                'Tabela',
                'Tamanho recomendado',
                'Confianca',
                'Status',
                'Plataforma',
                'Dispositivo',
                'Origem',
                'Compras',
                'Devolucoes',
                'Trocas',
                'Feedback ajudou',
                'Nota feedback',
                'Outlier score',
            ],
        ], collect($rows)->map(fn (array $row): array => [
            $row['recommendation_id'],
            $row['created_at'],
            $row['product_id'],
            $row['product_name'],
            $row['product_sku'],
            $row['measurement_table_name'],
            $row['recommended_size'],
            $row['confidence'],
            $row['status'],
            $row['platform'],
            $row['device_type'],
            $row['origin'],
            $row['purchases'],
            $row['returns'],
            $row['exchanges'],
            $row['feedback_helpful'] === null ? '' : ($row['feedback_helpful'] ? 'sim' : 'nao'),
            $row['feedback_rating'],
            $row['outlier_score'],
        ])->all()));
    }

    private function applyBrandFilter(Builder $query, string $brand): Builder
    {
        return $query->where(function (Builder $builder) use ($brand): void {
            $builder->where('metadata->normalized_brand->name', $brand)
                ->orWhere('metadata->normalized_brand_name', $brand)
                ->orWhere('metadata->brand', $brand);
        });
    }

    private function applyCategoryFilter(Builder $query, string $category): Builder
    {
        return $query->where(function (Builder $builder) use ($category): void {
            $builder->where('metadata->normalized_category->name', $category)
                ->orWhere('metadata->normalized_category_name', $category)
                ->orWhere('category', $category);
        });
    }

    private function productBrand(?Product $product): string
    {
        return data_get($product?->metadata ?? [], 'normalized_brand.name')
            ?: data_get($product?->metadata ?? [], 'normalized_brand_name')
            ?: data_get($product?->metadata ?? [], 'brand')
            ?: 'Sem marca';
    }

    private function productCategory(?Product $product): string
    {
        return data_get($product?->metadata ?? [], 'normalized_category.name')
            ?: data_get($product?->metadata ?? [], 'normalized_category_name')
            ?: $product?->category
            ?: 'Sem categoria';
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }
}
