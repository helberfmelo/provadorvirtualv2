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

class MercadoPagoCheckoutService implements CheckoutPaymentProvider
{
    private const PROVIDER_USER_AGENT = 'provadorvirtual/1.0';

    public function key(): string
    {
        return 'mercado_pago';
    }

    public function label(): string
    {
        return 'Mercado Pago';
    }

    public function configuration(): array
    {
        $publicKey = trim((string) config('services.mercado_pago.public_key'));

        return [
            'provider' => $this->key(),
            'provider_label' => $this->label(),
            'payment_methods' => $publicKey !== '' ? ['pix', 'credit_card'] : ['pix'],
            'credit_card_enabled' => $publicKey !== '',
            'public_key' => $publicKey !== '' ? $publicKey : null,
            'sdk_url' => 'https://sdk.mercadopago.com/js/v2',
            'tokenization' => 'mercado_pago_card_form',
            'token_url' => null,
            'token_query_param' => null,
            'token_expires_in_seconds' => 604800,
        ];
    }

    public function createOrder(CheckoutSession $session, array $buyerData): CheckoutSession
    {
        $accessToken = $this->requiredAccessToken();
        $providerOrderCode = $session->provider_order_code ?: ('PV-MP-'.strtoupper(Str::random(10)));
        $paymentMethod = $this->normalizePaymentMethod((string) ($buyerData['payment_method'] ?? 'pix'));
        $payload = $this->buildPaymentPayload($session, $buyerData, $providerOrderCode, $paymentMethod);

        try {
            $response = $this->providerClient($accessToken)
                ->withHeaders(['X-Idempotency-Key' => $providerOrderCode])
                ->post('/v1/payments', $payload)
                ->throw()
                ->json();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Nao foi possivel conectar ao Mercado Pago agora. Tente novamente em instantes.', 0, $exception);
        } catch (RequestException $exception) {
            Log::warning('O Mercado Pago recusou a criacao do checkout transparente.', [
                'status' => $exception->response?->status(),
                'response' => $exception->response?->json(),
            ]);

            throw new RuntimeException(
                $this->providerErrorMessage($exception, 'Nao foi possivel iniciar o checkout no Mercado Pago com os dados enviados.'),
                0,
                $exception,
            );
        }

        return $this->applyProviderPayload(
            $session,
            is_array($response) ? $response : [],
            $paymentMethod,
            $providerOrderCode,
        );
    }

