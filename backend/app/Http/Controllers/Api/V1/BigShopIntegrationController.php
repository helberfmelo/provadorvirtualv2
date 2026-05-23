<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
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
