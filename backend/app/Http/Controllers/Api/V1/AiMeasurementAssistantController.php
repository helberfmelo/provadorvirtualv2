<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuggestMeasurementTableRequest;
use App\Services\Ai\MeasurementTableSuggestionService;

class AiMeasurementAssistantController extends Controller
{
    use ResolvesMerchant;

    public function __construct(private readonly MeasurementTableSuggestionService $suggestions) {}

    public function status(): array
    {
        return [
            'data' => $this->suggestions->status(),
        ];
    }

    public function suggest(SuggestMeasurementTableRequest $request): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        return [
            'data' => $this->suggestions->suggest($merchant, $request->user(), $request->validated(), $company),
        ];
    }
}
