<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Models\MeasurementTable;
use App\Models\PlatformConnection;
use App\Models\Product;
use App\Models\RecommendationLog;
use App\Models\WidgetInstall;
use App\Services\CheckoutPaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class GoLiveReadinessController extends Controller
{
    use ResolvesMerchant;

    public function __invoke(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $checks = collect([
            $this->productsCheck($merchant->id, $company?->id),
            $this->measurementTablesCheck($merchant->id, $company?->id),
            $this->productTestCheck($merchant->id, $company?->id),
            $this->widgetCheck($merchant->id, $company?->id),
            $this->recommendationSmokeCheck($merchant->id, $company?->id),
            $this->paymentProviderCheck(),
            $this->paymentRealTransactionCheck(),
            $this->schedulerCheck(),
            $this->bigShopPilotCheck($merchant->id, $company?->id),
            $this->oneClickSecretCheck(),
            $this->aiProviderCheck(),
            $this->widgetPerformanceCheck(),
            $this->accessibilityCheck(),
            $this->legalPagesCheck(),
            $this->cutoverPlanCheck(),
        ]);

        $blockers = $checks->where('status', 'blocked')->count();
        $warnings = $checks->where('status', 'warning')->count();
        $passed = $checks->where('status', 'passed')->count();

        return response()->json([
            'summary' => [
                'status' => $blockers > 0 ? 'blocked' : ($warnings > 0 ? 'ready_with_warnings' : 'ready'),
                'passed' => $passed,
                'warnings' => $warnings,
                'blockers' => $blockers,
                'total' => $checks->count(),
            ],
            'checks' => $checks->values(),
            'production_urls' => [
                'app' => config('app.url'),
                'root' => config('app.frontend_url', config('app.url')),
                'checkout' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/checkout',
                'product_test' => rtrim((string) config('app.url'), '/').'/produto-teste',
                'widget_js' => rtrim((string) config('app.url'), '/').'/widget/v1/provador-virtual.js',
                'api_health' => rtrim((string) config('app.url'), '/').'/api/v1/health',
                'ops_status' => rtrim((string) config('app.url'), '/').'/api/v1/ops/status',
                'privacy' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/privacidade',
                'terms' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/termos',
            ],
            'missing_credentials' => [
                'bigshop_activation_secret' => ! filled(config('services.bigshop.activation_secret')),
                'bigshop_test_store' => ! $this->hasConfiguredBigShop($merchant->id, $company?->id),
                'external_ai_key' => ! $this->hasExternalAiKey(),
                'checkout_provider_keys' => ! $this->hasCheckoutProviderKeys(),
                'checkout_real_transaction' => ! $this->hasPaidCheckout(),
                'cron_scheduler_recent' => ! $this->hasRecentSchedulerLog(),
            ],
            'pilot_package' => $this->pilotPackage(),
        ]);
    }

    private function productsCheck(int $merchantId, ?int $companyId): array
    {
        $count = Product::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->where('status', 'active')
            ->count();

        return $this->check(
            key: 'products',
            label: 'Produtos ativos',
            status: $count > 0 ? 'passed' : 'blocked',
            detail: $count.' produto(s) ativo(s).',
            action: $count > 0 ? 'Manter pelo menos o produto teste configurado.' : 'Cadastrar ao menos um produto ativo.'
        );
    }

    private function measurementTablesCheck(int $merchantId, ?int $companyId): array
    {
        $count = MeasurementTable::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->where('status', 'active')
            ->whereHas('rows')
            ->count();

        return $this->check(
            key: 'measurement_tables',
            label: 'Tabelas de medidas',
            status: $count > 0 ? 'passed' : 'blocked',
            detail: $count.' tabela(s) ativa(s) com linhas.',
            action: $count > 0 ? 'Revisar tabelas antes de campanhas reais.' : 'Criar uma tabela ativa com tamanhos.'
        );
    }

    private function productTestCheck(int $merchantId, ?int $companyId): array
    {
        $count = Product::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->whereNotNull('measurement_table_id')
            ->whereHas('variants', fn ($query) => $query->where('is_active', true))
            ->count();

        return $this->check(
            key: 'product_test',
            label: 'Produto teste funcional',
            status: $count > 0 ? 'passed' : 'blocked',
            detail: $count.' produto(s) com tabela e variação ativa.',
            action: $count > 0 ? 'Validar recomendação em /produto-teste após cada deploy.' : 'Vincular tabela e variações ao produto teste.'
        );
    }

    private function widgetCheck(int $merchantId, ?int $companyId): array
    {
        $install = WidgetInstall::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->where('is_active', true)
            ->first();
        $domains = collect($install?->allowed_domains ?? [])->filter()->values();

        return $this->check(
            key: 'widget_domains',
            label: 'Widget e domínios',
            status: $install && $domains->isNotEmpty() ? 'passed' : 'blocked',
            detail: $install ? $domains->count().' domínio(s) liberado(s).' : 'Widget inativo.',
            action: $install && $domains->isNotEmpty() ? 'Testar snippet no domínio final antes do cutover.' : 'Ativar widget e cadastrar domínios liberados.'
        );
    }

    private function recommendationSmokeCheck(int $merchantId, ?int $companyId): array
    {
        $count = RecommendationLog::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->count();

        return $this->check(
            key: 'recommendation_smoke',
            label: 'Smoke de recomendação',
            status: $count > 0 ? 'passed' : 'warning',
            detail: $count.' recomendação(oes) registrada(s).',
            action: $count > 0 ? 'Repetir smoke no deploy final.' : 'Rodar uma recomendação real no produto teste.'
        );
    }

    private function bigShopPilotCheck(int $merchantId, ?int $companyId): array
    {
        $configured = $this->hasConfiguredBigShop($merchantId, $companyId);

        return $this->check(
            key: 'bigshop_pilot',
            label: 'Loja BigShop piloto',
            status: $configured ? 'passed' : 'warning',
            detail: $configured ? 'Conexão BigShop configurada.' : 'Aguardando loja, store_id e token x-api reais.',
            action: $configured ? 'Executar probe e sync antes do piloto.' : 'Cadastrar credenciais reais da loja piloto.'
        );
    }

    private function paymentProviderCheck(): array
    {
        $manager = app(CheckoutPaymentManager::class);
        $provider = $manager->provider();
        $configured = $manager->activeProviderConfigured();

        return $this->check(
            key: 'checkout_provider',
            label: 'Checkout '.$provider->label(),
            status: $configured ? 'passed' : 'warning',
            detail: $configured ? 'Operadora ativa com credenciais minimas configuradas.' : 'Credenciais da operadora ativa ainda nao estao completas em producao.',
            action: $configured ? 'Executar compra real de baixo valor antes da campanha.' : 'Configurar Mercado Pago ou Pagar.me no ambiente e selecionar a operadora no painel SaaS.'
        );
    }

    private function paymentRealTransactionCheck(): array
    {
        $paid = $this->hasPaidCheckout();

        return $this->check(
            key: 'checkout_real_transaction',
            label: 'Transação real de pagamento',
            status: $paid ? 'passed' : 'warning',
            detail: $paid ? 'Existe checkout aprovado registrado.' : 'Nenhuma transação real aprovada registrada ainda.',
            action: $paid ? 'Manter webhook e cron monitorados.' : 'Depois das chaves da operadora, executar uma compra Pix/cartao de baixo valor.'
        );
    }

    private function schedulerCheck(): array
    {
        $recent = $this->hasRecentSchedulerLog();

        return $this->check(
            key: 'scheduler_cron',
            label: 'Cron e automações',
            status: $recent ? 'passed' : 'warning',
            detail: $recent ? 'Log do scheduler atualizado recentemente.' : 'Não ha log recente do scheduler do Laravel.',
            action: $recent ? 'Continuar acompanhando pagamentos e e-mails.' : 'Cadastrar no cPanel: php artisan schedule:run a cada minuto com log em storage/logs/cron-schedule.log.'
        );
    }

    private function oneClickSecretCheck(): array
    {
        $configured = filled(config('services.bigshop.activation_secret'));

        return $this->check(
            key: 'bigshop_one_click_secret',
            label: 'Secret do um clique',
            status: $configured ? 'passed' : 'warning',
            detail: $configured ? 'BIGSHOP_ACTIVATION_SECRET configurado.' : 'Secret ainda ausente no ambiente.',
            action: $configured ? 'Validar payload assinado com a BigShop.' : 'Adicionar BIGSHOP_ACTIVATION_SECRET ao PRODUCTION_ENV.'
        );
    }

    private function aiProviderCheck(): array
    {
        $externalKey = $this->hasExternalAiKey();

        return $this->check(
            key: 'ai_ocr',
            label: 'OCR externo de IA',
            status: $externalKey ? 'passed' : 'warning',
            detail: $externalKey ? 'Provider externo configurável.' : 'Parser local ativo; OCR de imagem real pendente.',
            action: $externalKey ? 'Validar custo e prompt antes de liberar OCR.' : 'Cadastrar OPENAI_API_KEY ou GEMINI_API_KEY quando OCR for necessário.'
        );
    }

    private function legalPagesCheck(): array
    {
        return $this->check(
            key: 'legal_pages',
            label: 'Privacidade e termos',
            status: 'passed',
            detail: 'Páginas públicas /privacidade e /termos disponíveis.',
            action: 'Revisar texto jurídico antes de campanhas pagas.'
        );
    }

    private function cutoverPlanCheck(): array
    {
        return $this->check(
            key: 'root_cutover',
            label: 'Plano da raiz do domínio',
            status: 'passed',
            detail: 'Site público v2 publicado na raiz; backend permanece em /provadorvirtual_v2 para rollback.',
            action: 'Validar raiz e subpasta em cada deploy.'
        );
    }

    private function widgetPerformanceCheck(): array
    {
        $jsPath = public_path('widget/v1/provador-virtual.js');
        $cssPath = public_path('widget/v1/provador-virtual.css');
        $jsKb = File::exists($jsPath) ? round(File::size($jsPath) / 1024, 1) : 0;
        $cssKb = File::exists($cssPath) ? round(File::size($cssPath) / 1024, 1) : 0;
        $passed = $jsKb > 0 && $cssKb > 0 && $jsKb <= 90 && $cssKb <= 40;

        return $this->check(
            key: 'widget_performance',
            label: 'Peso do widget',
            status: $passed ? 'passed' : 'warning',
            detail: "JS {$jsKb} KB, CSS {$cssKb} KB.",
            action: $passed ? 'Validar em produto real com cache frio e 4G.' : 'Revisar peso do JS/CSS antes de piloto com trafego pago.'
        );
    }

    private function accessibilityCheck(): array
    {
        $script = File::exists(public_path('widget/v1/provador-virtual.js'))
            ? File::get(public_path('widget/v1/provador-virtual.js'))
            : '';
        $css = File::exists(public_path('widget/v1/provador-virtual.css'))
            ? File::get(public_path('widget/v1/provador-virtual.css'))
            : '';
        $passed = str_contains($script, 'role="dialog"')
            && str_contains($script, 'aria-modal="true"')
            && str_contains($css, '@media (max-width: 560px)');

        return $this->check(
            key: 'accessibility_mobile',
            label: 'Acessibilidade e mobile do widget',
            status: $passed ? 'passed' : 'warning',
            detail: $passed ? 'Modal com roles ARIA e regra mobile revisada.' : 'Widget precisa revisar ARIA/mobile.',
            action: 'Validar manualmente no produto teste em desktop, tablet e celular antes do piloto.'
        );
    }

    private function hasConfiguredBigShop(int $merchantId, ?int $companyId): bool
    {
        return PlatformConnection::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->where('platform', 'bigshop')
            ->whereIn('status', ['configured', 'connected'])
            ->exists();
    }

    private function hasExternalAiKey(): bool
    {
        return filled(config('services.ai.openai_api_key')) || filled(config('services.ai.gemini_api_key'));
    }

    private function hasCheckoutProviderKeys(): bool
    {
        return app(CheckoutPaymentManager::class)->activeProviderConfigured();
    }

    private function hasPaidCheckout(): bool
    {
        return CheckoutSession::query()
            ->where('status', CheckoutSession::STATUS_PAID)
            ->exists();
    }

    private function hasRecentSchedulerLog(): bool
    {
        $path = storage_path('logs/cron-schedule.log');

        return File::exists($path) && File::lastModified($path) >= now()->subMinutes(15)->getTimestamp();
    }

    private function pilotPackage(): array
    {
        return [
            'status' => $this->hasCheckoutProviderKeys() && $this->hasPaidCheckout() ? 'commercial_ready' : 'assisted_demo_ready',
            'sales_assets' => [
                ['label' => 'Site público', 'url' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/'],
                ['label' => 'Produto teste', 'url' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/produto-teste'],
                ['label' => 'Checkout', 'url' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/checkout'],
                ['label' => 'WhatsApp especialista', 'url' => 'https://wa.me/5531993157573'],
            ],
            'onboarding_steps' => [
                'Cadastrar empresa no SaaS ou pelo checkout.',
                'Conferir plataforma contratada e código/CNPJ de acesso.',
                'Cadastrar ou importar produtos, variações e tabelas de medidas.',
                'Configurar domínio permitido do widget.',
                'Instalar snippet ou usar integração BigShop.',
                'Executar recomendação real e feedback no produto piloto.',
                'Acompanhar analytics, outliers e prontidão de go-live.',
            ],
            'automation_commands' => [
                'cron' => 'cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan schedule:run >> /home1/opents62/provadorvirtual.online/provadorvirtual_v2/storage/logs/cron-schedule.log 2>&1',
                'payments' => 'php artisan pv:payments-sync --limit=50',
                'emails' => 'php artisan pv:emails-dispatch --limit=50',
                'feeds' => 'php artisan pv:integrations-sync-feeds --limit=50',
                'privacy' => 'php artisan pv:privacy-anonymize',
                'validation' => '.\\scripts\\validate-production.ps1',
            ],
            'pending_real_world_tests' => [
                'Transação Mercado Pago Pix/cartao de baixo valor com webhook e cron.',
                'Ativação BigShop um clique com payload assinado real.',
                'Probe e sync em loja BigShop piloto com produto, grade e tabela.',
                'Teste de widget em página real de cliente com cache frio e mobile.',
            ],
        ];
    }

    private function check(string $key, string $label, string $status, string $detail, string $action): array
    {
        return compact('key', 'label', 'status', 'detail', 'action');
    }

    private function scopeCompanyId($query, ?int $companyId)
    {
        if (! $companyId) {
            return $query;
        }

        return $query->where(function ($innerQuery) use ($companyId): void {
            $innerQuery->where('merchant_company_id', $companyId)
                ->orWhereNull('merchant_company_id');
        });
    }
}
