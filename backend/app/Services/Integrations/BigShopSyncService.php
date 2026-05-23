<?php

namespace App\Services\Integrations;

use App\Models\IntegrationEvent;
use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BigShopSyncService
{
    public function __construct(private readonly BigShopClient $client) {}

    public function probe(Merchant $merchant, PlatformConnection $connection): array
    {
        $result = $this->client->probe($connection);

        $connection->update([
            'status' => $result['ok'] ? 'connected' : 'error',
            'last_error' => $result['ok'] ? null : 'HTTP '.$result['status'],
        ]);

        $this->event($merchant, $connection, 'probe', $result['ok'] ? 'success' : 'failed', [
            'http_status' => $result['status'],
            'endpoints_count' => count($result['endpoints']),
        ]);

        return [
            'status' => $connection->fresh()->status,
            'http_status' => $result['status'],
            'endpoints_count' => count($result['endpoints']),
        ];
    }

    public function syncProducts(Merchant $merchant, MerchantCompany $company, PlatformConnection $connection): array
    {
        $products = $this->client->products($connection);
        $summary = DB::transaction(function () use ($merchant, $company, $products): array {
            $summary = [
                'products_synced' => 0,
                'variants_synced' => 0,
                'measurement_tables_synced' => 0,
                'products_without_measurement_table' => 0,
                'products_without_variants' => 0,
            ];

            foreach ($products as $payload) {
                if (! is_array($payload)) {
                    continue;
                }

                $table = $this->syncMeasurementTable($merchant, $company, $payload);
                if ($table) {
                    $summary['measurement_tables_synced']++;
                } else {
                    $summary['products_without_measurement_table']++;
                }

                $product = $this->syncProduct($merchant, $company, $payload, $table);
                $summary['products_synced']++;

                $variants = $this->variants($payload);
                if ($variants === []) {
                    $summary['products_without_variants']++;
                }

                foreach ($variants as $variantPayload) {
                    $this->syncVariant($product, $company, $variantPayload);
                    $summary['variants_synced']++;
                }
            }

            return $summary;
        });

        $connection->update([
            'status' => 'connected',
            'last_sync_at' => now(),
            'last_error' => null,
        ]);

        $this->event($merchant, $connection, 'sync_products', 'success', $summary);

        return $summary;
    }

    private function syncProduct(Merchant $merchant, MerchantCompany $company, array $payload, ?MeasurementTable $table): Product
    {
        $externalId = (string) $this->first($payload, ['id', 'external_product_id', 'codigo', 'product_id']);
        $sku = (string) $this->first($payload, ['sku', 'referencia', 'codigo_referencia']);
        $name = (string) ($this->first($payload, ['name', 'nome', 'title', 'titulo']) ?: 'Produto BigShop '.$externalId);

        $product = Product::query()
            ->where('merchant_id', $merchant->id)
            ->where(function ($query) use ($externalId, $sku): void {
                $query->when($externalId !== '', fn ($subQuery) => $subQuery->orWhere('external_product_id', $externalId))
                    ->when($sku !== '', fn ($subQuery) => $subQuery->orWhere('sku', $sku));
            })
            ->first();

        if (! $product) {
            $product = new Product([
                'merchant_id' => $merchant->id,
                'slug' => Str::slug($name) ?: Str::random(10),
            ]);
        }

        $product->fill([
            'merchant_company_id' => $company->id,
            'measurement_table_id' => $table?->id,
            'external_product_id' => $externalId ?: $product->external_product_id,
            'sku' => $sku ?: $product->sku,
            'name' => $name,
            'description' => $this->first($payload, ['description', 'descricao', 'descricao1', 'descricao2']),
            'category' => $this->first($payload, ['category', 'categoria', 'product_type']),
            'gender' => $this->normalizeGender($this->first($payload, ['gender', 'genero'])),
            'fit_profile' => $product->fit_profile ?: 'regular',
            'status' => 'active',
            'image_url' => $this->first($payload, ['image_url', 'image', 'imagem', 'foto']),
            'metadata' => array_merge($product->metadata ?? [], [
                'bigshop_last_sync_at' => now()->toISOString(),
            ]),
        ]);
        $product->save();

        return $product;
    }

    private function syncVariant(Product $product, MerchantCompany $company, array $payload): void
    {
        $size = (string) ($this->first($payload, ['size_label', 'size', 'tamanho', 'nome']) ?: 'UN');
        $externalId = (string) $this->first($payload, ['id', 'external_variant_id', 'grade_id']);

        $product->variants()->updateOrCreate(
            ['external_variant_id' => $externalId ?: null, 'size_label' => $size],
            [
                'merchant_id' => $product->merchant_id,
                'merchant_company_id' => $company->id,
                'sku' => $this->first($payload, ['sku', 'codigo', 'referencia']),
                'color' => $this->first($payload, ['color', 'cor', 'cornome']),
                'price' => $this->decimal($this->first($payload, ['price', 'preco', 'valor'])),
                'stock_quantity' => $this->integer($this->first($payload, ['stock_quantity', 'stock', 'estoque'])),
                'is_active' => true,
            ]
        );
    }

    private function syncMeasurementTable(Merchant $merchant, MerchantCompany $company, array $payload): ?MeasurementTable
    {
        $tablePayload = $this->first($payload, ['measurement_table', 'tabela_de_medidas', 'medidas']);

        if (! is_array($tablePayload)) {
            return null;
        }

        $rows = $tablePayload['rows'] ?? $tablePayload['linhas'] ?? $tablePayload['sizes'] ?? $tablePayload;
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $productName = (string) ($this->first($payload, ['name', 'nome', 'title']) ?: 'BigShop');
        $tableName = (string) ($tablePayload['name'] ?? $tablePayload['nome'] ?? 'Tabela BigShop - '.$productName);
        $table = MeasurementTable::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'name' => $tableName,
            ],
            [
                'merchant_company_id' => $company->id,
                'product_type' => (string) ($this->first($payload, ['product_type', 'category', 'categoria']) ?: 'clothing'),
                'gender' => $this->normalizeGender($this->first($payload, ['gender', 'genero'])),
                'fit_profile' => 'regular',
                'unit' => 'cm',
                'status' => 'active',
                'source' => 'bigshop',
            ]
        );

        $table->rows()->delete();

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $table->rows()->create([
                'size_label' => (string) ($this->first($row, ['size_label', 'size', 'tamanho', 'nome']) ?: 'Tam '.$index),
                'sort_order' => $index,
                'bust_min' => $this->decimal($this->first($row, ['bust_min', 'busto_min'])),
                'bust_max' => $this->decimal($this->first($row, ['bust_max', 'busto_max'])),
                'waist_min' => $this->decimal($this->first($row, ['waist_min', 'cintura_min'])),
                'waist_max' => $this->decimal($this->first($row, ['waist_max', 'cintura_max'])),
                'hip_min' => $this->decimal($this->first($row, ['hip_min', 'quadril_min'])),
                'hip_max' => $this->decimal($this->first($row, ['hip_max', 'quadril_max'])),
                'height_min' => $this->decimal($this->first($row, ['height_min', 'altura_min'])),
                'height_max' => $this->decimal($this->first($row, ['height_max', 'altura_max'])),
                'weight_min' => $this->decimal($this->first($row, ['weight_min', 'peso_min'])),
                'weight_max' => $this->decimal($this->first($row, ['weight_max', 'peso_max'])),
            ]);
        }

        return $table;
    }

    private function variants(array $payload): array
    {
        foreach (['variants', 'variations', 'grades', 'grade', 'productSizes'] as $key) {
            $value = Arr::get($payload, $key);
            if (is_array($value)) {
                return array_is_list($value) ? $value : array_values($value);
            }
        }

        return [];
    }

    private function event(Merchant $merchant, PlatformConnection $connection, string $type, string $status, array $summary): void
    {
        IntegrationEvent::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $connection->merchant_company_id,
            'platform_connection_id' => $connection->id,
            'platform' => 'bigshop',
            'event_type' => $type,
            'direction' => 'outbound',
            'status' => $status,
            'summary' => $summary,
            'occurred_at' => now(),
        ]);
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

    private function normalizeGender(mixed $value): ?string
    {
        $gender = Str::of((string) $value)->lower()->ascii()->toString();

        return match ($gender) {
            'f', 'fem', 'feminino', 'female' => 'female',
            'm', 'masc', 'masculino', 'male' => 'male',
            'infantil', 'kids', 'kid' => 'kids',
            'unissex', 'unisex' => 'unisex',
            default => null,
        };
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
