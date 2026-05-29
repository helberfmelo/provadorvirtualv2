<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecommendationAnalyticsRequest;
use App\Http\Requests\WidgetUsageAnalyticsRequest;
use App\Services\Analytics\RecommendationAnalyticsService;
use App\Services\Analytics\WidgetUsageAnalyticsService;

class AnalyticsController extends Controller
{
    use ResolvesMerchant;

    public function widgetUsage(WidgetUsageAnalyticsRequest $request, WidgetUsageAnalyticsService $widgetUsage): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        return [
            'data' => $widgetUsage->report($merchant, $company, $request->validated()),
        ];
    }

    public function recommendations(RecommendationAnalyticsRequest $request, RecommendationAnalyticsService $recommendations): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        return [
            'data' => $recommendations->report($merchant, $company, $request->validated()),
        ];
    }

    public function recommendationsExport(
        RecommendationAnalyticsRequest $request,
        RecommendationAnalyticsService $recommendations,
    ) {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $report = $request->string('report')->toString() === 'ranking' ? 'ranking' : 'recommendations';
        $filename = $report === 'ranking'
            ? 'provador-virtual-ranking-produtos.csv'
            : 'provador-virtual-recomendacoes.csv';

        return response($recommendations->exportCsv($merchant, $company, $request->validated(), $report), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
