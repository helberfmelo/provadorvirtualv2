<?php

namespace App\Services\Recommendation;

use App\Models\MeasurementTable;

class RecommendationEngine
{
    private const MEASURES = [
        'bust' => ['weight' => 1.0, 'tolerance' => 4.0, 'label' => 'busto'],
        'waist' => ['weight' => 1.0, 'tolerance' => 4.0, 'label' => 'cintura'],
        'hip' => ['weight' => 1.0, 'tolerance' => 4.0, 'label' => 'quadril'],
        'height' => ['weight' => 0.35, 'tolerance' => 8.0, 'label' => 'altura'],
        'weight' => ['weight' => 0.35, 'tolerance' => 6.0, 'label' => 'peso'],
        'length' => ['weight' => 0.45, 'tolerance' => 5.0, 'label' => 'comprimento'],
        'shoulder' => ['weight' => 0.55, 'tolerance' => 3.0, 'label' => 'ombro'],
    ];

    public function recommend(MeasurementTable $table, array $measurements): RecommendationResult
    {
        $normalized = $this->normalizeMeasurements($measurements);
        $usableMeasures = array_intersect_key($normalized, self::MEASURES);

        if ($usableMeasures === []) {
            return new RecommendationResult(
                recommendedSize: null,
                confidence: 0,
                fitNotes: [],
                warnings: ['Informe pelo menos uma medida corporal para recomendar um tamanho.'],
                scoreBreakdown: [],
                needsMoreData: true,
            );
        }

        $rows = $table->rows()->orderBy('sort_order')->get();

        if ($rows->isEmpty()) {
            return new RecommendationResult(
                recommendedSize: null,
                confidence: 0,
                fitNotes: [],
                warnings: ['Tabela de medidas sem linhas configuradas.'],
                scoreBreakdown: [],
                needsMoreData: true,
            );
        }

        $scores = $rows->map(function ($row) use ($usableMeasures): array {
            $details = [];
            $weightedScore = 0.0;
            $weightTotal = 0.0;

            foreach ($usableMeasures as $measure => $value) {
                $min = $this->decimalValue($row->{$measure.'_min'});
                $max = $this->decimalValue($row->{$measure.'_max'});

                if ($min === null || $max === null) {
                    continue;
                }

                $config = self::MEASURES[$measure];
                $penalty = $this->rangePenalty($value, $min, $max, $config['tolerance']);
                $weightedScore += $penalty * $config['weight'];
                $weightTotal += $config['weight'];
                $details[$measure] = [
                    'value' => $value,
                    'min' => $min,
                    'max' => $max,
                    'penalty' => round($penalty, 3),
                    'status' => $this->measureStatus($value, $min, $max),
                ];
            }

            $score = $weightTotal > 0 ? $weightedScore / $weightTotal : 99.0;

            return [
                'size_label' => $row->size_label,
                'score' => round($score, 4),
                'details' => $details,
            ];
        })->sortBy('score')->values();

        $best = $scores->first();
        $second = $scores->get(1);
        $matchedMeasures = count($best['details'] ?? []);
        $needsMoreData = $matchedMeasures < 2;
        $tie = $second && abs($second['score'] - $best['score']) <= 0.15;
        $confidence = $this->confidence($best['score'], $matchedMeasures, $tie);
        $warnings = [];

        if ($needsMoreData) {
            $warnings[] = 'Recomendacao com poucas medidas. Para melhorar a confianca, informe busto, cintura e quadril.';
        }

        if ($tie) {
            $warnings[] = 'Suas medidas ficaram proximas de dois tamanhos. Considere a preferencia de caimento.';
        }

        return new RecommendationResult(
            recommendedSize: $best['size_label'],
            confidence: $confidence,
            fitNotes: $this->fitNotes($best['details']),
            warnings: $warnings,
            scoreBreakdown: $scores->all(),
            needsMoreData: $needsMoreData,
        );
    }

    private function normalizeMeasurements(array $measurements): array
    {
        $normalized = [];

        foreach ($measurements as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $number = (float) $value;

            if ($number > 0) {
                $normalized[$key] = $number;
            }
        }

        return $normalized;
    }

    private function decimalValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function rangePenalty(float $value, float $min, float $max, float $tolerance): float
    {
        if ($value < $min) {
            return ($min - $value) / $tolerance;
        }

        if ($value > $max) {
            return ($value - $max) / $tolerance;
        }

        return 0.0;
    }

    private function measureStatus(float $value, float $min, float $max): string
    {
        if ($value < $min) {
            return 'below';
        }

        if ($value > $max) {
            return 'above';
        }

        return 'inside';
    }

    private function confidence(float $score, int $matchedMeasures, bool $tie): float
    {
        $confidence = 96 - ($score * 22);
        $confidence -= max(0, 3 - $matchedMeasures) * 8;

        if ($tie) {
            $confidence -= 6;
        }

        return max(35, min(98, $confidence));
    }

    private function fitNotes(array $details): array
    {
        $notes = [];

        foreach ($details as $measure => $detail) {
            $label = self::MEASURES[$measure]['label'];

            if ($detail['status'] === 'inside') {
                $notes[] = ucfirst($label).' dentro da faixa do tamanho recomendado.';
            } elseif ($detail['status'] === 'below') {
                $notes[] = ucfirst($label).' abaixo da faixa; o caimento pode ficar mais solto.';
            } else {
                $notes[] = ucfirst($label).' acima da faixa; o caimento pode ficar mais justo.';
            }
        }

        return array_slice($notes, 0, 4);
    }
}
