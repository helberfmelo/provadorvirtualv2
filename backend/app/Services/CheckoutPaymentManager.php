<?php

namespace App\Services;

use App\Contracts\CheckoutPaymentProvider;
use App\Models\BillingSubscription;
use App\Models\CheckoutSession;
use App\Models\PaymentEvent;
use App\Models\SaasSetting;
use Illuminate\Support\Str;
use RuntimeException;

class CheckoutPaymentManager
{
    public const SETTING_PAYMENT_PROVIDER = 'checkout.payment_provider';

    public const PROVIDER_PAGARME = 'pagarme';

    public const PROVIDER_MERCADO_PAGO = 'mercado_pago';

    public function __construct(
        private readonly PagarMeCheckoutService $pagarMe,
        private readonly MercadoPagoCheckoutService $mercadoPago,
    ) {}

    public function currentProviderKey(): string
    {
        $configured = SaasSetting::getValue(
            self::SETTING_PAYMENT_PROVIDER,
            config('services.checkout.default_provider', self::PROVIDER_MERCADO_PAGO),
        );

        return $this->normalizeProviderKey((string) $configured);
    }

    public function setCurrentProvider(string $provider): string
    {
        $normalized = $this->normalizeProviderKey($provider);
        SaasSetting::setValue(self::SETTING_PAYMENT_PROVIDER, $normalized);

        return $normalized;
    }

    public function provider(?string $provider = null): CheckoutPaymentProvider
    {
        return match ($this->normalizeProviderKey($provider ?: $this->currentProviderKey())) {
            self::PROVIDER_PAGARME => $this->pagarMe,
            self::PROVIDER_MERCADO_PAGO => $this->mercadoPago,
            default => throw new RuntimeException('Operadora de pagamento indisponivel.'),
        };
    }

    public function configuration(): array
    {
        $provider = $this->provider();

        return [
            ...$provider->configuration(),
            'active_provider' => $provider->key(),
            'available_providers' => $this->availableProviders(),
        ];
    }

    public function availableProviders(): array
    {
        return collect([$this->mercadoPago, $this->pagarMe])
            ->map(fn (CheckoutPaymentProvider $provider): array => [
                'key' => $provider->key(),
                'label' => $provider->label(),
                'configured' => $this->providerConfigured($provider->key()),
                'credit_card_enabled' => (bool) data_get($provider->configuration(), 'credit_card_enabled', false),
                'payment_methods' => data_get($provider->configuration(), 'payment_methods', []),
            ])
            ->values()
            ->all();
    }

    public function createOrder(CheckoutSession $session, array $buyerData): CheckoutSession
    {
        return $this->provider($session->provider)->createOrder($session, $buyerData);
    }

    public function handleWebhook(string $provider, array $payload, array $headers, string $rawBody, array $query = []): PaymentEvent
    {
        return $this->provider($provider)->handleWebhook($payload, $headers, $rawBody, $query);
    }

    public function publicCheckoutUrl(string $reference, ?string $provider = null): string
    {
        return $this->provider($provider)->publicCheckoutUrl($reference);
    }

    public function syncPendingCheckouts(int $limit = 50): array
    {
        $providers = [$this->mercadoPago, $this->pagarMe];
        $summary = [
            'checked' => 0,
            'updated' => 0,
            'paid' => 0,
            'failed' => 0,
            'errors' => 0,
            'providers' => [],
        ];

        foreach ($providers as $provider) {
            $providerSummary = $provider->syncPendingCheckouts($limit);
            $summary['providers'][$provider->key()] = $providerSummary;

            foreach (['checked', 'updated', 'paid', 'failed', 'errors'] as $key) {
                $summary[$key] += (int) ($providerSummary[$key] ?? 0);
            }
        }

        return [
            ...$summary,
            'limit' => $limit,
            'synced_at' => now()->toISOString(),
        ];
    }

    public function cancelSubscription(BillingSubscription $subscription): BillingSubscription
    {
        return $this->provider($subscription->provider)->cancelSubscription($subscription);
    }

    public function providerConfigured(string $provider): bool
    {
        return match ($this->normalizeProviderKey($provider)) {
            self::PROVIDER_MERCADO_PAGO => filled(config('services.mercado_pago.access_token'))
                && filled(config('services.mercado_pago.public_key')),
            self::PROVIDER_PAGARME => filled(config('services.pagarme.secret_key'))
                && filled(config('services.pagarme.public_key')),
            default => false,
        };
    }

    public function activeProviderConfigured(): bool
    {
        return $this->providerConfigured($this->currentProviderKey());
    }

    public function normalizeProviderKey(string $provider): string
    {
        $value = Str::of($provider)->lower()->replace(['-', ' ', '.'], '_')->toString();

        return match ($value) {
            'mercadopago', 'mercado_pago', 'mp' => self::PROVIDER_MERCADO_PAGO,
            'pagarme', 'pagar_me', 'pagar' => self::PROVIDER_PAGARME,
            default => self::PROVIDER_MERCADO_PAGO,
        };
    }
}
