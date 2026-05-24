<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePlatformConnectionRequest;
use App\Http\Resources\ImportJobResource;
use App\Http\Resources\PlatformConnectionResource;
use App\Models\ImportJob;
use App\Models\IntegrationEvent;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\WidgetInstall;
use App\Services\Audit\AuditLogger;
use App\Services\Integrations\XmlFeedSyncService;
use App\Support\ActiveTenant;
use App\Support\PlatformCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IntegrationController extends Controller
{
    use ResolvesMerchant;

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->activeCompany($request, $merchant);
        $connections = PlatformConnection::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->get()
            ->keyBy('platform');

        $data = collect($this->catalogForCompany($company))->map(function (array $platform) use ($connections): array {
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
            throw new NotFoundHttpException('Integração não encontrada.');
        }

        $merchant = $this->currentMerchant($request);
        $activeCompany = $this->activeCompany($request, $merchant);
        $this->guardPlatformAllowed($activeCompany, $platform);

        $data = $request->validated();
        $company = array_key_exists('merchant_company_id', $data)
            ? $this->merchantCompany($merchant, $data['merchant_company_id'])
            : $activeCompany;

        $connection = PlatformConnection::query()->firstOrNew([
            'merchant_id' => $merchant->id,
            'platform' => $platform,
        ]);

        $connection->fill([
            'merchant_company_id' => $company?->id,
            'external_store_id' => $data['external_store_id'] ?? $connection->external_store_id,
            'api_base_url' => $data['api_base_url'] ?? $connection->api_base_url,
            'feed_url' => $data['feed_url'] ?? $connection->feed_url,
            'feed_format' => $data['feed_format'] ?? $connection->feed_format ?? 'google_xml',
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
            'merchant_company_id' => $company?->id,
            'module' => 'integrations',
            'action' => 'update',
            'status' => $connection->status,
            'has_access_token' => filled($connection->access_token_encrypted),
            'has_webhook_secret' => filled($connection->webhook_secret_encrypted),
            'has_feed_url' => filled($connection->feed_url),
        ], $connection);

        return (new PlatformConnectionResource($connection->refresh()))
            ->response()
            ->setStatusCode(200);
    }

    public function syncXml(Request $request, string $platform, XmlFeedSyncService $xmlFeeds)
    {
        if (! PlatformCatalog::find($platform)) {
            throw new NotFoundHttpException('Integração não encontrada.');
        }

        $merchant = $this->currentMerchant($request);
        $company = $this->activeCompany($request, $merchant);
        $this->guardPlatformAllowed($company, $platform);

        $connection = PlatformConnection::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->where('platform', $platform)
            ->orderByRaw('merchant_company_id is null')
            ->first();

        if (! $connection) {
            throw ValidationException::withMessages([
                'feed_url' => ['Salve a integração com uma URL de XML/feed antes de sincronizar.'],
            ]);
        }

        $result = $xmlFeeds->sync(
            $connection,
            $connection->merchant_company_id ? $this->merchantCompany($merchant, $connection->merchant_company_id) : $company,
            'manual'
        );
        $job = $result['job'];

        app(AuditLogger::class)->log($request, $merchant, 'integration.xml_synced', 'integrations', 'info', [
            'platform' => $platform,
            'merchant_company_id' => $connection->merchant_company_id ?: $company?->id,
            'module' => 'integrations',
            'action' => 'sync_xml',
            'status' => $result['status'],
            'feed_host' => data_get($result, 'summary.feed_host'),
        ], $connection);

        if ($result['error'] || ! $job instanceof ImportJob) {
            throw ValidationException::withMessages([
                'feed_url' => [$result['error'] ?: 'Não foi possível sincronizar o XML/feed.'],
            ]);
        }

        return response()->json([
            'data' => array_merge((new ImportJobResource($job))->resolve(), [
                'connection_status' => $connection->refresh()->status,
            ]),
        ]);
    }

    public function validateInstall(Request $request, string $platform)
    {
        if (! PlatformCatalog::find($platform)) {
            throw new NotFoundHttpException('Integração não encontrada.');
        }

        $merchant = $this->currentMerchant($request);
        $company = $this->activeCompany($request, $merchant);
        $this->guardPlatformAllowed($company, $platform);

        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:255'],
        ]);
        $url = $this->installationUrl($data['url'] ?? null, $company);
        $host = (string) parse_url($url, PHP_URL_HOST);
        $install = WidgetInstall::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->orderByRaw('merchant_company_id is null')
            ->first();

        $httpStatus = null;
        $body = '';
        $error = null;

        try {
            $response = Http::timeout(8)->retry(1, 100)->get($url);
            $httpStatus = $response->status();
            $body = (string) $response->body();
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        $checks = $this->installationChecks(
            body: $body,
            platform: $platform,
            host: $host,
            allowedDomains: $install?->allowed_domains ?? [],
            reachable: $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 400
        );
        $status = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'failed')
            ? 'failed'
            : (collect($checks)->contains(fn (array $check): bool => $check['status'] === 'warning') ? 'warning' : 'passed');

        IntegrationEvent::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'platform_connection_id' => PlatformConnection::query()
                ->where('merchant_id', $merchant->id)
                ->tap(fn ($query) => $this->scopeCompany($query, $company))
                ->where('platform', $platform)
                ->value('id'),
            'platform' => $platform,
            'event_type' => 'install_validation',
            'direction' => 'outbound',
            'status' => $status,
            'summary' => [
                'url' => $url,
                'host' => $host,
                'http_status' => $httpStatus,
                'checks' => collect($checks)->mapWithKeys(fn (array $check): array => [$check['key'] => $check['status']])->all(),
            ],
            'error' => $error,
            'occurred_at' => now(),
        ]);

        app(AuditLogger::class)->log($request, $merchant, 'integration.install_validated', 'integrations', 'info', [
            'platform' => $platform,
            'merchant_company_id' => $company?->id,
            'module' => 'integrations',
            'action' => 'validate_install',
            'status' => $status,
            'url_host' => $host,
        ]);

        return response()->json([
            'data' => [
                'status' => $status,
                'url' => $url,
                'http_status' => $httpStatus,
                'checks' => $checks,
            ],
        ]);
    }

    private function statusFor(array $data, ?string $fallback): string
    {
        if (
            ! empty($data['external_store_id'])
            || ! empty($data['api_base_url'])
            || ! empty($data['access_token'])
            || ! empty($data['feed_url'])
        ) {
            return 'configured';
        }

        return $fallback ?: 'draft';
    }

    private function activeCompany(Request $request, Merchant $merchant): ?MerchantCompany
    {
        return app(ActiveTenant::class)->company($request, $merchant);
    }

    private function catalogForCompany(?MerchantCompany $company): array
    {
        if ($company?->platform === 'bigshop') {
            return ['bigshop' => PlatformCatalog::find('bigshop')];
        }

        return PlatformCatalog::all();
    }

    private function guardPlatformAllowed(?MerchantCompany $company, string $platform): void
    {
        abort_if(
            $company?->platform === 'bigshop' && $platform !== 'bigshop',
            403,
            'Sua empresa contratou o plano BigShop. A integração disponível para este contrato é apenas BigShop.'
        );
    }

    private function installationUrl(?string $url, ?MerchantCompany $company): string
    {
        $value = trim((string) ($url ?: $company?->domain));

        if ($value === '') {
            throw ValidationException::withMessages([
                'url' => ['Informe a URL pública da página de produto para validar.'],
            ]);
        }

        return $this->publicUrl($value, 'url', 'Informe uma URL pública válida.');
    }

    private function publicUrl(?string $url, string $field, string $message): string
    {
        $value = trim((string) $url);

        if ($value === '') {
            throw ValidationException::withMessages([
                $field => [$message],
            ]);
        }

        if (! Str::startsWith($value, ['http://', 'https://'])) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! filter_var($value, FILTER_VALIDATE_URL) || ! is_string($host) || $host === '') {
            throw ValidationException::withMessages([
                $field => ['Informe uma URL pública válida.'],
            ]);
        }

        $host = mb_strtolower($host);
        $blockedHosts = ['localhost', '127.0.0.1', '::1'];
        $isPublicIp = ! filter_var($host, FILTER_VALIDATE_IP)
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if (in_array($host, $blockedHosts, true) || str_ends_with($host, '.local') || ! $isPublicIp) {
            throw ValidationException::withMessages([
                $field => ['Use uma URL pública da loja.'],
            ]);
        }

        return $value;
    }

    private function installationChecks(string $body, string $platform, string $host, array $allowedDomains, bool $reachable): array
    {
        $content = mb_strtolower($body);
        $platformHint = 'data-platform="'.$platform.'"';

        return [
            $this->check(
                'domain_configured',
                'Domínio cadastrado no widget',
                $this->domainMatches($host, $allowedDomains),
                'Cadastre '.$host.' em /app/widget antes de publicar.'
            ),
            $this->check(
                'page_reachable',
                'Página de produto publicada',
                $reachable,
                'A URL precisa responder HTTP 2xx/3xx para o validador.'
            ),
            $this->check(
                'container_found',
                'Container do Provador Virtual encontrado',
                str_contains($content, 'provador-virtual-container') || str_contains($content, 'data-container-id'),
                'Inclua o container perto do seletor de tamanho.',
                warning: true
            ),
            $this->check(
                'script_found',
                'Script do widget carregado',
                str_contains($content, 'provadorvirtualscript') || str_contains($content, 'provador-virtual.js'),
                'Inclua o script oficial do widget na página de produto.'
            ),
            $this->check(
                'platform_hint',
                'Plataforma informada no snippet',
                str_contains($content, $platformHint) || str_contains($content, "data-platform='{$platform}'"),
                'Defina data-platform="'.$platform.'" no script.',
                warning: true
            ),
            $this->check(
                'product_identifiers',
                'Produto, variação ou SKU informados',
                str_contains($content, 'data-product-id') || str_contains($content, 'data-sku'),
                'Informe data-product-id e data-sku para identificar o produto.',
                warning: true
            ),
        ];
    }

    private function check(string $key, string $label, bool $passed, string $action, bool $warning = false): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $passed ? 'passed' : ($warning ? 'warning' : 'failed'),
            'action' => $passed ? null : $action,
        ];
    }

    private function domainMatches(string $host, array $allowedDomains): bool
    {
        $host = $this->normalizeHost($host);

        return collect($allowedDomains)
            ->map(fn (string $domain): string => $this->normalizeHost($domain))
            ->filter()
            ->contains(fn (string $domain): bool => $host === $domain || str_ends_with($host, '.'.$domain));
    }

    private function normalizeHost(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $host = parse_url(Str::startsWith($value, ['http://', 'https://']) ? $value : 'https://'.$value, PHP_URL_HOST) ?: $value;

        return preg_replace('/^www\./', '', $host) ?: $host;
    }
}
