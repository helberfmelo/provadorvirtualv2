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
        $connection = $this->connection($merchant->id);

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
        $connection = $this->connection($merchant->id);
        $company = $this->merchantCompany($merchant, $connection->merchant_company_id);

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

    private function connection(int $merchantId): PlatformConnection
    {
        $connection = PlatformConnection::query()
            ->where('merchant_id', $merchantId)
            ->where('platform', 'bigshop')
            ->first();

        if (! $connection) {
            throw ValidationException::withMessages([
                'bigshop' => 'Configure a conexao BigShop antes de continuar.',
            ]);
        }

        return $connection;
    }
}