    public function handleWebhook(array $payload, array $headers, string $rawBody, array $query = []): PaymentEvent
    {
        $paymentId = $this->resolvePaymentId($payload, $query);

        if (! $this->validSignature($headers, $paymentId)) {
            throw new RuntimeException('Assinatura do webhook invalida.');
        }

        $eventId = $this->extractString($payload, ['id', 'event_id'])
            ?: ($paymentId ? "payment_{$paymentId}_".($this->resolveNotificationType($payload, $query) ?: 'updated') : sha1($rawBody));
        $eventId = Str::startsWith($eventId, 'mp_') ? $eventId : 'mp_'.$eventId;
        $eventType = $this->resolveNotificationType($payload, $query) ?: 'unknown';

        return DB::transaction(function () use ($eventId, $eventType, $payload, $query, $paymentId): PaymentEvent {
            $event = PaymentEvent::query()->firstOrCreate(
                ['provider_event_id' => $eventId],
                [
                    'provider' => $this->key(),
                    'event_type' => $eventType,
                    'payload' => [
                        'notification' => $payload,
                        'query' => $query,
                    ],
                ],
            );

            if ($event->processed_at) {
                return $event;
            }

            $paymentPayload = $paymentId ? $this->fetchPayment($paymentId) : [];
            $session = $this->resolveSession($paymentPayload, $payload);

            if ($session) {
                $this->applyProviderPayload(
                    $session,
                    $paymentPayload,
                    $session->payment_method ?: $this->normalizePaymentMethod((string) data_get($paymentPayload, 'payment_method_id', 'pix')),
                    $session->provider_order_code ?: ('PV-MP-'.$session->public_reference),
                    [
                        'notification' => $payload,
                        'query' => $query,
                    ],
                );
            }

            $event->forceFill([
                'payload' => [
                    'notification' => $payload,
                    'query' => $query,
                    'payment' => $paymentPayload,
                ],
                'processed_at' => now(),
            ])->save();

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
                Log::warning('Falha ao sincronizar checkout pendente no Mercado Pago.', [
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
            throw new RuntimeException('Checkout sem payment_id do Mercado Pago.');
        }

        return $this->applyProviderPayload(
            $session,
            $this->fetchPayment($session->provider_order_id),
            $session->payment_method ?: 'pix',
            $session->provider_order_code ?: ('PV-MP-'.$session->public_reference),
        );
    }

    public function publicCheckoutUrl(string $reference): string
    {
        $base = trim((string) config('services.mercado_pago.checkout_success_url'))
            ?: trim((string) config('services.checkout.success_url'))
            ?: rtrim((string) config('app.frontend_url', config('app.url')), '/').'/checkout/sucesso';
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.http_build_query(['ref' => $reference]);
    }

    private function buildPaymentPayload(
        CheckoutSession $session,
        array $buyerData,
        string $providerOrderCode,
        string $paymentMethod,
    ): array {
        $amount = round($session->amount_cents / 100, 2);
        $payer = $this->payerPayload($buyerData);
        $payload = [
            'transaction_amount' => $amount,
            'description' => $session->plan_name.' - 12 meses',
            'external_reference' => $session->public_reference,
            'notification_url' => $this->notificationUrl(),
            'statement_descriptor' => 'PROVADORVIRT',
            'payer' => $payer,
            'metadata' => [
                'checkout_session_id' => (string) $session->id,
                'checkout_reference' => $session->public_reference,
                'merchant_company_id' => (string) $session->merchant_company_id,
                'internal_code' => $providerOrderCode,
                'platform' => 'provadorvirtual',
            ],
        ];

        if ($paymentMethod === 'credit_card') {
            $token = trim((string) ($buyerData['card_token'] ?? ''));
            $paymentMethodId = trim((string) ($buyerData['payment_method_id'] ?? ''));

            if ($token === '' || $paymentMethodId === '') {
                throw new RuntimeException('Dados do cartao incompletos para o Mercado Pago.');
            }

            return $this->cleanArray([
                ...$payload,
                'token' => $token,
                'payment_method_id' => $paymentMethodId,
                'issuer_id' => trim((string) ($buyerData['issuer_id'] ?? '')) ?: null,
                'installments' => max(1, min(10, (int) ($buyerData['installments'] ?? 1))),
            ]);
        }

        return $this->cleanArray([
            ...$payload,
            'payment_method_id' => 'pix',
            'date_of_expiration' => now()->addDay()->toIso8601String(),
        ]);
    }

    private function payerPayload(array $buyerData): array
    {
        [$firstName, $lastName] = $this->splitName((string) ($buyerData['admin_name'] ?? $buyerData['company_name'] ?? 'Cliente'));
        $document = preg_replace('/\D+/', '', (string) ($buyerData['admin_cpf'] ?? '')) ?: preg_replace('/\D+/', '', (string) ($buyerData['company_document'] ?? ''));

        return $this->cleanArray([
            'email' => mb_strtolower(trim((string) ($buyerData['admin_email'] ?? ''))),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'identification' => $document ? [
                'type' => strlen($document) === 14 ? 'CNPJ' : 'CPF',
                'number' => $document,
            ] : null,
            'address' => [
                'zip_code' => preg_replace('/\D+/', '', (string) ($buyerData['company_zip_code'] ?? '')),
                'street_name' => trim((string) ($buyerData['company_address_street'] ?? '')),
                'street_number' => trim((string) ($buyerData['company_address_number'] ?? '')),
                'neighborhood' => trim((string) ($buyerData['company_address_district'] ?? '')),
                'city' => trim((string) ($buyerData['company_address_city'] ?? '')),
                'federal_unit' => mb_strtoupper(trim((string) ($buyerData['company_address_state'] ?? ''))),
            ],
        ]);
    }

    private function splitName(string $name): array
    {
        $cleanName = trim((string) preg_replace('/[^\pL\pN\s]+/u', '', $name));
        $parts = preg_split('/\s+/', $cleanName) ?: [];
        $firstName = trim((string) ($parts[0] ?? 'Cliente')) ?: 'Cliente';
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Provador Virtual';

        return [$firstName, $lastName];
    }

    private function applyProviderPayload(
        CheckoutSession $session,
        array $payload,
        string $paymentMethod,
        string $providerOrderCode,
        array $notificationPayload = [],
    ): CheckoutSession {
        $status = $this->normalizePaymentStatus($payload);
        $snapshot = $this->paymentSnapshot($payload, $paymentMethod);
        $sessionStatus = $status === CheckoutSession::STATUS_PENDING ? CheckoutSession::STATUS_CHECKOUT_CREATED : $status;

        $session->forceFill([
            'provider_order_code' => $providerOrderCode,
            'provider_order_id' => $this->extractString($payload, ['id']) ?: $session->provider_order_id,
            'provider_charge_id' => $this->extractString($payload, ['id']) ?: $session->provider_charge_id,
            'payment_method' => $snapshot['method'] ?? $paymentMethod,
            'status' => $sessionStatus,
            'metadata' => [
                ...Arr::wrap($session->metadata),
                'provider_payload' => $payload,
                'last_webhook_payload' => $notificationPayload ?: data_get($session->metadata, 'last_webhook_payload'),
                'payment_snapshot' => $snapshot,
            ],
            'paid_at' => $status === CheckoutSession::STATUS_PAID ? ($session->paid_at ?: now()) : $session->paid_at,
            'expires_at' => $this->resolveExpiresAt($payload, $snapshot) ?: $session->expires_at,
            'last_provider_sync_at' => now(),
        ])->save();

        $freshSession = $session->fresh(['merchant', 'company', 'user']) ?? $session;
        $this->activateAccessIfPaid($freshSession);
        $this->dispatchStatusEmail($freshSession);

        return $freshSession;
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

    private function fetchPayment(string $paymentId): array
    {
        try {
            $payload = $this->providerClient($this->requiredAccessToken())
                ->get('/v1/payments/'.$paymentId)
                ->throw()
                ->json();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Nao foi possivel conectar ao Mercado Pago para sincronizar o checkout.', 0, $exception);
        } catch (RequestException $exception) {
            throw new RuntimeException(
                $this->providerErrorMessage($exception, 'Nao foi possivel consultar o pagamento no Mercado Pago.'),
                0,
                $exception,
            );
        }

        return is_array($payload) ? $payload : [];
    }

    private function resolveSession(array $paymentPayload, array $notificationPayload = []): ?CheckoutSession
    {
        $sessionId = $this->extractString($paymentPayload, ['metadata.checkout_session_id'])
            ?: $this->extractString($notificationPayload, ['metadata.checkout_session_id', 'data.metadata.checkout_session_id']);

        if ($sessionId) {
            return CheckoutSession::query()->find((int) $sessionId);
        }

        $reference = $this->extractString($paymentPayload, ['metadata.checkout_reference', 'external_reference'])
            ?: $this->extractString($notificationPayload, ['metadata.checkout_reference', 'data.metadata.checkout_reference']);

        if ($reference) {
            return CheckoutSession::query()
                ->where('public_reference', $reference)
                ->orWhere('provider_order_code', $reference)
                ->first();
        }

        $internalCode = $this->extractString($paymentPayload, ['metadata.internal_code'])
            ?: $this->extractString($notificationPayload, ['metadata.internal_code', 'data.metadata.internal_code']);

        return $internalCode ? CheckoutSession::query()->where('provider_order_code', $internalCode)->first() : null;
    }

    private function normalizePaymentStatus(array $payload): string
    {
        $status = Str::lower((string) ($this->extractString($payload, ['status']) ?? ''));

        return match ($status) {
            'approved' => CheckoutSession::STATUS_PAID,
            'rejected' => CheckoutSession::STATUS_FAILED,
            'cancelled', 'canceled' => CheckoutSession::STATUS_CANCELLED,
            'refunded', 'partially_refunded', 'charged_back' => CheckoutSession::STATUS_REFUNDED,
            default => CheckoutSession::STATUS_PENDING,
        };
    }

    private function paymentSnapshot(array $payload, ?string $fallbackMethod = null): array
    {
        $paymentMethodId = Str::lower((string) ($this->extractString($payload, ['payment_method_id']) ?: ''));
        $method = $paymentMethodId === 'pix'
            ? 'pix'
            : $this->normalizePaymentMethod((string) ($fallbackMethod ?: ($paymentMethodId ? 'credit_card' : 'pix')));

        return $this->cleanArray([
            'method' => $method,
            'provider_payment_id' => $this->extractString($payload, ['id']),
            'status' => Str::lower((string) ($this->extractString($payload, ['status']) ?: 'pending')),
            'status_detail' => $this->extractString($payload, ['status_detail']),
            'credit_card' => $method === 'credit_card' ? [
                'brand' => $paymentMethodId ?: $this->extractString($payload, ['payment_method.id']),
                'last_four_digits' => $this->extractString($payload, ['card.last_four_digits']),
                'first_six_digits' => $this->extractString($payload, ['card.first_six_digits']),
                'installments' => data_get($payload, 'installments'),
            ] : null,
            'pix' => $method === 'pix' ? [
                'qr_code' => $this->extractString($payload, ['point_of_interaction.transaction_data.qr_code']),
                'qr_code_base64' => $this->extractString($payload, ['point_of_interaction.transaction_data.qr_code_base64']),
                'ticket_url' => $this->extractString($payload, ['point_of_interaction.transaction_data.ticket_url']),
                'expires_at' => $this->extractString($payload, ['date_of_expiration', 'point_of_interaction.transaction_data.expiration_date']),
            ] : null,
        ]);
    }

    private function resolveExpiresAt(array $payload, array $snapshot): ?CarbonImmutable
    {
        $value = data_get($snapshot, 'pix.expires_at') ?: $this->extractString($payload, ['date_of_expiration']);

        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolvePaymentId(array $payload, array $query): ?string
    {
        $queryId = $query['data.id'] ?? $query['id'] ?? $query['resource_id'] ?? null;

        if (is_string($queryId) || is_numeric($queryId)) {
            return trim((string) $queryId) ?: null;
        }

        $id = $this->extractString($payload, ['data.id', 'resource', 'id']);
        if ($id && Str::contains($id, '/')) {
            return basename($id);
        }

        return $id;
    }

    private function resolveNotificationType(array $payload, array $query): ?string
    {
        $queryType = $query['type'] ?? $query['topic'] ?? null;

        if (is_string($queryType) && trim($queryType) !== '') {
            return trim($queryType);
        }

        return $this->extractString($payload, ['type', 'topic', 'action']);
    }

    private function providerClient(string $accessToken)
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(90)
            ->acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->withHeaders(['User-Agent' => self::PROVIDER_USER_AGENT]);
    }

    private function requiredAccessToken(): string
    {
        $accessToken = trim((string) config('services.mercado_pago.access_token'));

        if ($accessToken === '') {
            throw new RuntimeException('As credenciais do Mercado Pago nao estao configuradas.');
        }

        return $accessToken;
    }

    private function validSignature(array $headers, ?string $dataId): bool
    {
        $secret = trim((string) config('services.mercado_pago.webhook_secret'));
        if ($secret === '') {
            return true;
        }

        $normalized = collect($headers)
            ->mapWithKeys(fn ($value, $key) => [Str::lower((string) $key) => is_array($value) ? implode(',', $value) : (string) $value]);
        $provided = trim((string) $normalized->get('x-signature', ''));
        $requestId = trim((string) $normalized->get('x-request-id', ''));

        if ($provided === '' || $requestId === '' || blank($dataId)) {
            Log::warning('Webhook Mercado Pago sem cabecalhos suficientes para validar assinatura; pagamento sera consultado na API.', [
                'has_signature' => $provided !== '',
                'has_request_id' => $requestId !== '',
                'has_data_id' => filled($dataId),
            ]);

            return true;
        }

        $signatureParts = [];
        foreach (explode(',', $provided) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $signatureParts[$key] = $value;
        }

        $timestamp = $signatureParts['ts'] ?? '';
        $signature = $signatureParts['v1'] ?? '';

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$timestamp};";
        $calculated = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($calculated, $signature);
    }

    private function providerErrorMessage(RequestException $exception, string $fallback): string
    {
        $payload = $exception->response?->json();
        if (! is_array($payload)) {
            return $fallback;
        }

        $messages = $this->flattenMessages($payload['cause'] ?? $payload['errors'] ?? []);
        if ($messages !== []) {
            return implode(' | ', array_slice(array_unique($messages), 0, 3));
        }

        return $this->extractString($payload, ['message', 'error', 'status_detail']) ?: $fallback;
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
            if (is_array($item) && is_string($item['description'] ?? null)) {
                $messages[] = trim($item['description']);
            }

            $messages = [...$messages, ...$this->flattenMessages($item)];
        }

        return $messages;
    }

    private function extractString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if ((is_string($value) || is_numeric($value)) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function normalizePaymentMethod(string $value): string
    {
        return match (Str::lower(trim($value))) {
            'credit_card', 'visa', 'master', 'mastercard', 'amex', 'elo', 'hipercard' => 'credit_card',
            default => 'pix',
        };
    }

    private function notificationUrl(): string
    {
        return trim((string) config('services.mercado_pago.webhook_url'))
            ?: rtrim((string) config('app.url'), '/').'/api/v1/webhooks/mercado-pago';
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.mercado_pago.base_url'), '/');
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
