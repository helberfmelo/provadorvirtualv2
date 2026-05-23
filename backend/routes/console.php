<?php

use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\IntegrationEvent;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLearningEvent;
use App\Models\RecommendationLog;
use App\Models\RecommendationSession;
use App\Models\ShopperProfile;
use App\Models\User;
use App\Services\PagarMeCheckoutService;
use App\Services\TransactionalEmailService;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pv:create-master-admin {--email=} {--name=} {--cpf=} {--password=}', function (): int {
    $email = mb_strtolower(trim((string) $this->option('email')));
    $name = trim((string) $this->option('name'));
    $cpf = preg_replace('/\D+/', '', (string) $this->option('cpf')) ?: null;
    $password = (string) $this->option('password');

    if ($email === '' || $name === '' || $password === '') {
        $this->error('Informe --email, --name e --password.');

        return 1;
    }

    if ($cpf !== null && strlen($cpf) !== 11) {
        $this->error('CPF deve conter 11 digitos.');

        return 1;
    }

    $user = User::query()->updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'cpf' => $cpf,
            'role' => 'admin',
            'password' => Hash::make($password),
        ],
    );

    $this->info("Master admin pronto: {$user->email}");

    return 0;
})->purpose('Cria ou atualiza um master admin do SaaS sem expor a senha no banco de versao.');

