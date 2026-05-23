<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePlatformConnectionRequest;
use App\Http\Resources\PlatformConnectionResource;
use App\Models\PlatformConnection;
use App\Services\Audit\AuditLogger;
use App\Support\PlatformCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IntegrationController extends Controller
{
    use ResolvesMerchant;

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $connections = PlatformConnection::query()
            ->where('merchant_id', $merchant->id)
            ->get()
            ->keyBy('platform');

        $data = collect(PlatformCatalog::all())->map(function (array $platform) use ($connections): array {
            $connection = $connections->get($platform['key']);

            return array_merge($platform, [
                'connection' => $connection ? (new PlatformConnectionResource($connection))->resolve() : null,
                'status' => $connection?->status ?? $platform['status'],
                'has_connection' => (bool) $connection,
            ]);
        })->values();

        return response()->json(['data' => $data]);
    }

    public function update(UpdatePlatformConnectionRequest $request, string $platform)
    {
        if (! PlatformCatalog::find($platform)) {
            throw new NotFoundHttpException('Integracao nao encontrada.');
        }

        $merchant = $this->currentMerchant($request);
        $data = $request->validated();
        $company = $this->merchantCompany($merchant, $data['merchant_company_id'] ?? null);

        $connection = PlatformConnection::query()->firstOrNew([
            'merchant_id' => $merchant->id,
            'platform' => $platform,
        ]);

        $connection->fill([
            'merchant_company_id' => $company?->id,
            'external_store_id' => $data['external_store_id'] ?? $connection->external_store_id,
            'api_base_url' => $data['api_base_url'] ?? $connection->api_base_url,
            'status' => $data['status'] ?? $this->statusFor($data, $connection->status),
            'last_error' => null,
        ]);

        if (array_key_exists('access_token', $data)) {
            $connection->access_token_encrypted = filled($data['access_token'])
                ? Crypt::encryptString($data['access_token'])
                : null;
        }

        if (array_key_exists('webhook_secret', $data)) {
            $connection->webhook_secret_encrypted = filled($data['webhook_secret'])
                ? Crypt::encryptString($data['webhook_secret'])
                : null;
        }

        $connection->save();

        app(AuditLogger::class)->log($request, $merchant, 'integration.updated', 'integrations', 'info', [
            'platform' => $platform,
            'status' => $connection->status,
            'has_access_token' => filled($connection->access_token_encrypted),
            'has_webhook_secret' => filled($connection->webhook_secret_encrypted),
        ], $connection);

        return (new PlatformConnectionResource($connection->refresh()))
            ->response()
            ->setStatusCode(200);
    }

    private function statusFor(array $data, ?string $fallback): string
    {
        if (! empty($data['external_store_id']) || ! empty($data['api_base_url']) || ! empty($data['access_token'])) {
            return 'configured';
        }

        return $fallback ?: 'draft';
    }
}
