<?php

namespace App\Services\Recommendation;

use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\RecommendationLearningEvent;
use Illuminate\Support\Collection;

class MeasurementTableInsightService
{
    public function insights(Merchant $merchant, ?MerchantCompany $company = null, int $limit = 8): array
    {
        $events = RecommendationLearningEvent::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->where('occurred_at', '>=', now()->subDays(90))
            ->with(['product.measurementTable'])
            ->get()
            ->filter(fn (RecommendationLearningEvent $event): bool => (bool) $event->product?->measurementTable);

        return $events
            ->groupBy(fn (RecommendationLearningEvent $event): int => (int) $event->product->measurement_table_id)
            ->map(fn (Collection $group): array => $this->buildInsight($group))
            ->sortByDesc('priority_score')
            ->take($limit)
            ->values()
            ->all();
    }

    public function contextForSuggestion(Merchant $merchant, ?MerchantCompany $company, array $input): array
    {
        $matches = collect($this->insights($merchant, $company, 12))
            ->filter(fn (array $insight): bool => $this->matchesInput($insight, $input))
            ->take(3)
            ->values();

        return [
            'has_signals' => $matches->isNotEmpty(),
            'matching_insights' => $matches->map(fn (array $insight): array => [
                'measurement_table_id' => $insight['measurement_table_id'],
                'table_name' => $insight['table_name'],
                'suggested_action' => $insight['suggested_action'],
                'reason' => $insight['reason'],
                'signals' => $insight['signals'],
            ])->all(),
            'warnings' => $matches
                ->map(fn (array $insight): string => 'Aprendizado '.$insight['table_name'].': '.$insight['reason'])
                ->all(),
        ];
    }

    private function buildInsight(Collection $events): array
    {
        /** @var RecommendationLearningEvent $first */
        $first = $events->first();
        $table = $first->product->measurementTable;
        $returnEvents = $events->whereIn('event_type', ['return', 'exchange']);
        $purchaseEvents = $events->where('event_type', 'purchase');
        $feedbackEvents = $events->where('event_type', 'feedback');
        $smallReturns = $this->countPayloadValue($returnEvents, 'return_reason', 'size_too_small');
        $largeReturns = $this->countPayloadValue($returnEvents, 'return_reason', 'size_too_large');
        $fitReturns = $this->countPayloadValue($returnEvents, 'return_reason', 'fit_issue');
        $negativeFeedback = $feedbackEvents->where('signal', 'not_helpful')->count();
        $positiveFeedback = $feedbackEvents->where('signal', 'helpful')->count();
        $reviewEvents = $events->where('status', 'review')->count();
        $blockedEvents = $events->where('status', 'blocked_outlier')->count();
        $priority = ($returnEvents->count() * 5) + ($reviewEvents * 3) + ($blockedEvents * 2) + ($negativeFeedback * 2);
        [$action, $reason] = $this->actionAndReason(
            $events,
            $smallReturns,
            $largeReturns,
            $fitReturns,
            $negativeFeedback,
            $positiveFeedback,
        );

        return [
            'measurement_table_id' => $table->id,
            'table_name' => $table->name,
            'product_type' => $table->product_type,
            'gender' => $table->gender,
            'fit_profile' => $table->fit_profile,
            'suggested_action' => $action,
            'reason' => $reason,
            'priority_score' => $priority,
            'confidence' => $this->confidence($events->count()),
            'signals' => [
                'total' => $events->count(),
                'accepted' => $events->where('status', 'accepted')->count(),
                'review' => $reviewEvents,
                'blocked_outliers' => $blockedEvents,
                'purchases' => $purchaseEvents->count(),
                'returns' => $returnEvents->count(),
                'positive_feedback' => $positiveFeedback,
                'negative_feedback' => $negativeFeedback,
                'size_too_small' => $smallReturns,
                'size_too_large' => $largeReturns,
                'fit_issue' => $fitReturns,
                'return_rate' => $purchaseEvents->count() > 0
                    ? round(($returnEvents->count() / $purchaseEvents->count()) * 100, 1)
                    : null,
            ],
        ];
    }

    private function actionAndReason(
        Collection $events,
        int $smallReturns,
        int $largeReturns,
        int $fitReturns,
        int $negativeFeedback,
        int $positiveFeedback,
    ): array {
        if ($smallReturns > 0) {
            return [
                'review_size_too_small',
                'Há devolução por peça pequena; revise modelagem e ranges para evitar recomendar tamanho abaixo do ideal.',
            ];
        }

        if ($largeReturns > 0) {
            return [
                'review_size_too_large',
                'Há devolução por peça grande; revise modelagem e ranges para evitar recomendar tamanho acima do ideal.',
            ];
        }

        if ($fitReturns > 0) {
            return [
                'review_fit_profile',
                'Há devolução por caimento; revise o cadastro de modelagem antes de alterar medidas.',
            ];
        }

        if ($negativeFeedback > $positiveFeedback) {
            return [
                'review_feedback',
                'Feedback negativo superou positivo; confira a tabela antes de usar esses sinais no assistente.',
            ];
        }

        if ($events->count() < 5) {
            return [
                'collect_more_data',
                'Ainda há pouco volume; use os sinais como contexto, sem ajuste automático.',
            ];
        }

        return [
            'stable',
            'Sinais consistentes; a tabela pode servir como referência para novas sugestões.',
        ];
    }

    private function countPayloadValue(Collection $events, string $key, string $value): int
    {
        return $events
            ->filter(fn (RecommendationLearningEvent $event): bool => ($event->payload[$key] ?? null) === $value)
            ->count();
    }

    private function confidence(int $events): string
    {
        return match (true) {
            $events >= 30 => 'high',
            $events >= 10 => 'medium',
            default => 'low',
        };
    }

    private function matchesInput(array $insight, array $input): bool
    {
        foreach (['product_type', 'gender', 'fit_profile'] as $field) {
            if (! filled($input[$field] ?? null)) {
                continue;
            }

            if (! filled($insight[$field] ?? null)) {
                continue;
            }

            if (mb_strtolower((string) $input[$field]) !== mb_strtolower((string) $insight[$field])) {
                return false;
            }
        }

        return true;
    }
}