Artisan::command('pv:ensure-demo-store-owner {--email=} {--name=} {--cpf=} {--password=}', function (): int {
    $email = mb_strtolower(trim((string) $this->option('email')));
    $name = trim((string) $this->option('name'));
    $cpf = preg_replace('/\D+/', '', (string) $this->option('cpf')) ?: null;
    $password = (string) $this->option('password');

    if ($email === '' || $name === '' || $password === '') {
        $this->error('Informe --email, --name e --password.');

        return 1;
    }

    if ($cpf !== null && strlen($cpf) !== 11) {
        $this->error('CPF deve conter 11 digitos.');

        return 1;
    }

    $merchant = Merchant::query()->updateOrCreate(
        ['slug' => 'provador-virtual-demo'],
        [
            'name' => 'Provador Virtual Demo Store',
            'billing_status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ],
    );

    $companyData = [
        'name' => 'Provador Virtual Loja Teste',
        'legal_name' => 'Provador Virtual Loja Teste Ltda',
        'document' => '12345678000195',
        'domain' => 'provadorvirtual.online',
        'platform' => 'custom',
        'status' => 'active',
    ];

    foreach ([
        'zip_code' => '01001000',
        'street' => 'Praca da Se',
        'number' => '100',
        'district' => 'Se',
        'city' => 'Sao Paulo',
        'state' => 'SP',
        'country' => 'BR',
    ] as $column => $value) {
        if (Schema::hasColumn('merchant_companies', $column)) {
            $companyData[$column] = $value;
        }
    }

    $company = MerchantCompany::query()->updateOrCreate(
        [
            'merchant_id' => $merchant->id,
            'external_store_id' => 'pv-demo-store',
        ],
        $companyData,
    );

    if (Schema::hasColumn('merchant_companies', 'access_code')) {
        $company->ensureAccessCode();
        $company->refresh();
    }

    $userData = [
        'name' => $name,
        'role' => 'admin',
        'password' => Hash::make($password),
    ];

    if (Schema::hasColumn('users', 'cpf')) {
        $userData['cpf'] = $cpf;
    }

    if (Schema::hasColumn('users', 'status')) {
        $userData['status'] = 'active';
    }

    if (Schema::hasColumn('users', 'permissions')) {
        $userData['permissions'] = PermissionCatalog::full('saas');
    }

    $user = User::query()->updateOrCreate(['email' => $email], $userData);

    $pivotData = [
        'role' => 'owner',
        'is_owner' => true,
    ];

    if (Schema::hasColumn('merchant_user', 'merchant_company_id')) {
        $pivotData['merchant_company_id'] = $company->id;
    }

    if (Schema::hasColumn('merchant_user', 'status')) {
        $pivotData['status'] = 'active';
    }

    if (Schema::hasColumn('merchant_user', 'permissions')) {
        $pivotData['permissions'] = json_encode(PermissionCatalog::full('merchant'));
    }

    $user->merchants()->syncWithoutDetaching([
        $merchant->id => $pivotData,
    ]);

    $storeAccess = $company->access_code ?: $company->document ?: (string) $company->id;
    $this->info("Usuario {$user->email} vinculado como owner da loja teste {$storeAccess}.");

    return 0;
})->purpose('Vincula o master admin como owner da loja teste usada no provador virtual demo.');

Artisan::command('pv:privacy-anonymize {--days= : Dias de retencao de dados do widget} {--dry-run}', function (): int {
    $days = (int) ($this->option('days') ?: config('privacy.widget_data_retention_days', 30));
    $cutoff = now()->subDays(max(1, $days));
    $dryRun = (bool) $this->option('dry-run');

    $sessions = RecommendationSession::query()
        ->where('created_at', '<', $cutoff)
        ->where(function ($query): void {
            $query->whereNotNull('shopper_profile')
                ->orWhereNotNull('ip_hash')
                ->orWhereNotNull('user_agent_hash');
        });

    $logs = RecommendationLog::query()
        ->where('created_at', '<', $cutoff)
        ->where(function ($query): void {
            $query->whereNotNull('input_measurements')
                ->orWhereNotNull('score_breakdown');
        });

    $feedbacks = RecommendationFeedback::query()
        ->where('created_at', '<', $cutoff)
        ->whereNotNull('comment');

    $profiles = ShopperProfile::query()
        ->where('updated_at', '<', $cutoff)
        ->where(function ($query): void {
            $query->whereNotNull('measurements')
                ->orWhereNotNull('preferences')
                ->orWhereNotNull('write_token_hash');
        });

    $learningEvents = RecommendationLearningEvent::query()
        ->where('created_at', '<', $cutoff)
        ->whereNotNull('payload');

    $summary = [
        'cutoff' => $cutoff->toISOString(),
        'dry_run' => $dryRun,
        'recommendation_sessions' => $sessions->count(),
        'recommendation_logs' => $logs->count(),
        'recommendation_feedbacks' => $feedbacks->count(),
        'shopper_profiles' => $profiles->count(),
        'recommendation_learning_events' => $learningEvents->count(),
    ];

    if (! $dryRun) {
        $sessions->update([
            'shopper_profile' => null,
            'ip_hash' => null,
            'user_agent_hash' => null,
            'expires_at' => null,
        ]);

        $logs->update([
            'input_measurements' => null,
            'score_breakdown' => null,
        ]);

        $feedbacks->update([
            'comment' => null,
        ]);

        $profiles->update([
            'status' => 'anonymized',
            'write_token_hash' => null,
            'measurements' => null,
            'preferences' => null,
            'expires_at' => null,
        ]);

        $learningEvents->update([
            'payload' => null,
        ]);
    }

    $this->line(json_encode($summary, JSON_PRETTY_PRINT));

    return 0;
})->purpose('Anonimiza dados corporais e identificadores tecnicos antigos do widget.');

Artisan::command('pv:payments-sync {--limit=50 : Quantidade maxima de checkouts pendentes por execucao}', function (): int {
    $limit = max(1, min(200, (int) ($this->option('limit') ?: 50)));
    $summary = app(PagarMeCheckoutService::class)->syncPendingCheckouts($limit);

    $this->line(json_encode($summary, JSON_PRETTY_PRINT));

    return ((int) $summary['errors']) > 0 ? 2 : 0;
})->purpose('Consulta a Pagar.me e libera acessos de checkouts pendentes aprovados.');

Artisan::command('pv:emails-dispatch {--limit=50 : Quantidade maxima de checkouts avaliados por execucao}', function (): int {
    $limit = max(1, min(200, (int) ($this->option('limit') ?: 50)));
    $summary = app(TransactionalEmailService::class)->dispatchFinancialEmails($limit);

    $this->line(json_encode($summary, JSON_PRETTY_PRINT));

    return ((int) $summary['failed']) > 0 ? 2 : 0;
})->purpose('Dispara e-mails transacionais de pagamento pendente, confirmado e recusado.');

Artisan::command('pv:privacy-prune {--days= : Dias de retencao de logs operacionais} {--dry-run}', function (): int {
    $days = (int) ($this->option('days') ?: config('privacy.operational_log_retention_days', 180));
    $cutoff = now()->subDays(max(30, $days));
    $dryRun = (bool) $this->option('dry-run');

    $auditLogs = AuditLog::query()->where('created_at', '<', $cutoff);
    $aiUsageLogs = AiUsageLog::query()->where('created_at', '<', $cutoff);
    $integrationEvents = IntegrationEvent::query()->where('created_at', '<', $cutoff);

    $summary = [
        'cutoff' => $cutoff->toISOString(),
        'dry_run' => $dryRun,
        'audit_logs' => $auditLogs->count(),
        'ai_usage_logs' => $aiUsageLogs->count(),
        'integration_events' => $integrationEvents->count(),
    ];

    if (! $dryRun) {
        $auditLogs->delete();
        $aiUsageLogs->delete();
        $integrationEvents->delete();
    }

    $this->line(json_encode($summary, JSON_PRETTY_PRINT));

    return 0;
})->purpose('Remove logs operacionais antigos mantendo analytics de recomendacao.');

Schedule::command('pv:payments-sync --limit=50')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('pv:emails-dispatch --limit=50')->everyTenMinutes()->withoutOverlapping();
Schedule::command('pv:privacy-anonymize')->dailyAt('03:17')->withoutOverlapping();
Schedule::command('pv:privacy-prune')->weeklyOn(0, '03:37')->withoutOverlapping();
