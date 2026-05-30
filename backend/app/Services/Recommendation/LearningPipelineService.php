<?php

namespace App\Services\Recommendation;

use App\Models\Product;
use App\Models\RecommendationLearningEvent;
use Illuminate\Support\Collection;

class LearningPipelineService
{
    public function build(Collection $learningEvents, Collection $shopperProfiles, array $measurementTableInsights): array
    {
        $insights = collect($measurementTableInsights);
        $commercialSignals = $learningEvents->whereNotIn('event_type', ['recommendation']);
        $readyForLearning = $commercialSignals->filter(fn (RecommendationLearningEvent $event): bool => $this->isReadyForLearning($event));
        $manualReview = $commercialSignals->where('status', 'review');
        $blocked = $commercialSignals->where('status', 'blocked_outlier');
        $payloadRetentionDays = max(1, (int) config('privacy.learning_event_payload_retention_days', 180));
        $payloadCutoff = now()->subDays($payloadRetentionDays);
        $pendingPayloadAnonymization = $learningEvents
            ->filter(fn (RecommendationLearningEvent $event): bool => $event->created_at !== null
                && $event->created_at->lt($payloadCutoff)
                && ! empty($event->payload))
            ->count();

        $automaticCandidates = $insights
            ->filter(fn (array $insight): bool => ($insight['suggested_action'] ?? null) === 'stable'
                && (($insight['signals']['accepted'] ?? 0) >= 3))
            ->map(fn (array $insight): array => [
                'measurement_table_id' => $insight['measurement_table_id'],
                'table_name' => $insight['table_name'],
                'confidence' => $insight['confidence'],
                'accepted_signals' => $insight['signals']['accepted'] ?? 0,
                'purchases' => $insight['signals']['purchases'] ?? 0,
                'positive_feedback' => $insight['signals']['positive_feedback'] ?? 0,
                'explanation' => 'Sinais consistentes e sem alerta dominante. A base pode orientar novas sugestões da IA, sem alterar tabela automaticamente.',
            ])
            ->values()
            ->all();

        $manualReviewQueue = $insights
            ->filter(fn (array $insight): bool => ! in_array($insight['suggested_action'] ?? null, ['stable', 'collect_more_data'], true))
            ->map(fn (array $insight): array => [
                'measurement_table_id' => $insight['measurement_table_id'],
                'table_name' => $insight['table_name'],
                'suggested_action' => $insight['suggested_action'],
                'reason' => $insight['reason'],
                'priority_score' => $insight['priority_score'],
                'confidence' => $insight['confidence'],
                'signals' => $insight['signals'],
                'suggested_adjustment' => $insight['suggested_adjustment'] ?? null,
                'review_required' => true,
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'applied_recommendations' => $learningEvents
                    ->where('event_type', 'recommendation')
                    ->pluck('recommendation_log_id')
                    ->filter()
                    ->unique()
                    ->count(),
                'commercial_signals' => $commercialSignals->count(),
                'ready_for_learning' => $readyForLearning->count(),
                'manual_review' => $manualReview->count(),
                'blocked_outliers' => $blocked->count(),
                'linked_orders' => $learningEvents->where('event_type', 'purchase')->count(),
                'linked_returns_exchanges' => $learningEvents->whereIn('event_type', ['return', 'exchange'])->count(),
                'feedback_signals' => $learningEvents->where('event_type', 'feedback')->count(),
                'profiles_anonymized' => $shopperProfiles->where('status', 'anonymized')->count(),
                'payloads_pending_anonymization' => $pendingPayloadAnonymization,
            ],
            'guardrails' => [
                'review_required' => true,
                'applied_recommendation_scope' => 'A recomendacao usada no produto continua separada do aprendizado historico.',
                'automatic_learning_scope' => 'Pedidos, devolucoes, trocas e feedback alimentam contexto e prioridade, mas nenhuma tabela e alterada sem aprovacao humana.',
                'lgpd_scope' => 'Referencias de pedido ficam em hash e payloads antigos entram em anonimização por janela de retencao.',
            ],
            'automatic_learning' => [
                'enabled' => true,
                'review_required' => true,
                'candidates' => $automaticCandidates,
            ],
            'manual_review_queue' => $manualReviewQueue,
            'patterns' => [
                'by_product' => $this->groupPatterns(
                    $commercialSignals,
                    fn (RecommendationLearningEvent $event): ?array => $event->product
                        ? ['key' => 'product:'.$event->product->id, 'label' => $event->product->name ?: 'Produto #'.$event->product->id]
                        : null
                ),
                'by_measurement_table' => $this->groupPatterns(
                    $commercialSignals,
                    function (RecommendationLearningEvent $event): ?array {
                        $table = $event->product?->measurementTable;

                        return $table
                            ? ['key' => 'table:'.$table->id, 'label' => $table->name ?: 'Tabela #'.$table->id]
                            : null;
                    }
                ),
                'by_category' => $this->groupPatterns(
                    $commercialSignals,
                    function (RecommendationLearningEvent $event): ?array {
                        $category = $this->productCategory($event->product);

                        return $category !== null
                            ? ['key' => 'category:'.$category, 'label' => $category]
                            : null;
                    }
                ),
                'by_brand' => $this->groupPatterns(
                    $commercialSignals,
                    function (RecommendationLearningEvent $event): ?array {
                        $brand = $this->productBrand($event->product);

                        return $brand !== null
                            ? ['key' => 'brand:'.$brand, 'label' => $brand]
                            : null;
                    }
                ),
                'by_fit_profile' => $this->groupPatterns(
                    $commercialSignals,
                    function (RecommendationLearningEvent $event): ?array {
                        $fitProfile = $event->product?->fit_profile
                            ?: $event->product?->measurementTable?->fit_profile;

                        return $fitProfile !== null
                            ? ['key' => 'fit_profile:'.$fitProfile, 'label' => $fitProfile]
                            : null;
                    }
                ),
            ],
            'privacy' => [
                'order_reference_policy' => 'hash_only',
                'widget_retention_days' => max(1, (int) config('privacy.widget_data_retention_days', 30)),
                'feedback_comment_retention_days' => max(1, (int) config('privacy.feedback_comment_retention_days', 90)),
                'profile_retention_days' => max(1, (int) config('privacy.profile_retention_days', 180)),
                'learning_event_payload_retention_days' => $payloadRetentionDays,
                'operational_log_retention_days' => max(1, (int) config('privacy.operational_log_retention_days', 180)),
                'payloads_pending_anonymization' => $pendingPayloadAnonymization,
                'payloads_already_anonymized' => $learningEvents->filter(fn (RecommendationLearningEvent $event): bool => empty($event->payload))->count(),
                'review_required' => true,
            ],
        ];
    }

