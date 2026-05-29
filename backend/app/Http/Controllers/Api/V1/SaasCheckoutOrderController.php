<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingSubscription;
use App\Models\CheckoutAcceptance;
use App\Models\CheckoutSession;
use App\Models\MerchantCompany;
use App\Services\CheckoutPaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaasCheckoutOrderController extends Controller
{
    public function __construct(private readonly CheckoutPaymentManager $checkoutPayments) {}

    public function index(Request $request): array
    {
        $this->ensureAdmin($request);

        $sessions = CheckoutSession::query()
            ->with(['merchant', 'company', 'user', 'acceptance'])
            ->latest('id')
            ->limit(150)
            ->get();

        return [
            'data' => $sessions->map(fn (CheckoutSession $session): array => $this->serializeRow($session))->values(),
        ];
    }

    public function show(Request $request, CheckoutSession $checkoutSession): array
    {
        $this->ensureAdmin($request);

        $checkoutSession->load(['merchant', 'company', 'user', 'acceptance', 'billingSubscription']);

        return [
            'data' => [
                ...$this->serializeRow($checkoutSession),
                'lead' => [
                    'name' => $checkoutSession->lead_name,
                    'company' => $checkoutSession->lead_company,
                    'email' => $checkoutSession->lead_email,
                    'phone' => $checkoutSession->lead_phone,
                ],
                'merchant' => [
                    'id' => $checkoutSession->merchant?->id,
                    'name' => $checkoutSession->merchant?->name,
                    'slug' => $checkoutSession->merchant?->slug,
                    'billing_status' => $checkoutSession->merchant?->billing_status,
                ],
                'company' => $checkoutSession->company ? $this->serializeCompany($checkoutSession->company) : null,
                'user' => [
                    'id' => $checkoutSession->user?->id,
                    'name' => $checkoutSession->user?->name,
                    'email' => $checkoutSession->user?->email,
                    'cpf' => $checkoutSession->user?->cpf,
                    'role' => $checkoutSession->user?->role,
                    'status' => $checkoutSession->user?->status,
                ],
                'provider' => [
                    'key' => $checkoutSession->provider,
                    'label' => $this->providerLabel($checkoutSession),
                    'order_code' => $checkoutSession->provider_order_code,
                    'order_id' => $checkoutSession->provider_order_id,
                    'charge_id' => $checkoutSession->provider_charge_id,
                    'last_sync_at' => $checkoutSession->last_provider_sync_at?->toISOString(),
                ],
                'acceptance' => $this->serializeAcceptance($checkoutSession->acceptance),
                'billing_subscription' => $this->serializeSubscription($checkoutSession->billingSubscription),
                'failure' => data_get($checkoutSession->metadata, 'failure'),
                'payment_snapshot' => data_get($checkoutSession->metadata, 'payment_snapshot'),
                'provider_payload' => data_get($checkoutSession->metadata, 'provider_payload'),
                'last_webhook_payload' => data_get($checkoutSession->metadata, 'last_webhook_payload'),
                'metadata' => Arr::wrap($checkoutSession->metadata),
                'timestamps' => [
                    'created_at' => $checkoutSession->created_at?->toISOString(),
                    'updated_at' => $checkoutSession->updated_at?->toISOString(),
                    'paid_at' => $checkoutSession->paid_at?->toISOString(),
                    'expires_at' => $checkoutSession->expires_at?->toISOString(),
                ],
            ],
        ];
    }

    private function serializeRow(CheckoutSession $session): array
    {
        return [
            'id' => $session->id,
            'reference' => $session->public_reference,
            'created_at' => $session->created_at?->toISOString(),
            'lead_name' => $session->lead_name,
            'lead_email' => $session->lead_email,
            'lead_company' => $session->lead_company,
            'company_document' => $session->acceptance?->company_document ?: $session->company?->document,
            'plan_code' => $session->plan_code,
            'plan_name' => $session->plan_name,
            'amount_cents' => $session->amount_cents,
            'currency' => $session->currency,
            'provider' => $session->provider,
            'provider_label' => $this->providerLabel($session),
            'payment_method' => $session->payment_method,
            'status' => $session->status,
            'status_label' => $this->statusLabel($session->status),
            'failure_reason' => $this->failureReason($session),
            'paid_at' => $session->paid_at?->toISOString(),
            'expires_at' => $session->expires_at?->toISOString(),
            'merchant' => [
                'id' => $session->merchant?->id,
                'name' => $session->merchant?->name,
            ],
            'company' => [
                'id' => $session->company?->id,
                'name' => $session->company?->name,
                'access_code' => $session->company?->access_code,
                'status' => $session->company?->status,
            ],
        ];
    }

    private function serializeCompany(MerchantCompany $company): array
    {
        return [
            'id' => $company->id,
            'access_code' => $company->access_code,
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'document' => $company->document,
            'zip_code' => $company->zip_code,
            'street' => $company->street,
            'number' => $company->number,
            'complement' => $company->complement,
            'district' => $company->district,
            'city' => $company->city,
            'state' => $company->state,
            'country' => $company->country,
            'domain' => $company->domain,
            'platform' => $company->platform,
            'bigshop_discount_active' => (bool) $company->bigshop_discount_active,
            'external_store_id' => $company->external_store_id,
            'status' => $company->status,
        ];
    }

    private function serializeAcceptance(?CheckoutAcceptance $acceptance): ?array
    {
        if (! $acceptance) {
            return null;
        }

        return [
            'id' => $acceptance->id,
            'lead_email' => $acceptance->lead_email,
            'company_document' => $acceptance->company_document,
            'terms_version' => $acceptance->terms_version,
            'privacy_version' => $acceptance->privacy_version,
            'accepted_terms' => $acceptance->accepted_terms,
            'accepted_at' => $acceptance->accepted_at?->toISOString(),
            'ip_address' => $acceptance->ip_address,
            'user_agent' => $acceptance->user_agent,
            'metadata' => $acceptance->metadata,
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
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'plan_code' => $subscription->plan_code,
            'billing_cycle' => $subscription->billing_cycle,
            'status' => $subscription->status,
            'auto_renewal_enabled' => $subscription->auto_renewal_enabled,
            'amount_cents' => $subscription->amount_cents,
            'next_charge_at' => $subscription->next_charge_at?->toISOString(),
            'cancel_requested_at' => $subscription->cancel_requested_at?->toISOString(),
            'cancelled_at' => $subscription->cancelled_at?->toISOString(),
            'metadata' => $subscription->metadata,
        ];
    }

    private function failureReason(CheckoutSession $session): ?string
    {
        $metadata = Arr::wrap($session->metadata);

        return data_get($metadata, 'failure.message')
            ?: data_get($metadata, 'payment_snapshot.status_detail')
            ?: data_get($metadata, 'provider_payload.status_detail')
            ?: data_get($metadata, 'provider_payload.charges.0.last_transaction.gateway_response.errors.0.message')
            ?: data_get($metadata, 'provider_payload.charges.0.last_transaction.acquirer_message')
            ?: (in_array($session->status, [
                CheckoutSession::STATUS_FAILED,
                CheckoutSession::STATUS_CANCELLED,
                CheckoutSession::STATUS_EXPIRED,
                CheckoutSession::STATUS_REFUNDED,
            ], true) ? $this->statusLabel($session->status) : null);
    }

    private function providerLabel(CheckoutSession $session): string
    {
        try {
            return $this->checkoutPayments->provider($session->provider)->label();
        } catch (\Throwable) {
            return $session->provider;
        }
    }

    private function statusLabel(string $status): string
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

    private function ensureAdmin(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'support'], true)) {
            throw new HttpException(403, 'Acesso restrito ao painel SaaS.');
        }
    }
}
