<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CheckoutAcceptance;
use App\Models\CheckoutSession;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use App\Models\WidgetInstall;
use App\Services\CheckoutPaymentManager;
use App\Services\TransactionalEmailService;
use App\Support\CheckoutPlanCatalog;
use App\Support\PlatformCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class PublicCheckoutController extends Controller
{
    private const TERMS_VERSION = '2026-05-25';

    private const PRIVACY_VERSION = '2026-05-25';

    public function __construct(private readonly CheckoutPaymentManager $checkoutPayments) {}

    public function config(): array
    {
        return [
            'checkout' => $this->checkoutPayments->configuration(),
            'plans' => array_values($this->plans()),
            'pricing' => $this->pricingConfig(),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validateCheckout($request);
        $plan = $this->plans()[$data['plan_code']] ?? null;
        $pricing = $this->pricingFor($data);

        abort_if(! $plan, 422, 'Plano selecionado indisponível.');

        try {
            $provider = $this->checkoutPayments->currentProviderKey();
            $session = DB::transaction(fn (): CheckoutSession => $this->createPendingCheckoutSession(
                $data,
                $plan,
                $pricing,
                $provider,
                $request,
            ));

            $session = $this->checkoutPayments->createOrder($session, $data);
        } catch (RuntimeException $exception) {
            $failedSession = $session ?? null;

            if (isset($session) && $session instanceof CheckoutSession) {
                $failedSession = $this->markCheckoutAsFailed($session, $exception);
            }

            return response()->json($this->checkoutErrorPayload($exception, $failedSession), 422);
        }

        app(TransactionalEmailService::class)->sendForCheckout(TransactionalEmailService::CODE_SIGNUP, $session);

        return response()->json([
            'checkout_url' => $this->checkoutPayments->publicCheckoutUrl($session->public_reference, $session->provider),
            'reference' => $session->public_reference,
            'status' => $session->status,
            'company' => [
                'id' => $session->company?->id,
                'name' => $session->company?->name,
                'access_code' => $session->company?->access_code,
            ],
            'payment' => data_get($session->metadata, 'payment_snapshot', []),
        ], 201);
    }

    public function show(string $reference)
    {
        $session = CheckoutSession::query()
            ->with(['merchant', 'company', 'user'])
            ->where('public_reference', $reference)
            ->firstOrFail();

        return response()->json([
            'session' => [
                'reference' => $session->public_reference,
                'status' => $session->status,
                'status_label' => $this->statusLabel($session->status),
                'plan_name' => $session->plan_name,
                'amount_cents' => $session->amount_cents,
                'provider' => $session->provider,
                'provider_label' => $this->checkoutPayments->provider($session->provider)->label(),
                'payment_method' => $session->payment_method,
                'paid_at' => $session->paid_at?->toISOString(),
                'expires_at' => $session->expires_at?->toISOString(),
                'company' => [
                    'id' => $session->company?->id,
                    'name' => $session->company?->name,
                    'access_code' => $session->company?->access_code,
                    'status' => $session->company?->status,
                ],
                'admin' => [
                    'name' => $session->user?->name ?: $session->lead_name,
                    'email' => $session->user?->email ?: $session->lead_email,
                ],
                'failure' => data_get($session->metadata, 'failure'),
            ],
            'payment' => data_get($session->metadata, 'payment_snapshot', []),
        ]);
    }

    public function webhook(Request $request, string $provider = 'pagarme')
    {
        try {
            $event = $this->checkoutPayments->handleWebhook(
                $provider,
                $request->all(),
                $request->headers->all(),
                $request->getContent(),
                $request->query(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        return response()->json([
            'processed' => true,
            'event_id' => $event->provider_event_id,
        ]);
    }

    private function validateCheckout(Request $request): array
    {
        $request->merge([
            'company_document' => preg_replace('/\D+/', '', (string) $request->input('company_document')) ?: '',
            'company_zip_code' => preg_replace('/\D+/', '', (string) $request->input('company_zip_code')) ?: '',
            'admin_cpf' => preg_replace('/\D+/', '', (string) $request->input('admin_cpf')) ?: '',
            'admin_email' => mb_strtolower(trim((string) $request->input('admin_email'))),
            'company_address_state' => filled($request->input('company_address_state'))
                ? mb_strtoupper(trim((string) $request->input('company_address_state')))
                : '',
            'platform' => $request->input('platform') ?: 'bigshop',
        ]);

        return $request->validate([
            'plan_code' => ['required', 'string', Rule::in(array_keys($this->plans()))],
            'payment_method' => ['required', 'string', Rule::in($this->checkoutPayments->allowedPaymentMethods())],
            'platform' => ['nullable', 'string', Rule::in(PlatformCatalog::keys())],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_legal_name' => ['nullable', 'string', 'max:255'],
            'company_document' => ['required', 'string', 'size:14'],
            'company_domain' => ['nullable', 'string', 'max:180'],
            'company_zip_code' => ['nullable', 'string', 'size:8'],
            'company_address_street' => ['nullable', 'string', 'max:255'],
            'company_address_number' => ['nullable', 'string', 'max:40'],
            'company_address_complement' => ['nullable', 'string', 'max:255'],
            'company_address_district' => ['nullable', 'string', 'max:255'],
            'company_address_city' => ['nullable', 'string', 'max:255'],
            'company_address_state' => ['nullable', 'string', 'size:2'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_cpf' => ['required', 'string', 'size:11'],
            'admin_phone' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'card_token' => ['nullable', 'string', 'max:255', 'required_if:payment_method,credit_card'],
            'payment_method_id' => ['nullable', 'string', 'max:80'],
            'issuer_id' => ['nullable', 'string', 'max:80'],
            'card_brand' => ['nullable', 'string', 'max:80'],
            'card_last_four_digits' => ['nullable', 'string', 'max:4'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:10'],
            'accepted_terms' => ['required', 'accepted'],
        ]);
    }

    private function plans(): array
    {
        return CheckoutPlanCatalog::plans();
    }

    private function pricingConfig(): array
    {
        return CheckoutPlanCatalog::pricingConfig();
    }

    private function pricingFor(array $data): array
    {
        return CheckoutPlanCatalog::pricingFor($data);
    }

    private function createPendingCheckoutSession(
        array $data,
        array $plan,
        array $pricing,
        string $provider,
        Request $request,
    ): CheckoutSession {
        $acceptedAt = now();
        $companyName = $this->checkoutCompanyName($data);

        $merchant = Merchant::query()->create([
            'name' => $companyName,
            'slug' => $this->uniqueMerchantSlug($companyName),
            'billing_status' => 'pending_payment',
            'trial_ends_at' => null,
        ]);

        $user = $this->resolveCheckoutUser($data);
        $merchant->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'is_owner' => true],
        ]);

        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => $companyName,
            'legal_name' => $data['company_legal_name'] ?? null,
            'document' => $data['company_document'],
            'zip_code' => $data['company_zip_code'] ?? null,
            'street' => $data['company_address_street'] ?? null,
            'number' => $data['company_address_number'] ?? null,
            'complement' => $data['company_address_complement'] ?? null,
            'district' => $data['company_address_district'] ?? null,
            'city' => $data['company_address_city'] ?? null,
            'state' => $data['company_address_state'] ?? null,
            'country' => 'BR',
            'domain' => $data['company_domain'] ?? null,
            'platform' => $data['platform'] ?? 'custom',
            'external_store_id' => null,
            'status' => 'pending_payment',
        ]);
        $company->ensureAccessCode();

        WidgetInstall::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'public_key' => 'pv_'.Str::lower(Str::random(24)),
            'platform' => $company->platform,
            'allowed_domains' => array_values(array_filter([
                $company->domain,
                'localhost',
                '127.0.0.1',
            ])),
            'theme' => [
                'primary' => '#0f172a',
                'secondary' => '#ff4d5e',
                'accent' => '#ff7a1a',
                'background' => '#ffffff',
                'text' => '#111827',
                'font_family' => 'Manrope, Inter, Arial, sans-serif',
                'font_size' => '14',
                'font_weight' => '800',
                'button_radius' => '8',
                'confetti_enabled' => true,
                'presentation_mode' => 'drawer',
            ],
            'is_active' => true,
        ]);

        $session = CheckoutSession::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $user->id,
            'public_reference' => Str::random(48),
            'plan_code' => $plan['code'],
            'plan_name' => $plan['name'],
            'lead_name' => $data['admin_name'],
            'lead_company' => $companyName,
            'lead_email' => $data['admin_email'],
            'lead_phone' => $data['admin_phone'],
            'amount_cents' => $pricing['payable_cents'],
            'currency' => 'BRL',
            'provider' => $provider,
            'payment_method' => $data['payment_method'],
            'status' => CheckoutSession::STATUS_PENDING,
            'metadata' => [
                'plan' => $plan,
                'pricing' => $pricing,
                'platform' => $company->platform,
                'company_access_code' => $company->access_code,
                'checkout_collected_company_fields' => ['company_document'],
                'company_profile_pending' => true,
                'legal_acceptance' => [
                    'terms_version' => self::TERMS_VERSION,
                    'privacy_version' => self::PRIVACY_VERSION,
                    'accepted_at' => $acceptedAt->toISOString(),
                ],
                'recurrence' => [
                    'auto_renewal_requested' => $data['payment_method'] === 'credit_card'
                        && ($plan['billing_cycle'] ?? null) === CheckoutPlanCatalog::PLAN_MONTHLY,
                    'annual_auto_renewal_enabled' => false,
                    'annual_auto_renewal_reason' => 'Renovacao anual fica pendente ate validacao operacional sem risco de dupla cobranca ou conflito com parcelamento.',
                ],
            ],
        ]);

        CheckoutAcceptance::query()->create([
            'checkout_session_id' => $session->id,
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $user->id,
            'lead_email' => $data['admin_email'],
            'company_document' => $data['company_document'],
            'terms_version' => self::TERMS_VERSION,
            'privacy_version' => self::PRIVACY_VERSION,
            'accepted_terms' => true,
            'accepted_at' => $acceptedAt,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'plan_code' => $plan['code'],
                'billing_cycle' => $plan['billing_cycle'] ?? $plan['code'],
                'platform' => $company->platform,
                'payment_method' => $data['payment_method'],
                'amount_cents' => $pricing['payable_cents'],
                'collected_company_fields' => ['company_document'],
                'auto_renewal_requested' => $data['payment_method'] === 'credit_card'
                    && ($plan['billing_cycle'] ?? null) === CheckoutPlanCatalog::PLAN_MONTHLY,
            ],
        ]);

        return $session;
    }

    private function markCheckoutAsFailed(CheckoutSession $session, RuntimeException $exception): CheckoutSession
    {
        $rawMessage = trim($exception->getMessage());
        $friendlyMessage = $this->friendlyCheckoutErrorMessage($rawMessage, $session->payment_method);
        $errorCode = $this->checkoutErrorCode($rawMessage, $session);

        $session->forceFill([
            'status' => CheckoutSession::STATUS_FAILED,
            'metadata' => [
                ...Arr::wrap($session->metadata),
                'failure' => [
                    'message' => $friendlyMessage,
                    'technical_message' => $rawMessage !== $friendlyMessage ? $rawMessage : null,
                    'error_code' => $errorCode,
                    'failed_at' => now()->toISOString(),
                    'provider' => $session->provider,
                    'payment_method' => $session->payment_method,
                ],
            ],
            'last_provider_sync_at' => now(),
        ])->save();

        try {
            app(TransactionalEmailService::class)->sendForCheckout(TransactionalEmailService::CODE_PAYMENT_ERROR, $session);
        } catch (\Throwable) {
            // E-mail transacional não deve mascarar o erro original do checkout.
        }

        return $session->fresh(['merchant', 'company', 'user']) ?? $session;
    }

    private function checkoutErrorPayload(RuntimeException $exception, ?CheckoutSession $session): array
    {
        $rawMessage = trim($exception->getMessage());
        $paymentMethod = (string) ($session?->payment_method ?: '');

        return array_filter([
            'message' => $this->friendlyCheckoutErrorMessage($rawMessage, $paymentMethod),
            'error_code' => $this->checkoutErrorCode($rawMessage, $session),
            'reference' => $session?->public_reference,
            'provider' => $session?->provider,
            'payment_method' => $paymentMethod ?: null,
        ], fn ($value) => filled($value));
    }

    private function friendlyCheckoutErrorMessage(string $message, string $paymentMethod): string
    {
        $message = trim($message);

        if ($message !== '' && ! $this->looksLikeTechnicalProviderMessage($message)) {
            return $message;
        }

        return match ($paymentMethod) {
            'pix' => 'Não conseguimos gerar o Pix agora. Confira os dados informados e tente novamente em instantes.',
            'boleto' => 'Não conseguimos gerar o boleto agora. Confira os dados informados e tente novamente em instantes.',
            'credit_card' => 'Não foi possível aprovar o cartão. Confira os dados, tente outro cartão ou escolha Pix.',
            default => 'Não foi possível concluir a contratação agora. Confira os dados e tente novamente em instantes.',
        };
    }

    private function looksLikeTechnicalProviderMessage(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        return $normalized === ''
            || Str::startsWith($normalized, ['|', ';'])
            || Str::contains($normalized, ['internal_error', 'bad_request', 'invalid_', 'idempotency'])
            || Str::contains($normalized, ['date_of_expiration', 'must be valid date', 'yyyy-mm-dd'])
            || (bool) preg_match('/\d{2}-\d{2}-\d{4}t\d{2}:\d{2}:\d{2}utc;[0-9a-f-]{32,36}/i', $message);
    }

    private function checkoutErrorCode(string $message, ?CheckoutSession $session): string
    {
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $message, $matches)) {
            return $matches[0];
        }

        return 'PV-CHK-'.($session?->id ?: Str::upper(Str::random(8)));
    }

    private function resolveCheckoutUser(array $data): User
    {
        $emailUser = User::query()->where('email', $data['admin_email'])->first();
        $cpfUser = User::query()->where('cpf', $data['admin_cpf'])->first();

        if ($emailUser && $cpfUser && (int) $emailUser->id !== (int) $cpfUser->id) {
            throw new RuntimeException('E-mail e CPF já pertencem a usuários diferentes. Use os dados do mesmo usuário ou fale com o suporte.');
        }

        $user = $emailUser ?: $cpfUser ?: new User;
        $payload = [
            'name' => $data['admin_name'],
            'email' => $user->exists ? $user->email : $data['admin_email'],
            'cpf' => $user->cpf ?: $data['admin_cpf'],
            'role' => in_array($user->role, ['admin', 'support'], true) ? $user->role : 'merchant',
            'password' => Hash::make($data['password']),
        ];

        if ($user->exists && $user->email !== $data['admin_email']) {
            $emailInUse = User::query()
                ->where('email', $data['admin_email'])
                ->whereKeyNot($user->id)
                ->exists();

            if (! $emailInUse) {
                $payload['email'] = $data['admin_email'];
            }
        }

        $user->forceFill($payload)->save();

        return $user;
    }

    private function checkoutCompanyName(array $data): string
    {
        $provided = trim((string) ($data['company_name'] ?? ''));
        if ($provided !== '') {
            return $provided;
        }

        return 'Empresa CNPJ '.$this->maskDocument((string) $data['company_document']);
    }

    private function maskDocument(string $document): string
    {
        $digits = preg_replace('/\D+/', '', $document) ?: $document;

        if (strlen($digits) !== 14) {
            return $digits;
        }

        return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'.substr($digits, 8, 4).'-'.substr($digits, 12, 2);
    }

    private function uniqueMerchantSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'lojista';
        $slug = $base;
        $counter = 2;

        while (Merchant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            CheckoutSession::STATUS_PAID => 'Pagamento aprovado',
            CheckoutSession::STATUS_FAILED => 'Pagamento não aprovado',
            CheckoutSession::STATUS_CANCELLED => 'Pagamento cancelado',
            CheckoutSession::STATUS_EXPIRED => 'Pagamento expirado',
            CheckoutSession::STATUS_REFUNDED => 'Pagamento estornado',
            CheckoutSession::STATUS_CHECKOUT_CREATED => 'Pagamento iniciado',
            default => 'Aguardando confirmação',
        };
    }
}
