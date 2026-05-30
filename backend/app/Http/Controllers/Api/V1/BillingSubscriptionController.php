<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingSubscription;
use App\Models\CheckoutSession;
use App\Models\IntegrationChangeRequest;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Services\Audit\AuditLogger;
use App\Services\CheckoutPaymentManager;
use App\Support\ActiveTenant;
use App\Support\PlatformCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use RuntimeException;

class BillingSubscriptionController extends Controller
{
    public function __construct(private readonly CheckoutPaymentManager $checkoutPayments) {}

    public function show(Request $request): array
    {
        $tenant = app(ActiveTenant::class);
        $merchant = $tenant->merchant($request);
        $company = $tenant->company($request, $merchant);
        $subscription = $this->currentSubscription($merchant, $company);
        $checkout = $this->latestCheckout($merchant, $company);

        return [
            'data' => [
                'company' => $this->serializeCompany($merchant, $company),
                'plan' => $this->serializePlan($subscription, $checkout),
                'subscription' => $this->serializeSubscription($subscription),
                'payment_links' => $this->paymentLinks($merchant, $company),
                'commercial_requests' => $this->commercialRequests($merchant, $company),
                'actions' => [
                    'can_disable_auto_renewal' => (bool) $subscription?->auto_renewal_enabled,
                    'financial_changes_managed_by' => 'admin',
                    'financial_changes_note' => 'Mudanças de plano, diferença comercial e liberação de cobrança continuam controladas pelo Admin.',
                ],
            ],
        ];
    }

