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
            $session = DB::transaction(function () use ($data, $plan, $pricing, $provider, $request): CheckoutSession {
                $acceptedAt = now();

                $merchant = Merchant::query()->create([
                    'name' => $data['company_name'],
                    'slug' => $this->uniqueMerchantSlug($data['company_name']),
                    'billing_status' => 'pending_payment',
                    'trial_ends_at' => null,
                ]);

                $user = $this->resolveCheckoutUser($data);
                $merchant->users()->syncWithoutDetaching([
                    $user->id => ['role' => 'owner', 'is_owner' => true],
                ]);

                $company = MerchantCompany::query()->create([
                    'merchant_id' => $merchant->id,
                    'name' => $data['company_name'],
                    'legal_name' => $data['company_legal_name'],
                    'document' => $data['company_document'],
                    'zip_code' => $data['company_zip_code'],
                    'street' => $data['company_address_street'],
                    'number' => $data['company_address_number'],
                    'complement' => $data['company_address_complement'] ?? null,
                    'district' => $data['company_address_district'],
                    'city' => $data['company_address_city'],
                    'state' => $data['company_address_state'],
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
                    'lead_company' => $data['company_name'],
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
                        'auto_renewal_requested' => $data['payment_method'] === 'credit_card'
                            && ($plan['billing_cycle'] ?? null) === CheckoutPlanCatalog::PLAN_MONTHLY,
                    ],
                ]);

                return $this->checkoutPayments->createOrder($session, $data);
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
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
            'company_address_state' => mb_strtoupper(trim((string) $request->input('company_address_state'))),
            'platform' => $request->input('platform') ?: 'bigshop',
        ]);

        return $request->validate([
            'plan_code' => ['required', 'string', Rule::in(array_keys($this->plans()))],
            'payment_method' => ['required', 'string', Rule::in($this->checkoutPayments->allowedPaymentMethods())],
            'platform' => ['nullable', 'string', Rule::in(PlatformCatalog::keys())],
            'company_name' => ['required', 'string', 'max:255'],
            'company_legal_name' => ['required', 'string', 'max:255'],
            'company_document' => ['required', 'string', 'min:11', 'max:14'],
            'company_domain' => ['nullable', 'string', 'max:180'],
            'company_zip_code' => ['required', 'string', 'size:8'],
            'company_address_street' => ['required', 'string', 'max:255'],
            'company_address_number' => ['required', 'string', 'max:40'],
            'company_address_complement' => ['nullable', 'string', 'max:255'],
            'company_address_district' => ['required', 'string', 'max:255'],
            'company_address_city' => ['required', 'string', 'max:255'],
            'company_address_state' => ['required', 'string', 'size:2'],
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
