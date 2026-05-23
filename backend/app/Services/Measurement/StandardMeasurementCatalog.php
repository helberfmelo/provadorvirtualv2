<?php

namespace App\Services\Measurement;

use Illuminate\Support\Str;

class StandardMeasurementCatalog
{
    public const MARKET_BASIS = 'Base inteligente herdada do v1, com medidas padrao do mercado brasileiro por genero, produto, altura, peso, idade e formato corporal.';

    public function templates(): array
    {
        $products = $this->products();

        return array_values(array_map(
            fn (array $product, int $index): array => $this->templateFromProduct($product, $index),
            $products,
            array_keys($products)
        ));
    }

    public function metadata(): array
    {
        $products = $this->products();

        return [
            'source' => 'v1_standard_models',
            'market_basis' => self::MARKET_BASIS,
            'templates_count' => count($products),
            'genders' => array_values(array_unique(array_map(
                fn (array $product): string => (string) ($product['genero'] ?? ''),
                $products
            ))),
            'product_types' => array_values(array_unique(array_map(
                fn (array $product): string => (string) ($product['tipo_produto'] ?? ''),
                $products
            ))),
        ];
    }

    private function products(): array
    {
        $path = database_path('data/default_measurement_tables_data.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded['produtos'] ?? null) ? $decoded['produtos'] : [];
    }

    private function templateFromProduct(array $product, int $index): array
    {
        $genderLabel = (string) ($product['genero'] ?? 'Outro');
        $productTypeLabel = (string) ($product['tipo_produto'] ?? 'Produto');
        $name = (string) ($product['nome_referencia'] ?? "{$productTypeLabel} padrao");

        return [
            'key' => Str::slug("v1-{$index}-{$genderLabel}-{$productTypeLabel}-{$name}"),
            'name' => $name,
            'product_type' => $this->normalizeProductType($productTypeLabel),
            'product_type_label' => $productTypeLabel,
            'gender' => $this->normalizeGender($genderLabel),
            'gender_label' => $genderLabel,
            'fit_profile' => 'regular',
            'source' => 'standard_catalog',
            'source_label' => 'Base inteligente do mercado brasileiro',
            'market_basis' => self::MARKET_BASIS,
            'fields' => $product['campos_medida'] ?? [],
            'rows' => array_values(array_map(
                fn (array $size, int $rowIndex): array => $this->rowFromSize($size, $rowIndex),
                $product['tamanhos'] ?? [],
                array_keys($product['tamanhos'] ?? [])
            )),
        ];
    }

    private function rowFromSize(array $size, int $index): array
    {
        $row = [
            'size_label' => (string) ($size['nome'] ?? ''),
            'sort_order' => $index,
            'height_min' => $this->numberOrNull($size['altura_recomendada_min_cm'] ?? null),
            'height_max' => $this->numberOrNull($size['altura_recomendada_max_cm'] ?? null),
            'weight_min' => $this->numberOrNull($size['peso_recomendado_min_kg'] ?? null),
            'weight_max' => $this->numberOrNull($size['peso_recomendado_max_kg'] ?? null),
        ];

        foreach (($size['medidas'] ?? []) as $measurement => $range) {
            $field = $this->fieldForMeasurement((string) $measurement);

            if (! $field || ! is_array($range)) {
                continue;
            }

            if (($row["{$field}_min"] ?? null) !== null || ($row["{$field}_max"] ?? null) !== null) {
                continue;
            }

            $row["{$field}_min"] = $this->numberOrNull($range['min'] ?? null);
            $row["{$field}_max"] = $this->numberOrNull($range['max'] ?? null);
        }

        return array_filter($row, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function fieldForMeasurement(string $measurement): ?string
    {
        $value = Str::of($measurement)->ascii()->lower()->replace(['-', ' '], '_')->toString();

        if (Str::contains($value, ['busto', 'peito', 'torax'])) {
            return 'bust';
        }

        if (Str::contains($value, 'cintura')) {
            return 'waist';
        }

        if (Str::contains($value, 'quadril')) {
            return 'hip';
        }

        if (Str::contains($value, 'ombro')) {
            return 'shoulder';
        }

        if (Str::contains($value, 'altura')) {
            return 'height';
        }

        if (Str::contains($value, 'peso')) {
            return 'weight';
        }

        if (Str::contains($value, ['comprimento', 'entrepernas', 'manga', 'gancho', 'pe'])) {
            return 'length';
        }

        return null;
    }

    private function normalizeGender(string $gender): string
    {
        $value = Str::of($gender)->ascii()->lower()->toString();

        return match (true) {
            Str::contains($value, 'masculino') => 'male',
            Str::contains($value, 'feminino') => 'female',
            Str::contains($value, 'infantil') => 'kids',
            Str::contains($value, 'unissex') => 'unisex',
            default => 'unisex',
        };
    }

    private function normalizeProductType(string $productType): string
    {
        $value = Str::of($productType)->ascii()->lower()->toString();

        return match (true) {
            Str::contains($value, ['camisa social']) => 'shirt',
            Str::contains($value, ['camiseta infantil']) => 'kids_shirt',
            Str::contains($value, ['calca infantil']) => 'kids_pants',
            Str::contains($value, ['body']) => 'baby_body',
            Str::contains($value, ['camiseta', 'camisa']) => 'shirt',
            Str::contains($value, ['blusa']) => 'blouse',
            Str::contains($value, ['calca']) => 'pants',
            Str::contains($value, ['vestido']) => 'dress',
            Str::contains($value, ['saia']) => 'skirt',
            Str::contains($value, ['bermuda', 'short']) => 'shorts',
            Str::contains($value, ['jaqueta']) => 'jacket',
            Str::contains($value, ['moletom']) => 'sweatshirt',
            Str::contains($value, ['sutia']) => 'bra',
            Str::contains($value, ['calcado', 'sapato', 'tenis']) => 'shoes',
            default => 'custom',
        };
    }

    private function numberOrNull(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }
}
