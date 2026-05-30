<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportMerchantOrdersRequest;
use App\Http\Requests\ListMerchantOrdersRequest;
use App\Services\Orders\MerchantOrderService;

class MerchantOrderController extends Controller
{
    use ResolvesMerchant;

    public function __construct(private readonly MerchantOrderService $orders) {}

    public function overview(ListMerchantOrdersRequest $request): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        return [
            'data' => $this->orders->overview($merchant, $company, $request->validated()),
        ];
    }

    public function index(ListMerchantOrdersRequest $request): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $orders = $this->orders->paginate($merchant, $company, $request->validated());

        return [
            'data' => collect($orders->items())->map(fn ($order): array => [
                'id' => $order->id,
                'order_reference' => $order->order_reference,
                'source' => $order->source,
                'source_platform' => $order->source_platform,
                'status' => $order->status,
                'ordered_at' => $order->ordered_at?->toISOString(),
                'items_count' => $order->items_count,
                'total_quantity' => $order->total_quantity,
                'total_amount_cents' => $order->total_amount_cents,
                'currency' => $order->currency,
                'used_virtual_try_on' => (bool) $order->used_virtual_try_on,
                'assisted_items_count' => $order->assisted_items_count,
                'assisted_revenue_cents' => $order->assisted_revenue_cents,
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'ordered_size' => $item->ordered_size,
                    'recommended_size' => $item->recommended_size,
                    'recommendation_confidence' => $item->recommendation_confidence,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'line_total_cents' => $item->line_total_cents,
                    'used_virtual_try_on' => (bool) $item->used_virtual_try_on,
                ])->values(),
            ])->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ];
    }

    public function template()
    {
        return response($this->orders->templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo-pedidos-provador-virtual.csv"',
        ]);
    }

    public function import(ImportMerchantOrdersRequest $request): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        return $this->orders->importCsv(
            $merchant,
            $company,
            (string) $request->validated('content'),
            $request->boolean('commit', true),
        );
    }
}