    public function updateAutoRenewal(Request $request)
    {
        $data = $request->validate([
            'auto_renewal_enabled' => ['required', 'boolean'],
        ]);

        if ((bool) $data['auto_renewal_enabled']) {
            return response()->json([
                'message' => 'A reativação automática da renovação ainda não está disponível pelo painel.',
            ], 422);
        }

        $tenant = app(ActiveTenant::class);
        $merchant = $tenant->merchant($request);
        $company = $tenant->company($request, $merchant);
        $subscription = $this->currentSubscription($merchant, $company);

        if (! $subscription) {
            return response()->json([
                'message' => 'Nenhuma assinatura recorrente encontrada para esta empresa.',
            ], 404);
        }

        try {
            $subscription = $this->checkoutPayments->cancelSubscription($subscription);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return [
            'data' => $this->serializeSubscription($subscription),
        ];
    }

    public function resolvePaymentLink(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['checkout_status', 'commercial_request'])],
            'id' => ['required', 'integer', 'min:1'],
        ]);
        $tenant = app(ActiveTenant::class);
        $merchant = $tenant->merchant($request);
        $company = $tenant->company($request, $merchant);

        if ($data['type'] === 'checkout_status') {
            $checkout = CheckoutSession::query()
                ->where('merchant_id', $merchant->id)
                ->where('merchant_company_id', $company?->id)
                ->findOrFail((int) $data['id']);
            $url = $this->checkoutStatusUrl($checkout);
            $title = $this->checkoutLinkTitle($checkout);

            app(AuditLogger::class)->log($request, $merchant, 'billing.payment_link_opened', 'billing', 'info', [
                'merchant_company_id' => $company?->id,
                'module' => 'billing',
                'action' => 'open_payment_link',
                'link_type' => 'checkout_status',
                'checkout_session_id' => $checkout->id,
                'checkout_reference' => $checkout->public_reference,
                'checkout_status' => $checkout->status,
                'payment_method' => $checkout->payment_method,
                'payment_link_host' => $this->linkHost($url),
            ], $checkout);

            return response()->json([
                'data' => [
                    'title' => $title,
                    'url' => $url,
                ],
            ]);
        }

        $changeRequest = IntegrationChangeRequest::query()
            ->where('merchant_id', $merchant->id)
            ->where('merchant_company_id', $company?->id)
            ->findOrFail((int) $data['id']);

        abort_unless(filled($changeRequest->payment_link), 404, 'Link comercial indisponível.');

        app(AuditLogger::class)->log($request, $merchant, 'billing.payment_link_opened', 'billing', 'info', [
            'merchant_company_id' => $company?->id,
            'module' => 'billing',
            'action' => 'open_payment_link',
            'link_type' => 'commercial_request',
            'integration_change_request_id' => $changeRequest->id,
            'request_status' => $changeRequest->status,
            'payment_link_host' => $this->linkHost($changeRequest->payment_link),
        ], $changeRequest);

        return response()->json([
            'data' => [
                'title' => 'Abrir link comercial',
                'url' => $changeRequest->payment_link,
            ],
        ]);
    }

    private function currentSubscription(Merchant $merchant, ?MerchantCompany $company): ?BillingSubscription
    {
        return BillingSubscription::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn ($query) => $query->where('merchant_company_id', $company->id))
            ->latest('id')
            ->first();
    }

    private function latestCheckout(Merchant $merchant, ?MerchantCompany $company): ?CheckoutSession
    {
        return CheckoutSession::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn ($query) => $query->where('merchant_company_id', $company->id))
            ->latest('id')
            ->first();
    }

    private function serializeCompany(Merchant $merchant, ?MerchantCompany $company): array
    {
        $platform = $company?->platform ?: 'custom';
        $commercialStatus = $this->commercialStatusFor($merchant, $company);

        return [
            'merchant_name' => $merchant->name,
            'merchant_slug' => $merchant->slug,
            'billing_status' => $merchant->billing_status,
            'billing_status_label' => $this->commercialStatusLabel($merchant->billing_status ?: 'active'),
            'company' => [
                'id' => $company?->id,
                'name' => $company?->name,
                'access_code' => $company?->access_code,
                'platform' => $platform,
                'platform_label' => PlatformCatalog::find($platform)['name'] ?? $platform,
                'bigshop_discount_active' => (bool) $company?->bigshop_discount_active,
                'commercial_status' => $commercialStatus,
                'commercial_status_label' => $this->commercialStatusLabel($commercialStatus),
                'bigshop_benefit_label' => $company?->bigshop_discount_active ? 'Preço especial BigShop ativo para esta empresa.' : null,
            ],
        ];
    }

    private function serializePlan(?BillingSubscription $subscription, ?CheckoutSession $checkout): array
    {
        $planCode = $subscription?->plan_code ?: $checkout?->plan_code;
        $billingCycle = $subscription?->billing_cycle
            ?: data_get($checkout?->metadata, 'plan.billing_cycle')
            ?: $checkout?->plan_code;
        $amountCents = $subscription?->amount_cents ?: $checkout?->amount_cents;
        $currency = $subscription?->currency ?: ($checkout?->currency ?: 'BRL');
        $nextDueAt = $subscription?->next_charge_at?->toISOString()
            ?: data_get($checkout?->metadata, 'payment_snapshot.pix.expires_at')
            ?: data_get($checkout?->metadata, 'payment_snapshot.boleto.expires_at');

        return [
            'plan_code' => $planCode,
            'plan_name' => $checkout?->plan_name ?: $this->planName($planCode),
            'billing_cycle' => $billingCycle,
            'billing_cycle_label' => $this->billingCycleLabel($billingCycle),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'provider' => $subscription?->provider ?: $checkout?->provider,
            'provider_label' => $this->providerLabel($subscription?->provider ?: $checkout?->provider),
            'payment_method' => $subscription?->payment_method ?: $checkout?->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($subscription?->payment_method ?: $checkout?->payment_method),
            'status' => $subscription?->status ?: $checkout?->status,
            'status_label' => $subscription ? $this->subscriptionStatusLabel($subscription->status) : $this->checkoutStatusLabel($checkout?->status),
            'started_at' => $subscription?->started_at?->toISOString() ?: $checkout?->paid_at?->toISOString(),
            'next_due_at' => $nextDueAt,
            'cancel_requested_at' => $subscription?->cancel_requested_at?->toISOString(),
            'auto_renewal_enabled' => (bool) $subscription?->auto_renewal_enabled,
        ];
    }

    private function serializeSubscription(?BillingSubscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'provider' => $subscription->provider,
            'plan_code' => $subscription->plan_code,
            'billing_cycle' => $subscription->billing_cycle,
            'payment_method' => $subscription->payment_method,
            'status' => $subscription->status,
            'auto_renewal_enabled' => $subscription->auto_renewal_enabled,
            'amount_cents' => $subscription->amount_cents,
            'currency' => $subscription->currency,
            'next_charge_at' => $subscription->next_charge_at?->toISOString(),
            'started_at' => $subscription->started_at?->toISOString(),
            'cancel_requested_at' => $subscription->cancel_requested_at?->toISOString(),
            'cancelled_at' => $subscription->cancelled_at?->toISOString(),
        ];
    }

    private function paymentLinks(Merchant $merchant, ?MerchantCompany $company): array
    {
        $checkoutLinks = CheckoutSession::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn ($query) => $query->where('merchant_company_id', $company->id))
            ->whereIn('status', [
                CheckoutSession::STATUS_PENDING,
                CheckoutSession::STATUS_CHECKOUT_CREATED,
                CheckoutSession::STATUS_PAID,
                CheckoutSession::STATUS_FAILED,
                CheckoutSession::STATUS_CANCELLED,
                CheckoutSession::STATUS_EXPIRED,
            ])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (CheckoutSession $checkout): array => [
                'id' => $checkout->id,
                'type' => 'checkout_status',
                'title' => $this->checkoutLinkTitle($checkout),
                'description' => $this->checkoutLinkDescription($checkout),
                'status' => $checkout->status,
                'status_label' => $this->checkoutStatusLabel($checkout->status),
                'host' => $this->linkHost($this->checkoutStatusUrl($checkout)),
                'created_at' => $checkout->created_at?->toISOString(),
                'expires_at' => data_get($checkout->metadata, 'payment_snapshot.pix.expires_at')
                    ?: data_get($checkout->metadata, 'payment_snapshot.boleto.expires_at'),
                'access' => [
                    'type' => 'checkout_status',
                    'id' => $checkout->id,
                ],
            ]);
        $commercialLinks = IntegrationChangeRequest::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn ($query) => $query->where('merchant_company_id', $company->id))
            ->whereNotNull('payment_link')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (IntegrationChangeRequest $changeRequest): array => [
                'id' => $changeRequest->id,
                'type' => 'commercial_request',
                'title' => 'Link comercial da troca',
                'description' => 'Pagamento ou etapa comercial liberada pelo Admin para esta empresa.',
                'status' => $changeRequest->status,
                'status_label' => $this->changeRequestStatusLabel($changeRequest->status),
                'host' => $this->linkHost($changeRequest->payment_link),
                'created_at' => $changeRequest->created_at?->toISOString(),
                'expires_at' => null,
                'access' => [
                    'type' => 'commercial_request',
                    'id' => $changeRequest->id,
                ],
            ]);

        return $checkoutLinks
            ->concat($commercialLinks)
            ->sortByDesc(fn (array $item) => $item['created_at'] ?: '')
            ->values()
            ->all();
    }

    private function commercialRequests(Merchant $merchant, ?MerchantCompany $company): array
    {
        return IntegrationChangeRequest::query()
            ->with(['auditLogs.user'])
            ->where('merchant_id', $merchant->id)
            ->when($company, fn ($query) => $query->where('merchant_company_id', $company->id))
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (IntegrationChangeRequest $changeRequest): array => [
                'id' => $changeRequest->id,
                'from_platform' => $changeRequest->from_platform,
                'from_platform_label' => PlatformCatalog::find($changeRequest->from_platform)['name'] ?? $changeRequest->from_platform,
                'to_platform' => $changeRequest->to_platform,
                'to_platform_label' => PlatformCatalog::find($changeRequest->to_platform)['name'] ?? $changeRequest->to_platform,
                'status' => $changeRequest->status,
                'status_label' => $this->changeRequestStatusLabel($changeRequest->status),
                'requested_at' => $changeRequest->requested_at?->toISOString(),
                'resolved_at' => $changeRequest->resolved_at?->toISOString(),
                'financial_summary' => data_get($changeRequest->metadata, 'financial_summary'),
                'payment_link_available' => filled($changeRequest->payment_link),
                'payment_link_host' => $this->linkHost($changeRequest->payment_link),
                'payment_link_access' => filled($changeRequest->payment_link) ? [
                    'type' => 'commercial_request',
                    'id' => $changeRequest->id,
                ] : null,
                'history' => $changeRequest->auditLogs
                    ->map(fn ($log): array => [
                        'id' => $log->id,
                        'event' => $log->event,
                        'label' => $this->changeRequestHistoryLabel($log->event),
                        'severity' => $log->severity,
                        'actor_name' => $log->user?->name,
                        'occurred_at' => $log->created_at?->toISOString(),
                        'metadata' => Arr::only($log->metadata ?: [], [
                            'status_from',
                            'status_to',
                            'payment_link_host',
                            'payment_link_changed',
                            'apply_change_requested',
                            'accepted_at',
                        ]),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    private function commercialStatusFor(Merchant $merchant, ?MerchantCompany $company): string
    {
        if ($company?->platform === 'bigshop' && $company->bigshop_discount_active) {
            return 'bigshop_benefit';
        }

        return $merchant->billing_status ?: ($company?->status ?: 'active');
    }

    private function commercialStatusLabel(?string $status): string
    {
        return [
            'bigshop_benefit' => 'Benefício BigShop',
            'trialing' => 'Trial',
            'active' => 'Comercial ativo',
            'pending_payment' => 'Pagamento pendente',
            'past_due' => 'Em atraso',
            'canceled' => 'Cancelado',
            'inactive' => 'Empresa inativa',
        ][(string) $status] ?? ((string) $status ?: 'Sem status');
    }

    private function planName(?string $planCode): ?string
    {
        return match ($planCode) {
            'annual' => 'Provador Virtual Anual',
            'monthly' => 'Provador Virtual Mensal',
            default => $planCode,
        };
    }

    private function billingCycleLabel(?string $billingCycle): string
    {
        return match ($billingCycle) {
            'annual' => 'Anual',
            'monthly' => 'Mensal',
            default => $billingCycle ?: 'Sob consulta',
        };
    }

    private function providerLabel(?string $provider): ?string
    {
        if (! $provider) {
            return null;
        }

        return $this->checkoutPayments->provider($provider)->label();
    }

    private function paymentMethodLabel(?string $method): ?string
    {
        return [
            'credit_card' => 'Cartão',
            'pix' => 'Pix',
            'boleto' => 'Boleto',
        ][(string) $method] ?? $method;
    }

    private function subscriptionStatusLabel(?string $status): string
    {
        return [
            'authorized' => 'Ativa',
            'pending' => 'Pendente',
            'paused' => 'Pausada',
            'canceled' => 'Cancelada',
        ][(string) $status] ?? ((string) $status ?: 'Sem status');
    }

    private function checkoutStatusLabel(?string $status): string
    {
        return match ($status) {
            CheckoutSession::STATUS_PAID => 'Pago',
            CheckoutSession::STATUS_FAILED => 'Falhou',
            CheckoutSession::STATUS_CANCELLED => 'Cancelado',
            CheckoutSession::STATUS_EXPIRED => 'Expirado',
            CheckoutSession::STATUS_REFUNDED => 'Estornado',
            CheckoutSession::STATUS_CHECKOUT_CREATED => 'Iniciado',
            default => 'Pendente',
        };
    }

    private function changeRequestStatusLabel(?string $status): string
    {
        return [
            IntegrationChangeRequest::STATUS_PENDING => 'Pendente',
            IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED => 'Link enviado',
            IntegrationChangeRequest::STATUS_APPROVED => 'Aprovada',
            IntegrationChangeRequest::STATUS_COMPLETED => 'Concluída',
            IntegrationChangeRequest::STATUS_CANCELLED => 'Cancelada',
        ][(string) $status] ?? ((string) $status ?: 'Sem status');
    }

    private function changeRequestHistoryLabel(string $event): string
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

    private function checkoutLinkTitle(CheckoutSession $checkout): string
    {
        return match ($checkout->status) {
            CheckoutSession::STATUS_PAID => 'Ver comprovante da contratação',
            CheckoutSession::STATUS_FAILED => 'Revisar tentativa de cobrança',
            CheckoutSession::STATUS_CANCELLED => 'Ver cobrança cancelada',
            CheckoutSession::STATUS_EXPIRED => 'Ver cobrança expirada',
            default => 'Acompanhar cobrança em aberto',
        };
    }

    private function checkoutLinkDescription(CheckoutSession $checkout): string
    {
        return match ($checkout->payment_method) {
            'pix' => 'Abra o resumo com QR Code e vencimento do Pix.',
            'boleto' => 'Abra o resumo com boleto e vencimento.',
            'credit_card' => 'Abra o resumo da contratação registrada pelo SaaS.',
            default => 'Abra o resumo financeiro desta contratação.',
        };
    }

    private function checkoutStatusUrl(CheckoutSession $checkout): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $base.'/checkout/sucesso?'.http_build_query(['ref' => $checkout->public_reference]);
    }

    private function linkHost(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) ?: null;
    }
}
