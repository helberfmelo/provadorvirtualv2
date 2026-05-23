<?php

namespace Tests\Feature;

use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailSend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaasEmailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_smtp_and_manage_transactional_templates(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/saas/email-settings')
            ->assertOk()
            ->assertJsonPath('data.has_smtp_password', false);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/saas/email-settings', [
                'mailer' => 'smtp',
                'host' => 'mail.provadorvirtual.online',
                'port' => 587,
                'username' => 'noreply@provadorvirtual.online',
                'smtp_password' => 'secret-test',
                'encryption' => 'tls',
                'from_address' => 'noreply@provadorvirtual.online',
                'from_name' => 'Provador Virtual',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.host', 'mail.provadorvirtual.online')
            ->assertJsonPath('data.has_smtp_password', true)
            ->assertJsonMissingPath('data.smtp_password');

        $this->withHeaders($headers)
            ->getJson('/api/v1/saas/transactional-emails')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $created = $this->withHeaders($headers)
            ->postJson('/api/v1/saas/transactional-emails', [
                'name' => 'Aviso operacional',
                'description' => 'Mensagem manual para lojistas.',
                'subject' => 'Aviso do Provador Virtual',
                'body' => 'Ola {{nome}}, temos um aviso para {{empresa}}.',
                'variables' => ['nome', 'empresa'],
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'aviso_operacional')
            ->json('data');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/saas/transactional-emails/'.$created['id'], [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('transactional_emails', [
            'code' => 'aviso_operacional',
            'is_active' => false,
        ]);

        TransactionalEmailSend::query()->create([
            'transactional_email_id' => $created['id'],
            'code' => 'aviso_operacional',
            'recipient_email' => 'cliente@example.com',
            'recipient_name' => 'Cliente',
            'subject' => 'Aviso',
            'body' => 'Mensagem',
            'status' => TransactionalEmailSend::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/saas/transactional-email-sends')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'aviso_operacional')
            ->assertJsonPath('data.0.status', TransactionalEmailSend::STATUS_SENT);
    }

    public function test_merchant_cannot_manage_saas_email_settings(): void
    {
        $merchant = User::query()->create([
            'name' => 'Lojista',
            'email' => 'lojista@example.com',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);

        $token = $merchant->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/saas/transactional-emails')
            ->assertForbidden();
    }

    private function adminToken(): string
    {
        $admin = User::query()->create([
            'name' => 'Admin SaaS',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        TransactionalEmail::query()->delete();

        return $admin->createToken('test')->plainTextToken;
    }
}
