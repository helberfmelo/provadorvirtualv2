<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportMerchantReturnsRequest;
use App\Http\Requests\ListMerchantReturnsRequest;
use App\Services\Returns\MerchantReturnService;

class MerchantReturnController extends Controller
{
    use ResolvesMerchant;

    public function __construct(private readonly MerchantReturnService $returns) {}

    public function overview(ListMerchantReturnsRequest $request): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        return [
            'data' => $this->returns->overview($merchant, $company, $request->validated()),
        ];
    }

    public function index(ListMerchantReturnsRequest $request): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $returns = $this->returns->paginate($merchant, $company, $request->validated());

        return [
            'data' => collect($returns->items())->map(fn ($return): array => [
                'id' => $return->id,
                'return_reference' => $return->return_reference,
                'order_reference' => $return->order_reference,
                'source' => $return->source,
                'source_platform' => $return->source_platform,
                'status' => $return->status,
                'processed_at' => $return->processed_at?->toISOString(),
                'items_count' => $return->items_count,
                'total_quantity' => $return->total_quantity,
                'refund_amount_cents' => $return->refund_amount_cents,
                'used_virtual_try_on' => (bool) $return->used_virtual_try_on,
                'assisted_items_count' => $return->assisted_items_count,
                'assisted_refund_cents' => $return->assisted_refund_cents,
                'items' => $return->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'ordered_at' => $item->ordered_at?->toISOString(),
                    'returned_at' => $item->returned_at?->toISOString(),
                    'ordered_size' => $item->ordered_size,
                    'ideal_size' => $item->ideal_size,
                    'returned_size' => $item->returned_size,
                    'exchanged_to_size' => $item->exchanged_to_size,
                    'return_reason' => $item->return_reason,
                    'status' => $item->status,
                    'quantity' => $item->quantity,
                    'refund_amount_cents' => $item->refund_amount_cents,
                    'used_virtual_try_on' => (bool) $item->used_virtual_try_on,
                    'recommendation_confidence' => $item->recommendation_confidence,
                ])->values(),
            ])->values(),
            'meta' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
                'from' => $returns->firstItem(),
                'to' => $returns->lastItem(),
            ],
        ];
    }

    public function template()
    {
        $format = request()->string('format')->lower()->toString() === 'xlsx' ? 'xlsx' : 'csv';
        $template = $this->returns->template($format);

        return response($template['content'], 200, [
            'Content-Type' => $template['content_type'],
            'Content-Disposition' => 'attachment; filename="'.$template['filename'].'"',
        ]);
    }

    public function import(ImportMerchantReturnsRequest $request): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        return $this->returns->import(
            $merchant,
            $company,
            $request->validated(),
        );
    }
}
