<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use App\Models\WidgetInstall;
use App\Services\PagarMeCheckoutService;
use App\Services\TransactionalEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class PublicCheckoutController extends Controller
{
    public function __construct(private readonly PagarMeCheckoutService $checkoutService) {}

    public function config(): array
    {
        return [
            'checkout' => $this->checkoutService->configuration(),
            'plans' => array_values($this->plans()),
            'pricing' => $this->pricingConfig(),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validateCheckout($request);
        $plan = $this->plans()[$data['plan_code']] ?? null;
        $pricing = $this->pricingFor($data);

        abort_if(! $plan, 422, 'Plano selecionado indisponivel.');

        try {
            $session = DB::transaction(function () use ($data, $plan, $pricing): CheckoutSession {
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
                    'provider' => 'pagarme',
                    'payment_method' => $data['payment_method'],
                    'status' => CheckoutSession::STATUS_PENDING,
                    'metadata' => [
                        'plan' => $plan,
                        'pricing' => $pricing,
                        'platform' => $company->platform,
                        'company_access_code' => $company->access_code,
                    ],
                ]);

                return $this->checkoutService->createOrder($session, $data);
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        app(TransactionalEmailService::class)->sendForCheckout(TransactionalEmailService::CODE_SIGNUP, $session);

        return response()->json([
            'checkout_url' => $this->checkoutService->publicCheckoutUrl($session->public_reference),
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

    public function webhook(Request $request)
    {
        try {
            $event = $this->checkoutService->handleWebhook($request->all(), $request->headers->all(), $request->getContent());
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
            'payment_method' => ['required', 'string', 'in:credit_card,pix'],
            'platform' => ['nullable', 'string', 'in:bigshop,shopify,woocommerce,nuvemshop,vtex,tray,custom'],
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
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
    }

    private function plans(): array
    {
        return [
            'annual' => [
                'code' => 'annual',
                'name' => 'Provador Virtual Anual',
                'price_cents' => 18990,
                'currency' => 'BRL',
                'description' => 'Plano anual unico com widget, tabela de medidas, recomendacao inteligente, integracoes padrao e suporte de ativacao.',
            ],
        ];
    }

    private function pricingConfig(): array
    {
        return [
            'default' => $this->pricingVariant('Demais plataformas', 18990),
            'bigshop' => $this->pricingVariant('Cliente BigShop', 12990),
        ];
    }

    private function pricingFor(array $data): array
    {
        $platform = $data['platform'] === 'bigshop' ? 'bigshop' : 'default';
        $variant = $this->pricingConfig()[$platform];
        $paymentMethod = $data['payment_method'] === 'credit_card' ? 'credit_card' : 'pix';

        return [
            ...$variant,
            'platform_price_key' => $platform,
            'payment_method' => $paymentMethod,
            'payable_cents' => $paymentMethod === 'pix'
                ? $variant['annual_pix_cents']
                : $variant['annual_card_cents'],
        ];
    }

    private function pricingVariant(string $label, int $monthlyCents): array
    {
        $annualCardCents = $monthlyCents * 12;

        return [
            'label' => $label,
            'monthly_cents' => $monthlyCents,
            'annual_card_cents' => $annualCardCents,
            'annual_pix_cents' => (int) round($annualCardCents * 0.95),
            'pix_discount_percent' => 5,
            'max_installments' => 12,
        ];
    }

    private function resolveCheckoutUser(array $data): User
    {
        $emailUser = User::query()->where('email', $data['admin_email'])->first();
        $cpfUser = User::query()->where('cpf', $data['admin_cpf'])->first();

        if ($emailUser && $cpfUser && (int) $emailUser->id !== (int) $cpfUser->id) {
            throw new RuntimeException('E-mail e CPF ja pertencem a usuarios diferentes. Use os dados do mesmo usuario ou fale com o suporte.');
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
            CheckoutSession::STATUS_FAILED => 'Pagamento nao aprovado',
            CheckoutSession::STATUS_CANCELLED => 'Pagamento cancelado',
            CheckoutSession::STATUS_EXPIRED => 'Pagamento expirado',
            CheckoutSession::STATUS_REFUNDED => 'Pagamento estornado',
            CheckoutSession::STATUS_CHECKOUT_CREATED => 'Pagamento iniciado',
            default => 'Aguardando confirmacao',
        };
    }
}
