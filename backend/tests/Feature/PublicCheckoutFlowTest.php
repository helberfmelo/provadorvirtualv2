<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\MerchantCompany;
use App\Models\SaasSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_checkout_creates_transparent_pagarme_order_and_company(): void
    {
        $this->configurePagarme();

        Http::fake([
            'https://api.pagar.me/core/v5/orders' => Http::response([
                'id' => 'or_pv_123',
                'status' => 'pending',
                'charges' => [
                    [
                        'id' => 'ch_pv_123',
                        'status' => 'pending',
                        'payment_method' => 'pix',
                        'last_transaction' => [
                            'qr_code' => '000201-pix',
                            'expires_at' => now()->addDay()->toIso8601String(),
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson('/api/v1/public/checkout', $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', 'checkout_created')
            ->assertJsonPath('company.access_code', now()->year.'0001')
            ->assertJsonPath('payment.pix.qr_code', '000201-pix');

        $this->assertDatabaseHas('merchant_companies', [
            'name' => 'Loja Checkout Teste',
            'status' => 'pending_payment',
        ]);
        $this->assertDatabaseHas('checkout_acceptances', [
            'lead_email' => 'admin.checkout@example.com',
            'company_document' => '11222333000181',
            'accepted_terms' => true,
            'terms_version' => '2026-05-25',
            'privacy_version' => '2026-05-25',
        ]);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.pagar.me/core/v5/orders'
                && data_get($payload, 'payments.0.payment_method') === 'pix'
                && data_get($payload, 'items.0.amount') === 512772
                && data_get($payload, 'customer.address.zip_code') === '01001000'
                && str_starts_with((string) data_get($payload, 'code'), 'PV-');
        });

        $this->getJson('/api/v1/public/checkout/'.$response->json('reference'))
            ->assertOk()
            ->assertJsonPath('session.company.access_code', now()->year.'0001');
    }

    public function test_public_checkout_accepts_only_company_document_for_company_data(): void
    {
        $this->configurePagarme();

        Http::fake([
            'https://api.pagar.me/core/v5/orders' => Http::response([
                'id' => 'or_pv_cnpj_only',
                'status' => 'pending',
                'charges' => [
                    [
                        'id' => 'ch_pv_cnpj_only',
                        'status' => 'pending',
                        'payment_method' => 'pix',
                    ],
                ],
            ]),
        ]);

        $payload = $this->payload();
        unset(
            $payload['company_name'],
            $payload['company_legal_name'],
            $payload['company_domain'],
            $payload['company_zip_code'],
            $payload['company_address_street'],
            $payload['company_address_number'],
            $payload['company_address_district'],
            $payload['company_address_city'],
            $payload['company_address_state'],
        );

        $response = $this->postJson('/api/v1/public/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('status', 'checkout_created');

        $this->assertDatabaseHas('merchant_companies', [
            'document' => '11222333000181',
            'name' => 'Empresa CNPJ 11.222.333/0001-81',
            'legal_name' => null,
            'domain' => null,
        ]);

        $session = CheckoutSession::query()->where('public_reference', $response->json('reference'))->firstOrFail();
        $this->assertTrue((bool) data_get($session->metadata, 'company_profile_pending'));
        $this->assertSame(['company_document'], data_get($session->metadata, 'checkout_collected_company_fields'));

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return data_get($payload, 'customer.name') === 'Empresa CNPJ 11.222.333/0001-81'
                && data_get($payload, 'customer.document') === '11222333000181'
                && data_get($payload, 'customer.address') === null;
        });
    }

    public function test_provider_rejection_is_recorded_as_failed_checkout_attempt(): void
    {
        $this->configurePagarme();

        Http::fake([
            'https://api.pagar.me/core/v5/orders' => Http::response([
                'errors' => [
                    'payment' => ['Cartão recusado pela operadora.'],
                ],
            ], 422),
        ]);

        $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'payment_method' => 'credit_card',
            'card_token' => 'card-token-recused',
            'installments' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cartão recusado pela operadora.');

        $session = CheckoutSession::query()->firstOrFail();
        $this->assertSame(CheckoutSession::STATUS_FAILED, $session->status);
        $this->assertSame('Cartão recusado pela operadora.', data_get($session->metadata, 'failure.message'));
        $this->assertDatabaseHas('checkout_acceptances', [
            'checkout_session_id' => $session->id,
            'lead_email' => 'admin.checkout@example.com',
        ]);
    }

    public function test_public_checkout_creates_transparent_mercado_pago_pix_payment(): void
    {
        $this->configureMercadoPago();

        Http::fake([
            'https://api.mercadopago.com/v1/payments' => Http::response([
                'id' => 998877,
                'status' => 'pending',
                'status_detail' => 'pending_waiting_transfer',
                'payment_method_id' => 'pix',
                'date_of_expiration' => now()->addDay()->toIso8601String(),
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => '000201-mp-pix',
                        'qr_code_base64' => 'base64-pix',
                        'ticket_url' => 'https://www.mercadopago.com.br/payments/998877/ticket',
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson('/api/v1/public/checkout', $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', 'checkout_created')
            ->assertJsonPath('payment.pix.qr_code', '000201-mp-pix')
            ->assertJsonPath('payment.pix.qr_code_base64', 'base64-pix');

        $this->assertDatabaseHas('checkout_sessions', [
            'public_reference' => $response->json('reference'),
            'provider' => 'mercado_pago',
            'provider_order_id' => '998877',
        ]);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $idempotencyHeader = $request->header('X-Idempotency-Key');
            $idempotencyKey = is_array($idempotencyHeader)
                ? (string) ($idempotencyHeader[0] ?? '')
                : (string) $idempotencyHeader;

            return $request->url() === 'https://api.mercadopago.com/v1/payments'
                && Str::isUuid($idempotencyKey)
                && data_get($payload, 'payment_method_id') === 'pix'
                && data_get($payload, 'transaction_amount') === 5127.72
                && data_get($payload, 'payer.address.zip_code') === '01001000'
                && data_get($payload, 'metadata.platform') === 'provadorvirtual';
        });

        $session = CheckoutSession::query()->where('public_reference', $response->json('reference'))->firstOrFail();
        $this->assertTrue(Str::isUuid((string) data_get($session->metadata, 'mercado_pago.idempotency_key')));
    }

    public function test_mercado_pago_opaque_pix_error_is_returned_with_friendly_message_and_code(): void
    {
        $this->configureMercadoPago();

        Http::fake([
            'https://api.mercadopago.com/v1/payments' => Http::response([
                'message' => ' | 25-05-2026T21:37:38UTC;3e640a80-db11-4831-bf3f-34b1c503bf20',
                'error' => 'bad_request',
            ], 422),
        ]);

        $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'plan_code' => 'monthly',
            'platform' => 'bigshop',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Não conseguimos gerar o Pix agora. Confira os dados informados e tente novamente em instantes.')
            ->assertJsonPath('error_code', '3e640a80-db11-4831-bf3f-34b1c503bf20')
            ->assertJsonPath('provider', 'mercado_pago')
            ->assertJsonPath('payment_method', 'pix');

        $session = CheckoutSession::query()->firstOrFail();
        $this->assertSame(CheckoutSession::STATUS_FAILED, $session->status);
        $this->assertSame(
            'Não conseguimos gerar o Pix agora. Confira os dados informados e tente novamente em instantes.',
            data_get($session->metadata, 'failure.message'),
        );
        $this->assertSame(
            '| 25-05-2026T21:37:38UTC;3e640a80-db11-4831-bf3f-34b1c503bf20',
            data_get($session->metadata, 'failure.technical_message'),
        );
        $this->assertSame('3e640a80-db11-4831-bf3f-34b1c503bf20', data_get($session->metadata, 'failure.error_code'));
    }

    public function test_public_checkout_config_exposes_monthly_and_annual_prices(): void
    {
        $this->configureMercadoPago();

        $this->getJson('/api/v1/public/checkout/config')
            ->assertOk()
            ->assertJsonPath('plans.0.code', 'annual')
            ->assertJsonPath('plans.1.code', 'monthly')
            ->assertJsonPath('pricing.default.monthly.monthly_cents', 48980)
            ->assertJsonPath('pricing.default.annual.monthly_cents', 44980)
            ->assertJsonPath('pricing.default.annual.card_total_cents', 539760)
            ->assertJsonPath('pricing.default.annual.savings_percent', 8.2)
            ->assertJsonPath('pricing.bigshop.monthly.monthly_cents', 38980)
            ->assertJsonPath('pricing.bigshop.annual.monthly_cents', 34990)
            ->assertJsonPath('pricing.bigshop.annual.card_total_cents', 419880)
            ->assertJsonPath('pricing.bigshop.annual.savings_percent', 10.2);
    }

    public function test_bigshop_platform_receives_discounted_annual_price(): void
    {
        $this->configurePagarme();

        Http::fake([
            'https://api.pagar.me/core/v5/orders' => Http::response([
                'id' => 'or_pv_bigshop',
                'status' => 'pending',
                'charges' => [
                    [
                        'id' => 'ch_pv_bigshop',
                        'status' => 'pending',
                        'payment_method' => 'pix',
                        'last_transaction' => [
                            'qr_code' => '000201-pix-bigshop',
                            'expires_at' => now()->addDay()->toIso8601String(),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'platform' => 'bigshop',
        ])->assertCreated();

        Http::assertSent(function ($request): bool {
            return data_get($request->data(), 'items.0.amount') === 398886
                && data_get($request->data(), 'metadata.merchant_company_id') === '1';
        });

        $this->assertDatabaseHas('merchant_companies', [
            'name' => 'Loja Checkout Teste',
            'platform' => 'bigshop',
        ]);
    }

    public function test_monthly_plan_uses_monthly_price_by_platform(): void
    {
        $this->configurePagarme();

        Http::fake([
            'https://api.pagar.me/core/v5/orders' => Http::response([
                'id' => 'or_pv_monthly',
                'status' => 'pending',
                'charges' => [
                    [
                        'id' => 'ch_pv_monthly',
                        'status' => 'pending',
                        'payment_method' => 'pix',
                    ],
                ],
            ]),
        ]);

        $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'plan_code' => 'monthly',
            'platform' => 'bigshop',
        ])->assertCreated();

        Http::assertSent(function ($request): bool {
            return data_get($request->data(), 'items.0.amount') === 38980
                && data_get($request->data(), 'items.0.description') === 'Provador Virtual Mensal - 1 mes';
        });
    }

    public function test_monthly_credit_card_creates_mercado_pago_subscription(): void
    {
        $this->configureMercadoPago();

        Http::fake([
            'https://api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_monthly_123',
                'status' => 'authorized',
                'external_reference' => 'mp-monthly-ref',
                'payment_method_id' => 'visa',
                'next_payment_date' => now()->addMonth()->toIso8601String(),
                'date_created' => now()->toIso8601String(),
                'auto_recurring' => [
                    'frequency' => 1,
                    'frequency_type' => 'months',
                    'transaction_amount' => 489.8,
                    'currency_id' => 'BRL',
                ],
            ], 201),
        ]);

        $response = $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'plan_code' => 'monthly',
            'payment_method' => 'credit_card',
            'card_token' => 'mp-card-token',
            'payment_method_id' => 'visa',
            'installments' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('payment.subscription.id', 'preapproval_monthly_123')
            ->assertJsonPath('payment.subscription.status', 'authorized');

        $this->assertNotEmpty($response->json('reference'));
        $this->assertDatabaseHas('billing_subscriptions', [
            'provider' => 'mercado_pago',
            'provider_subscription_id' => 'preapproval_monthly_123',
            'plan_code' => 'monthly',
            'billing_cycle' => 'monthly',
            'auto_renewal_enabled' => true,
            'amount_cents' => 48980,
        ]);
        $this->assertDatabaseHas('merchant_companies', [
            'name' => 'Loja Checkout Teste',
            'status' => 'active',
        ]);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.mercadopago.com/preapproval'
                && data_get($payload, 'card_token_id') === 'mp-card-token'
                && data_get($payload, 'auto_recurring.frequency') === 1
                && data_get($payload, 'auto_recurring.frequency_type') === 'months'
                && data_get($payload, 'auto_recurring.transaction_amount') === 489.8
                && data_get($payload, 'status') === 'authorized';
        });
    }

    public function test_checkout_does_not_accept_boleto(): void
    {
        $this->configurePagarme();

        $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'payment_method' => 'boleto',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_mercado_pago_boleto_is_available_only_when_saas_enables_it(): void
    {
        $this->configureMercadoPago();
        SaasSetting::setValue('checkout.boleto_enabled', true);

        Http::fake([
            'https://api.mercadopago.com/v1/payments' => Http::response([
                'id' => 778899,
                'status' => 'pending',
                'status_detail' => 'pending_waiting_payment',
                'payment_method_id' => 'bolbradesco',
                'payment_type_id' => 'ticket',
                'date_of_expiration' => now()->addDays(3)->toIso8601String(),
                'transaction_details' => [
                    'external_resource_url' => 'https://www.mercadopago.com.br/payments/778899/ticket',
                    'digitable_line' => '23791.11111 22222.222222 33333.333333 1 99990000048980',
                ],
            ]),
        ]);

        $this->getJson('/api/v1/public/checkout/config')
            ->assertOk()
            ->assertJsonPath('checkout.boleto_enabled', true)
            ->assertJsonPath('checkout.payment_methods.2', 'boleto');

        $response = $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'plan_code' => 'monthly',
            'payment_method' => 'boleto',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'checkout_created')
            ->assertJsonPath('payment.boleto.ticket_url', 'https://www.mercadopago.com.br/payments/778899/ticket')
            ->assertJsonPath('payment.boleto.digitable_line', '23791.11111 22222.222222 33333.333333 1 99990000048980');

        $this->assertNotEmpty($response->json('reference'));
        $this->assertDatabaseHas('checkout_sessions', [
            'provider' => 'mercado_pago',
            'provider_order_id' => '778899',
            'payment_method' => 'boleto',
            'amount_cents' => 48980,
        ]);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.mercadopago.com/v1/payments'
                && data_get($payload, 'payment_method_id') === 'bolbradesco'
                && data_get($payload, 'transaction_amount') === 489.8;
        });
    }

    public function test_checkout_requires_terms_acceptance(): void
    {
        $this->configurePagarme();

        $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'accepted_terms' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('accepted_terms');
    }

    public function test_same_user_can_contract_more_than_one_company(): void
    {
        $this->configurePagarme();

        Http::fake([
            'https://api.pagar.me/core/v5/orders' => Http::response([
                'id' => 'or_pv_multi',
                'status' => 'pending',
                'charges' => [
                    ['id' => 'ch_pv_multi', 'status' => 'pending', 'payment_method' => 'pix'],
                ],
            ]),
        ]);

        $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'company_name' => 'Loja Multi A',
            'company_document' => '11222333000181',
        ])->assertCreated();

        $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'company_name' => 'Loja Multi B',
            'company_document' => '22333444000191',
        ])->assertCreated();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('merchants', 2);
        $this->assertSame(2, DB::table('merchant_user')->count());
    }

    public function test_checkout_rejects_when_email_and_cpf_belong_to_different_users(): void
    {
        $this->configurePagarme();

        User::query()->create([
            'name' => 'Email Owner',
            'email' => 'admin.checkout@example.com',
            'cpf' => '11122233344',
            'role' => 'merchant',
            'password' => bcrypt('password123'),
        ]);
        User::query()->create([
            'name' => 'CPF Owner',
            'email' => 'outro@example.com',
            'cpf' => '05521345620',
            'role' => 'merchant',
            'password' => bcrypt('password123'),
        ]);

        Http::fake([
            'https://api.pagar.me/core/v5/orders' => Http::response(['id' => 'or_should_not_run']),
        ]);

        $this->postJson('/api/v1/public/checkout', $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'E-mail e CPF já pertencem a usuários diferentes. Use os dados do mesmo usuário ou fale com o suporte.');
    }

    public function test_pagarme_webhook_activates_paid_company(): void
    {
        $this->configurePagarme();

        Http::fake([
            'https://api.pagar.me/core/v5/orders' => Http::response([
                'id' => 'or_pv_123',
                'status' => 'pending',
                'charges' => [
                    ['id' => 'ch_pv_123', 'status' => 'pending', 'payment_method' => 'pix'],
                ],
            ]),
        ]);

        $checkout = $this->postJson('/api/v1/public/checkout', $this->payload())->assertCreated();
        $company = MerchantCompany::query()->firstOrFail();

        $this->postJson('/api/v1/webhooks/pagarme', [
            'id' => 'evt_paid_123',
            'type' => 'order.paid',
            'data' => [
                'status' => 'paid',
                'metadata' => [
                    'checkout_reference' => $checkout->json('reference'),
                    'checkout_session_id' => '1',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('merchant_companies', [
            'id' => $company->id,
            'status' => 'active',
        ]);
    }

    public function test_mercado_pago_webhook_activates_paid_company(): void
    {
        $this->configureMercadoPago();

        Http::fake([
            'https://api.mercadopago.com/v1/payments' => Http::response([
                'id' => 998877,
                'status' => 'pending',
                'payment_method_id' => 'pix',
            ]),
            'https://api.mercadopago.com/v1/payments/998877' => Http::response([
                'id' => 998877,
                'status' => 'approved',
                'payment_method_id' => 'pix',
                'metadata' => [
                    'checkout_session_id' => '1',
                    'checkout_reference' => 'mp-ref',
                ],
            ]),
        ]);

        $checkout = $this->postJson('/api/v1/public/checkout', [
            ...$this->payload(),
            'company_domain' => 'mp-checkout.test',
        ])->assertCreated();
        $company = MerchantCompany::query()->firstOrFail();

        $this->postJson('/api/v1/webhooks/mercado-pago', [
            'id' => 'evt_mp_paid_123',
            'type' => 'payment',
            'data' => [
                'id' => '998877',
            ],
        ])->assertOk();

        $this->assertNotEmpty($checkout->json('reference'));
        $this->assertDatabaseHas('merchant_companies', [
            'id' => $company->id,
            'status' => 'active',
        ]);
    }

    private function payload(): array
    {
        return [
            'plan_code' => 'annual',
            'payment_method' => 'pix',
            'platform' => 'custom',
            'company_name' => 'Loja Checkout Teste',
            'company_legal_name' => 'Loja Checkout Teste Ltda',
            'company_document' => '11222333000181',
            'company_domain' => 'checkout.test',
            'company_zip_code' => '01001000',
            'company_address_street' => 'Praca da Se',
            'company_address_number' => '10',
            'company_address_district' => 'Se',
            'company_address_city' => 'Sao Paulo',
            'company_address_state' => 'SP',
            'admin_name' => 'Admin Checkout',
            'admin_email' => 'admin.checkout@example.com',
            'admin_cpf' => '05521345620',
            'admin_phone' => '11999990000',
            'password' => 'checkout123',
            'password_confirmation' => 'checkout123',
            'accepted_terms' => true,
        ];
    }

    private function configurePagarme(): void
    {
        SaasSetting::setValue('checkout.payment_provider', 'pagarme');
        config()->set('services.checkout.default_provider', 'pagarme');
        config()->set('services.pagarme.secret_key', 'sk_test_checkout');
        config()->set('services.pagarme.public_key', 'pk_test_checkout');
        config()->set('services.pagarme.base_url', 'https://api.pagar.me/core/v5');
        config()->set('services.pagarme.checkout_success_url', 'https://provadorvirtual.online/checkout/sucesso');
    }

    private function configureMercadoPago(): void
    {
        SaasSetting::setValue('checkout.payment_provider', 'mercado_pago');
        config()->set('services.checkout.default_provider', 'mercado_pago');
        config()->set('services.mercado_pago.access_token', 'APP_USR-test-token');
        config()->set('services.mercado_pago.public_key', 'APP_USR-test-public');
        config()->set('services.mercado_pago.base_url', 'https://api.mercadopago.com');
        config()->set('services.mercado_pago.webhook_secret', null);
        config()->set('services.mercado_pago.checkout_success_url', 'https://provadorvirtual.online/checkout/sucesso');
        config()->set('services.mercado_pago.webhook_url', 'https://provadorvirtual.online/api/v1/webhooks/mercado-pago');
    }
}