    private function groupPatterns(Collection $events, callable $resolver): array
    {
        return $events
            ->map(function (RecommendationLearningEvent $event) use ($resolver): ?array {
                $group = $resolver($event);

                if ($group === null || empty($group['key']) || empty($group['label'])) {
                    return null;
                }

                return [
                    'group_key' => $group['key'],
                    'group_label' => $group['label'],
                    'event' => $event,
                ];
            })
            ->filter()
            ->groupBy('group_key')
            ->map(function (Collection $group): array {
                $events = $group->pluck('event');
                $label = (string) $group->first()['group_label'];

                return $this->patternRow($events, $label);
            })
            ->sortByDesc(fn (array $row): array => [$row['manual_review'], $row['returns'] + $row['exchanges'], $row['total_signals']])
            ->take(5)
            ->values()
            ->all();
    }

    private function patternRow(Collection $events, string $label): array
    {
        $purchases = $events->where('event_type', 'purchase')->count();
        $returns = $events->where('event_type', 'return')->count();
        $exchanges = $events->where('event_type', 'exchange')->count();
        $positiveFeedback = $events->where('event_type', 'feedback')->where('signal', 'helpful')->count();
        $negativeFeedback = $events->where('event_type', 'feedback')->where('signal', 'not_helpful')->count();
        $readyForLearning = $events->filter(fn (RecommendationLearningEvent $event): bool => $this->isReadyForLearning($event))->count();
        $manualReview = $events->where('status', 'review')->count();
        $blocked = $events->where('status', 'blocked_outlier')->count();

        return [
            'label' => $label,
            'total_signals' => $events->count(),
            'ready_for_learning' => $readyForLearning,
            'manual_review' => $manualReview,
            'blocked_outliers' => $blocked,
            'purchases' => $purchases,
            'returns' => $returns,
            'exchanges' => $exchanges,
            'positive_feedback' => $positiveFeedback,
            'negative_feedback' => $negativeFeedback,
            'return_rate' => $purchases > 0 ? round((($returns + $exchanges) / $purchases) * 100, 1) : null,
            'attention' => $this->patternAttention($returns, $exchanges, $negativeFeedback, $positiveFeedback, $blocked, $readyForLearning),
        ];
    }

    private function patternAttention(
        int $returns,
        int $exchanges,
        int $negativeFeedback,
        int $positiveFeedback,
        int $blocked,
        int $readyForLearning,
    ): string {
        if (($returns + $exchanges) > 0) {
            return 'Revisar devolucoes e trocas';
        }

        if ($negativeFeedback > $positiveFeedback) {
            return 'Revisar feedback';
        }

        if ($blocked > 0) {
            return 'Investigar outliers';
        }

        if ($readyForLearning >= 3) {
            return 'Base consistente';
        }

        return 'Coletar mais dados';
    }

    private function isReadyForLearning(RecommendationLearningEvent $event): bool
    {
        return $event->status === 'accepted'
            && (float) $event->learning_weight >= 1
            && in_array($event->event_type, ['feedback', 'purchase', 'return', 'exchange'], true);
    }

    private function productBrand(?Product $product): ?string
    {
        $brand = data_get($product?->metadata ?? [], 'normalized_brand.name')
            ?: data_get($product?->metadata ?? [], 'normalized_brand_name')
            ?: data_get($product?->metadata ?? [], 'brand')
            ?: data_get($product?->metadata ?? [], 'brand_name');

        return filled($brand) ? (string) $brand : null;
    }

    private function productCategory(?Product $product): ?string
    {
        $category = data_get($product?->metadata ?? [], 'normalized_category.name')
            ?: data_get($product?->metadata ?? [], 'normalized_category_name')
            ?: $product?->category;

        return filled($category) ? (string) $category : null;
    }
}
