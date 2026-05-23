<?php

namespace App\Services\Recommendation;

class RecommendationResult
{
    public function __construct(
        public readonly ?string $recommendedSize,
        public readonly float $confidence,
        public readonly array $fitNotes,
        public readonly array $warnings,
        public readonly array $scoreBreakdown,
        public readonly bool $needsMoreData,
    ) {}

    public function toArray(): array
    {
        return [
            'recommended_size' => $this->recommendedSize,
            'confidence' => round($this->confidence, 2),
            'fit_notes' => $this->fitNotes,
            'warnings' => $this->warnings,
            'score_breakdown' => $this->scoreBreakdown,
            'needs_more_data' => $this->needsMoreData,
        ];
    }
}
