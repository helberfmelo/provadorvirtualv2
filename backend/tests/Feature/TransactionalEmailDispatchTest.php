<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\EmailSetting;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailSend;
use App\Models\User;
use App\Services\TransactionalEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionalEmailDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_sends_checkout_email_and_records_history(): void
    {
        $this->configureEmail();
        $session = $this->checkoutSession(CheckoutSession::STATUS_CHECKOUT_CREATED);

        $send = app(TransactionalEmailService::class)
            ->sendForCheckout(TransactionalEmailService::CODE_SIGNUP, $session);

        $this->assertSame(TransactionalEmailSend::STATUS_SENT, $send->status);
        $this->assertDatabaseHas('transactional_email_sends', [
            'checkout_session_id' => $session->id,
            'code' => TransactionalEmailService::CODE_SIGNUP,
            'recipient_email' => 'owner@example.com',
            'status' => TransactionalEmailSend::STATUS_SENT,
        ]);
    }

    public function test_email_dispatch_command_sends_financial_events_without_duplicates(): void
    {
        $this->configureEmail();
        $paid = $this->checkoutSession(CheckoutSession::STATUS_PAID, 'paid-ref');
        $failed = $this->checkoutSession(CheckoutSession::STATUS_FAILED, 'failed-ref');
        $pending = $this->checkoutSession(CheckoutSession::STATUS_CHECKOUT_CREATED, 'pending-ref');

        $this->artisan('pv:emails-dispatch', ['--limit' => 10])
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactional_email_sends', [
            'checkout_session_id' => $paid->id,
            'code' => TransactionalEmailService::CODE_PAYMENT_CONFIRMED,
            'status' => TransactionalEmailSend::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('transactional_email_sends', [
            'checkout_session_id' => $failed->id,
            'code' => TransactionalEmailService::CODE_PAYMENT_ERROR,
            'status' => TransactionalEmailSend::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('transactional_email_sends', [
            'checkout_session_id' => $pending->id,
            'code' => TransactionalEmailService::CODE_PAYMENT_PENDING,
            'status' => TransactionalEmailSend::STATUS_SENT,
        ]);

        $this->artisan('pv:emails-dispatch', ['--limit' => 10])
            ->assertExitCode(0);

        $this->assertSame(3, TransactionalEmailSend::query()->count());
    }

    private function configureEmail(): void
    {
        TransactionalEmail::ensureDefaults();
        EmailSetting::query()->updateOrCreate(
            ['scope' => EmailSetting::DEFAULT_SCOPE],
            [
                'mailer' => 'log',
                'from_address' => 'noreply@provadorvirtual.online',
                'from_name' => 'Provador Virtual',
                'is_active' => true,
            ],
        );
    }

    private function checkoutSession(string $status, string $reference = 'checkout-ref'): CheckoutSession
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Owner Teste',
                'role' => 'merchant',
                'password' => bcrypt('password123'),
            ],
        );
        $merchant = Merchant::query()->firstOrCreate(
            ['slug' => 'loja-email'],
            [
                'name' => 'Loja Email',
                'billing_status' => $status === CheckoutSession::STATUS_PAID ? 'active' : 'pending_payment',
            ],
        );
        $company = MerchantCompany::query()->firstOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Loja Email Ltda'],
            [
                'platform' => 'bigshop',
                'status' => $status === CheckoutSession::STATUS_PAID ? 'active' : 'pending_payment',
            ],
        );
        $company->ensureAccessCode();

        return CheckoutSession::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $user->id,
            'public_reference' => $reference,
            'plan_code' => 'annual',
            'plan_name' => 'Provador Virtual Anual',
            'lead_name' => 'Owner Teste',
            'lead_company' => 'Loja Email Ltda',
            'lead_email' => 'owner@example.com',
            'amount_cents' => 148086,
            'provider' => 'pagarme',
            'payment_method' => 'pix',
            'status' => $status,
            'metadata' => [
                'payment_snapshot' => [
                    'pix' => [
                        'qr_code' => '000201-pix',
                    ],
                ],
            ],
        ]);
    }
}
