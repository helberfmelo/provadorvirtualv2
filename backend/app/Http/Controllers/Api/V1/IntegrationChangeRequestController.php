<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntegrationChangeRequest;
use App\Models\MerchantCompany;
use App\Services\Audit\AuditLogger;
use App\Services\TransactionalEmailService;
use App\Support\ActiveTenant;
use App\Support\CheckoutPlanCatalog;
use App\Support\PlatformCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IntegrationChangeRequestController extends Controller
{
    public function store(Request $request)
    {
        $merchant = app(ActiveTenant::class)->merchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);

        abort_if(! $company, 404, 'Empresa ativa não encontrada.');

        $data = $request->validate([
            'to_platform' => ['required', 'string', Rule::in(PlatformCatalog::keys())],
            'accepted_terms' => ['accepted'],
        ]);

        if ($company->platform !== 'bigshop' || ! $company->bigshop_discount_active) {
            throw ValidationException::withMessages([
                'to_platform' => ['Esta empresa pode trocar a plataforma diretamente no painel.'],
            ]);
        }

        if ($data['to_platform'] === 'bigshop') {
            throw ValidationException::withMessages([
                'to_platform' => ['Escolha uma plataforma diferente da BigShop para solicitar a troca.'],
            ]);
        }

        $existing = IntegrationChangeRequest::query()
            ->where('merchant_company_id', $company->id)
            ->whereIn('status', [
                IntegrationChangeRequest::STATUS_PENDING,
                IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED,
                IntegrationChangeRequest::STATUS_APPROVED,
            ])
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'data' => $this->serialize($existing->load(['merchant', 'company', 'user', 'auditLogs.user']), false),
                'message' => 'Já existe uma solicitação de troca em andamento para esta loja.',
            ], 200);
        }

        $financialSummary = $this->financialSummary($company->platform, $data['to_platform']);
        $changeRequest = IntegrationChangeRequest::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $request->user()?->id,
            'from_platform' => $company->platform,
            'to_platform' => $data['to_platform'],
            'status' => IntegrationChangeRequest::STATUS_PENDING,
            'terms_version' => 'bigshop-change-2026-05-29',
            'terms_accepted_at' => now(),
            'requested_at' => now(),
            'metadata' => [
                'bigshop_discount_active' => (bool) $company->bigshop_discount_active,
                'company_platform' => $company->platform,
                'financial_summary' => $financialSummary,
            ],
        ]);

        app(AuditLogger::class)->log($request, $merchant, 'integration_change.requested', 'integrations', 'warning', [
            'merchant_company_id' => $company->id,
            'from_platform' => $company->platform,
            'to_platform' => $data['to_platform'],
            'financial_summary' => $financialSummary,
        ], $changeRequest);

        app(AuditLogger::class)->log($request, $merchant, 'integration_change.terms_accepted', 'integrations', 'info', [
            'merchant_company_id' => $company->id,
            'terms_version' => $changeRequest->terms_version,
            'accepted_at' => $changeRequest->terms_accepted_at?->toISOString(),
        ], $changeRequest);

        $this->notifyChangeRequest(TransactionalEmailService::CODE_BIGSHOP_CHANGE_REQUESTED, $changeRequest);

        return response()->json([
            'data' => $this->serialize($changeRequest->load(['merchant', 'company', 'user', 'auditLogs.user']), false),
        ], 201);
    }

    public function current(Request $request): array
    {
        $merchant = app(ActiveTenant::class)->merchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);

        abort_if(! $company, 404, 'Empresa ativa não encontrada.');

        $changeRequest = IntegrationChangeRequest::query()
            ->with(['merchant', 'company', 'user', 'auditLogs.user'])
            ->where('merchant_company_id', $company->id)
            ->orderByRaw("case when status in ('pending', 'payment_requested', 'approved') then 0 else 1 end")
            ->latest('id')
            ->first();

        return [
            'data' => $changeRequest ? $this->serialize($changeRequest, false) : null,
        ];
    }

    public function index(Request $request): array
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                IntegrationChangeRequest::STATUS_PENDING,
                IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED,
                IntegrationChangeRequest::STATUS_APPROVED,
                IntegrationChangeRequest::STATUS_COMPLETED,
                IntegrationChangeRequest::STATUS_CANCELLED,
            ])],
            'company_id' => ['nullable', 'integer', 'exists:merchant_companies,id'],
        ]);

        $requests = IntegrationChangeRequest::query()
            ->with(['merchant', 'company', 'user', 'auditLogs.user'])
            ->when(filled($data['status'] ?? null), fn ($query) => $query->where('status', $data['status']))
            ->when(filled($data['company_id'] ?? null), fn ($query) => $query->where('merchant_company_id', $data['company_id']))
            ->orderByRaw("case when status in ('pending', 'payment_requested', 'approved') then 0 else 1 end")
            ->latest('id')
            ->limit(80)
            ->get();

        return [
            'data' => $requests->map(fn (IntegrationChangeRequest $changeRequest): array => $this->serialize($changeRequest))->values(),
        ];
    }

    public function update(Request $request, IntegrationChangeRequest $integrationChangeRequest)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                IntegrationChangeRequest::STATUS_PENDING,
                IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED,
                IntegrationChangeRequest::STATUS_APPROVED,
                IntegrationChangeRequest::STATUS_COMPLETED,
                IntegrationChangeRequest::STATUS_CANCELLED,
            ])],
            'payment_link' => ['nullable', 'url', 'max:255'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'apply_change' => ['nullable', 'boolean'],
        ]);

        $status = $data['status'] ?? $integrationChangeRequest->status;
        $previousStatus = $integrationChangeRequest->status;
        $previousPaymentLink = $integrationChangeRequest->payment_link;

        $integrationChangeRequest->forceFill([
            'status' => $status,
            'payment_link' => array_key_exists('payment_link', $data)
                ? ($data['payment_link'] ?: null)
                : $integrationChangeRequest->payment_link,
            'admin_notes' => array_key_exists('admin_notes', $data)
                ? ($data['admin_notes'] ?: null)
                : $integrationChangeRequest->admin_notes,
            'resolved_at' => in_array($status, [
                IntegrationChangeRequest::STATUS_COMPLETED,
                IntegrationChangeRequest::STATUS_CANCELLED,
            ], true) ? now() : null,
        ])->save();

        $this->auditUpdate($request, $integrationChangeRequest->refresh(), $previousStatus, $previousPaymentLink, $data);

        if (
            $status === IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED
            && ($previousStatus !== $status || $previousPaymentLink !== $integrationChangeRequest->payment_link)
        ) {
            $this->notifyChangeRequest(
                TransactionalEmailService::CODE_BIGSHOP_CHANGE_PAYMENT_PENDING,
                $integrationChangeRequest,
            );
        }

        if (($data['apply_change'] ?? false) && $status === IntegrationChangeRequest::STATUS_COMPLETED) {
            $this->applyChange($integrationChangeRequest->refresh(), $request);
            $this->notifyChangeRequest(
                TransactionalEmailService::CODE_BIGSHOP_CHANGE_COMPLETED,
                $integrationChangeRequest->refresh(),
            );
        }

        return response()->json([
            'data' => $this->serialize($integrationChangeRequest->refresh()->load(['merchant', 'company', 'user', 'auditLogs.user'])),
        ]);
    }

    private function applyChange(IntegrationChangeRequest $changeRequest, Request $request): void
    {
        $company = MerchantCompany::query()
            ->with('merchant')
            ->findOrFail($changeRequest->merchant_company_id);
        $previousPlatform = $company->platform;
        $previousBenefit = (bool) $company->bigshop_discount_active;

        $company->forceFill([
            'platform' => $changeRequest->to_platform,
            'bigshop_discount_active' => false,
        ])->save();

        app(AuditLogger::class)->log($request, $company->merchant, 'integration_change.applied', 'integrations', 'info', [
            'merchant_company_id' => $company->id,
            'from_platform' => $previousPlatform,
            'to_platform' => $changeRequest->to_platform,
            'bigshop_discount_was_active' => $previousBenefit,
            'bigshop_discount_active' => false,
        ], $changeRequest);
    }

    private function serialize(IntegrationChangeRequest $changeRequest, bool $includeInternal = true): array
    {
        return [
            'id' => $changeRequest->id,
            'from_platform' => $changeRequest->from_platform,
            'from_platform_label' => $this->platformLabel($changeRequest->from_platform),
            'to_platform' => $changeRequest->to_platform,
            'to_platform_label' => $this->platformLabel($changeRequest->to_platform),
            'status' => $changeRequest->status,
            'status_label' => $this->statusLabel($changeRequest->status),
            'terms_version' => $changeRequest->terms_version,
            'terms_accepted_at' => $changeRequest->terms_accepted_at?->toISOString(),
            'requested_at' => $changeRequest->requested_at?->toISOString(),
            'resolved_at' => $changeRequest->resolved_at?->toISOString(),
            'payment_link' => $changeRequest->payment_link,
            'admin_notes' => $includeInternal ? $changeRequest->admin_notes : null,
            'financial_summary' => data_get($changeRequest->metadata, 'financial_summary'),
            'history' => $this->history($changeRequest),
            'merchant' => [
                'id' => $changeRequest->merchant?->id,
                'name' => $changeRequest->merchant?->name,
                'slug' => $changeRequest->merchant?->slug,
            ],
            'company' => [
                'id' => $changeRequest->company?->id,
                'name' => $changeRequest->company?->name,
                'access_code' => $changeRequest->company?->access_code,
                'domain' => $changeRequest->company?->domain,
                'platform' => $changeRequest->company?->platform,
                'bigshop_discount_active' => (bool) $changeRequest->company?->bigshop_discount_active,
            ],
            'user' => [
                'id' => $changeRequest->user?->id,
                'name' => $changeRequest->user?->name,
                'email' => $changeRequest->user?->email,
            ],
        ];
    }

    private function auditUpdate(
        Request $request,
        IntegrationChangeRequest $changeRequest,
        string $previousStatus,
        ?string $previousPaymentLink,
        array $data,
    ): void {
        $changeRequest->loadMissing('merchant');
        $event = match ($changeRequest->status) {
            IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED => 'integration_change.payment_requested',
            IntegrationChangeRequest::STATUS_APPROVED => 'integration_change.approved',
            IntegrationChangeRequest::STATUS_COMPLETED => 'integration_change.completed',
            IntegrationChangeRequest::STATUS_CANCELLED => 'integration_change.cancelled',
            default => 'integration_change.updated',
        };

        app(AuditLogger::class)->log($request, $changeRequest->merchant, $event, 'integrations', 'info', [
            'merchant_company_id' => $changeRequest->merchant_company_id,
            'status_from' => $previousStatus,
            'status_to' => $changeRequest->status,
            'has_payment_link' => filled($changeRequest->payment_link),
            'payment_link_host' => $this->linkHost($changeRequest->payment_link),
            'payment_link_changed' => $previousPaymentLink !== $changeRequest->payment_link,
            'admin_notes_updated' => array_key_exists('admin_notes', $data),
            'apply_change_requested' => (bool) ($data['apply_change'] ?? false),
        ], $changeRequest);
    }

    private function notifyChangeRequest(string $code, IntegrationChangeRequest $changeRequest): void
    {
        $changeRequest->loadMissing(['company', 'user']);

        if (! $changeRequest->company) {
            return;
        }

        try {
            app(TransactionalEmailService::class)->sendForCompany(
                $code,
                $changeRequest->company,
                $changeRequest->user,
                $this->notificationContext($changeRequest),
            );
        } catch (\Throwable $exception) {
            Log::warning('Falha ao registrar e-mail transacional de troca BigShop.', [
                'code' => $code,
                'integration_change_request_id' => $changeRequest->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function notificationContext(IntegrationChangeRequest $changeRequest): array
    {
        return [
            'plataforma_atual' => $this->platformLabel($changeRequest->from_platform),
            'nova_plataforma' => $this->platformLabel($changeRequest->to_platform),
            'status_solicitacao' => $this->statusLabel($changeRequest->status),
            'link_pagamento' => $changeRequest->payment_link ?: 'Aguardando envio pelo SaaS.',
            'link_integracoes' => $this->frontendUrl('/app/integracoes'),
            'resumo_financeiro' => data_get($changeRequest->metadata, 'financial_summary.short_text')
                ?: 'Troca sujeita a revisão comercial do SaaS.',
        ];
    }

    private function financialSummary(string $fromPlatform, string $toPlatform): array
    {
        $pricing = CheckoutPlanCatalog::pricingConfig();
        $fromKey = $fromPlatform === 'bigshop' ? 'bigshop' : 'default';
        $toKey = $toPlatform === 'bigshop' ? 'bigshop' : 'default';
        $fromAnnual = $pricing[$fromKey]['annual'];
        $toAnnual = $pricing[$toKey]['annual'];
        $fromMonthly = $pricing[$fromKey]['monthly'];
        $toMonthly = $pricing[$toKey]['monthly'];
        $annualMonthlyDifference = $toAnnual['monthly_cents'] - $fromAnnual['monthly_cents'];
        $monthlyDifference = $toMonthly['monthly_cents'] - $fromMonthly['monthly_cents'];

        return [
            'currency' => 'BRL',
            'from_label' => $pricing[$fromKey]['label'],
            'to_label' => $pricing[$toKey]['label'],
            'annual_from_monthly_cents' => $fromAnnual['monthly_cents'],
            'annual_to_monthly_cents' => $toAnnual['monthly_cents'],
            'annual_monthly_difference_cents' => $annualMonthlyDifference,
            'annual_total_difference_cents' => $toAnnual['card_total_cents'] - $fromAnnual['card_total_cents'],
            'monthly_from_cents' => $fromMonthly['monthly_cents'],
            'monthly_to_cents' => $toMonthly['monthly_cents'],
            'monthly_difference_cents' => $monthlyDifference,
            'short_text' => 'Referência anual: '.$this->money($fromAnnual['monthly_cents']).'/mês para '.$this->money($toAnnual['monthly_cents']).'/mês. Diferença estimada: '.$this->money($annualMonthlyDifference).'/mês, sujeita ao ciclo e pagamento.',
        ];
    }

    private function history(IntegrationChangeRequest $changeRequest): array
    {
        if (! $changeRequest->relationLoaded('auditLogs')) {
            return [];
        }

        return $changeRequest->auditLogs
            ->map(fn ($log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'label' => $this->historyLabel($log->event),
                'severity' => $log->severity,
                'actor_name' => $log->user?->name,
                'occurred_at' => $log->created_at?->toISOString(),
                'metadata' => Arr::only($log->metadata ?: [], [
                    'from_platform',
                    'to_platform',
                    'status_from',
                    'status_to',
                    'terms_version',
                    'accepted_at',
                    'has_payment_link',
                    'payment_link_host',
                    'payment_link_changed',
                    'admin_notes_updated',
                    'apply_change_requested',
                    'bigshop_discount_was_active',
                    'bigshop_discount_active',
                ]),
            ])
            ->values()
            ->all();
    }

    private function historyLabel(string $event): string
    {
        return [
            'integration_change.requested' => 'Solicitação criada',
            'integration_change.terms_accepted' => 'Termos aceitos',
            'integration_change.updated' => 'Solicitação atualizada',
            'integration_change.payment_requested' => 'Pagamento solicitado',
            'integration_change.approved' => 'Troca aprovada',
            'integration_change.completed' => 'Troca concluída',
            'integration_change.applied' => 'Plataforma aplicada',
            'integration_change.cancelled' => 'Solicitação cancelada',
        ][$event] ?? $event;
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $base.'/'.ltrim($path, '/');
    }

    private function linkHost(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) ?: null;
    }

    private function money(int $amountCents): string
    {
        return 'R$ '.number_format($amountCents / 100, 2, ',', '.');
    }

    private function platformLabel(string $platform): string
    {
        return PlatformCatalog::find($platform)['name'] ?? $platform;
    }

    private function statusLabel(string $status): string
    {
        return [
            IntegrationChangeRequest::STATUS_PENDING => 'Pendente',
            IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED => 'Link enviado',
            IntegrationChangeRequest::STATUS_APPROVED => 'Aprovada',
            IntegrationChangeRequest::STATUS_COMPLETED => 'Concluída',
            IntegrationChangeRequest::STATUS_CANCELLED => 'Cancelada',
        ][$status] ?? $status;
    }

    private function ensureAdmin(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'support'], true)) {
            throw new HttpException(403, 'Acesso restrito ao painel SaaS.');
        }
    }
}
