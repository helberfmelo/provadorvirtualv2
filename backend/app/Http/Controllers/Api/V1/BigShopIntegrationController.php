<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Models\IntegrationEvent;
use App\Models\PlatformConnection;
use App\Services\Integrations\BigShopSyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BigShopIntegrationController extends Controller
{
    use ResolvesMerchant;

    public function __construct(private readonly BigShopSyncService $bigShop) {}

    public function probe(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $connection = $this->connection($merchant->id, $company?->id);

        try {
            return response()->json([
                'data' => $this->bigShop->probe($merchant, $connection),
            ]);
        } catch (RuntimeException $exception) {
            $connection->update([
                'status' => 'error',
                'last_error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'bigshop' => $exception->getMessage(),
            ]);
        }
    }

    public function sync(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $connection = $this->connection($merchant->id, $company?->id);
        $company = $connection->merchant_company_id
            ? $this->merchantCompany($merchant, $connection->merchant_company_id)
            : $company;

        try {
            return response()->json([
                'data' => $this->bigShop->syncProducts($merchant, $company, $connection),
            ]);
        } catch (RuntimeException $exception) {
            $connection->update([
                'status' => 'error',
                'last_error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'bigshop' => $exception->getMessage(),
            ]);
        }
    }

    public function activations(Request $request): array
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        $events = IntegrationEvent::query()
            ->with('company:id,name,access_code,external_store_id,domain')
            ->where('merchant_id', $merchant->id)
            ->where('platform', 'bigshop')
            ->where('event_type', 'one_click_activation')
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'data' => $events->map(fn (IntegrationEvent $event): array => [
                'id' => $event->id,
                'status' => $event->status,
                'store_id' => data_get($event->summary, 'store_id'),
                'store_domain' => data_get($event->summary, 'store_domain'),
                'has_access_token' => (bool) data_get($event->summary, 'has_access_token'),
                'widget_public_key' => data_get($event->summary, 'widget_public_key'),
                'contract_version' => data_get($event->summary, 'contract_version'),
                'company' => $event->company ? [
                    'id' => $event->company->id,
                    'name' => $event->company->name,
                    'access_code' => $event->company->access_code,
                    'external_store_id' => $event->company->external_store_id,
                    'domain' => $event->company->domain,
                ] : null,
                'occurred_at' => $event->occurred_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    private function connection(int $merchantId, ?int $companyId): PlatformConnection
    {
        $connection = PlatformConnection::query()
            ->where('merchant_id', $merchantId)
            ->when($companyId, function ($query) use ($companyId): void {
                $query->where(function ($innerQuery) use ($companyId): void {
                    $innerQuery->where('merchant_company_id', $companyId)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->where('platform', 'bigshop')
            ->orderByRaw('merchant_company_id is null')
            ->first();

        if (! $connection) {
            throw ValidationException::withMessages([
                'bigshop' => 'Configure a conexao BigShop antes de continuar.',
            ]);
        }

        return $connection;
    }
}
