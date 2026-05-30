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
use App\Models\Product;
use App\Models\WidgetInstall;
use App\Services\Audit\AuditLogger;
use App\Services\Imports\ImportRuleImpactService;
use App\Services\Imports\ImportRuleMapper;
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
        $events = IntegrationEvent::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereIn('event_type', ['install_validation', 'webhook_test'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->groupBy('platform');

        $data = collect($this->catalogForCompany($company))->map(function (array $platform) use ($connections, $events): array {
            $connection = $connections->get($platform['key']);
            $status = $connection ? $this->effectiveConnectionStatus($connection) : $platform['status'];
            $connectionData = $connection ? (new PlatformConnectionResource($connection))->resolve() : null;
            $platformEvents = $events->get($platform['key'], collect());

            if ($connectionData) {
                $connectionData['status'] = $status;
            }

            return array_merge($platform, [
                'connection' => $connectionData,
                'status' => $status,
                'has_connection' => (bool) $connection,
                'diagnostics' => [
                    'last_install_validation' => $this->lastInstallValidation($platformEvents),
                    'recent_webhook_logs' => $platformEvents
                        ->where('event_type', 'webhook_test')
                        ->take(5)
                        ->map(fn (IntegrationEvent $event): array => $this->webhookLogItem($event))
                        ->values()
                        ->all(),
                ],
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
        $before = $connection->exists ? $this->connectionSummary($connection) : null;
        $rotatedSecrets = [];

        $connection->fill([
            'merchant_company_id' => $company?->id,
            'external_store_id' => $data['external_store_id'] ?? $connection->external_store_id,
            'api_base_url' => $data['api_base_url'] ?? $connection->api_base_url,
            'feed_url' => $data['feed_url'] ?? $connection->feed_url,
            'feed_format' => $data['feed_format'] ?? $connection->feed_format ?? 'google_xml',
            'import_rules' => array_key_exists('import_rules', $data)
                ? app(ImportRuleMapper::class)->normalize($data['import_rules'])
                : $connection->import_rules,
            'last_error' => null,
        ]);

        if (array_key_exists('access_token', $data)) {
            $rotatedSecrets[] = 'access_token';
            $connection->access_token_encrypted = filled($data['access_token'])
                ? Crypt::encryptString($data['access_token'])
                : null;
        }

        if (array_key_exists('webhook_secret', $data)) {
            $rotatedSecrets[] = 'webhook_secret';
            $connection->webhook_secret_encrypted = filled($data['webhook_secret'])
                ? Crypt::encryptString($data['webhook_secret'])
                : null;
        }

        $connection->status = $this->statusFor($data['status'] ?? null, $connection);
        $connection->save();

        app(AuditLogger::class)->log($request, $merchant, 'integration.updated', 'integrations', 'info', [
            'platform' => $platform,
            'merchant_company_id' => $company?->id,
            'module' => 'integrations',
            'action' => 'update',
            'before' => $before,
            'after' => $this->connectionSummary($connection),
            'context_data' => [
                'secrets_rotated' => array_values(array_unique($rotatedSecrets)),
                'secret_rotation_count' => count(array_unique($rotatedSecrets)),
            ],
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
        $diagnostics = $this->installationDiagnostics($body, $platform, $host, $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 400);
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
                'diagnostics' => $diagnostics,
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
                'diagnostics' => $diagnostics,
                'checked_at' => now()->toISOString(),
            ],
        ]);
    }

    public function testWebhook(Request $request, string $platform)
    {
        if (! PlatformCatalog::find($platform)) {
            throw new NotFoundHttpException('Integração não encontrada.');
        }

        $merchant = $this->currentMerchant($request);
        $company = $this->activeCompany($request, $merchant);
        $this->guardPlatformAllowed($company, $platform);

        $data = $request->validate([
            'event' => ['nullable', 'string', 'max:80'],
            'product_id' => ['nullable', 'string', 'max:120'],
            'variant_id' => ['nullable', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:120'],
        ]);

        $connection = $this->connectionFor($merchant->id, $company?->id, $platform);

        if (! $connection || blank($connection->webhook_secret_encrypted)) {
            throw ValidationException::withMessages([
                'webhook_secret' => ['Salve um webhook secret antes de executar o teste. O segredo é write-only e não volta em claro.'],
            ]);
        }

        $secret = Crypt::decryptString($connection->webhook_secret_encrypted);
        $payload = [
            'event' => $data['event'] ?? 'provadorvirtual.webhook_test',
            'platform' => $platform,
            'store_id' => $connection->external_store_id,
            'product_id' => $data['product_id'] ?? 'PRODUCT-ID',
            'variant_id' => $data['variant_id'] ?? 'VARIANT-ID',
            'sku' => $data['sku'] ?? 'SKU-TESTE',
            'occurred_at' => now()->toISOString(),
        ];
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $signature = hash_hmac('sha256', $encodedPayload, $secret);
        $signatureMasked = 'sha256:'.substr($signature, 0, 6).'...'.substr($signature, -6);

        $event = IntegrationEvent::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'platform_connection_id' => $connection->id,
            'platform' => $platform,
            'event_type' => 'webhook_test',
            'direction' => 'inbound',
            'status' => 'passed',
            'summary' => [
                'secret' => 'stored_write_only',
                'signature_masked' => $signatureMasked,
                'signature_header' => 'X-Provador-Signature',
                'payload_keys' => array_keys($payload),
                'store_id' => $connection->external_store_id,
            ],
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        app(AuditLogger::class)->log($request, $merchant, 'integration.webhook_tested', 'integrations', 'info', [
            'platform' => $platform,
            'merchant_company_id' => $company?->id,
            'module' => 'integrations',
            'action' => 'test_webhook',
            'signature_masked' => $signatureMasked,
            'payload_keys' => array_keys($payload),
        ], $connection);

        return response()->json([
            'data' => [
                'status' => 'passed',
                'platform' => $platform,
                'has_webhook_secret' => true,
                'signature_header' => 'X-Provador-Signature',
                'signature_masked' => $signatureMasked,
                'payload' => $payload,
                'log' => $this->webhookLogItem($event),
                'recent_logs' => $this->recentWebhookLogs($merchant->id, $company?->id, $platform),
            ],
        ]);
    }

    public function simulateImportRules(Request $request, string $platform, ImportRuleImpactService $impact)
    {
        if (! PlatformCatalog::find($platform)) {
            throw new NotFoundHttpException('Integração não encontrada.');
        }

        $merchant = $this->currentMerchant($request);
        $company = $this->activeCompany($request, $merchant);
        $this->guardPlatformAllowed($company, $platform);

        $data = $request->validate([
            'import_rules' => ['nullable', 'array'],
            'import_rules.*' => ['nullable', 'array'],
            'import_rules.*.enabled' => ['nullable', 'boolean'],
            'import_rules.*.required' => ['nullable', 'boolean'],
            'import_rules.*.source_field' => ['nullable', 'string', 'max:120'],
            'import_rules.*.fallback' => ['nullable', 'string', 'max:120'],
            'import_rules.*.aliases' => ['nullable', 'array'],
            'import_rules.*.aliases.*' => ['nullable', 'string', 'max:120'],
        ]);

        $connection = $this->connectionFor($merchant->id, $company?->id, $platform);
        $rules = array_key_exists('import_rules', $data)
            ? $data['import_rules']
            : ($connection?->import_rules ?? []);

        return response()->json([
            'data' => $impact->simulate($merchant, $company, $platform, $connection, $rules),
        ]);
    }

    public function syncHistory(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->activeCompany($request, $merchant);
        $events = IntegrationEvent::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereIn('event_type', ['dry_run_import', 'sync_products', 'xml_feed_sync'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $jobs = ImportJob::query()
            ->where('merchant_id', $merchant->id)
            ->whereIn('id', $events->map(fn (IntegrationEvent $event): mixed => data_get($event->summary, 'import_job_id'))->filter()->values())
            ->get()
            ->keyBy('id');

        $items = $events->map(fn (IntegrationEvent $event): array => $this->syncHistoryItem(
            $event,
            $jobs->get(data_get($event->summary, 'import_job_id'))
        ))->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $items->count(),
                'with_errors' => $items->where('counters.errors', '>', 0)->count(),
                'warnings' => $items->where('counters.warnings', '>', 0)->count(),
                'last_status' => $items->first()['status'] ?? null,
                'totals' => $this->syncTotals($items),
                'by_origin' => $items->groupBy('origin.method')->map->count()->all(),
                'by_status' => $items->groupBy('status')->map->count()->all(),
                'issue_summary' => $this->syncIssueSummary($items),
                'timeline' => $items->take(16)->map(fn (array $item): array => [
                    'id' => $item['id'],
                    'status' => $item['status'],
                    'title' => $item['title'],
                    'origin' => $item['origin'],
                    'occurred_at' => $item['occurred_at'],
                    'total' => $item['counters']['total'],
                    'errors' => $item['counters']['errors'],
                    'warnings' => $item['counters']['warnings'],
                ])->values()->all(),
            ],
        ]);
    }

    public function exportSyncIssues(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->activeCompany($request, $merchant);
        $data = $request->validate([
            'event_id' => ['nullable', 'integer'],
        ]);

        $events = IntegrationEvent::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereIn('event_type', ['dry_run_import', 'sync_products', 'xml_feed_sync'])
            ->when($data['event_id'] ?? null, fn ($query, $eventId) => $query->whereKey($eventId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $jobs = ImportJob::query()
            ->where('merchant_id', $merchant->id)
            ->whereIn('id', $events->map(fn (IntegrationEvent $event): mixed => data_get($event->summary, 'import_job_id'))->filter()->values())
            ->get()
            ->keyBy('id');

        $rows = $events->flatMap(function (IntegrationEvent $event) use ($jobs): array {
            $issues = $this->syncIssues($event, $jobs->get(data_get($event->summary, 'import_job_id')));

            return $issues->map(fn (array $issue): array => [
                'execution_key' => $this->syncExecutionKey($event),
                'occurred_at' => $event->occurred_at?->toISOString(),
                'platform' => $event->platform,
                'severity' => $issue['severity'],
                'status' => data_get($issue, 'resolution.status', 'open'),
                'code' => $issue['code'],
                'root_cause' => $issue['cause_label'],
                'product_id' => $issue['product_id'],
                'product_name' => $issue['product_name'],
                'sku' => data_get($issue, 'context.sku'),
                'variant' => $issue['grid_id'],
                'category' => data_get($issue, 'context.category'),
                'brand' => data_get($issue, 'context.brand'),
                'sizes' => implode('|', data_get($issue, 'context.sizes', [])),
                'message' => $issue['message'],
                'recommended_action' => $issue['recommended_action_label'],
                'resolution_reason' => data_get($issue, 'resolution.reason'),
            ])->all();
        })->values();

        $headers = [
            'execution_key',
            'occurred_at',
            'platform',
            'severity',
            'status',
            'code',
            'root_cause',
            'product_id',
            'product_name',
            'sku',
            'variant',
            'category',
            'brand',
            'sizes',
            'message',
            'recommended_action',
            'resolution_reason',
        ];
        $csv = collect([$headers])
            ->concat($rows->map(fn (array $row): array => collect($headers)->map(fn (string $key): mixed => $row[$key] ?? null)->all()))
            ->map(fn (array $row): string => collect($row)->map(fn (mixed $value): string => $this->csvCell($value))->implode(','))
            ->implode("\n");

        return response("\xEF\xBB\xBF".$csv."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="provador-sync-erros.csv"',
        ]);
    }

    public function resolveSyncIssues(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->activeCompany($request, $merchant);
        $data = $request->validate([
            'event_id' => ['required', 'integer'],
            'issue_uids' => ['required', 'array', 'min:1', 'max:80'],
            'issue_uids.*' => ['required', 'string', 'max:80'],
            'action' => ['required', 'string', 'in:ignore,request_reprocess,reviewed'],
            'reason' => ['nullable', 'required_if:action,ignore', 'string', 'max:500'],
        ]);

        $event = IntegrationEvent::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereIn('event_type', ['dry_run_import', 'sync_products', 'xml_feed_sync'])
            ->whereKey((int) $data['event_id'])
            ->firstOrFail();
        $job = ImportJob::query()
            ->where('merchant_id', $merchant->id)
            ->whereKey(data_get($event->summary, 'import_job_id'))
            ->first();
        $issues = $this->syncIssues($event, $job);
        $selected = $issues
            ->whereIn('uid', collect($data['issue_uids'])->map(fn (mixed $uid): string => (string) $uid)->all())
            ->values();

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages([
                'issue_uids' => ['Nenhum erro selecionado foi encontrado nesta execução.'],
            ]);
        }

        $payload = $event->payload ?? [];
        $resolutions = data_get($payload, 'issue_resolutions', []);
        $status = match ($data['action']) {
            'ignore' => 'ignored',
            'request_reprocess' => 'reprocess_requested',
            default => 'reviewed',
        };

        foreach ($selected as $issue) {
            $resolutions[$issue['uid']] = [
                'status' => $status,
                'action' => $data['action'],
                'reason' => $data['reason'] ?? null,
                'actor_user_id' => $request->user()?->id,
                'updated_at' => now()->toISOString(),
            ];
        }

        $payload['issue_resolutions'] = $resolutions;
        $event->update(['payload' => $payload]);

        app(AuditLogger::class)->log($request, $merchant, 'integration.sync_issue_actioned', 'integrations', 'info', [
            'platform' => $event->platform,
            'merchant_company_id' => $company?->id,
            'module' => 'integrations',
            'action' => 'sync_issue_'.$data['action'],
            'event_id' => $event->id,
            'issue_uids' => $selected->pluck('uid')->values()->all(),
            'status' => $status,
            'reason_present' => filled($data['reason'] ?? null),
        ], $event);

        $event->refresh();
        $updatedIssues = $this->syncIssues($event, $job);

        return response()->json([
            'data' => [
                'event' => $this->syncHistoryItem($event, $job),
                'issue_summary' => $this->syncIssueSummary(collect([[
                    'issues' => $updatedIssues->all(),
                ]])),
            ],
        ]);
    }

    private function statusFor(?string $requestedStatus, PlatformConnection $connection): string
    {
        if (in_array($requestedStatus, ['connected', 'disabled', 'error'], true)) {
            return $requestedStatus;
        }

        if ($this->hasMinimumConfiguration($connection)) {
            return 'configured';
        }

        return 'draft';
    }

    private function effectiveConnectionStatus(PlatformConnection $connection): string
    {
        if (in_array($connection->status, ['connected', 'disabled', 'error'], true)) {
            return $connection->status;
        }

        return $this->hasMinimumConfiguration($connection) ? 'configured' : ($connection->status ?: 'draft');
    }

    private function hasMinimumConfiguration(PlatformConnection $connection): bool
    {
        $hasToken = filled($connection->access_token_encrypted);
        $hasFeed = filled($connection->feed_url);
        $hasApi = filled($connection->api_base_url);
        $hasStore = filled($connection->external_store_id);

        if ($connection->platform === 'bigshop') {
            return $hasStore && ($hasToken || $hasFeed);
        }

        return $hasFeed || ($hasApi && $hasToken) || $hasStore;
    }

    private function connectionFor(int $merchantId, ?int $companyId, string $platform): ?PlatformConnection
    {
        return PlatformConnection::query()
            ->where('merchant_id', $merchantId)
            ->when($companyId, function ($query) use ($companyId): void {
                $query->where(function ($innerQuery) use ($companyId): void {
                    $innerQuery->where('merchant_company_id', $companyId)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->where('platform', $platform)
            ->orderByRaw('merchant_company_id is null')
            ->first();
    }

    private function lastInstallValidation($events): ?array
    {
        $event = $events->where('event_type', 'install_validation')->first();

        if (! $event instanceof IntegrationEvent) {
            return null;
        }

        return [
            'id' => $event->id,
            'status' => $event->status,
            'url' => data_get($event->summary, 'url'),
            'host' => data_get($event->summary, 'host'),
            'http_status' => data_get($event->summary, 'http_status'),
            'checks' => data_get($event->summary, 'checks', []),
            'diagnostics' => data_get($event->summary, 'diagnostics', []),
            'checked_at' => $event->occurred_at?->toISOString(),
        ];
    }

    private function recentWebhookLogs(int $merchantId, ?int $companyId, string $platform): array
    {
        return IntegrationEvent::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompany($query, $companyId ? MerchantCompany::query()->find($companyId) : null))
            ->where('platform', $platform)
            ->where('event_type', 'webhook_test')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (IntegrationEvent $event): array => $this->webhookLogItem($event))
            ->values()
            ->all();
    }

    private function webhookLogItem(IntegrationEvent $event): array
    {
        return [
            'id' => $event->id,
            'status' => $event->status,
            'event_type' => data_get($event->payload, 'event', $event->event_type),
            'signature_masked' => data_get($event->summary, 'signature_masked'),
            'store_id' => data_get($event->summary, 'store_id'),
            'payload_keys' => data_get($event->summary, 'payload_keys', []),
            'occurred_at' => $event->occurred_at?->toISOString(),
        ];
    }

    private function syncHistoryItem(IntegrationEvent $event, ?ImportJob $job): array
    {
        $summary = $event->summary ?? [];
        $payload = $event->payload ?? [];
        $issues = $this->syncIssues($event, $job);
        $counters = $this->syncCounters($summary, $issues, $job);

        return [
            'id' => $event->id,
            'execution_key' => $this->syncExecutionKey($event),
            'platform' => $event->platform,
            'event_type' => $event->event_type,
            'title' => $this->syncEventTitle($event->event_type),
            'origin' => $this->syncOrigin($event),
            'status' => $event->status,
            'occurred_at' => $event->occurred_at?->toISOString(),
            'duration_seconds' => $this->syncDurationSeconds($summary, $job),
            'error' => $event->error,
            'counters' => $counters,
            'summary' => [
                'products_valid' => data_get($summary, 'products_valid'),
                'products_with_grids' => data_get($summary, 'products_with_grids'),
                'products_without_grids' => data_get($summary, 'products_without_grids'),
                'grids_read' => data_get($summary, 'grids_read'),
                'grids_joined' => data_get($summary, 'grids_joined'),
                'grids_without_product' => data_get($summary, 'grids_without_product'),
                'grids_without_size' => data_get($summary, 'grids_without_size'),
                'sizes_detected' => data_get($summary, 'sizes_detected'),
                'import_job_id' => data_get($summary, 'import_job_id'),
                'import_status' => data_get($summary, 'import_status'),
                'feed_host' => data_get($summary, 'feed_host'),
                'http_status' => data_get($summary, 'http_status'),
                'trigger' => data_get($summary, 'trigger'),
                'source' => data_get($summary, 'source'),
            ],
            'sample_products' => collect(data_get($payload, 'sample_products', []))->take(8)->values()->all(),
            'issue_groups' => $this->syncIssueGroups($issues),
            'issues' => $issues->take(80)->values()->all(),
        ];
    }

    private function syncCounters(array $summary, $issues, ?ImportJob $job): array
    {
        $products = $this->counterValue($summary, [
            'products_read',
            'products_synced',
            'summary.products',
            'total_rows',
        ], (int) ($job?->total_rows ?? 0));
        $errors = $this->counterValue($summary, ['errors_count', 'failed_rows'], (int) $issues->where('severity', 'error')->count());
        $warnings = $this->counterValue($summary, ['warnings_count'], (int) $issues->where('severity', 'warning')->count());
        $inserted = $this->counterValue($summary, [
            'inserted',
            'created',
            'products_created',
            'imported_rows',
            'summary.created',
        ], (int) ($job?->imported_rows ?? 0));
        $updated = $this->counterValue($summary, [
            'updated',
            'products_updated',
            'summary.updated',
        ]);
        $ignored = $this->counterValue($summary, [
            'ignored',
            'skipped',
            'products_without_grids',
            'products_without_measurement_table',
            'summary.ignored',
        ]);
        $unknown = $this->counterValue($summary, [
            'unknown',
            'unknown_rows',
            'grids_without_product',
            'summary.unknown',
        ]);
        $unchanged = $this->counterValue($summary, [
            'unchanged',
            'without_changes',
            'summary.unchanged',
        ]);
        $total = $this->counterValue($summary, ['total', 'total_rows'], $products + $unknown + $ignored);

        return [
            'total' => $total,
            'inserted' => $inserted,
            'updated' => $updated,
            'ignored' => $ignored,
            'unknown' => $unknown,
            'unchanged' => $unchanged,
            'products' => $products,
            'variants' => $this->counterValue($summary, [
                'variants_detected',
                'variants_synced',
                'summary.variants',
            ]),
            'tables' => $this->counterValue($summary, [
                'measurement_tables_synced',
                'summary.measurement_tables',
            ]),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function counterValue(array $summary, array $keys, int $default = 0): int
    {
        foreach ($keys as $key) {
            $value = data_get($summary, $key);

            if ($value !== null && $value !== '') {
                return max(0, (int) $value);
            }
        }

        return max(0, $default);
    }

    private function syncTotals($items): array
    {
        $keys = ['total', 'inserted', 'updated', 'ignored', 'unknown', 'unchanged', 'products', 'variants', 'tables', 'errors', 'warnings'];

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => (int) $items->sum("counters.{$key}")])
            ->all();
    }

    private function syncExecutionKey(IntegrationEvent $event): string
    {
        return implode('-', array_filter([
            $event->platform,
            $event->event_type,
            $event->occurred_at?->format('YmdHis') ?: $event->id,
            $event->id,
        ]));
    }

    private function syncDurationSeconds(array $summary, ?ImportJob $job): ?int
    {
        if ($job?->started_at && $job?->finished_at) {
            return max(0, $job->started_at->diffInSeconds($job->finished_at));
        }

        $duration = data_get($summary, 'duration_seconds');

        return $duration === null ? null : max(0, (int) $duration);
    }

    private function syncOrigin(IntegrationEvent $event): array
    {
        $summary = $event->summary ?? [];
        $trigger = (string) (data_get($summary, 'trigger') ?: data_get($summary, 'origin') ?: '');

        if (in_array($trigger, ['manual', 'scheduled', 'webhook'], true)) {
            $method = $trigger;
        } elseif ($event->event_type === 'xml_feed_sync') {
            $method = 'xml_feed';
        } elseif ($event->event_type === 'sync_products') {
            $method = 'api';
        } else {
            $method = 'manual';
        }

        $source = match ($event->event_type) {
            'sync_products', 'dry_run_import' => $event->platform === 'bigshop' ? 'BigShop' : 'API',
            'xml_feed_sync' => 'XML/feed',
            default => $event->platform ?: 'manual',
        };

        $methodLabel = [
            'manual' => 'Manual',
            'scheduled' => 'Agendada',
            'webhook' => 'Webhook',
            'xml_feed' => 'XML/feed',
            'api' => 'API',
        ][$method] ?? ucfirst($method);

        return [
            'method' => $method,
            'source' => $source,
            'label' => $methodLabel.' / '.$source,
        ];
    }

    private function syncIssues(IntegrationEvent $event, ?ImportJob $job)
    {
        $issues = collect(data_get($event->payload ?? [], 'issues', []))
            ->filter(fn (mixed $issue): bool => is_array($issue))
            ->map(fn (array $issue): array => $this->syncIssueItem($issue, $event->event_type));

        if ($job?->errors) {
            $issues = $issues->merge(collect($job->errors)->map(fn (array $row): array => $this->syncIssueItem([
                'severity' => 'error',
                'code' => 'import_row_failed',
                'product_id' => data_get($row, 'data.sku') ?: data_get($row, 'data.external_product_id'),
                'product_name' => data_get($row, 'data.name'),
                'grid_id' => data_get($row, 'data.variant_sku'),
                'line' => data_get($row, 'line'),
                'message' => collect(data_get($row, 'errors', []))->filter()->join(' | ') ?: 'Linha não importada.',
            ], 'import_row_failed')));
        }

        if ($event->error) {
            $issues->push($this->syncIssueItem([
                'severity' => 'error',
                'code' => 'sync_error',
                'product_id' => null,
                'product_name' => null,
                'grid_id' => null,
                'line' => null,
                'message' => $event->error,
            ], 'sync_error'));
        }

        $sampleProducts = collect(data_get($event->payload ?? [], 'sample_products', []))
            ->filter(fn (mixed $product): bool => is_array($product))
            ->keyBy(fn (array $product): string => (string) ($product['external_product_id'] ?? $product['sku'] ?? ''));
        $productKeys = $issues
            ->pluck('product_id')
            ->filter()
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->values();
        $products = $productKeys->isEmpty()
            ? collect()
            : Product::query()
                ->where('merchant_id', $event->merchant_id)
                ->when($event->merchant_company_id, fn ($query) => $query->where(function ($companyQuery) use ($event): void {
                    $companyQuery->where('merchant_company_id', $event->merchant_company_id)
                        ->orWhereNull('merchant_company_id');
                }))
                ->where(function ($query) use ($productKeys): void {
                    $query->whereIn('external_product_id', $productKeys)
                        ->orWhereIn('sku', $productKeys);
                })
                ->with(['variants' => fn ($query) => $query->select(['id', 'product_id', 'sku', 'size_label', 'is_active'])])
                ->get()
                ->mapWithKeys(function (Product $product): array {
                    $keys = [];

                    if ($product->external_product_id) {
                        $keys[(string) $product->external_product_id] = $product;
                    }

                    if ($product->sku) {
                        $keys[(string) $product->sku] = $product;
                    }

                    return $keys;
                });
        $resolutions = data_get($event->payload ?? [], 'issue_resolutions', []);

        return $issues
            ->map(fn (array $issue): array => $this->syncIssueEnriched($issue, $sampleProducts, $products, is_array($resolutions) ? $resolutions : []))
            ->values();
    }

    private function syncIssueItem(array $issue, string $fallbackCode): array
    {
        $productId = $issue['product_id'] ?? null;
        $code = (string) ($issue['code'] ?? $fallbackCode);

        return [
            'severity' => in_array($issue['severity'] ?? null, ['error', 'warning'], true) ? $issue['severity'] : 'warning',
            'code' => $code,
            'product_id' => $productId,
            'product_name' => $issue['product_name'] ?? null,
            'grid_id' => $issue['grid_id'] ?? null,
            'line' => $issue['line'] ?? null,
            'message' => (string) ($issue['message'] ?? 'Pendência de sincronização.'),
            'sku' => $issue['sku'] ?? null,
            'category' => $issue['category'] ?? null,
            'brand' => $issue['brand'] ?? null,
            'sizes' => $issue['sizes'] ?? [],
            'product_url' => $issue['product_url'] ?? null,
            'variant_sku' => $issue['variant_sku'] ?? null,
        ];
    }

    private function syncIssueEnriched(array $issue, $sampleProducts, $products, array $resolutions): array
    {
        $productId = $issue['product_id'] ? (string) $issue['product_id'] : null;
        $sample = $productId ? $sampleProducts->get($productId, []) : [];
        $product = $productId ? $products->get($productId) : null;
        $code = (string) $issue['code'];
        $rootCause = $this->syncIssueRootCause($code);
        $context = $this->syncIssueContext($issue, is_array($sample) ? $sample : [], $product instanceof Product ? $product : null);
        $recommendedAction = $this->syncIssueRecommendedAction($rootCause['key'], $productId, $context);
        $uid = $this->syncIssueUid($issue);
        $resolution = $this->syncIssueResolution($resolutions[$uid] ?? null);
        $availableActions = $this->syncIssueAvailableActions($rootCause['key'], $productId, $context, $recommendedAction);

        return [
            ...$issue,
            'uid' => $uid,
            'root_cause' => $rootCause['key'],
            'cause_label' => $rootCause['label'],
            'context' => $context,
            'recommended_action' => $recommendedAction['key'],
            'recommended_action_label' => $recommendedAction['label'],
            'recommended_action_url' => $recommendedAction['url'],
            'available_actions' => $availableActions,
            'resolution' => $resolution,
            'action_url' => $recommendedAction['url'],
            'action_label' => $recommendedAction['label'],
            'rule_url' => '/app/regras-de-importacao',
            'related' => [
                'product' => filled($productId),
                'rule' => $rootCause['key'] === 'import_rule' || ! filled($productId),
                'table' => $rootCause['key'] === 'measurement_table',
                'modeling' => $rootCause['key'] === 'modeling',
                'category' => $rootCause['key'] === 'category',
                'brand' => $rootCause['key'] === 'brand',
            ],
        ];
    }

    private function syncIssueContext(array $issue, array $sample, ?Product $product): array
    {
        $sizes = collect($issue['sizes'] ?? [])
            ->merge(data_get($sample, 'sizes', []))
            ->merge($product?->variants?->pluck('size_label') ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'product_id' => $issue['product_id'] ?? $product?->external_product_id,
            'product_name' => $issue['product_name'] ?? data_get($sample, 'name') ?? $product?->name,
            'sku' => $issue['sku'] ?? data_get($sample, 'sku') ?? $product?->sku,
            'variant_id' => $issue['grid_id'] ?? null,
            'variant_sku' => $issue['variant_sku'] ?? data_get($sample, 'variant_sku'),
            'sizes' => $sizes,
            'category' => $issue['category'] ?? data_get($sample, 'category') ?? $product?->category,
            'brand' => $issue['brand'] ?? data_get($sample, 'brand') ?? data_get($product?->metadata ?? [], 'brand'),
            'gender' => data_get($sample, 'gender') ?? $product?->gender,
            'age_group' => data_get($sample, 'age_group') ?? data_get($product?->metadata ?? [], 'age_group'),
            'fit_profile' => data_get($sample, 'fit_profile') ?? $product?->fit_profile,
            'product_url' => $issue['product_url'] ?? data_get($sample, 'product_url'),
            'line' => $issue['line'] ?? null,
        ];
    }

    private function syncIssueRootCause(string $code): array
    {
        $normalized = Str::of($code)->lower()->toString();

        return match (true) {
            str_contains($normalized, 'measurement') || str_contains($normalized, 'table') => ['key' => 'measurement_table', 'label' => 'Tabela de medidas'],
            str_contains($normalized, 'modeling') || str_contains($normalized, 'fit_profile') => ['key' => 'modeling', 'label' => 'Modelagem'],
            str_contains($normalized, 'category') => ['key' => 'category', 'label' => 'Categoria'],
            str_contains($normalized, 'brand') => ['key' => 'brand', 'label' => 'Marca'],
            str_contains($normalized, 'rule') || str_contains($normalized, 'mapping') => ['key' => 'import_rule', 'label' => 'Regra de importação'],
            str_contains($normalized, 'grid') || str_contains($normalized, 'size') || str_contains($normalized, 'variant') => ['key' => 'size_grid', 'label' => 'Grade e tamanhos'],
            str_contains($normalized, 'product_id') || str_contains($normalized, 'product_not_found') => ['key' => 'product_identity', 'label' => 'Identificação do produto'],
            str_contains($normalized, 'sync') || str_contains($normalized, 'import_row') || str_contains($normalized, 'failed') => ['key' => 'import_failure', 'label' => 'Falha de importação'],
            default => ['key' => 'data_quality', 'label' => 'Qualidade dos dados'],
        };
    }

    private function syncIssueRecommendedAction(string $rootCause, ?string $productId, array $context): array
    {
        $productSearch = $productId ? rawurlencode($productId) : '';

        return match ($rootCause) {
            'measurement_table' => [
                'key' => 'link_table',
                'label' => 'Vincular tabela',
                'url' => '/app/produtos?filtro=sem_tabela'.($productSearch ? '&busca='.$productSearch : ''),
            ],
            'modeling' => [
                'key' => 'create_modeling',
                'label' => 'Criar modelagem',
                'url' => '/app/modelagens',
            ],
            'category' => [
                'key' => 'review_category',
                'label' => 'Revisar categoria',
                'url' => '/app/categorias',
            ],
            'brand' => [
                'key' => 'review_brand',
                'label' => 'Revisar marca',
                'url' => '/app/marcas',
            ],
            'import_rule' => [
                'key' => 'review_rule',
                'label' => 'Revisar regra',
                'url' => '/app/regras-de-importacao',
            ],
            'import_failure' => [
                'key' => 'request_reprocess',
                'label' => 'Reprocessar',
                'url' => '/app/integracoes',
            ],
            default => filled($productId)
                ? [
                    'key' => 'open_product',
                    'label' => 'Abrir produto',
                    'url' => '/app/produtos?busca='.$productSearch,
                ]
                : [
                    'key' => 'review_rule',
                    'label' => 'Revisar regra',
                    'url' => '/app/regras-de-importacao',
                ],
        };
    }

    private function syncIssueAvailableActions(string $rootCause, ?string $productId, array $context, array $recommendedAction): array
    {
        $actions = collect([$recommendedAction]);
        $productSearch = $productId ? rawurlencode($productId) : '';

        if (filled($productId) && $recommendedAction['key'] !== 'open_product') {
            $actions->push([
                'key' => 'open_product',
                'label' => 'Abrir produto',
                'url' => '/app/produtos?busca='.$productSearch,
                'kind' => 'link',
            ]);
        }

        foreach ([
            'link_table' => ['label' => 'Vincular tabela', 'url' => '/app/produtos?filtro=sem_tabela'.($productSearch ? '&busca='.$productSearch : '')],
            'create_modeling' => ['label' => 'Criar modelagem', 'url' => '/app/modelagens'],
            'review_category' => ['label' => 'Revisar categoria', 'url' => '/app/categorias'],
            'review_brand' => ['label' => 'Revisar marca', 'url' => '/app/marcas'],
            'review_rule' => ['label' => 'Revisar regra', 'url' => '/app/regras-de-importacao'],
        ] as $key => $action) {
            $shouldShow = match ($key) {
                'link_table' => $rootCause === 'measurement_table',
                'create_modeling' => $rootCause === 'modeling',
                'review_category' => $rootCause === 'category',
                'review_brand' => $rootCause === 'brand',
                'review_rule' => in_array($rootCause, ['import_rule', 'data_quality', 'product_identity', 'size_grid'], true),
                default => false,
            };

            if ($shouldShow && $actions->where('key', $key)->isEmpty()) {
                $actions->push(['key' => $key, ...$action, 'kind' => 'link']);
            }
        }

        $actions->push(['key' => 'request_reprocess', 'label' => 'Reprocessar', 'url' => null, 'kind' => 'api']);
        $actions->push(['key' => 'ignore', 'label' => 'Ignorar', 'url' => null, 'kind' => 'api']);

        return $actions
            ->map(fn (array $action): array => [
                'key' => $action['key'],
                'label' => $action['label'],
                'url' => $action['url'] ?? null,
                'kind' => $action['kind'] ?? 'link',
            ])
            ->unique('key')
            ->values()
            ->all();
    }

    private function syncIssueUid(array $issue): string
    {
        return substr(sha1(json_encode([
            $issue['code'] ?? null,
            $issue['product_id'] ?? null,
            $issue['grid_id'] ?? null,
            $issue['line'] ?? null,
            $issue['message'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), 0, 20);
    }

    private function syncIssueResolution(mixed $resolution): array
    {
        if (! is_array($resolution)) {
            return [
                'status' => 'open',
                'label' => 'Aberto',
                'reason' => null,
                'updated_at' => null,
            ];
        }

        $status = (string) ($resolution['status'] ?? 'open');

        return [
            'status' => $status,
            'label' => [
                'ignored' => 'Ignorado',
                'reprocess_requested' => 'Reprocessamento solicitado',
                'reviewed' => 'Revisado',
                'open' => 'Aberto',
            ][$status] ?? ucfirst($status),
            'reason' => $resolution['reason'] ?? null,
            'updated_at' => $resolution['updated_at'] ?? null,
        ];
    }

    private function syncIssueGroups($issues): array
    {
        return collect($issues)
            ->groupBy('root_cause')
            ->map(function ($group, string $key): array {
                $open = $group->where('resolution.status', 'open');

                return [
                    'key' => $key,
                    'label' => $group->first()['cause_label'] ?? $key,
                    'count' => $group->count(),
                    'open_count' => $open->count(),
                    'ignored_count' => $group->where('resolution.status', 'ignored')->count(),
                    'reprocess_requested_count' => $group->where('resolution.status', 'reprocess_requested')->count(),
                    'critical_count' => $open->where('severity', 'error')->count(),
                    'recommended_action_label' => $group->first()['recommended_action_label'] ?? 'Revisar',
                    'recommended_action_url' => $group->first()['recommended_action_url'] ?? null,
                    'issue_uids' => $group->pluck('uid')->values()->all(),
                    'product_ids' => $group->pluck('product_id')->filter()->unique()->values()->all(),
                    'sample_messages' => $group->pluck('message')->filter()->unique()->take(3)->values()->all(),
                ];
            })
            ->sortByDesc(fn (array $group): int => ($group['critical_count'] * 1000) + $group['open_count'])
            ->values()
            ->all();
    }

    private function syncIssueSummary($items): array
    {
        $issues = collect($items)
            ->flatMap(fn (array $item): array => $item['issues'] ?? [])
            ->values();
        $open = $issues->where('resolution.status', 'open');

        return [
            'total' => $issues->count(),
            'open' => $open->count(),
            'critical_open' => $open->where('severity', 'error')->count(),
            'ignored' => $issues->where('resolution.status', 'ignored')->count(),
            'reprocess_requested' => $issues->where('resolution.status', 'reprocess_requested')->count(),
            'reviewed' => $issues->where('resolution.status', 'reviewed')->count(),
            'by_cause' => $issues
                ->groupBy('root_cause')
                ->map(fn ($group): array => [
                    'label' => $group->first()['cause_label'] ?? 'Causa',
                    'count' => $group->count(),
                    'open' => $group->where('resolution.status', 'open')->count(),
                ])
                ->all(),
        ];
    }

    private function csvCell(mixed $value): string
    {
        $value = str_replace('"', '""', (string) $value);

        return '"'.$value.'"';
    }

    private function syncEventTitle(string $type): string
    {
        return [
            'dry_run_import' => 'Prévia BigShop',
            'sync_products' => 'Sync API BigShop',
            'xml_feed_sync' => 'Sync XML/feed',
        ][$type] ?? $type;
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
        $diagnostics = $this->installationDiagnostics($body, $platform, $host, $reachable);

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
                (bool) data_get($diagnostics, 'container.found'),
                'Inclua o container perto do seletor de tamanho.',
                warning: true
            ),
            $this->check(
                'script_found',
                'Script do widget carregado',
                (bool) data_get($diagnostics, 'script.found'),
                'Inclua o script oficial do widget na página de produto.'
            ),
            $this->check(
                'platform_hint',
                'Plataforma informada no snippet',
                (bool) data_get($diagnostics, 'platform.found'),
                'Defina data-platform="'.$platform.'" no script.',
                warning: true
            ),
            $this->check(
                'product_id_found',
                'Produto informado',
                (bool) data_get($diagnostics, 'product_id.found'),
                'Informe data-product-id com o identificador do produto na plataforma.'
            ),
            $this->check(
                'variant_id_found',
                'Variação informada',
                (bool) data_get($diagnostics, 'variant_id.found'),
                'Informe data-variant-id quando houver grade, cor ou tamanho selecionável.',
                warning: true
            ),
            $this->check(
                'sku_found',
                'SKU informado',
                (bool) data_get($diagnostics, 'sku.found'),
                'Informe data-sku para cruzar widget, catálogo e relatórios.',
                warning: true
            ),
            $this->check(
                'buttons_rendered',
                'Botões do provador renderizados',
                (bool) data_get($diagnostics, 'buttons.found'),
                'Após o script carregar, confirme os botões Descubra seu tamanho e Tabela de Medidas na página. Se usar GTM, valide também no Preview/Tag Assistant.',
                warning: true
            ),
        ];
    }

    private function installationDiagnostics(string $body, string $platform, string $host, bool $reachable): array
    {
        $content = mb_strtolower($body);
        $platformValue = $this->extractAttribute($body, 'data-platform');
        $productId = $this->extractAttribute($body, 'data-product-id');
        $variantId = $this->extractAttribute($body, 'data-variant-id');
        $sku = $this->extractAttribute($body, 'data-sku');
        preg_match('/<script[^>]+src=["\']([^"\']*provador-virtual\.js[^"\']*)["\']/i', $body, $scriptMatch);

        return [
            'host' => $host,
            'reachable' => $reachable,
            'container' => [
                'found' => str_contains($content, 'provador-virtual-container') || str_contains($content, 'data-container-id'),
                'selector' => str_contains($content, 'provador-virtual-container') ? '#provador-virtual-container' : null,
            ],
            'script' => [
                'found' => str_contains($content, 'provadorvirtualscript') || str_contains($content, 'provador-virtual.js'),
                'src' => $scriptMatch[1] ?? null,
            ],
            'platform' => [
                'found' => $platformValue === $platform,
                'value' => $platformValue,
                'expected' => $platform,
            ],
            'product_id' => [
                'found' => filled($productId),
                'value' => $productId,
            ],
            'variant_id' => [
                'found' => filled($variantId),
                'value' => $variantId,
            ],
            'sku' => [
                'found' => filled($sku),
                'value' => $sku,
            ],
            'buttons' => [
                'found' => str_contains($content, 'descubra seu tamanho')
                    || str_contains($content, 'tabela de medidas')
                    || str_contains($content, 'pv-main-button')
                    || str_contains($content, 'pv-size-table'),
                'labels' => collect(['descubra seu tamanho', 'tabela de medidas'])
                    ->filter(fn (string $label): bool => str_contains($content, $label))
                    ->values()
                    ->all(),
            ],
            'gtm' => [
                'detected' => str_contains($content, 'googletagmanager.com/gtm.js')
                    || str_contains($content, 'gtm-')
                    || str_contains($content, 'datalayer'),
            ],
        ];
    }

    private function extractAttribute(string $body, string $attribute): ?string
    {
        if (! preg_match('/\s'.preg_quote($attribute, '/').'\s*=\s*(["\'])(.*?)\1/i', $body, $match)) {
            return null;
        }

        $value = trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value === '' ? null : $value;
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

    private function connectionSummary(PlatformConnection $connection): array
    {
        return [
            'platform' => $connection->platform,
            'merchant_company_id' => $connection->merchant_company_id,
            'external_store_id' => $connection->external_store_id,
            'api_base_url' => $connection->api_base_url,
            'feed_url' => $connection->feed_url,
            'feed_format' => $connection->feed_format ?: 'google_xml',
            'status' => $connection->status,
            'has_access_token' => filled($connection->access_token_encrypted),
            'has_webhook_secret' => filled($connection->webhook_secret_encrypted),
            'import_rules_active' => app(ImportRuleMapper::class)->summarize($connection->import_rules ?? [])['active'],
        ];
    }
}
