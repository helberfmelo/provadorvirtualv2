<?php

namespace App\Services\Analytics;

use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\RecommendationLearningEvent;
use App\Models\WidgetUsageEvent;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WidgetUsageAnalyticsService
{
    public function report(Merchant $merchant, ?MerchantCompany $company, array $filters): array
    {
        [$dateFrom, $dateTo, $normalizedFilters] = $this->normalizeFilters($filters);

        $events = WidgetUsageEvent::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->whereBetween('occurred_at', [$dateFrom, $dateTo]);

        $this->applyEventFilters($events, $normalizedFilters);

        $counts = $this->eventCounts(clone $events);
        $conversions = $this->conversionCount($merchant, $company, $normalizedFilters, $dateFrom, $dateTo);
        $impressions = $counts['button_impression'];
        $opens = $counts['virtual_try_on_open'];
        $recommendations = $counts['recommendation_generated'];
        $tableOpens = $counts['measurement_table_open'];
        $sizeSelections = $counts['size_selected'];
        $feedbacks = $counts['feedback_submitted'];

        return [
            'filters' => $normalizedFilters,
            'summary' => [
                'button_impressions' => $impressions,
                'virtual_try_on_opens' => $opens,
                'measurement_table_opens' => $tableOpens,
                'recommendations_generated' => $recommendations,
                'size_selections' => $sizeSelections,
                'feedback_submitted' => $feedbacks,
                'conversions' => $conversions,
                'usage_rate' => $this->rate($opens, $impressions),
                'table_rate' => $this->rate($tableOpens, $impressions),
                'selection_rate' => $this->rate($sizeSelections, $recommendations),
                'conversion_rate' => $this->rate($conversions, $recommendations),
            ],
            'funnel' => [
                ['key' => 'button_impression', 'label' => 'Botões exibidos', 'count' => $impressions],
                ['key' => 'virtual_try_on_open', 'label' => 'Provador aberto', 'count' => $opens],
                ['key' => 'recommendation_generated', 'label' => 'Recomendações geradas', 'count' => $recommendations],
                ['key' => 'measurement_table_open', 'label' => 'Tabela consultada', 'count' => $tableOpens],
                ['key' => 'size_selected', 'label' => 'Tamanho aplicado', 'count' => $sizeSelections],
                ['key' => 'conversion', 'label' => 'Compras sinalizadas', 'count' => $conversions],
            ],
            'daily' => $this->dailySeries($merchant, $company, $normalizedFilters, $dateFrom, $dateTo),
            'device_distribution' => $this->deviceDistribution(clone $events, $impressions > 0),
            'filter_options' => $this->filterOptions($merchant, $company),
        ];
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
            ],
        ];
    }

    private function applyEventFilters(Builder $query, array $filters, string $table = 'widget_usage_events'): void
    {
        $query
            ->when($filters['product_id'], fn (Builder $builder, int $productId) => $builder->where("{$table}.product_id", $productId))
            ->when($filters['measurement_table_id'], fn (Builder $builder, int $tableId) => $builder->where("{$table}.measurement_table_id", $tableId))
            ->when($filters['platform'], fn (Builder $builder, string $platform) => $builder->where("{$table}.platform", $platform))
            ->when($filters['device_type'], fn (Builder $builder, string $deviceType) => $builder->where("{$table}.device_type", $deviceType))
            ->when($filters['brand'], fn (Builder $builder, string $brand) => $builder->where("{$table}.brand_label", $brand))
            ->when($filters['category'], fn (Builder $builder, string $category) => $builder->where("{$table}.category_label", $category));
    }

    private function eventCounts(Builder $query): array
    {
        $grouped = $query
            ->selectRaw('event_name, COUNT(*) as aggregate')
            ->groupBy('event_name')
            ->pluck('aggregate', 'event_name');

        return [
            'button_impression' => (int) ($grouped['button_impression'] ?? 0),
            'virtual_try_on_open' => (int) ($grouped['virtual_try_on_open'] ?? 0),
            'measurement_table_open' => (int) ($grouped['measurement_table_open'] ?? 0),
            'recommendation_generated' => (int) ($grouped['recommendation_generated'] ?? 0),
            'size_selected' => (int) ($grouped['size_selected'] ?? 0),
            'feedback_submitted' => (int) ($grouped['feedback_submitted'] ?? 0),
        ];
    }

    private function conversionCount(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $filters,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): int {
        $query = RecommendationLearningEvent::query()
            ->where('recommendation_learning_events.merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('recommendation_learning_events.merchant_company_id', $company->id)
                        ->orWhereNull('recommendation_learning_events.merchant_company_id');
                });
            })
            ->where('recommendation_learning_events.event_type', 'purchase')
            ->whereBetween('recommendation_learning_events.occurred_at', [$dateFrom, $dateTo])
            ->join('widget_usage_events as usage_events', function ($join): void {
                $join->on('usage_events.recommendation_log_id', '=', 'recommendation_learning_events.recommendation_log_id')
                    ->where('usage_events.event_name', '=', 'recommendation_generated');
            })
            ->where('usage_events.merchant_id', $merchant->id);

        if ($company) {
            $query->where(function (Builder $builder) use ($company): void {
                $builder->where('usage_events.merchant_company_id', $company->id)
                    ->orWhereNull('usage_events.merchant_company_id');
            });
        }

        $this->applyEventFilters($query, $filters, 'usage_events');

        return (int) $query
            ->distinct('recommendation_learning_events.id')
            ->count('recommendation_learning_events.id');
    }

    private function dailySeries(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $filters,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): array {
        $events = WidgetUsageEvent::query()
            ->selectRaw('DATE(occurred_at) as day, event_name, COUNT(*) as aggregate')
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->whereBetween('occurred_at', [$dateFrom, $dateTo]);

        $this->applyEventFilters($events, $filters);

        $eventGroups = $events
            ->groupByRaw('DATE(occurred_at), event_name')
            ->get()
            ->groupBy('day');

        $conversionRows = RecommendationLearningEvent::query()
            ->selectRaw('DATE(recommendation_learning_events.occurred_at) as day, COUNT(DISTINCT recommendation_learning_events.id) as aggregate')
            ->where('recommendation_learning_events.merchant_id', $merchant->id)
            ->when($company, function (Builder $builder) use ($company): void {
                $builder->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('recommendation_learning_events.merchant_company_id', $company->id)
                        ->orWhereNull('recommendation_learning_events.merchant_company_id');
                });
            })
            ->where('recommendation_learning_events.event_type', 'purchase')
            ->whereBetween('recommendation_learning_events.occurred_at', [$dateFrom, $dateTo])
            ->join('widget_usage_events as usage_events', function ($join): void {
                $join->on('usage_events.recommendation_log_id', '=', 'recommendation_learning_events.recommendation_log_id')
                    ->where('usage_events.event_name', '=', 'recommendation_generated');
            })
            ->where('usage_events.merchant_id', $merchant->id);

        if ($company) {
            $conversionRows->where(function (Builder $builder) use ($company): void {
                $builder->where('usage_events.merchant_company_id', $company->id)
                    ->orWhereNull('usage_events.merchant_company_id');
            });
        }

        $this->applyEventFilters($conversionRows, $filters, 'usage_events');

        $conversionGroups = $conversionRows
            ->groupByRaw('DATE(recommendation_learning_events.occurred_at)')
            ->pluck('aggregate', 'day');

        $period = CarbonPeriod::create($dateFrom->copy()->startOfDay(), $dateTo->copy()->startOfDay());

        return collect($period)->map(function (Carbon $day) use ($eventGroups, $conversionGroups): array {
            $group = collect($eventGroups->get($day->toDateString(), []))->pluck('aggregate', 'event_name');

            return [
                'date' => $day->toDateString(),
                'button_impressions' => (int) ($group['button_impression'] ?? 0),
                'virtual_try_on_opens' => (int) ($group['virtual_try_on_open'] ?? 0),
                'measurement_table_opens' => (int) ($group['measurement_table_open'] ?? 0),
                'recommendations_generated' => (int) ($group['recommendation_generated'] ?? 0),
                'size_selections' => (int) ($group['size_selected'] ?? 0),
                'feedback_submitted' => (int) ($group['feedback_submitted'] ?? 0),
                'conversions' => (int) ($conversionGroups[$day->toDateString()] ?? 0),
            ];
        })->values()->all();
    }

    private function deviceDistribution(Builder $query, bool $preferImpressions): array
    {
        $base = clone $query;

        if ($preferImpressions) {
            $base->where('event_name', 'button_impression');
        }

        $distribution = $base
            ->selectRaw('device_type, COUNT(*) as aggregate')
            ->groupBy('device_type')
            ->get();

        if ($distribution->isEmpty() && $preferImpressions) {
            return $this->deviceDistribution($query, false);
        }

        $total = max(1, (int) $distribution->sum('aggregate'));

        return $distribution
            ->map(fn ($row): array => [
                'device_type' => $row->device_type ?: 'desktop',
                'count' => (int) $row->aggregate,
                'share' => round((((int) $row->aggregate) / $total) * 100, 1),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function filterOptions(Merchant $merchant, ?MerchantCompany $company): array
    {
        $products = Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'metadata', 'category']);

        $tables = MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $usageEvents = WidgetUsageEvent::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
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
                ->map(fn (Product $product): ?string => $this->brandLabelForProduct($product))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'categories' => $products
                ->map(fn (Product $product): ?string => $this->categoryLabelForProduct($product))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'platforms' => $usageEvents
                ->pluck('platform')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'device_types' => $this->sortDeviceTypes(
                $usageEvents
                    ->pluck('device_type')
                    ->filter()
                    ->unique()
                    ->values()
            )->all(),
        ];
    }

    private function brandLabelForProduct(Product $product): ?string
    {
        return $this->cleanString(
            data_get($product->metadata ?? [], 'normalized_brand.name')
            ?: data_get($product->metadata ?? [], 'normalized_brand_name')
            ?: data_get($product->metadata ?? [], 'brand')
        );
    }

    private function categoryLabelForProduct(Product $product): ?string
    {
        return $this->cleanString(
            data_get($product->metadata ?? [], 'normalized_category.name')
            ?: data_get($product->metadata ?? [], 'normalized_category_name')
            ?: $product->category
        );
    }

    private function sortDeviceTypes(Collection $deviceTypes): Collection
    {
        $order = ['mobile', 'desktop', 'tablet'];

        return $deviceTypes->sortBy(function (string $deviceType) use ($order): int {
            $position = array_search($deviceType, $order, true);

            return $position === false ? 99 : $position;
        })->values();
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
        if (! is_scalar($value)) {
            return null;
        }

        $clean = trim((string) $value);

        return $clean !== '' ? $clean : null;
    }
}
