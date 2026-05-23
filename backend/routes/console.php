<?php

use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\IntegrationEvent;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLog;
use App\Models\RecommendationSession;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

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

    $summary = [
        'cutoff' => $cutoff->toISOString(),
        'dry_run' => $dryRun,
        'recommendation_sessions' => $sessions->count(),
        'recommendation_logs' => $logs->count(),
        'recommendation_feedbacks' => $feedbacks->count(),
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
    }

    $this->line(json_encode($summary, JSON_PRETTY_PRINT));

    return 0;
})->purpose('Anonimiza dados corporais e identificadores tecnicos antigos do widget.');

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
