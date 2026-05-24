<?php

namespace Tests\Feature;

use App\Models\MerchantCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.pagar.me/core/v5/orders'
                && data_get($payload, 'payments.0.payment_method') === 'pix'
                && data_get($payload, 'items.0.amount') === 216486
                && data_get($payload, 'customer.address.zip_code') === '01001000'
                && str_starts_with((string) data_get($payload, 'code'), 'PV-');
        });

        $this->getJson('/api/v1/public/checkout/'.$response->json('reference'))
            ->assertOk()
            ->assertJsonPath('session.company.access_code', now()->year.'0001');
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
            return data_get($request->data(), 'items.0.amount') === 148086
                && data_get($request->data(), 'metadata.merchant_company_id') === '1';
        });

        $this->assertDatabaseHas('merchant_companies', [
            'name' => 'Loja Checkout Teste',
            'platform' => 'bigshop',
        ]);
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
        ];
    }

    private function configurePagarme(): void
    {
        config()->set('services.pagarme.secret_key', 'sk_test_checkout');
        config()->set('services.pagarme.public_key', 'pk_test_checkout');
        config()->set('services.pagarme.base_url', 'https://api.pagar.me/core/v5');
        config()->set('services.pagarme.checkout_success_url', 'https://provadorvirtual.online/checkout/sucesso');
    }
}
