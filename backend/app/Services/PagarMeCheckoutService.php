<?php

namespace App\Services;

use App\Contracts\CheckoutPaymentProvider;
use App\Models\CheckoutSession;
use App\Models\PaymentEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PagarMeCheckoutService implements CheckoutPaymentProvider
{
    private const PROVIDER_USER_AGENT = 'provadorvirtual/1.0';

    public function key(): string
    {
        return 'pagarme';
    }

    public function label(): string
    {
        return 'Pagar.me';
    }

    public function configuration(): array
    {
        $publicKey = trim((string) config('services.pagarme.public_key'));

        return [
            'provider' => $this->key(),
            'provider_label' => $this->label(),
            'payment_methods' => $publicKey !== '' ? ['pix', 'credit_card'] : ['pix'],
            'credit_card_enabled' => $publicKey !== '',
            'public_key' => $publicKey !== '' ? $publicKey : null,
            'sdk_url' => null,
            'tokenization' => 'pagarme_tokens',
            'token_url' => $this->baseUrl().'/tokens',
            'token_query_param' => 'appId',
            'token_expires_in_seconds' => 60,
        ];
    }

    public function createOrder(CheckoutSession $session, array $buyerData): CheckoutSession
    {
        $secretKey = $this->requiredSecretKey();
        $providerOrderCode = $session->provider_order_code ?: ('PV-'.strtoupper(Str::random(10)));
        $paymentMethod = $this->normalizePaymentMethod((string) ($buyerData['payment_method'] ?? 'pix'));
        $payload = $this->buildOrderPayload($session, $buyerData, $providerOrderCode, $paymentMethod);

        try {
            $response = $this->providerClient($secretKey)
                ->post('/orders', $payload)
                ->throw()
                ->json();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Não foi possível conectar a Pagar.me agora. Tente novamente em instantes.', 0, $exception);
        } catch (RequestException $exception) {
            Log::warning('A Pagar.me recusou a criação do checkout transparente.', [
                'status' => $exception->response?->status(),
                'response' => $exception->response?->json(),
            ]);

            throw new RuntimeException(
                $this->providerErrorMessage($exception, 'Não foi possível iniciar o checkout na Pagar.me com os dados enviados.'),
                0,
                $exception,
            );
        }

        return $this->applyProviderPayload($session, $response, $paymentMethod, $providerOrderCode);
    }

    public function handleWebhook(array $payload, array $headers, string $rawBody, array $query = []): PaymentEvent
    {
        if (! $this->validSignature($headers, $rawBody)) {
            throw new RuntimeException('Assinatura do webhook invalida.');
        }

        $eventId = $this->extractString($payload, ['id', 'event_id', 'data.id']) ?: sha1($rawBody);
        $eventType = $this->extractString($payload, ['type', 'event', 'name']) ?: 'unknown';

        return DB::transaction(function () use ($eventId, $eventType, $payload): PaymentEvent {
            $event = PaymentEvent::query()->firstOrCreate(
                ['provider_event_id' => $eventId],
                [
                    'provider' => 'pagarme',
                    'event_type' => $eventType,
                    'payload' => $payload,
                ],
            );

            if ($event->processed_at) {
                return $event;
            }

            $session = $this->resolveSession($payload);
            if ($session) {
                $status = $this->normalizeStatus($eventType, $payload);
                $this->applyStatus($session, $status, $payload);
            }

            $event->forceFill(['processed_at' => now()])->save();

            return $event;
        });
    }

    public function syncPendingCheckouts(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $syncBefore = now()->subMinutes(2);
        $summary = [
            'checked' => 0,
            'updated' => 0,
            'paid' => 0,
            'failed' => 0,
            'errors' => 0,
        ];

        $sessions = CheckoutSession::query()
            ->whereIn('status', [CheckoutSession::STATUS_PENDING, CheckoutSession::STATUS_CHECKOUT_CREATED])
            ->where('provider', $this->key())
            ->whereNotNull('provider_order_id')
            ->where(function ($query) use ($syncBefore): void {
                $query->whereNull('last_provider_sync_at')
                    ->orWhere('last_provider_sync_at', '<=', $syncBefore);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($sessions as $session) {
            $summary['checked']++;

            try {
                $before = $session->status;
                $synced = $this->syncCheckoutSession($session);

                if ($synced->status !== $before) {
                    $summary['updated']++;
                }

                if ($synced->status === CheckoutSession::STATUS_PAID) {
                    $summary['paid']++;
                }

                if (in_array($synced->status, [CheckoutSession::STATUS_FAILED, CheckoutSession::STATUS_CANCELLED, CheckoutSession::STATUS_EXPIRED], true)) {
                    $summary['failed']++;
                }
            } catch (\Throwable $exception) {
                $summary['errors']++;
                Log::warning('Falha ao sincronizar checkout pendente na Pagar.me.', [
                    'checkout_session_id' => $session->id,
                    'provider_order_id' => $session->provider_order_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            ...$summary,
            'limit' => $limit,
            'synced_at' => now()->toISOString(),
        ];
    }

    public function syncCheckoutSession(CheckoutSession $session): CheckoutSession
    {
        if (! $session->provider_order_id) {
            throw new RuntimeException('Checkout sem order_id da Pagar.me.');
        }

        try {
            $payload = $this->providerClient($this->requiredSecretKey())
                ->get('/orders/'.$session->provider_order_id)
                ->throw()
                ->json();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Não foi possível conectar a Pagar.me para sincronizar o checkout.', 0, $exception);
        } catch (RequestException $exception) {
            throw new RuntimeException(
                $this->providerErrorMessage($exception, 'Não foi possível consultar o pedido na Pagar.me.'),
                0,
                $exception,
            );
        }

        return $this->applyProviderPayload(
            $session,
            is_array($payload) ? $payload : [],
            $session->payment_method ?: 'pix',
            $session->provider_order_code ?: ('PV-'.$session->public_reference),
        );
    }

    public function publicCheckoutUrl(string $reference): string
    {
        $base = trim((string) config('services.pagarme.checkout_success_url')) ?: url('/checkout/sucesso');
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.http_build_query(['ref' => $reference]);
    }

    private function buildOrderPayload(
        CheckoutSession $session,
        array $buyerData,
        string $providerOrderCode,
        string $paymentMethod,
    ): array {
        $document = preg_replace('/\D+/', '', (string) ($buyerData['company_document'] ?? '')) ?: '';
        $customerType = strlen($document) === 11 ? 'individual' : 'company';

        return $this->cleanArray([
            'closed' => true,
            'code' => $providerOrderCode,
            'items' => [
                [
                    'code' => $session->plan_code,
                    'amount' => $session->amount_cents,
                    'description' => $session->plan_name.' - 12 meses',
                    'quantity' => 1,
                ],
            ],
            'customer' => [
                'name' => trim((string) (($buyerData['company_legal_name'] ?? null) ?: $buyerData['company_name'])),
                'type' => $customerType,
                'email' => trim((string) $buyerData['admin_email']),
                'document' => $document,
                'phones' => $this->phonePayload((string) ($buyerData['admin_phone'] ?? '')),
                'address' => $this->addressPayload($buyerData),
            ],
            'payments' => [
                $this->paymentPayload($paymentMethod, $buyerData, $session),
            ],
            'metadata' => [
                'checkout_session_id' => (string) $session->id,
                'checkout_reference' => $session->public_reference,
                'merchant_company_id' => (string) $session->merchant_company_id,
                'internal_code' => $providerOrderCode,
                'platform' => 'provadorvirtual',
            ],
        ]);
    }

    private function paymentPayload(string $paymentMethod, array $buyerData, CheckoutSession $session): array
    {
        return match ($paymentMethod) {
            'credit_card' => $this->cleanArray([
                'payment_method' => 'credit_card',
                'credit_card' => [
                    'operation_type' => 'auth_and_capture',
                    'installments' => max(1, min(10, (int) ($buyerData['installments'] ?? 1))),
                    'statement_descriptor' => 'PROVADORVIRT',
                    'card_token' => trim((string) ($buyerData['card_token'] ?? '')),
                ],
            ]),
            default => [
                'payment_method' => 'pix',
                'pix' => [
                    'expires_in' => 86400,
                    'additional_information' => [
                        ['name' => 'Plano', 'value' => Str::limit($session->plan_name, 50, '')],
                        ['name' => 'Empresa', 'value' => Str::limit($session->lead_company, 50, '')],
                        ['name' => 'Periodo', 'value' => '12 meses'],
                    ],
                ],
            ],
        };
    }

    private function applyProviderPayload(
        CheckoutSession $session,
        array $payload,
        string $paymentMethod,
        string $providerOrderCode,
    ): CheckoutSession {
        $status = $this->normalizeOrderStatus($payload);
        $snapshot = $this->paymentSnapshot($payload, $paymentMethod);

        $session->forceFill([
            'provider_order_code' => $providerOrderCode,
            'provider_order_id' => $this->extractString($payload, ['id']),
            'provider_charge_id' => $this->extractString($payload, ['charges.0.id']),
            'payment_method' => $snapshot['method'] ?? $paymentMethod,
            'status' => $status === CheckoutSession::STATUS_PENDING ? CheckoutSession::STATUS_CHECKOUT_CREATED : $status,
            'metadata' => [
                ...Arr::wrap($session->metadata),
                'provider_payload' => $payload,
                'payment_snapshot' => $snapshot,
            ],
            'paid_at' => $status === CheckoutSession::STATUS_PAID ? now() : null,
            'expires_at' => $this->resolveExpiresAt($payload, $snapshot),
            'last_provider_sync_at' => now(),
        ])->save();

        $freshSession = $session->fresh(['merchant', 'company', 'user']) ?? $session;
        $this->activateAccessIfPaid($freshSession);
        $this->dispatchStatusEmail($freshSession);

        return $freshSession;
    }

    private function applyStatus(CheckoutSession $session, string $status, array $payload): void
    {
        $snapshot = $this->paymentSnapshot($payload, $session->payment_method);

        $session->forceFill([
            'status' => $status,
            'provider_order_id' => $this->extractString($payload, ['data.order.id', 'data.object.order.id', 'order.id']) ?: $session->provider_order_id,
            'provider_charge_id' => $this->extractString($payload, ['data.charges.0.id', 'data.object.charges.0.id', 'charges.0.id']) ?: $session->provider_charge_id,
            'metadata' => [
                ...Arr::wrap($session->metadata),
                'last_webhook_payload' => $payload,
                'payment_snapshot' => $snapshot,
            ],
            'paid_at' => $status === CheckoutSession::STATUS_PAID ? ($session->paid_at ?: now()) : $session->paid_at,
            'expires_at' => $this->resolveExpiresAt($payload, $snapshot) ?: $session->expires_at,
            'last_provider_sync_at' => now(),
        ])->save();

        $freshSession = $session->fresh(['merchant', 'company', 'user']) ?? $session;
        $this->activateAccessIfPaid($freshSession);
        $this->dispatchStatusEmail($freshSession);
    }

    private function activateAccessIfPaid(CheckoutSession $session): void
    {
        if ($session->status !== CheckoutSession::STATUS_PAID) {
            return;
        }

        $session->merchant?->forceFill(['billing_status' => 'active'])->save();
        $session->company?->forceFill(['status' => 'active'])->save();
    }

    private function dispatchStatusEmail(CheckoutSession $session): void
    {
        try {
            $emailService = app(TransactionalEmailService::class);
            $code = $emailService->codeForSessionStatus($session);

            if ($code) {
                $emailService->sendForCheckout($code, $session);
            }
        } catch (\Throwable $exception) {
            Log::warning('Falha ao registrar e-mail transacional de checkout.', [
                'checkout_session_id' => $session->id,
                'status' => $session->status,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveSession(array $payload): ?CheckoutSession
    {
        $sessionId = $this->extractString($payload, [
            'data.metadata.checkout_session_id',
            'data.object.metadata.checkout_session_id',
            'metadata.checkout_session_id',
        ]);

        if ($sessionId) {
            return CheckoutSession::query()->find((int) $sessionId);
        }

        $code = $this->extractString($payload, [
            'data.metadata.internal_code',
            'data.object.metadata.internal_code',
            'metadata.internal_code',
            'data.order.code',
            'data.object.order.code',
            'order.code',
        ]);

        return $code ? CheckoutSession::query()->where('provider_order_code', $code)->first() : null;
    }

    private function normalizeStatus(string $eventType, array $payload): string
    {
        $type = Str::lower($eventType);
        $status = Str::lower((string) (
            data_get($payload, 'data.status')
            ?? data_get($payload, 'data.object.status')
            ?? data_get($payload, 'status')
            ?? ''
        ));

        if (Str::contains($type, ['paid', 'charge.paid', 'order.paid']) || $status === 'paid') {
            return CheckoutSession::STATUS_PAID;
        }

        if (Str::contains($type, ['cancel', 'canceled']) || in_array($status, ['canceled', 'cancelled', 'voided'], true)) {
            return CheckoutSession::STATUS_CANCELLED;
        }

        if (Str::contains($type, ['refund', 'refunded']) || in_array($status, ['refunded', 'chargedback'], true)) {
            return CheckoutSession::STATUS_REFUNDED;
        }

        if (Str::contains($type, ['failed', 'declined', 'refused']) || in_array($status, ['failed', 'declined', 'refused', 'not_authorized'], true)) {
            return CheckoutSession::STATUS_FAILED;
        }

        if (Str::contains($type, ['expired']) || $status === 'expired') {
            return CheckoutSession::STATUS_EXPIRED;
        }

        return CheckoutSession::STATUS_PENDING;
    }

    private function normalizeOrderStatus(array $payload): string
    {
        $status = Str::lower((string) ($this->extractString($payload, ['status']) ?? ''));

        if (in_array($status, ['paid', 'overpaid'], true)) {
            return CheckoutSession::STATUS_PAID;
        }

        if (in_array($status, ['failed', 'declined', 'refused', 'not_authorized'], true)) {
            return CheckoutSession::STATUS_FAILED;
        }

        if (in_array($status, ['canceled', 'cancelled'], true)) {
            return CheckoutSession::STATUS_CANCELLED;
        }

        if ($status === 'expired') {
            return CheckoutSession::STATUS_EXPIRED;
        }

        if (in_array($status, ['refunded', 'chargedback'], true)) {
            return CheckoutSession::STATUS_REFUNDED;
        }

        $chargeStatuses = collect(Arr::wrap($payload['charges'] ?? []))
            ->map(fn ($charge): string => Str::lower((string) data_get($charge, 'status')))
            ->filter();

        if ($chargeStatuses->contains(fn (string $value): bool => in_array($value, ['paid', 'captured'], true))) {
            return CheckoutSession::STATUS_PAID;
        }

        if ($chargeStatuses->contains(fn (string $value): bool => in_array($value, ['failed', 'not_authorized', 'with_error'], true))) {
            return CheckoutSession::STATUS_FAILED;
        }

        if ($chargeStatuses->contains(fn (string $value): bool => in_array($value, ['canceled', 'cancelled', 'voided'], true))) {
            return CheckoutSession::STATUS_CANCELLED;
        }

        return CheckoutSession::STATUS_PENDING;
    }

    private function paymentSnapshot(array $payload, ?string $fallbackMethod = null): array
    {
        $method = $this->normalizePaymentMethod((string) (
            $this->extractString($payload, ['charges.0.payment_method', 'data.charges.0.payment_method'])
            ?: ($fallbackMethod ?: 'pix')
        ));

        return $this->cleanArray([
            'method' => $method,
            'status' => Str::lower((string) (
                $this->extractString($payload, ['charges.0.status', 'data.charges.0.status', 'status']) ?: 'pending'
            )),
            'credit_card' => $method === 'credit_card' ? [
                'brand' => $this->extractString($payload, ['charges.0.last_transaction.card.brand']),
                'last_four_digits' => $this->extractString($payload, ['charges.0.last_transaction.card.last_four_digits', 'charges.0.last_transaction.card.last_4_digits']),
            ] : null,
            'pix' => $method === 'pix' ? [
                'qr_code' => $this->extractString($payload, ['charges.0.last_transaction.qr_code']),
                'qr_code_url' => $this->extractString($payload, ['charges.0.last_transaction.qr_code_url']),
                'expires_at' => $this->extractString($payload, ['charges.0.last_transaction.expires_at']),
            ] : null,
        ]);
    }

    private function addressPayload(array $buyerData): array
    {
        return $this->cleanArray([
            'line_1' => trim((string) ($buyerData['company_address_street'] ?? '')).', '.trim((string) ($buyerData['company_address_number'] ?? '')),
            'line_2' => trim((string) ($buyerData['company_address_complement'] ?? '')) ?: null,
            'zip_code' => preg_replace('/\D+/', '', (string) ($buyerData['company_zip_code'] ?? '')) ?: null,
            'city' => trim((string) ($buyerData['company_address_city'] ?? '')),
            'state' => trim((string) ($buyerData['company_address_state'] ?? '')),
            'country' => 'BR',
        ]);
    }

    private function phonePayload(string $value): ?array
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if (Str::startsWith($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 10) {
            return null;
        }

        return [
            'mobile_phone' => [
                'country_code' => '55',
                'area_code' => substr($digits, 0, 2),
                'number' => substr($digits, 2),
            ],
        ];
    }

    private function resolveExpiresAt(array $payload, array $snapshot): ?CarbonImmutable
    {
        $value = data_get($snapshot, 'pix.expires_at')
            ?: $this->extractString($payload, ['charges.0.last_transaction.expires_at', 'charges.0.last_transaction.due_at']);

        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function providerClient(string $secretKey)
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(90)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($secretKey, '')
            ->withHeaders(['User-Agent' => self::PROVIDER_USER_AGENT]);
    }

    private function requiredSecretKey(): string
    {
        $secretKey = trim((string) config('services.pagarme.secret_key'));

        if ($secretKey === '') {
            throw new RuntimeException('As credenciais da Pagar.me não estão configuradas.');
        }

        return $secretKey;
    }

    private function validSignature(array $headers, string $rawBody): bool
    {
        $secret = trim((string) config('services.pagarme.webhook_secret'));
        if ($secret === '') {
            return true;
        }

        $normalized = collect($headers)
            ->mapWithKeys(fn ($value, $key) => [Str::lower((string) $key) => is_array($value) ? implode(',', $value) : (string) $value]);
        $provided = trim((string) (
            $normalized->get('x-hub-signature-256')
            ?? $normalized->get('x-hub-signature')
            ?? $normalized->get('x-pagarme-signature')
            ?? ''
        ));

        if ($provided === '') {
            return false;
        }

        $calculated = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($calculated, $provided) || hash_equals("sha256={$calculated}", $provided);
    }

    private function providerErrorMessage(RequestException $exception, string $fallback): string
    {
        $payload = $exception->response?->json();
        if (! is_array($payload)) {
            return $fallback;
        }

        $messages = $this->flattenMessages($payload['errors'] ?? $payload['details'] ?? []);
        if ($messages !== []) {
            return implode(' | ', array_slice(array_unique($messages), 0, 3));
        }

        return $this->extractString($payload, ['message', 'title', 'error']) ?: $fallback;
    }

    private function flattenMessages(mixed $value): array
    {
        if (is_string($value) && trim($value) !== '') {
            return [trim($value)];
        }

        if (! is_array($value)) {
            return [];
        }

        $messages = [];
        foreach ($value as $item) {
            $messages = [...$messages, ...$this->flattenMessages($item)];
        }

        return $messages;
    }

    private function extractString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function normalizePaymentMethod(string $value): string
    {
        return match (Str::lower(trim($value))) {
            'credit_card' => 'credit_card',
            default => 'pix',
        };
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.pagarme.base_url'), '/');
    }

    private function cleanArray(array $data): array
    {
        $cleaned = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $nested = $this->cleanArray($value);
                if ($nested !== []) {
                    $cleaned[$key] = $nested;
                }

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }
}
