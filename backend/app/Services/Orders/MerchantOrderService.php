<?php

namespace App\Services\Orders;

use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\MerchantOrder;
use App\Models\MerchantOrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecommendationLearningEvent;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantOrderService
{
    public function overview(Merchant $merchant, ?MerchantCompany $company, array $filters): array
    {
        $query = $this->filteredQuery($merchant, $company, $filters);
        $orders = (clone $query)->with('items')->get();
        $topProducts = $orders
            ->flatMap(fn (MerchantOrder $order) => $order->items)
            ->groupBy(fn (MerchantOrderItem $item): string => $item->product_name)
            ->map(function (Collection $items, string $productName): array {
                $quantity = (int) $items->sum('quantity');
                $revenue = (int) $items->sum('line_total_cents');
                $assistedQuantity = (int) $items->where('used_virtual_try_on', true)->sum('quantity');

                return [
                    'product_name' => $productName,
                    'quantity' => $quantity,
                    'revenue_cents' => $revenue,
                    'assisted_quantity' => $assistedQuantity,
                ];
            })
            ->sortByDesc('quantity')
            ->take(5)
            ->values();

        return [
            'filters' => $this->resolvedFilters($filters),
            'summary' => [
                'orders_total' => $orders->count(),
                'assisted_orders' => $orders->where('used_virtual_try_on', true)->count(),
                'unassisted_orders' => $orders->where('used_virtual_try_on', false)->count(),
                'revenue_cents' => (int) $orders->sum('total_amount_cents'),
                'assisted_revenue_cents' => (int) $orders->sum('assisted_revenue_cents'),
                'items_total' => (int) $orders->sum('items_count'),
                'quantity_total' => (int) $orders->sum('total_quantity'),
            ],
            'status_breakdown' => $orders
                ->groupBy('status')
                ->map(fn (Collection $group, string $status): array => [
                    'status' => $status,
                    'count' => $group->count(),
                ])
                ->values(),
            'source_breakdown' => $orders
                ->groupBy('source')
                ->map(fn (Collection $group, string $source): array => [
                    'source' => $source,
                    'count' => $group->count(),
                ])
                ->values(),
            'top_products' => $topProducts,
            'filter_options' => [
                'statuses' => (clone $this->baseQuery($merchant, $company))
                    ->select('status')
                    ->distinct()
                    ->orderBy('status')
                    ->pluck('status')
                    ->values(),
                'sources' => (clone $this->baseQuery($merchant, $company))
                    ->select('source')
                    ->distinct()
                    ->orderBy('source')
                    ->pluck('source')
                    ->values(),
            ],
        ];
    }

    public function paginate(Merchant $merchant, ?MerchantCompany $company, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        return $this->filteredQuery($merchant, $company, $filters)
            ->with(['items'])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function importCsv(Merchant $merchant, ?MerchantCompany $company, string $content, bool $commit = true): array
    {
        $rows = $this->parseCsv($content);
        $preview = collect($rows)->map(function (array $row, int $index) use ($merchant, $company): array {
            $normalized = $this->normalizeImportRow($merchant, $company, $row);

            return [
                'line' => $index + 2,
                'valid' => $normalized['errors'] === [],
                ...$normalized,
            ];
        })->values();

        $summary = [
            'rows' => $preview->count(),
            'valid' => $preview->where('valid', true)->count(),
            'invalid' => $preview->where('valid', false)->count(),
        ];

        if (! $commit) {
            return [
                'summary' => $summary,
                'rows' => $preview,
            ];
        }

        if ($summary['invalid'] > 0) {
            throw ValidationException::withMessages([
                'content' => ['Revise as linhas inválidas antes de importar os pedidos.'],
            ]);
        }

        $grouped = $preview->groupBy('order_reference');
        $hashes = $grouped->keys()->map(fn (string $reference): string => hash('sha256', $reference))->values();
        $learningEvents = $this->matchingLearningEvents($merchant, $company, $hashes);

        DB::transaction(function () use ($grouped, $merchant, $company, $learningEvents): void {
            foreach ($grouped as $orderReference => $lines) {
                $first = $lines->first();
                $hash = hash('sha256', $orderReference);

                $order = MerchantOrder::query()->updateOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'merchant_company_id' => $company?->id,
                        'order_reference_hash' => $hash,
                    ],
                    [
                        'source' => 'csv',
                        'source_platform' => $first['source_platform'] ?: null,
                        'order_reference' => $orderReference,
                        'status' => $first['status'],
                        'ordered_at' => $first['ordered_at'],
                        'currency' => $first['currency'],
                        'metadata' => [
                            'imported_at' => now()->toISOString(),
                        ],
                    ]
                );

                $order->items()->delete();

                $items = $lines->map(function (array $line) use ($learningEvents, $hash): array {
                    $match = $this->matchLearningEvent($learningEvents, $hash, $line['sku'], $line['product_id'], $line['ordered_size']);
                    $usedVirtualTryOn = $match !== null;

                    return [
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $line['product_variant_id'],
                        'recommendation_log_id' => $match?->recommendation_log_id,
                        'measurement_table_id' => $match?->product?->measurement_table_id,
                        'sku' => $line['sku'],
                        'product_name' => $line['product_name'],
                        'ordered_size' => $line['ordered_size'],
                        'recommended_size' => $match?->recommended_size,
                        'recommendation_confidence' => $match?->confidence,
                        'quantity' => $line['quantity'],
                        'unit_price_cents' => $line['unit_price_cents'],
                        'line_total_cents' => $line['line_total_cents'],
                        'used_virtual_try_on' => $usedVirtualTryOn,
                        'metadata' => [
                            'import_line' => $line['line'],
                            'matched_order_reference_hash' => $usedVirtualTryOn ? $hash : null,
                        ],
                    ];
                })->values();

                $order->items()->createMany($items->all());

                $order->update([
                    'items_count' => $items->count(),
                    'total_quantity' => (int) $items->sum('quantity'),
                    'total_amount_cents' => (int) ($first['total_amount_cents'] > 0 ? $first['total_amount_cents'] : $items->sum('line_total_cents')),
                    'used_virtual_try_on' => $items->contains('used_virtual_try_on', true),
                    'assisted_items_count' => $items->where('used_virtual_try_on', true)->count(),
                    'assisted_revenue_cents' => (int) $items->where('used_virtual_try_on', true)->sum('line_total_cents'),
                ]);
            }
        });

        return [
            'summary' => [
                ...$summary,
                'imported_orders' => $grouped->count(),
            ],
            'rows' => $preview,
        ];
    }

    public function templateCsv(): string
    {
        return $this->toCsv([
            ['order_reference', 'ordered_at', 'status', 'currency', 'total_amount', 'sku', 'product_name', 'ordered_size', 'quantity', 'unit_price', 'source_platform'],
            ['PV-2026-0001', now()->subDay()->format('Y-m-d H:i:s'), 'paid', 'BRL', '379.80', 'PV-AURORA-MIDI-M', 'Vestido Midi Aurora', 'M', '1', '189.90', 'custom'],
            ['PV-2026-0001', now()->subDay()->format('Y-m-d H:i:s'), 'paid', 'BRL', '379.80', 'PV-SOLAR-BLUSA-P', 'Blusa Canelada Solar', 'P', '1', '189.90', 'custom'],
        ]);
    }

    private function baseQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return MerchantOrder::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id));
    }

    private function filteredQuery(Merchant $merchant, ?MerchantCompany $company, array $filters): Builder
    {
        $resolved = $this->resolvedFilters($filters);

        return $this->baseQuery($merchant, $company)
            ->when($resolved['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('ordered_at', '>=', $dateFrom))
            ->when($resolved['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('ordered_at', '<=', $dateTo))
            ->when($resolved['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($resolved['source'], fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($resolved['assisted'] === 'yes', fn (Builder $query) => $query->where('used_virtual_try_on', true))
            ->when($resolved['assisted'] === 'no', fn (Builder $query) => $query->where('used_virtual_try_on', false))
            ->when($resolved['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery->where('order_reference', 'like', '%'.$search.'%')
                        ->orWhereHas('items', fn (Builder $itemQuery) => $itemQuery
                            ->where('product_name', 'like', '%'.$search.'%')
                            ->orWhere('sku', 'like', '%'.$search.'%'));
                });
            });
    }

    private function resolvedFilters(array $filters): array
    {
        $period = $filters['period'] ?? '30d';
        $today = Carbon::today();
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($period !== 'custom') {
            [$dateFrom, $dateTo] = match ($period) {
                'today' => [$today->toDateString(), $today->toDateString()],
                '7d' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
                '90d' => [$today->copy()->subDays(89)->toDateString(), $today->toDateString()],
                default => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
            };
        }

        return [
            'period' => $period,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => trim((string) ($filters['status'] ?? '')),
            'assisted' => $filters['assisted'] ?? 'all',
            'source' => trim((string) ($filters['source'] ?? '')),
            'search' => trim((string) ($filters['search'] ?? '')),
            'per_page' => (int) ($filters['per_page'] ?? 20),
        ];
    }

    private function parseCsv(string $content): array
    {
        $content = trim(str_replace("\r\n", "\n", $content));
        $lines = array_values(array_filter(explode("\n", $content), fn (string $line): bool => trim($line) !== ''));

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'content' => ['Envie um CSV com cabeçalho e pelo menos uma linha de pedido.'],
            ]);
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $headers = array_map([$this, 'cleanCell'], str_getcsv(array_shift($lines), $delimiter));

        return collect($lines)->map(function (string $line) use ($headers, $delimiter): array {
            $columns = str_getcsv($line, $delimiter);
            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = $this->cleanCell($columns[$index] ?? '');
            }

            return $row;
        })->all();
    }

    private function normalizeImportRow(Merchant $merchant, ?MerchantCompany $company, array $row): array
    {
        $orderReference = $this->value($row, ['order_reference', 'pedido', 'order_id', 'order_number']);
        $productName = $this->value($row, ['product_name', 'produto', 'name']);
        $sku = $this->value($row, ['sku', 'variant_sku']);
        $orderedSize = $this->value($row, ['ordered_size', 'size', 'tamanho']);
        $status = $this->normalizeStatus($this->value($row, ['status', 'situacao', 'estado']) ?: 'paid');
        $currency = strtoupper($this->value($row, ['currency', 'moeda']) ?: 'BRL');
        $quantity = $this->integerValue($this->value($row, ['quantity', 'quantidade']) ?: '1');
        $unitPriceCents = $this->moneyToCents($this->value($row, ['unit_price', 'preco_unitario', 'valor_unitario']));
        $totalAmountCents = $this->moneyToCents($this->value($row, ['total_amount', 'valor_total', 'total']));
        $orderedAt = $this->datetimeValue($this->value($row, ['ordered_at', 'date', 'data']));
        $sourcePlatform = $this->value($row, ['source_platform', 'platform', 'plataforma']) ?: 'custom';
        [$productId, $variantId] = $this->resolveProductReferences($merchant, $company, $sku, $productName);
        $errors = [];

        if ($orderReference === '') {
            $errors[] = 'Informe o identificador do pedido.';
        }

        if ($orderedAt === null) {
            $errors[] = 'Informe a data do pedido em formato válido.';
        }

        if ($productName === '') {
            $errors[] = 'Informe o nome do produto.';
        }

        if ($quantity < 1) {
            $errors[] = 'Quantidade deve ser maior ou igual a 1.';
        }

        return [
            'errors' => $errors,
            'order_reference' => $orderReference,
            'ordered_at' => $orderedAt,
            'status' => $status,
            'currency' => $currency,
            'total_amount_cents' => $totalAmountCents,
            'sku' => $sku ?: null,
            'product_name' => $productName,
            'ordered_size' => $orderedSize ?: null,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
            'line_total_cents' => $unitPriceCents * max(1, $quantity),
            'source_platform' => $sourcePlatform,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
        ];
    }

    private function resolveProductReferences(Merchant $merchant, ?MerchantCompany $company, string $sku, string $productName): array
    {
        if ($sku !== '') {
            $variant = ProductVariant::query()
                ->where('merchant_id', $merchant->id)
                ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id))
                ->where('sku', $sku)
                ->first();

            if ($variant) {
                return [$variant->product_id, $variant->id];
            }

            $product = Product::query()
                ->where('merchant_id', $merchant->id)
                ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id))
                ->where('sku', $sku)
                ->first();

            if ($product) {
                return [$product->id, null];
            }
        }

        $product = Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id))
            ->where('name', $productName)
            ->first();

        return [$product?->id, null];
    }

    private function matchingLearningEvents(Merchant $merchant, ?MerchantCompany $company, Collection $hashes): Collection
    {
        if ($hashes->isEmpty()) {
            return collect();
        }

        return RecommendationLearningEvent::query()
            ->with(['product'])
            ->where('merchant_id', $merchant->id)
            ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id))
            ->whereIn('event_type', ['purchase', 'exchange', 'return'])
            ->where(function (Builder $query) use ($hashes): void {
                foreach ($hashes as $hash) {
                    $query->orWhere('payload->order_reference_hash', $hash);
                }
            })
            ->get();
    }

    private function matchLearningEvent(Collection $events, string $hash, ?string $sku, ?int $productId, ?string $orderedSize): ?RecommendationLearningEvent
    {
        return $events
            ->first(function (RecommendationLearningEvent $event) use ($hash, $sku, $productId, $orderedSize): bool {
                if ((string) data_get($event->payload, 'order_reference_hash') !== $hash) {
                    return false;
                }

                if ($productId && (int) $event->product_id === $productId) {
                    return true;
                }

                if ($orderedSize && $event->selected_size && mb_strtolower($event->selected_size) === mb_strtolower($orderedSize)) {
                    return true;
                }

                return $sku !== null && $event->product?->sku === $sku;
            });
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = mb_strtolower(trim($status));

        return match ($normalized) {
            'paid', 'pago', 'approved', 'completed', 'concluido' => 'paid',
            'pending', 'pendente', 'processing', 'em processamento' => 'pending',
            'cancelled', 'cancelado', 'canceled' => 'cancelled',
            'refunded', 'refund', 'estornado' => 'refunded',
            default => 'paid',
        };
    }

    private function moneyToCents(?string $value): int
    {
        if ($value === null || trim($value) === '') {
            return 0;
        }

        $normalized = str_replace(['R$', ' '], '', trim($value));

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (int) round(((float) $normalized) * 100);
    }

    private function integerValue(?string $value): int
    {
        return max(0, (int) preg_replace('/\D+/', '', (string) $value));
    }

    private function datetimeValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toISOString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanCell(string $value): string
    {
        return trim(str_replace("\xEF\xBB\xBF", '', $value));
    }

    private function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }
}
