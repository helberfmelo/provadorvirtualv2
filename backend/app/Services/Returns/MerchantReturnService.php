<?php

namespace App\Services\Returns;

use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\MerchantOrder;
use App\Models\MerchantOrderItem;
use App\Models\MerchantReturn;
use App\Models\MerchantReturnItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecommendationLearningEvent;
use App\Models\RecommendationLog;
use App\Services\Recommendation\LearningSignalService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class MerchantReturnService
{
    private const REQUIRED_MAPPING_FIELDS = [
        'order_reference',
        'processed_at',
        'product_name',
        'status',
        'return_reason',
        'quantity',
    ];

    private const MAPPING_LABELS = [
        'return_reference' => 'Protocolo da devolução',
        'order_reference' => 'Pedido',
        'ordered_at' => 'Data do pedido',
        'processed_at' => 'Data da devolução/troca',
        'status' => 'Status',
        'return_reason' => 'Motivo',
        'sku' => 'SKU',
        'product_name' => 'Produto',
        'ordered_size' => 'Tamanho comprado',
        'ideal_size' => 'Tamanho ideal',
        'returned_size' => 'Tamanho devolvido',
        'exchanged_to_size' => 'Novo tamanho',
        'quantity' => 'Quantidade',
        'refund_amount' => 'Valor devolvido',
        'source_platform' => 'Plataforma',
    ];

    private const FIELD_ALIASES = [
        'return_reference' => ['return_reference', 'return_id', 'refund_id', 'protocolo', 'protocolo_devolucao'],
        'order_reference' => ['order_reference', 'order_id', 'order_number', 'pedido', 'numero_pedido'],
        'ordered_at' => ['ordered_at', 'order_date', 'data_pedido', 'purchase_date'],
        'processed_at' => ['processed_at', 'returned_at', 'return_date', 'exchange_date', 'data_devolucao', 'data_troca'],
        'status' => ['status', 'situacao', 'resultado', 'tipo'],
        'return_reason' => ['return_reason', 'reason', 'motivo', 'motivo_devolucao', 'motivo_troca'],
        'sku' => ['sku', 'variant_sku', 'referencia', 'ref'],
        'product_name' => ['product_name', 'produto', 'name', 'nome_produto'],
        'ordered_size' => ['ordered_size', 'size', 'tamanho', 'tamanho_comprado'],
        'ideal_size' => ['ideal_size', 'recommended_size', 'tamanho_ideal', 'tamanho_recomendado'],
        'returned_size' => ['returned_size', 'tamanho_devolvido', 'size_returned'],
        'exchanged_to_size' => ['exchanged_to_size', 'exchange_size', 'tamanho_troca', 'novo_tamanho'],
        'quantity' => ['quantity', 'quantidade', 'qtd'],
        'refund_amount' => ['refund_amount', 'valor_devolvido', 'refund_total', 'valor_estornado'],
        'source_platform' => ['source_platform', 'platform', 'plataforma'],
    ];

    public function __construct(private readonly LearningSignalService $learningSignals) {}

    public function overview(Merchant $merchant, ?MerchantCompany $company, array $filters): array
    {
        $query = $this->filteredQuery($merchant, $company, $filters);
        $returns = (clone $query)->with('items')->get();
        $items = $returns->flatMap(fn (MerchantReturn $return) => $return->items);

        $topProducts = $items
            ->groupBy(fn (MerchantReturnItem $item): string => $item->product_name)
            ->map(function (Collection $group, string $productName): array {
                return [
                    'product_name' => $productName,
                    'quantity' => (int) $group->sum('quantity'),
                    'refund_amount_cents' => (int) $group->sum('refund_amount_cents'),
                    'assisted_quantity' => (int) $group->where('used_virtual_try_on', true)->sum('quantity'),
                ];
            })
            ->sortByDesc('quantity')
            ->take(5)
            ->values();

        return [
            'filters' => $this->resolvedFilters($filters),
            'summary' => [
                'returns_total' => $returns->count(),
                'assisted_returns' => $returns->where('used_virtual_try_on', true)->count(),
                'unassisted_returns' => $returns->where('used_virtual_try_on', false)->count(),
                'refund_amount_cents' => (int) $returns->sum('refund_amount_cents'),
                'assisted_refund_cents' => (int) $returns->sum('assisted_refund_cents'),
                'items_total' => (int) $returns->sum('items_count'),
                'quantity_total' => (int) $returns->sum('total_quantity'),
                'exchanges_total' => $items->where('status', 'exchange')->count(),
            ],
            'status_breakdown' => $items
                ->groupBy('status')
                ->map(fn (Collection $group, string $status): array => [
                    'status' => $status,
                    'count' => $group->count(),
                ])
                ->values(),
            'reason_breakdown' => $items
                ->groupBy('return_reason')
                ->map(fn (Collection $group, string $reason): array => [
                    'reason' => $reason,
                    'count' => $group->count(),
                ])
                ->sortByDesc('count')
                ->values(),
            'source_breakdown' => $returns
                ->groupBy('source')
                ->map(fn (Collection $group, string $source): array => [
                    'source' => $source,
                    'count' => $group->count(),
                ])
                ->values(),
            'top_products' => $topProducts,
            'filter_options' => [
                'statuses' => (clone $this->baseItemQuery($merchant, $company))
                    ->select('status')
                    ->distinct()
                    ->orderBy('status')
                    ->pluck('status')
                    ->values(),
                'reasons' => (clone $this->baseItemQuery($merchant, $company))
                    ->select('return_reason')
                    ->distinct()
                    ->orderBy('return_reason')
                    ->pluck('return_reason')
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
            ->with('items')
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function template(string $format): array
    {
        $rows = [
            array_keys(self::MAPPING_LABELS),
            ['RET-2026-0001', 'PV-ORDER-2026-001', now()->subDays(2)->format('Y-m-d H:i:s'), now()->subDay()->format('Y-m-d H:i:s'), 'returned', 'ficou pequeno', 'PV-AURORA-MIDI-M', 'Vestido Midi Aurora', 'M', 'G', 'M', '', '1', '189.90', 'custom'],
            ['RET-2026-0002', 'PV-ORDER-2026-002', now()->subDay()->format('Y-m-d H:i:s'), now()->format('Y-m-d H:i:s'), 'exchange', 'ficou grande', 'PV-ESSENCIAL-CAMISETA-M', 'Camiseta Essencial Marinho', 'M', 'P', 'M', 'P', '1', '79.90', 'custom'],
        ];

        return $format === 'xlsx'
            ? [
                'content' => $this->xlsxContent($rows, 'Devolucoes'),
                'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'filename' => 'modelo-devolucoes-provador-virtual.xlsx',
            ]
            : [
                'content' => $this->csvContent($rows),
                'content_type' => 'text/csv; charset=UTF-8',
                'filename' => 'modelo-devolucoes-provador-virtual.csv',
            ];
    }

    public function import(Merchant $merchant, ?MerchantCompany $company, array $payload): array
    {
        $format = $payload['format'];
        $headers = $this->extractHeaders($format, (string) $payload['content']);
        $mapping = $this->resolvedMapping($headers, $payload['mapping'] ?? []);
        $rows = $this->parseSpreadsheet($format, (string) $payload['content']);

        $preview = collect($rows)->map(function (array $row) use ($merchant, $company, $mapping): array {
            $normalized = $this->normalizeImportRow($merchant, $company, $row, $mapping);

            return [
                'line' => $row['_line'] ?? null,
                'valid' => $normalized['errors'] === [],
                ...$normalized,
            ];
        })->values();

        $summary = [
            'rows' => $preview->count(),
            'valid' => $preview->where('valid', true)->count(),
            'invalid' => $preview->where('valid', false)->count(),
        ];
        $responseRows = $preview
            ->map(fn (array $row): array => Arr::except($row, [
                'matched_order',
                'matched_order_item',
                'matched_learning_event',
                'product_id',
                'product_variant_id',
                'return_import_key',
                'format',
            ]))
            ->values();

        $response = [
            'summary' => $summary,
            'columns' => [
                'available' => array_values($headers),
                'mapping' => $mapping,
                'labels' => self::MAPPING_LABELS,
                'required' => self::REQUIRED_MAPPING_FIELDS,
            ],
            'rows' => $responseRows,
        ];

        if (! ($payload['commit'] ?? true)) {
            return $response;
        }

        if ($summary['invalid'] > 0) {
            throw ValidationException::withMessages([
                'content' => ['Revise as linhas inválidas antes de importar as devoluções.'],
            ]);
        }

        DB::transaction(function () use ($preview, $merchant, $company): void {
            $grouped = $preview->groupBy('return_reference');

            foreach ($grouped as $returnReference => $lines) {
                $first = $lines->first();
                $return = MerchantReturn::query()->updateOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'merchant_company_id' => $company?->id,
                        'return_reference_hash' => hash('sha256', $returnReference),
                    ],
                    [
                        'source' => 'import',
                        'source_platform' => $first['source_platform'] ?: null,
                        'return_reference' => $returnReference,
                        'order_reference' => $first['order_reference'],
                        'order_reference_hash' => $first['order_reference'] ? hash('sha256', $first['order_reference']) : null,
                        'status' => $first['status'],
                        'processed_at' => $first['processed_at'],
                        'metadata' => [
                            'imported_at' => now()->toISOString(),
                            'format' => $first['format'],
                        ],
                    ]
                );

                $return->items()->delete();

                $items = $lines->map(function (array $line) use ($merchant, $company): array {
                    $eventType = $line['status'] === 'exchange' ? 'exchange' : 'return';
                    $usedVirtualTryOn = $line['matched_order_item']?->used_virtual_try_on
                        || $line['matched_learning_event'] !== null;
                    $recommendationLogId = $line['matched_order_item']?->recommendation_log_id
                        ?? $line['matched_learning_event']?->recommendation_log_id;

                    if ($recommendationLogId) {
                        $this->syncLearningEvent(
                            $merchant,
                            $company,
                            (int) $recommendationLogId,
                            $line,
                            $eventType,
                        );
                    }

                    return [
                        'merchant_order_id' => $line['matched_order']?->id,
                        'merchant_order_item_id' => $line['matched_order_item']?->id,
                        'recommendation_log_id' => $recommendationLogId,
                        'measurement_table_id' => $line['matched_order_item']?->measurement_table_id,
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $line['product_variant_id'],
                        'sku' => $line['sku'],
                        'product_name' => $line['product_name'],
                        'ordered_at' => $line['ordered_at'],
                        'returned_at' => $line['processed_at'],
                        'ordered_size' => $line['ordered_size'],
                        'ideal_size' => $line['ideal_size'],
                        'returned_size' => $line['returned_size'],
                        'exchanged_to_size' => $line['exchanged_to_size'],
                        'return_reason' => $line['return_reason'],
                        'status' => $line['status'],
                        'quantity' => $line['quantity'],
                        'refund_amount_cents' => $line['refund_amount_cents'],
                        'used_virtual_try_on' => (bool) $usedVirtualTryOn,
                        'recommendation_confidence' => $line['matched_order_item']?->recommendation_confidence
                            ?? $line['matched_learning_event']?->confidence,
                        'metadata' => [
                            'import_line' => $line['line'],
                            'return_import_key' => $line['return_import_key'],
                        ],
                    ];
                })->values();

                $return->items()->createMany($items->all());

                $return->update([
                    'items_count' => $items->count(),
                    'total_quantity' => (int) $items->sum('quantity'),
                    'refund_amount_cents' => (int) $items->sum('refund_amount_cents'),
                    'used_virtual_try_on' => $items->contains('used_virtual_try_on', true),
                    'assisted_items_count' => $items->where('used_virtual_try_on', true)->count(),
                    'assisted_refund_cents' => (int) $items->where('used_virtual_try_on', true)->sum('refund_amount_cents'),
                ]);
            }
        });

        return [
            ...$response,
            'summary' => [
                ...$summary,
                'imported_returns' => $preview->groupBy('return_reference')->count(),
            ],
        ];
    }

    private function baseQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return MerchantReturn::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id));
    }

    private function baseItemQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return MerchantReturnItem::query()
            ->whereHas('returnRecord', function (Builder $query) use ($merchant, $company): void {
                $query->where('merchant_id', $merchant->id)
                    ->when($company, fn (Builder $innerQuery) => $innerQuery->where('merchant_company_id', $company->id));
            });
    }

    private function filteredQuery(Merchant $merchant, ?MerchantCompany $company, array $filters): Builder
    {
        $resolved = $this->resolvedFilters($filters);

        return $this->baseQuery($merchant, $company)
            ->when($resolved['date_from'], fn (Builder $query, string $dateFrom) => $query->whereDate('processed_at', '>=', $dateFrom))
            ->when($resolved['date_to'], fn (Builder $query, string $dateTo) => $query->whereDate('processed_at', '<=', $dateTo))
            ->when($resolved['source'], fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($resolved['assisted'] === 'yes', fn (Builder $query) => $query->where('used_virtual_try_on', true))
            ->when($resolved['assisted'] === 'no', fn (Builder $query) => $query->where('used_virtual_try_on', false))
            ->when($resolved['status'], fn (Builder $query, string $status) => $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('status', $status)))
            ->when($resolved['reason'], fn (Builder $query, string $reason) => $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('return_reason', $reason)))
            ->when($resolved['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery->where('return_reference', 'like', '%'.$search.'%')
                        ->orWhere('order_reference', 'like', '%'.$search.'%')
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
            'reason' => trim((string) ($filters['reason'] ?? '')),
            'assisted' => $filters['assisted'] ?? 'all',
            'source' => trim((string) ($filters['source'] ?? '')),
            'search' => trim((string) ($filters['search'] ?? '')),
            'per_page' => (int) ($filters['per_page'] ?? 20),
        ];
    }

    private function extractHeaders(string $format, string $content): array
    {
        $rows = $this->parseSpreadsheet($format, $content);
        $headers = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                if (! str_starts_with($key, '_')) {
                    $headers[$key] = $key;
                }
            }
        }

        return $headers;
    }

    private function resolvedMapping(array $headers, array $provided): array
    {
        $normalizedHeaders = array_values($headers);
        $mapping = [];

        foreach (self::FIELD_ALIASES as $field => $aliases) {
            $selected = trim((string) ($provided[$field] ?? ''));
            if ($selected !== '' && in_array($selected, $normalizedHeaders, true)) {
                $mapping[$field] = $selected;

                continue;
            }

            $mapping[$field] = collect($aliases)
                ->first(fn (string $alias) => in_array($alias, $normalizedHeaders, true));
        }

        return $mapping;
    }

    private function normalizeImportRow(Merchant $merchant, ?MerchantCompany $company, array $row, array $mapping): array
    {
        $orderReference = $this->mappedValue($row, $mapping, 'order_reference');
        $returnReference = $this->mappedValue($row, $mapping, 'return_reference') ?: $orderReference.'-'.($row['_line'] ?? uniqid());
        $productName = $this->mappedValue($row, $mapping, 'product_name');
        $sku = $this->mappedValue($row, $mapping, 'sku');
        $status = $this->normalizeStatus($this->mappedValue($row, $mapping, 'status') ?: 'returned');
        $returnReason = $this->normalizeReason($this->mappedValue($row, $mapping, 'return_reason'));
        $orderedAt = $this->datetimeValue($this->mappedValue($row, $mapping, 'ordered_at'));
        $processedAt = $this->datetimeValue($this->mappedValue($row, $mapping, 'processed_at'));
        $orderedSize = $this->mappedValue($row, $mapping, 'ordered_size');
        $idealSize = $this->mappedValue($row, $mapping, 'ideal_size');
        $returnedSize = $this->mappedValue($row, $mapping, 'returned_size') ?: $orderedSize;
        $exchangedToSize = $this->mappedValue($row, $mapping, 'exchanged_to_size');
        $quantity = $this->integerValue($this->mappedValue($row, $mapping, 'quantity') ?: '1');
        $refundAmountCents = $this->moneyToCents($this->mappedValue($row, $mapping, 'refund_amount'));
        $sourcePlatform = $this->mappedValue($row, $mapping, 'source_platform') ?: 'custom';
        [$productId, $variantId] = $this->resolveProductReferences($merchant, $company, $sku, $productName);
        [$matchedOrder, $matchedOrderItem] = $this->matchOrderItem($merchant, $company, $orderReference, $sku, $productId, $orderedSize);
        $matchedLearningEvent = $this->matchLearningEvent($merchant, $company, $orderReference, $sku, $productId, $orderedSize);
        $errors = [];

        foreach (self::REQUIRED_MAPPING_FIELDS as $field) {
            if (! filled($mapping[$field] ?? null)) {
                $errors[] = 'Mapeie a coluna obrigatória "'.self::MAPPING_LABELS[$field].'" antes de importar.';
            }
        }

        if ($orderReference === '') {
            $errors[] = $this->importError($row, $mapping, 'order_reference', 'Informe o pedido.');
        }

        if ($processedAt === null) {
            $errors[] = $this->importError($row, $mapping, 'processed_at', 'Informe a data da devolução/troca em formato válido.');
        }

        if ($productName === '') {
            $errors[] = $this->importError($row, $mapping, 'product_name', 'Informe o nome do produto.');
        }

        if ($quantity < 1) {
            $errors[] = $this->importError($row, $mapping, 'quantity', 'Quantidade deve ser maior ou igual a 1.');
        }

        return [
            'errors' => $errors,
            'format' => $row['_format'] ?? 'csv',
            'return_reference' => $returnReference,
            'order_reference' => $orderReference,
            'ordered_at' => $orderedAt,
            'processed_at' => $processedAt,
            'status' => $status,
            'return_reason' => $returnReason,
            'sku' => $sku ?: null,
            'product_name' => $productName,
            'ordered_size' => $orderedSize ?: null,
            'ideal_size' => $idealSize ?: null,
            'returned_size' => $returnedSize ?: null,
            'exchanged_to_size' => $exchangedToSize ?: null,
            'quantity' => $quantity,
            'refund_amount_cents' => $refundAmountCents,
            'source_platform' => $sourcePlatform,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'matched_order' => $matchedOrder,
            'matched_order_item' => $matchedOrderItem,
            'matched_learning_event' => $matchedLearningEvent,
            'return_import_key' => sha1(implode('|', [
                $orderReference,
                $returnReference,
                $sku,
                $productName,
                $status,
                $returnReason,
                $processedAt,
                $orderedSize,
                $exchangedToSize,
            ])),
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

    private function matchOrderItem(Merchant $merchant, ?MerchantCompany $company, string $orderReference, string $sku, ?int $productId, string $orderedSize): array
    {
        if ($orderReference === '') {
            return [null, null];
        }

        $order = MerchantOrder::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id))
            ->where('order_reference_hash', hash('sha256', $orderReference))
            ->with('items')
            ->first();

        if (! $order) {
            return [null, null];
        }

        $item = $order->items->first(function (MerchantOrderItem $item) use ($sku, $productId, $orderedSize): bool {
            if ($sku !== '' && $item->sku === $sku) {
                return true;
            }

            if ($productId && (int) $item->product_id === $productId) {
                return true;
            }

            return $orderedSize !== '' && mb_strtolower((string) $item->ordered_size) === mb_strtolower($orderedSize);
        });

        return [$order, $item];
    }

    private function matchLearningEvent(Merchant $merchant, ?MerchantCompany $company, string $orderReference, string $sku, ?int $productId, string $orderedSize): ?RecommendationLearningEvent
    {
        if ($orderReference === '') {
            return null;
        }

        $hash = hash('sha256', $orderReference);

        return RecommendationLearningEvent::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id))
            ->where('event_type', 'purchase')
            ->where('payload->order_reference_hash', $hash)
            ->get()
            ->first(function (RecommendationLearningEvent $event) use ($sku, $productId, $orderedSize): bool {
                if ($productId && (int) $event->product_id === $productId) {
                    return true;
                }

                if ($orderedSize !== '' && mb_strtolower((string) $event->selected_size) === mb_strtolower($orderedSize)) {
                    return true;
                }

                return $sku !== '' && $event->product?->sku === $sku;
            });
    }

    private function syncLearningEvent(Merchant $merchant, ?MerchantCompany $company, int $recommendationLogId, array $line, string $eventType): void
    {
        $log = RecommendationLog::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id))
            ->find($recommendationLogId);

        if (! $log) {
            return;
        }

        RecommendationLearningEvent::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn (Builder $query) => $query->where('merchant_company_id', $company->id))
            ->where('event_type', $eventType)
            ->where('payload->return_import_key', $line['return_import_key'])
            ->delete();

        $event = $this->learningSignals->recordCommerceSignal($log, [
            'signal' => $eventType,
            'ordered_size' => $line['ordered_size'],
            'returned_size' => $line['returned_size'],
            'exchanged_to_size' => $line['exchanged_to_size'],
            'selected_size' => $eventType === 'exchange'
                ? ($line['exchanged_to_size'] ?: $line['ordered_size'])
                : ($line['returned_size'] ?: $line['ordered_size']),
            'return_reason' => $line['return_reason'],
            'source' => 'returns_import',
            'source_platform' => $line['source_platform'],
            'order_reference' => $line['order_reference'],
            'order_status' => $line['status'],
            'quantity' => $line['quantity'],
            'unit_price' => round($line['refund_amount_cents'] / 100, 2),
            'occurred_at' => $line['processed_at'],
        ]);

        if (! $event) {
            return;
        }

        $event->update([
            'payload' => [
                ...($event->payload ?? []),
                'return_import_key' => $line['return_import_key'],
                'ideal_size' => $line['ideal_size'],
            ],
        ]);
    }

    private function parseSpreadsheet(string $format, string $content): array
    {
        return match ($format) {
            'xlsx' => $this->parseXlsx(base64_decode($content, true) ?: ''),
            'json' => $this->parseJson($content),
            default => $this->parseCsv($content),
        };
    }

    private function parseCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', trim($content));

        if ($content === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $delimiter = $this->detectDelimiter($lines[0] ?? '');
        $headers = array_map(fn (string $header): string => $this->normalizeHeader($header), str_getcsv((string) array_shift($lines), $delimiter));
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            $row = ['_line' => $index + 2, '_columns' => [], '_format' => 'csv'];

            foreach ($headers as $position => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = trim((string) ($values[$position] ?? ''));
                $row['_columns'][$header] = $position + 1;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function parseJson(string $content): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('JSON inválido para importação de devoluções.');
        }

        $items = array_is_list($decoded) ? $decoded : ($decoded['data'] ?? $decoded['items'] ?? []);

        if (! is_array($items)) {
            throw new RuntimeException('JSON sem coleção de devoluções.');
        }

        $rows = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = ['_line' => $index + 2, '_columns' => [], '_format' => 'json'];

            foreach ($item as $key => $value) {
                $header = $this->normalizeHeader((string) $key);
                $normalized[$header] = is_scalar($value) || $value === null ? trim((string) $value) : json_encode($value);
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    private function parseXlsx(string $content): array
    {
        if ($content === '' || ! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Arquivo XLSX inválido ou extensão ZIP indisponível.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'pv-returns-xlsx-');
        file_put_contents($temp, $content);
        $zip = new ZipArchive;

        if ($zip->open($temp) !== true) {
            @unlink($temp);
            throw new RuntimeException('Não foi possível abrir o XLSX.');
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($temp);

        if (! $sheet) {
            throw new RuntimeException('A primeira planilha do XLSX não foi encontrada.');
        }

        return $this->parseXlsxSheet($sheet, $sharedStrings);
    }

    private function parseXlsxSheet(string $xml, array $sharedStrings): array
    {
        $sheet = simplexml_load_string($xml);

        if (! $sheet instanceof SimpleXMLElement) {
            throw new RuntimeException('Planilha XLSX inválida.');
        }

        $rows = [];
        $headers = [];
        $namespaces = $sheet->getNamespaces(true);
        $namespace = $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sheetRows = $sheet->children($namespace)->sheetData->children($namespace)->row;
        $rowNumber = 0;

        foreach ($sheetRows as $row) {
            $cells = [];

            foreach ($row->children($namespace)->c as $cell) {
                $reference = (string) $cell->attributes()['r'];
                $columnIndex = $this->columnIndex((string) preg_replace('/\d+/', '', $reference));
                $cells[$columnIndex] = trim($this->xlsxCellValue($cell, $sharedStrings));
            }

            if ($rowNumber === 0) {
                foreach ($cells as $columnIndex => $header) {
                    $headers[$columnIndex] = $this->normalizeHeader($header);
                }

                $rowNumber++;

                continue;
            }

            if (collect($cells)->filter()->isEmpty()) {
                $rowNumber++;

                continue;
            }

            $normalized = ['_line' => $rowNumber + 1, '_columns' => [], '_format' => 'xlsx'];

            foreach ($headers as $columnIndex => $header) {
                if ($header === '') {
                    continue;
                }

                $normalized[$header] = $cells[$columnIndex] ?? '';
                $normalized['_columns'][$header] = $columnIndex;
            }

            $rows[] = $normalized;
            $rowNumber++;
        }

        return $rows;
    }

    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! $xml) {
            return [];
        }

        $sheet = simplexml_load_string($xml);

        if (! $sheet instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];
        $namespaces = $sheet->getNamespaces(true);
        $sheet->registerXPathNamespace('m', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($sheet->xpath('//m:si') ?: [] as $item) {
            $item->registerXPathNamespace('m', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $strings[] = trim(implode('', array_map('strval', $item->xpath('.//m:t') ?: [])));
        }

        return $strings;
    }

    private function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell->attributes()['t'];

        if ($type === 's') {
            return (string) ($sharedStrings[(int) $cell->v] ?? '');
        }

        if ($type === 'inlineStr') {
            $namespaces = $cell->getNamespaces(true);
            $namespace = $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $inline = $cell->children($namespace)->is;

            if ($inline) {
                return trim(implode('', array_map('strval', $inline->xpath('.//*[local-name()="t"]') ?: [])));
            }

            return '';
        }

        return (string) ($cell->v ?? '');
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split(Str::upper($letters)) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return $index;
    }

    private function csvContent(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (mixed $value): string => (string) $value, $row), ';');
        }

        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }

    private function xlsxContent(array $rows, string $sheetName): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extensão ZIP indisponível para gerar XLSX.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'pv-returns-export-');
        $zip = new ZipArchive;
        $zip->open($temp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRels());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheet($rows));
        $zip->close();
        $content = file_get_contents($temp) ?: '';
        @unlink($temp);

        return $content;
    }

    private function xlsxSheet(array $rows): string
    {
        $xmlRows = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = [];

            foreach (array_values($row) as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $cells[] = '<c r="'.$cell.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
            }

            $xmlRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData></worksheet>';
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function xlsxRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function xlsxWorkbook(string $sheetName): string
    {
        $escaped = htmlspecialchars($sheetName, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$escaped.'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function detectDelimiter(string $headerLine): string
    {
        return substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
    }

    private function normalizeHeader(string $header): string
    {
        return trim(Str::of($header)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString());
    }

    private function mappedValue(array $row, array $mapping, string $field): string
    {
        $column = $mapping[$field] ?? null;

        if (! $column) {
            return '';
        }

        return trim((string) ($row[$column] ?? ''));
    }

    private function importError(array $row, array $mapping, string $field, string $message): string
    {
        $columnKey = $mapping[$field] ?? $field;
        $column = $row['_columns'][$columnKey] ?? null;
        $line = $row['_line'] ?? '?';

        return $column
            ? "Linha {$line}, coluna {$column}: {$message}"
            : "Linha {$line}: {$message}";
    }

    private function normalizeStatus(string $value): string
    {
        $normalized = Str::of($value)->lower()->ascii()->trim()->toString();

        return match ($normalized) {
            'troca', 'exchange', 'exchanged' => 'exchange',
            'pendente', 'pending', 'awaiting' => 'pending',
            'rejeitada', 'rejected', 'denied' => 'rejected',
            default => 'returned',
        };
    }

    private function normalizeReason(string $value): string
    {
        $normalized = Str::of($value)->lower()->ascii()->trim()->toString();

        return match (true) {
            $normalized === '',
            $normalized === 'desconhecido',
            $normalized === 'unknown' => 'unknown',
            str_contains($normalized, 'pequen') => 'size_too_small',
            str_contains($normalized, 'grande') => 'size_too_large',
            str_contains($normalized, 'caimento'),
            str_contains($normalized, 'modelagem'),
            str_contains($normalized, 'fit') => 'fit_issue',
            str_contains($normalized, 'defeit') => 'defect',
            str_contains($normalized, 'arrepend'),
            str_contains($normalized, 'mudanca de ideia'),
            str_contains($normalized, 'changed_mind') => 'changed_mind',
            str_contains($normalized, 'troca') => 'other',
            default => 'other',
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
}
