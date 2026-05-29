<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaxonomyMappingSuggestionResource;
use App\Http\Resources\TaxonomyVersionResource;
use App\Models\TaxonomyMappingSuggestion;
use App\Services\Catalog\TaxonomyIntelligenceService;
use Illuminate\Http\Request;

class TaxonomyIntelligenceController extends Controller
{
    use ResolvesMerchant;

    public function __construct(private readonly TaxonomyIntelligenceService $intelligence) {}

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $dashboard = $this->intelligence->dashboard($merchant, $company);

        return response()->json([
            'summary' => $dashboard['summary'],
            'active_version' => (new TaxonomyVersionResource($dashboard['active_version']))->resolve($request),
            'suggestions' => TaxonomyMappingSuggestionResource::collection($dashboard['suggestions'])->resolve($request),
            'learning_events' => $dashboard['learning_events'],
            'signals' => $dashboard['signals'],
        ]);
    }

    public function generate(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validate([
            'type' => ['nullable', 'in:all,category,categories,brand,brands'],
        ]);
        $result = $this->intelligence->generateSuggestions($merchant, $company, $data['type'] ?? 'all');

        return response()->json([
            'summary' => $result['summary'],
            'suggestions' => TaxonomyMappingSuggestionResource::collection($result['suggestions'])->resolve($request),
        ], 201);
    }

    public function approve(Request $request, TaxonomyMappingSuggestion $suggestion)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validate([
            'apply_to_products' => ['nullable', 'boolean'],
            'confirm_low_confidence' => ['nullable', 'boolean'],
            'taxonomy_category_id' => ['nullable', 'integer', 'exists:taxonomy_categories,id'],
            'taxonomy_name' => ['nullable', 'string', 'max:160'],
            'normalized_brand_id' => ['nullable', 'integer', 'exists:normalized_brands,id'],
            'normalized_name' => ['nullable', 'string', 'max:160'],
        ]);
        $result = $this->intelligence->approveSuggestion(
            $suggestion,
            $merchant,
            $company,
            $request->user()?->id,
            $data
        );

        return (new TaxonomyMappingSuggestionResource($result['suggestion']))
            ->additional(['summary' => $result['summary']]);
    }

    public function reject(Request $request, TaxonomyMappingSuggestion $suggestion)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:240'],
        ]);
        $result = $this->intelligence->rejectSuggestion(
            $suggestion,
            $merchant,
            $company,
            $request->user()?->id,
            $data['reason'] ?? null
        );

        return (new TaxonomyMappingSuggestionResource($result['suggestion']))
            ->additional(['summary' => $result['summary']]);
    }
}
