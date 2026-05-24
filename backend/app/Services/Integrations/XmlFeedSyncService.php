<?php

namespace App\Services\Integrations;

use App\Models\IntegrationEvent;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Services\Imports\ImportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class XmlFeedSyncService
{
    public function __construct(private readonly ImportService $imports) {}

    public function sync(PlatformConnection $connection, ?MerchantCompany $fallbackCompany = null, string $trigger = 'manual'): array
    {
        $merchant = $connection->merchant()->firstOrFail();
        $company = $connection->company ?: $fallbackCompany;
        $feedUrl = trim((string) $connection->feed_url);
        $host = '';
        $httpStatus = null;
        $job = null;
        $error = null;
        $status = 'failed';

        try {
            $feedUrl = $this->publicUrl($feedUrl);
            $host = (string) parse_url($feedUrl, PHP_URL_HOST);
            $response = Http::timeout(30)->retry(1, 200)->get($feedUrl);
            $httpStatus = $response->status();

            if (! $response->successful()) {
                throw new RuntimeException('O XML/feed respondeu HTTP '.$httpStatus.'.');
            }

            $job = $this->imports->commit($merchant, $company, [
                'type' => 'products',
                'source_format' => $connection->feed_format ?: 'google_xml',
                'filename' => $feedUrl,
                'content' => (string) $response->body(),
            ]);

            $status = in_array($job->status, ['completed', 'completed_with_warnings'], true)
                ? ($job->status === 'completed' ? 'success' : 'warning')
                : 'failed';

            $connection->update([
                'status' => $status === 'failed' ? 'error' : 'connected',
                'last_sync_at' => now(),
                'last_error' => $status === 'failed' ? 'Importação XML finalizada com erro.' : null,
            ]);

            if ($status === 'failed') {
                $error = data_get($job->errors, '0.errors.0') ?: 'Importação XML finalizada com erro.';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $connection->update([
                'status' => 'error',
                'last_error' => $error,
            ]);
        }

        $summary = [
            'trigger' => $trigger,
            'feed_host' => $host,
            'feed_url' => $feedUrl,
            'http_status' => $httpStatus,
            'import_job_id' => $job?->id,
            'import_status' => $job?->status,
            'total_rows' => $job?->total_rows,
            'imported_rows' => $job?->imported_rows,
            'failed_rows' => $job?->failed_rows,
            'summary' => $job?->summary ?? [],
        ];

        $event = IntegrationEvent::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $connection->merchant_company_id ?: $company?->id,
            'platform_connection_id' => $connection->id,
            'platform' => $connection->platform,
            'event_type' => 'xml_feed_sync',
            'direction' => 'outbound',
            'status' => $status,
            'summary' => $summary,
            'error' => $error,
            'occurred_at' => now(),
        ]);

        return [
            'status' => $status,
            'error' => $error,
            'job' => $job,
            'event' => $event,
            'feed_url' => $feedUrl,
            'http_status' => $httpStatus,
            'summary' => $summary,
        ];
    }

    private function publicUrl(?string $url): string
    {
        $value = trim((string) $url);

        if ($value === '') {
            throw new RuntimeException('Informe a URL pública do XML/feed antes de sincronizar.');
        }

        if (! Str::startsWith($value, ['http://', 'https://'])) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! filter_var($value, FILTER_VALIDATE_URL) || ! is_string($host) || $host === '') {
            throw new RuntimeException('Informe uma URL pública válida.');
        }

        $host = mb_strtolower($host);
        $blockedHosts = ['localhost', '127.0.0.1', '::1'];
        $isPublicIp = ! filter_var($host, FILTER_VALIDATE_IP)
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if (in_array($host, $blockedHosts, true) || str_ends_with($host, '.local') || ! $isPublicIp) {
            throw new RuntimeException('Use uma URL pública da loja.');
        }

        return $value;
    }
}
