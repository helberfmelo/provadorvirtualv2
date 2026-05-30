<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LegalAcceptance;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaasAuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_saas_can_list_audit_logs_and_legal_acceptances_by_company(): void
    {
        $merchant = Merchant::query()->create([
            'name' => 'Loja Auditoria',
            'slug' => 'loja-auditoria',
            'billing_status' => 'active',
        ]);
        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja Auditoria',
            'access_code' => '20260099',
            'platform' => 'custom',
            'status' => 'active',
        ]);
        $merchantUser = User::query()->create([
            'name' => 'Usuário Loja',
            'email' => 'loja.audit@example.com',
            'cpf' => '05521345620',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);
        $admin = User::query()->create([
            'name' => 'Admin SaaS',
            'email' => 'admin.audit@example.com',
            'cpf' => '12345678901',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        AuditLog::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $admin->id,
            'event' => 'widget_install.published',
            'category' => 'widget',
            'module' => 'widget',
            'action' => 'publish',
            'severity' => 'info',
            'metadata' => [
                'before' => ['platform' => 'custom'],
                'after' => ['platform' => 'bigshop'],
                'context_data' => ['published_at' => now()->toISOString()],
            ],
        ]);

        LegalAcceptance::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $merchantUser->id,
            'context' => 'checkout',
            'document_type' => 'terms_and_privacy',
            'terms_version' => '2026-05-25',
            'privacy_version' => '2026-05-25',
            'accepted_at' => now(),
            'ip_address' => '127.0.0.1',
            'ip_hash' => hash('sha256', '127.0.0.1'.config('app.key')),
            'user_agent' => 'PHPUnit',
            'metadata' => ['plan_code' => 'annual'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('saas-audit')->plainTextToken)
            ->getJson('/api/v1/saas/audit-logs?merchant_company_id='.$company->id)
            ->assertOk()
            ->assertJsonPath('data.summary.logs', 1)
            ->assertJsonPath('data.summary.acceptances', 1)
            ->assertJsonPath('data.logs.0.event', 'widget_install.published')
            ->assertJsonPath('data.logs.0.before.platform', 'custom')
            ->assertJsonPath('data.logs.0.after.platform', 'bigshop')
            ->assertJsonPath('data.acceptances.0.document_type', 'terms_and_privacy')
            ->assertJsonPath('data.acceptances.0.ip_masked', '127.0.0.xxx');

        $this->assertSame('Loja Auditoria', $response->json('data.logs.0.company.name'));
    }

    public function test_saas_can_export_audit_csv(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin SaaS',
            'email' => 'admin.audit.csv@example.com',
            'cpf' => '12345678901',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        AuditLog::query()->create([
            'event' => 'imports.committed',
            'category' => 'imports',
            'module' => 'imports',
            'action' => 'commit',
            'severity' => 'info',
            'metadata' => [
                'before' => null,
                'after' => ['import_job_id' => 55],
            ],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('saas-audit-export')->plainTextToken)
            ->get('/api/v1/saas/audit-logs/export')
            ->assertOk();

        $content = ltrim($response->streamedContent(), "\xEF\xBB\xBF");

        $this->assertStringContainsString('tipo;empresa;codigo_empresa;ator', $content);
        $this->assertStringContainsString('auditoria', $content);
        $this->assertStringContainsString('Importação executada', $content);
    }
}
