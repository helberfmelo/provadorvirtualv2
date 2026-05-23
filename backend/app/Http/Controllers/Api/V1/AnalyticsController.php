<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Models\IntegrationEvent;
use App\Models\Product;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLog;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ResolvesMerchant;

    public function recommendations(Request $request): array
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
                        'recommendations' => $group->count(),
                        'average_confidence' => round((float) $group->avg('confidence'), 2),
                    ])
                    ->sortByDesc('recommendations')
                    ->values()
                    ->all(),
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
}
