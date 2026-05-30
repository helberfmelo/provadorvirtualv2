<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LegalAcceptance;
use App\Models\MerchantCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaasAuditController extends Controller
{
    public function index(Request $request): array
    {
        $filters = $this->filters($request);
        $logs = $this->auditLogsQuery($filters)
            ->with(['company:id,name,access_code', 'user:id,name,email'])
            ->orderByDesc('id')
            ->limit($filters['limit'])
            ->get();
        $acceptances = $this->acceptancesQuery($filters)
            ->with(['company:id,name,access_code', 'user:id,name,email'])
            ->orderByDesc('id')
            ->limit(min($filters['limit'], 100))
            ->get();

        return [
            'data' => [
                'summary' => [
                    'logs' => $logs->count(),
                    'critical_logs' => $logs->whereIn('severity', ['warning', 'error'])->count(),
                    'acceptances' => $acceptances->count(),
                    'companies' => MerchantCompany::query()->count(),
                ],
                'logs' => $logs->map(fn (AuditLog $log): array => $this->serializeLog($log))->values(),
                'acceptances' => $acceptances->map(fn (LegalAcceptance $acceptance): array => $this->serializeAcceptance($acceptance))->values(),
                'filters' => [
                    'companies' => MerchantCompany::query()
                        ->select(['id', 'name', 'access_code'])
                        ->orderBy('name')
                        ->limit(300)
                        ->get(),
                    'categories' => AuditLog::query()
                        ->select('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category')
                        ->filter()
                        ->values(),
                    'modules' => AuditLog::query()
                        ->select('module')
                        ->distinct()
                        ->orderBy('module')
                        ->pluck('module')
                        ->filter()
                        ->values(),
                    'events' => AuditLog::query()
                        ->select('event')
                        ->distinct()
                        ->orderBy('event')
                        ->limit(300)
                        ->pluck('event')
                        ->filter()
                        ->values(),
                    'document_types' => LegalAcceptance::query()
                        ->select('document_type')
                        ->distinct()
                        ->orderBy('document_type')
                        ->pluck('document_type')
                        ->filter()
                        ->values(),
                ],
            ],
        ];
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $logs = $this->auditLogsQuery($filters)
            ->with(['company:id,name,access_code', 'user:id,name,email'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get();
        $acceptances = $this->acceptancesQuery($filters)
            ->with(['company:id,name,access_code', 'user:id,name,email'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get();
        $filename = 'auditoria-saas-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($logs, $acceptances): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'tipo',
                'empresa',
                'codigo_empresa',
                'ator',
                'e_mail_ator',
                'evento',
                'categoria',
                'modulo',
                'acao',
                'severidade',
                'quando',
                'antes',
                'depois',
                'contexto',
                'versao_termos',
                'versao_privacidade',
                'ip_mascarado',
            ], ';');

            foreach ($logs as $log) {
                $serialized = $this->serializeLog($log);
                fputcsv($handle, [
                    'auditoria',
                    $serialized['company']['name'] ?? '',
                    $serialized['company']['access_code'] ?? '',
                    $serialized['user']['name'] ?? '',
                    $serialized['user']['email'] ?? '',
                    $serialized['event_label'],
                    $serialized['category'],
                    $serialized['module'],
                    $serialized['action'] ?? '',
                    $serialized['severity'],
                    $serialized['created_at'],
                    json_encode($serialized['before'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($serialized['after'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($serialized['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    '',
                    '',
                    '',
                ], ';');
            }

            foreach ($acceptances as $acceptance) {
                $serialized = $this->serializeAcceptance($acceptance);
                fputcsv($handle, [
                    'aceite',
                    $serialized['company']['name'] ?? '',
                    $serialized['company']['access_code'] ?? '',
                    $serialized['user']['name'] ?? '',
                    $serialized['user']['email'] ?? '',
                    $serialized['document_label'],
                    'legal',
                    $serialized['context'],
                    $serialized['source_label'],
                    'info',
                    $serialized['accepted_at'],
                    '',
                    '',
                    json_encode($serialized['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $serialized['terms_version'],
                    $serialized['privacy_version'],
                    $serialized['ip_masked'],
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'merchant_company_id' => ['nullable', 'integer', 'exists:merchant_companies,id'],
            'category' => ['nullable', 'string', 'max:80'],
            'module' => ['nullable', 'string', 'max:80'],
            'event' => ['nullable', 'string', 'max:120'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]) + [
            'limit' => max(1, min((int) $request->integer('limit', 80), 200)),
        ];
    }

    private function auditLogsQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->when($filters['merchant_company_id'] ?? null, fn (Builder $query, int $companyId) => $query->where('merchant_company_id', $companyId))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $query->where('module', $module))
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $dateTo) => $query->whereDate('created_at', '<=', $dateTo));
    }

    private function acceptancesQuery(array $filters): Builder
    {
        return LegalAcceptance::query()
            ->when($filters['merchant_company_id'] ?? null, fn (Builder $query, int $companyId) => $query->where('merchant_company_id', $companyId))
            ->when($filters['document_type'] ?? null, fn (Builder $query, string $documentType) => $query->where('document_type', $documentType))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $dateFrom) => $query->whereDate('accepted_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $dateTo) => $query->whereDate('accepted_at', '<=', $dateTo));
    }

    private function serializeLog(AuditLog $log): array
    {
        $metadata = $log->metadata ?? [];
        $before = $metadata['before'] ?? $metadata['before_access'] ?? null;
        $after = $metadata['after'] ?? $metadata['after_access'] ?? null;

        unset($metadata['before'], $metadata['after'], $metadata['before_access'], $metadata['after_access']);

        return [
            'id' => $log->id,
            'event' => $log->event,
            'event_label' => $this->eventLabel($log->event),
            'category' => $log->category,
            'module' => $log->module,
            'action' => $log->action,
            'severity' => $log->severity,
            'company' => $log->company ? [
                'id' => $log->company->id,
                'name' => $log->company->name,
                'access_code' => $log->company->access_code,
            ] : null,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
            'before' => $before,
            'after' => $after,
            'context' => $metadata,
            'created_at' => $log->created_at?->toISOString(),
        ];
    }

    private function serializeAcceptance(LegalAcceptance $acceptance): array
    {
        return [
            'id' => $acceptance->id,
            'context' => $acceptance->context,
            'context_label' => $this->contextLabel($acceptance->context),
            'document_type' => $acceptance->document_type,
            'document_label' => $this->documentLabel($acceptance->document_type),
            'source_label' => $this->sourceLabel($acceptance->context),
            'terms_version' => $acceptance->terms_version,
            'privacy_version' => $acceptance->privacy_version,
            'accepted_at' => $acceptance->accepted_at?->toISOString(),
            'ip_masked' => $this->maskIp($acceptance->ip_address),
            'company' => $acceptance->company ? [
                'id' => $acceptance->company->id,
                'name' => $acceptance->company->name,
                'access_code' => $acceptance->company->access_code,
            ] : null,
            'user' => $acceptance->user ? [
                'id' => $acceptance->user->id,
                'name' => $acceptance->user->name,
                'email' => $acceptance->user->email,
            ] : null,
            'metadata' => $acceptance->metadata ?? [],
        ];
    }

    private function eventLabel(string $event): string
    {
        return [
            'legal.checkout_terms_accepted' => 'Aceite do checkout',
            'widget_install.published' => 'Widget publicado',
            'widget_install.draft_saved' => 'Rascunho do widget salvo',
            'widget_install.discarded' => 'Rascunho do widget descartado',
            'integration.updated' => 'Integração atualizada',
            'imports.committed' => 'Importação executada',
            'measurement_table.imported' => 'Tabelas importadas',
            'measurement_table.updated' => 'Tabela alterada',
            'product.bulk_measurement_table_linked' => 'Vínculo em massa aplicado',
            'product.bulk_measurement_table_undone' => 'Vínculo em massa desfeito',
        ][$event] ?? $event;
    }

    private function contextLabel(string $context): string
    {
        return [
            'checkout' => 'Checkout',
            'integration_change' => 'Troca BigShop',
        ][$context] ?? $context;
    }

    private function documentLabel(string $documentType): string
    {
        return [
            'terms_and_privacy' => 'Termos e privacidade',
            'bigshop_change_terms' => 'Termos de troca BigShop',
        ][$documentType] ?? $documentType;
    }

    private function sourceLabel(string $context): string
    {
        return [
            'checkout' => 'Contratação pública',
            'integration_change' => 'Solicitação de troca',
        ][$context] ?? $context;
    }

    private function maskIp(?string $ipAddress): ?string
    {
        if (! $ipAddress) {
            return null;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ipAddress);

            return $parts[0].'.'.$parts[1].'.'.$parts[2].'.xxx';
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ipAddress);

            return implode(':', array_slice($parts, 0, 4)).':xxxx';
        }

        return null;
    }
}
