<?php

namespace App\Support;

class WidgetModalCatalog
{
    public static function default(): array
    {
        return [
            'logo_text' => 'Provador Virtual',
            'logo_url' => null,
            'kicker' => 'Provador Virtual',
            'title' => 'Descubra seu tamanho',
            'subtitle' => 'Uma jornada rápida para melhorar a precisão da recomendação.',
            'step_labels' => ['Medidas', 'Corpo', 'Detalhes', 'Resultado'],
            'table_title' => 'Tabela de Medidas',
            'table_unit_label' => 'cm',
            'footer_note' => 'Toque no tamanho para aplicar na página do produto.',
            'background' => '#ffffff',
            'surface' => '#f8fafc',
            'text' => '#111827',
            'accent' => '#ff4d5e',
            'border' => '#e5e7eb',
            'radius' => 16,
            'font_family' => 'Manrope, Inter, Arial, sans-serif',
            'font_size' => 15,
            'font_weight' => 700,
            'table_style' => 'clean',
        ];
    }

    public static function normalize(?array $modal): array
    {
        $modal = $modal ?? [];
        $default = self::default();

        $stepLabels = array_values(array_filter(array_map(function ($label): string {
            return trim((string) $label);
        }, is_array($modal['step_labels'] ?? null) ? $modal['step_labels'] : $default['step_labels']), fn (string $label): bool => $label !== ''));

        if (count($stepLabels) < 4) {
            $stepLabels = array_merge($stepLabels, array_slice($default['step_labels'], count($stepLabels)));
        }

        $tableStyle = in_array($modal['table_style'] ?? null, ['clean', 'compact', 'cards'], true)
            ? $modal['table_style']
            : $default['table_style'];

        return [
            'logo_text' => self::normalizeText($modal['logo_text'] ?? $default['logo_text'], $default['logo_text'], 48),
            'logo_url' => self::normalizeUrl($modal['logo_url'] ?? null),
            'kicker' => self::normalizeText($modal['kicker'] ?? $default['kicker'], $default['kicker'], 64),
            'title' => self::normalizeText($modal['title'] ?? $default['title'], $default['title'], 96),
            'subtitle' => self::normalizeText($modal['subtitle'] ?? $default['subtitle'], $default['subtitle'], 180),
            'step_labels' => array_slice(array_map(fn (string $label): string => self::normalizeText($label, '', 32), $stepLabels), 0, 4),
            'table_title' => self::normalizeText($modal['table_title'] ?? $default['table_title'], $default['table_title'], 64),
            'table_unit_label' => self::normalizeText($modal['table_unit_label'] ?? $default['table_unit_label'], $default['table_unit_label'], 16),
            'footer_note' => self::normalizeText($modal['footer_note'] ?? $default['footer_note'], $default['footer_note'], 120),
            'background' => self::normalizeColor($modal['background'] ?? $default['background'], $default['background']),
            'surface' => self::normalizeColor($modal['surface'] ?? $default['surface'], $default['surface']),
            'text' => self::normalizeColor($modal['text'] ?? $default['text'], $default['text']),
            'accent' => self::normalizeColor($modal['accent'] ?? $default['accent'], $default['accent']),
            'border' => self::normalizeColor($modal['border'] ?? $default['border'], $default['border']),
            'radius' => self::normalizeRadius($modal['radius'] ?? $default['radius']),
            'font_family' => self::normalizeText($modal['font_family'] ?? $default['font_family'], $default['font_family'], 120),
            'font_size' => self::normalizeNumeric($modal['font_size'] ?? $default['font_size'], $default['font_size'], 12, 20),
            'font_weight' => self::normalizeNumeric($modal['font_weight'] ?? $default['font_weight'], $default['font_weight'], 400, 900),
            'table_style' => $tableStyle,
        ];
    }

    private static function normalizeText($value, string $fallback, int $maxLength): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return $fallback;
        }

        return mb_substr($text, 0, $maxLength);
    }

    private static function normalizeUrl($value): ?string
    {
        $url = trim((string) $value);

        if ($url === '') {
            return null;
        }

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://'.$url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private static function normalizeColor($value, string $fallback): string
    {
        $color = trim((string) $value);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : $fallback;
    }

    private static function normalizeRadius($value): int
    {
        $radius = (int) $value;

        if ($radius < 0) {
            return 0;
        }

        return min($radius, 28);
    }

    private static function normalizeNumeric($value, int $fallback, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $number = (int) $value;

        if ($number < $min) {
            return $min;
        }

        return min($number, $max);
    }
}
