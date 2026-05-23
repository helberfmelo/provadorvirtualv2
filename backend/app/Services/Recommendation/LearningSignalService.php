<?php

namespace App\Services\Recommendation;

use App\Models\MeasurementTable;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLearningEvent;
use App\Models\RecommendationLog;
use App\Models\ShopperProfile;
use Illuminate\Support\Str;

class LearningSignalService
{
    private const MEASURE_KEYS = ['bust', 'waist', 'hip', 'height', 'weight', 'length', 'shoulder'];

    public function recordRecommendation(
        RecommendationLog $log,
        MeasurementTable $table,
        array $measurements,
        ?ShopperProfile $profile,
    ): RecommendationLearningEvent {
        $assessment = $this->assess($table, $measurements, $log->recommended_size, null, (float) $log->confidence);
        $event = $this->createEvent($log, $profile, [
            'event_type' => 'recommendation',
            'signal' => 'recommended_size',
            'selected_size' => null,
            'assessment' => $assessment,
            'payload' => [
                'measurement_keys' => array_values(array_intersect(array_keys($measurements), self::MEASURE_KEYS)),
                'needs_more_data' => $log->status === 'needs_more_data',
            ],
        ]);

        $log->update([
            'outlier_score' => $assessment['score'],
            'learning_status' => $assessment['status'],
            'learning_reason' => $assessment['reason'],
        ]);

        if ($profile) {
            $profile->update(['outlier_score' => max((float) $profile->outlier_score, $assessment['score'])]);
        }

        return $event;
    }

    public function recordFeedback(RecommendationFeedback $feedback): ?RecommendationLearningEvent
    {
        $log = $feedback->recommendationLog()
            ->with(['session.shopperProfile', 'product.measurementTable.rows'])
            ->first();

        if (! $log || ! $log->product?->measurementTable) {
            return null;
        }

        $assessment = $this->assess(
            $log->product->measurementTable,
            $log->input_measurements ?? [],
            $log->recommended_size,
            $feedback->selected_size,
            (float) $log->confidence,
        );

        return $this->createEvent($log, $log->session?->shopperProfile, [
            'event_type' => 'feedback',
            'signal' => $feedback->was_helpful === false ? 'not_helpful' : 'helpful',
            'selected_size' => $feedback->selected_size,
            'recommendation_feedback_id' => $feedback->id,
            'assessment' => $assessment,
            'payload' => [
                'was_helpful' => $feedback->was_helpful,
                'rating' => $feedback->rating,
                'has_comment' => filled($feedback->comment),
            ],
        ]);
    }

    public function recordCommerceSignal(RecommendationLog $log, array $data): ?RecommendationLearningEvent
    {
        $log->loadMissing(['session.shopperProfile', 'product.measurementTable.rows']);

        if (! $log->product?->measurementTable) {
            return null;
        }

        $assessment = $this->assess(
            $log->product->measurementTable,
            $log->input_measurements ?? [],
            $log->recommended_size,
            $data['selected_size'] ?? null,
            (float) $log->confidence,
            $data['signal'] ?? null,
        );

        return $this->createEvent($log, $log->session?->shopperProfile, [
            'event_type' => $data['signal'],
            'signal' => $data['signal'],
            'selected_size' => $data['selected_size'] ?? null,
            'assessment' => $assessment,
            'payload' => [
                'source' => $data['source'] ?? 'widget',
                'order_reference_hash' => filled($data['order_reference'] ?? null)
                    ? hash('sha256', (string) $data['order_reference'])
                    : null,
                'notes' => $data['notes'] ?? null,
            ],
        ]);
    }

