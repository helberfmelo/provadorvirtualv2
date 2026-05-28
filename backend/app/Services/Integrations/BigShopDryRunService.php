<?php

namespace App\Services\Integrations;

use App\Models\IntegrationEvent;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BigShopDryRunService
{
    public function __construct(private readonly BigShopClient $client) {}

    public function run(Merchant $merchant, MerchantCompany $company, PlatformConnection $connection): array
    {
        $products = array_values(array_filter(
            $this->client->products($connection),
            fn (mixed $product): bool => is_array($product)
        ));
        $grids = array_values(array_filter(
            $this->client->productGrids($connection),
            fn (mixed $grid): bool => is_array($grid)
        ));

        $issues = [];
        $productsById = [];
        $joinedGrids = [];
        $gridsWithoutProduct = 0;
        $gridsWithoutSize = 0;
        $detectedSizes = [];

        foreach ($products as $product) {
            $productId = $this->productId($product);
            if ($productId === null) {
                $issues[] = $this->issue('error', 'product_id_missing', null, $this->productName($product), 'Produto sem id BigShop confiavel.');

                continue;
            }

            $productsById[$productId] = $product;
            $joinedGrids[$productId] = [];
        }

        foreach ($grids as $grid) {
            $productId = $this->gridProductId($grid);
            $gridId = $this->gridId($grid);
            $size = $this->sizeFromGrid($grid);

            if ($productId === null) {
                $issues[] = $this->issue('error', 'grid_product_id_missing', null, null, 'Grade sem produtoid para cruzar com produto.', $gridId);
                $gridsWithoutProduct++;

                continue;
            }

            if (! isset($productsById[$productId])) {
                $issues[] = $this->issue('error', 'grid_product_not_found', $productId, null, 'Grade aponta para produto que nao veio em products.', $gridId);
                $gridsWithoutProduct++;

                continue;
            }

            if ($size === null) {
                $issues[] = $this->issue('warning', 'grid_size_missing', $productId, $this->productName($productsById[$productId]), 'Grade sem tamanho extraivel em caracteristicas.', $gridId);
                $gridsWithoutSize++;
            } else {
                $detectedSizes[$size] = true;
            }

            $joinedGrids[$productId][] = [
                'grid_id' => $gridId,
                'sku' => $this->first($grid, ['sku', 'codigo', 'referencia', 'codexterno']),
                'size' => $size,
                'color' => $this->first($grid, ['color', 'cor', 'cornome']),
                'stock' => $this->integer($this->first($grid, ['stock_quantity', 'stock', 'estoque'])),
                'price' => $this->decimal($this->first($grid, ['price', 'preco', 'valor'])),
            ];
        }

        $sampleProducts = [];
        $productsWithGrids = 0;
        $productsWithoutGrids = 0;
        $joinedGridCount = 0;

        foreach ($productsById as $productId => $product) {
            $productGrids = $joinedGrids[$productId] ?? [];
            $joinedGridCount += count($productGrids);

            if ($productGrids === []) {
                $productsWithoutGrids++;
                $issues[] = $this->issue('warning', 'product_without_grids', $productId, $this->productName($product), 'Produto sem grades em product_grids.');
            } else {
                $productsWithGrids++;
            }

            if ($this->first($product, ['category', 'categoria', 'product_type']) === null) {
                $issues[] = $this->issue('warning', 'product_category_missing', $productId, $this->productName($product), 'Produto sem categoria para mapeamento.');
            }

            if (count($sampleProducts) < 8) {
                $sizes = array_values(array_unique(array_filter(array_column($productGrids, 'size'))));
                $sampleProducts[] = [
                    'external_product_id' => $productId,
                    'name' => $this->productName($product),
                    'sku' => $this->first($product, ['sku', 'referencia', 'codigo_referencia', 'codexterno']),
                    'brand' => $this->first($product, ['brand', 'marca']),
                    'category' => $this->first($product, ['category', 'categoria', 'product_type']),
                    'gender' => $this->first($product, ['gender', 'genero']),
                    'grid_count' => count($productGrids),
                    'sizes' => array_slice($sizes, 0, 10),
                ];
            }
        }

        $errorCount = count(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'error'));
        $warningCount = count(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'warning'));
        $summary = [
            'dry_run' => true,
            'status' => $errorCount > 0 ? 'warning' : 'ready',
            'products_read' => count($products),
            'products_valid' => count($productsById),
            'products_with_grids' => $productsWithGrids,
            'products_without_grids' => $productsWithoutGrids,
            'grids_read' => count($grids),
            'grids_joined' => $joinedGridCount,
            'grids_without_product' => $gridsWithoutProduct,
            'grids_without_size' => $gridsWithoutSize,
            'variants_detected' => $joinedGridCount,
            'sizes_detected' => count($detectedSizes),
            'errors_count' => $errorCount,
            'warnings_count' => $warningCount,
            'sample_products' => $sampleProducts,
            'issues' => array_slice($issues, 0, 50),
            'limited' => [
                'sample_products' => count($productsById) > 8,
                'issues' => count($issues) > 50,
            ],
        ];

        IntegrationEvent::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'platform_connection_id' => $connection->id,
            'platform' => 'bigshop',
            'event_type' => 'dry_run_import',
            'direction' => 'outbound',
            'status' => $summary['status'],
            'summary' => Arr::except($summary, ['sample_products', 'issues']),
            'payload' => Arr::only($summary, ['sample_products', 'issues', 'limited']),
            'occurred_at' => now(),
        ]);

        return $summary;
    }

    private function productId(array $payload): ?string
    {
        return $this->stringOrNull($this->first($payload, ['id', '_id', 'external_product_id', 'codigo', 'product_id', 'produtoid']));
    }

    private function gridProductId(array $payload): ?string
    {
        return $this->stringOrNull($this->first($payload, ['produtoid', 'produto_id', 'product_id', 'idproduto', 'external_product_id']));
    }

    private function gridId(array $payload): ?string
    {
        return $this->stringOrNull($this->first($payload, ['id', '_id', 'grade_id', 'external_variant_id', 'codigo']));
    }

    private function productName(array $payload): ?string
    {
        return $this->stringOrNull($this->first($payload, ['name', 'nome', 'title', 'titulo', 'descricao']));
    }

    private function sizeFromGrid(array $payload): ?string
    {
        $direct = $this->stringOrNull($this->first($payload, ['size_label', 'size', 'tamanho', 'grade_tamanho']));
        if ($direct !== null) {
            return $this->normalizeSize($direct);
        }

        foreach (['caracteristicas', 'characteristics', 'attributes', 'atributos'] as $key) {
            $size = $this->sizeFromCharacteristics(Arr::get($payload, $key));
            if ($size !== null) {
                return $size;
            }
        }

        return null;
    }

    private function sizeFromCharacteristics(mixed $characteristics): ?string
    {
        if (is_string($characteristics)) {
            $decoded = json_decode($characteristics, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->sizeFromCharacteristics($decoded);
            }

            return $this->sizeFromText($characteristics);
        }

        if (! is_array($characteristics)) {
            return null;
        }

        if (! array_is_list($characteristics)) {
            foreach ($characteristics as $key => $value) {
                if ($this->isSizeLabel($key)) {
                    return $this->normalizeSize($value);
                }
            }
        }

        foreach ($characteristics as $item) {
            if (is_string($item)) {
                $size = $this->sizeFromText($item);
                if ($size !== null) {
                    return $size;
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $label = $this->first($item, ['nome', 'name', 'label', 'chave', 'key', 'caracteristica']);
            if ($label !== null && $this->isSizeLabel($label)) {
                $rawSize = $this->first($item, ['valor', 'value', 'descricao', 'description', 'nome_valor']);

                return $rawSize === null ? null : $this->normalizeSize($rawSize);
            }

            foreach ($item as $key => $value) {
                if ($this->isSizeLabel($key)) {
                    return $this->normalizeSize($value);
                }
            }
        }

        return null;
    }

    private function sizeFromText(string $value): ?string
    {
        if (preg_match('/(?:tamanho|size)\s*[:=\-]\s*([[:alnum:]\/+.-]{1,24})/iu', $value, $matches)) {
            return $this->normalizeSize($matches[1]);
        }

        return null;
    }

    private function normalizeSize(mixed $value): ?string
    {
        $size = trim((string) $value);
        $size = preg_replace('/^(tamanho|size)\s*[:=\-]?\s*/iu', '', $size);
        $size = trim((string) $size);

        if ($size === '' || mb_strlen($size) > 32) {
            return null;
        }

        return Str::upper($size);
    }

    private function issue(string $severity, string $code, ?string $productId, ?string $productName, string $message, ?string $gridId = null): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'product_id' => $productId,
            'product_name' => $productName,
            'grid_id' => $gridId,
            'message' => $message,
        ];
    }

    private function first(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    private function isSizeLabel(mixed $value): bool
    {
        $label = Str::of((string) $value)->lower()->ascii()->toString();

        return str_contains($label, 'tamanho') || str_contains($label, 'size');
    }

    private function decimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', (string) $value));
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) preg_replace('/[^0-9-]/', '', (string) $value);
    }
}