    private function createEvent(RecommendationLog $log, ?ShopperProfile $profile, array $data): RecommendationLearningEvent
    {
        $assessment = $data['assessment'];

        return RecommendationLearningEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $log->merchant_id,
            'merchant_company_id' => $log->merchant_company_id,
            'shopper_profile_id' => $profile?->id,
            'recommendation_log_id' => $log->id,
            'recommendation_feedback_id' => $data['recommendation_feedback_id'] ?? null,
            'product_id' => $log->product_id,
            'product_variant_id' => $log->product_variant_id,
            'event_type' => $data['event_type'],
            'signal' => $data['signal'] ?? null,
            'recommended_size' => $log->recommended_size,
            'selected_size' => $data['selected_size'] ?? null,
            'confidence' => $log->confidence,
            'outlier_score' => $assessment['score'],
            'learning_weight' => $assessment['weight'],
            'status' => $assessment['status'],
            'reason' => $assessment['reason'],
            'payload' => $data['payload'] ?? [],
            'occurred_at' => now(),
        ]);
    }

    private function assess(
        MeasurementTable $table,
        array $measurements,
        ?string $recommendedSize,
        ?string $selectedSize,
        float $confidence,
        ?string $commerceSignal = null,
    ): array {
        $score = 0.0;
        $reasons = [];

        $rangeScore = $this->rangeOutlierScore($table, $measurements);
        if ($rangeScore > 0) {
            $score += $rangeScore;
            $reasons[] = 'medidas fora da faixa historica da tabela';
        }

        $bmiScore = $this->bmiOutlierScore($measurements);
        if ($bmiScore > 0) {
            $score += $bmiScore;
            $reasons[] = 'relacao altura/peso atipica';
        }

        $sizeDistance = $this->sizeDistance($table, $recommendedSize, $selectedSize);
        if ($sizeDistance > 1) {
            $score += min(45, $sizeDistance * 18);
            $reasons[] = 'tamanho escolhido distante do recomendado';
        }

        if ($confidence < 45) {
            $score += 10;
            $reasons[] = 'confiança baixa';
        }

        $negativeCommerceSignal = in_array($commerceSignal, ['return', 'exchange'], true);

        if ($negativeCommerceSignal) {
            $score += 25;
            $reasons[] = 'sinal comercial exige revisão';
        }

        $score = round(min(100, $score), 2);
        $status = match (true) {
            $score >= 65 => 'blocked_outlier',
            $score >= 45 || $negativeCommerceSignal => 'review',
            default => 'accepted',
        };

        return [
            'score' => $score,
            'status' => $status,
            'reason' => $reasons === [] ? 'sinal dentro da faixa esperada' : implode('; ', $reasons),
            'weight' => $status === 'blocked_outlier' ? 0 : round(max(0.15, 1 - ($score / 100)), 3),
        ];
    }

    private function rangeOutlierScore(MeasurementTable $table, array $measurements): float
    {
        $rows = $table->rows;
        $score = 0.0;

        foreach (self::MEASURE_KEYS as $key) {
            if (! isset($measurements[$key])) {
                continue;
            }

            $mins = $rows->pluck($key.'_min')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);
            $maxes = $rows->pluck($key.'_max')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);

            if ($mins->isEmpty() || $maxes->isEmpty()) {
                continue;
            }

            $min = $mins->min();
            $max = $maxes->max();
            $range = max(1, $max - $min);
            $value = (float) $measurements[$key];

            if ($value < $min) {
                $score += min(28, (($min - $value) / $range) * 50);
            } elseif ($value > $max) {
                $score += min(28, (($value - $max) / $range) * 50);
            }
        }

        return round(min(45, $score), 2);
    }

    private function bmiOutlierScore(array $measurements): float
    {
        if (empty($measurements['height']) || empty($measurements['weight'])) {
            return 0.0;
        }

        $heightMeters = ((float) $measurements['height']) / 100;

        if ($heightMeters <= 0) {
            return 0.0;
        }

        $bmi = ((float) $measurements['weight']) / ($heightMeters ** 2);

        if ($bmi < 14 || $bmi > 48) {
            return 35.0;
        }

        if ($bmi < 16 || $bmi > 40) {
            return 20.0;
        }

        return 0.0;
    }

    private function sizeDistance(MeasurementTable $table, ?string $recommendedSize, ?string $selectedSize): int
    {
        if (! $recommendedSize || ! $selectedSize) {
            return 0;
        }

        $labels = $table->rows->pluck('size_label')
            ->map(fn ($label) => mb_strtolower((string) $label))
            ->values();

        $recommendedIndex = $labels->search(mb_strtolower($recommendedSize));
        $selectedIndex = $labels->search(mb_strtolower($selectedSize));

        if ($recommendedIndex === false || $selectedIndex === false) {
            return 0;
        }

        return abs((int) $recommendedIndex - (int) $selectedIndex);
    }
}
