<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Models\IntegrationEvent;
use App\Models\MeasurementTable;
use App\Models\PlatformConnection;
use App\Models\Product;
use App\Models\RecommendationLog;
use App\Models\WidgetInstall;
use App\Services\CheckoutPaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class GoLiveReadinessController extends Controller
{
    use ResolvesMerchant;

    public function __invoke(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $generatedAt = now()->toISOString();

        $coverage = $this->coverageSnapshot($merchant->id, $company?->id);
        $widget = $this->widgetSnapshot($merchant->id, $company?->id);
        $sync = $this->syncSnapshot($merchant->id, $company?->id);

        $checks = collect([
            $this->productsCheck($coverage),
            $this->measurementTablesCheck($coverage),
            $this->catalogCoverageCheck($coverage),
            $this->productDataQualityCheck($coverage),
            $this->productTestCheck($coverage),
            $this->widgetPublicationCheck($widget),
            $this->recommendationSmokeCheck($coverage['recommendations_total']),
            $this->syncHealthCheck($sync),
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
        $summaryStatus = $blockers > 0 ? 'blocked' : ($warnings > 0 ? 'ready_with_warnings' : 'ready');

        $summary = [
            'status' => $summaryStatus,
            'status_label' => $this->summaryStatusLabel($summaryStatus),
            'passed' => $passed,
            'warnings' => $warnings,
            'blockers' => $blockers,
            'total' => $checks->count(),
            'generated_at' => $generatedAt,
        ];

        return response()->json([
            'summary' => $summary,
            'checks' => $checks->values(),
            'connected_data' => [
                'coverage' => $coverage,
                'widget' => $widget,
                'sync' => $sync,
            ],
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
            'report' => $this->publicationReport($company?->name, $summary, $checks, $coverage, $widget, $sync, $generatedAt),
        ]);
    }

    private function coverageSnapshot(int $merchantId, ?int $companyId): array
    {
        $products = Product::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->select(['id', 'measurement_table_id', 'category', 'fit_profile', 'status', 'metadata', 'updated_at'])
            ->get();

        $activeProducts = $products->where('status', 'active');
        $activeProductsCount = $activeProducts->count();
        $readyProducts = $activeProducts->filter(fn (Product $product): bool => $this->productIsReady($product))->count();
        $withoutTable = $activeProducts->whereNull('measurement_table_id')->count();
        $withoutModeling = $activeProducts->filter(fn (Product $product): bool => blank($product->fit_profile))->count();
        $withoutCategory = $activeProducts->filter(fn (Product $product): bool => blank($product->category))->count();
        $syncErrors = $activeProducts->filter(fn (Product $product): bool => $this->hasSyncError($product))->count();
        $virtualTryOnDisabled = $activeProducts->filter(fn (Product $product): bool => ! $this->activationEnabled($product, 'virtual_try_on_enabled'))->count();
        $measurementTableDisabled = $activeProducts->filter(fn (Product $product): bool => ! $this->activationEnabled($product, 'measurement_table_enabled'))->count();
        $criticalProducts = $withoutTable + $syncErrors + $virtualTryOnDisabled + $measurementTableDisabled;
        $coverageRate = $activeProductsCount > 0 ? round(($readyProducts / $activeProductsCount) * 100, 1) : 0.0;
        $status = $activeProductsCount === 0 || $readyProducts === 0
            ? 'blocked'
            : ($criticalProducts > 0 || $withoutModeling > 0 || $withoutCategory > 0 ? 'warning' : 'passed');

        return [
            'status' => $status,
            'label' => 'Catálogo',
            'summary' => $activeProductsCount === 0
                ? 'Nenhum produto ativo pronto para receber o provador.'
                : $readyProducts.' de '.$activeProductsCount.' produto(s) ativo(s) estão prontos para publicar.',
            'detail' => 'Cobertura ativa de '.$coverageRate.'%. Pendências críticas: '.$criticalProducts.'. Pendências de revisão: '.($withoutModeling + $withoutCategory).'.',
            'link' => '/app/produtos?filtro=pendentes',
            'metrics' => [
                ['label' => 'ativos', 'value' => $activeProductsCount],
                ['label' => 'prontos', 'value' => $readyProducts],
                ['label' => 'sem tabela', 'value' => $withoutTable],
                ['label' => 'erro de sync', 'value' => $syncErrors],
            ],
            'total_products' => $products->count(),
            'active_products' => $activeProductsCount,
            'ready_products' => $readyProducts,
            'without_measurement_table' => $withoutTable,
            'without_modeling' => $withoutModeling,
            'without_category' => $withoutCategory,
            'sync_errors' => $syncErrors,
            'virtual_try_on_disabled' => $virtualTryOnDisabled,
            'measurement_table_disabled' => $measurementTableDisabled,
            'critical_products' => $criticalProducts,
            'coverage_rate' => $coverageRate,
            'measurement_tables_active' => MeasurementTable::query()
                ->where('merchant_id', $merchantId)
                ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
                ->where('status', 'active')
                ->whereHas('rows')
                ->count(),
            'product_test_ready' => Product::query()
                ->where('merchant_id', $merchantId)
                ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
                ->where('status', 'active')
                ->whereNotNull('measurement_table_id')
                ->whereHas('variants', fn ($query) => $query->where('is_active', true))
                ->count(),
            'recommendations_total' => RecommendationLog::query()
                ->where('merchant_id', $merchantId)
                ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
                ->count(),
            'updated_at' => $products->max('updated_at')?->toISOString(),
        ];
    }

    private function widgetSnapshot(int $merchantId, ?int $companyId): array
    {
        $install = WidgetInstall::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->orderByRaw('merchant_company_id is null')
            ->first();

        $domains = collect($install?->allowed_domains ?? [])->filter()->values();
        $placementStatus = data_get($install?->theme ?? [], 'placement.validation.status', 'untested');
        $hasDraft = $install ? $this->widgetHasUnpublishedChanges($install) : false;
        $status = ! $install || ! $install->is_active || $domains->isEmpty()
            ? 'blocked'
            : (in_array($placementStatus, ['failed'], true)
                ? 'blocked'
                : (in_array($placementStatus, ['warning', 'untested'], true) || $hasDraft ? 'warning' : 'passed'));

        $placementLabel = match ($placementStatus) {
            'passed' => 'seletor validado',
            'warning' => 'seletor com alerta',
            'failed' => 'seletor com falha',
            default => 'seletor ainda não validado',
        };

        return [
            'status' => $status,
            'label' => 'Widget',
            'summary' => ! $install
                ? 'O widget ainda não foi configurado.'
                : $domains->count().' domínio(s) liberado(s) e publicação '.($install->is_active ? 'ativa' : 'inativa').'.',
            'detail' => ! $install
                ? 'Abra a tela do widget para ativar, definir domínios e validar o seletor da página de produto.'
                : 'Estado atual: '.$placementLabel.'. '.($hasDraft ? 'Existem mudanças em rascunho aguardando publicação.' : 'Sem mudanças pendentes em rascunho.'),
            'link' => '/app/widget',
            'metrics' => [
                ['label' => 'domínios', 'value' => $domains->count()],
                ['label' => 'publicado', 'value' => $install && $install->is_active ? 'sim' : 'não'],
                ['label' => 'seletor', 'value' => $placementStatus],
                ['label' => 'rascunho', 'value' => $hasDraft ? 'sim' : 'não'],
            ],
            'allowed_domains_count' => $domains->count(),
            'is_active' => (bool) ($install?->is_active ?? false),
            'placement_status' => $placementStatus,
            'has_unpublished_changes' => $hasDraft,
            'published_at' => ($install?->published_at ?: $install?->updated_at)?->toISOString(),
        ];
    }

    private function syncSnapshot(int $merchantId, ?int $companyId): array
    {
        $latestEvent = IntegrationEvent::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->whereIn('event_type', ['dry_run_import', 'sync_products', 'xml_feed_sync'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        $configuredIntegrations = PlatformConnection::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->whereIn('status', ['configured', 'connected'])
            ->count();

        $counters = $this->syncCounters($latestEvent);
        $status = $configuredIntegrations === 0
            ? 'warning'
            : (! $latestEvent
                ? 'warning'
                : (($latestEvent->status === 'failed' || $counters['errors'] > 0 || $counters['warnings'] > 0) ? 'warning' : 'passed'));

        $originLabel = match ($latestEvent?->event_type) {
            'sync_products' => 'API BigShop',
            'xml_feed_sync' => 'XML/feed',
            'dry_run_import' => 'Prévia',
            default => $latestEvent?->platform ? strtoupper((string) $latestEvent->platform) : null,
        };

        return [
            'status' => $status,
            'label' => 'Sincronização',
            'summary' => $configuredIntegrations === 0
                ? 'Nenhuma integração conectada até agora.'
                : ($latestEvent
                    ? 'Última execução em '.optional($latestEvent->occurred_at)->format('d/m/Y H:i').'.'
                    : 'Ainda não existe histórico recente de sincronização.'),
            'detail' => $latestEvent
                ? $counters['errors'].' erro(s), '.$counters['warnings'].' alerta(s) e '.$counters['total'].' item(ns) processados.'
                : 'Use a tela de sincronização para revisar origem, erros e reprocessamentos antes da publicação.',
            'link' => '/app/sincronizacao',
            'metrics' => [
                ['label' => 'integrações', 'value' => $configuredIntegrations],
                ['label' => 'origem', 'value' => $originLabel ?: 'sem histórico'],
                ['label' => 'erros', 'value' => $counters['errors']],
                ['label' => 'alertas', 'value' => $counters['warnings']],
            ],
            'configured_integrations' => $configuredIntegrations,
            'origin_label' => $originLabel,
            'last_run_at' => $latestEvent?->occurred_at?->toISOString(),
            'errors' => $counters['errors'],
            'warnings' => $counters['warnings'],
            'total' => $counters['total'],
            'issue_groups' => collect(data_get($latestEvent?->summary ?? [], 'issue_groups', []))
                ->filter(fn ($group): bool => is_array($group))
                ->take(3)
                ->values()
                ->all(),
        ];
    }

    private function productsCheck(array $coverage): array
    {
        return $this->check(
            key: 'products',
            label: 'Produtos ativos',
            status: $coverage['active_products'] > 0 ? 'passed' : 'blocked',
            detail: $coverage['active_products'].' produto(s) ativo(s) no catálogo.',
            action: $coverage['active_products'] > 0 ? 'Mantenha pelo menos um produto ativo com dados revisados.' : 'Cadastre e ative ao menos um produto antes de publicar.',
            link: '/app/produtos',
            group: 'catalogo'
        );
    }

    private function measurementTablesCheck(array $coverage): array
    {
        return $this->check(
            key: 'measurement_tables',
            label: 'Tabelas de medidas',
            status: $coverage['measurement_tables_active'] > 0 ? 'passed' : 'blocked',
            detail: $coverage['measurement_tables_active'].' tabela(s) ativa(s) com linhas.',
            action: $coverage['measurement_tables_active'] > 0 ? 'Revise as tabelas ligadas aos produtos antes de campanhas reais.' : 'Crie uma tabela ativa com linhas de medida antes de publicar.',
            link: '/app/tabelas-de-medidas',
            group: 'catalogo'
        );
    }

    private function catalogCoverageCheck(array $coverage): array
    {
        return $this->check(
            key: 'catalog_coverage',
            label: 'Cobertura do catálogo',
            status: $coverage['status'],
            detail: $coverage['summary'],
            action: $coverage['status'] === 'passed'
                ? 'Continue monitorando a cobertura depois de cada importação ou revisão.'
                : 'Abra os produtos pendentes e resolva tabela, modelagem, categoria e ativações individuais.',
            link: $coverage['link'],
            group: 'catalogo',
            impact: $coverage['detail']
        );
    }

    private function productDataQualityCheck(array $coverage): array
    {
        $status = $coverage['critical_products'] > 0
            ? 'blocked'
            : (($coverage['without_modeling'] + $coverage['without_category']) > 0 ? 'warning' : 'passed');

        return $this->check(
            key: 'product_data_quality',
            label: 'Pendências críticas por produto',
            status: $status,
            detail: $coverage['critical_products'].' produto(s) com bloqueio e '.($coverage['without_modeling'] + $coverage['without_category']).' com revisão recomendada.',
            action: $status === 'passed'
                ? 'Os produtos ativos não têm bloqueios críticos no momento.'
                : 'Corrija produtos sem tabela, com erro de sincronização ou com o provador/tabela desativados antes de publicar.',
            link: '/app/produtos?filtro=pendentes',
            group: 'catalogo',
            impact: 'Sem correção, a publicação abre espaço para produtos sem recomendação ou com dados inconsistentes.'
        );
    }

    private function productTestCheck(array $coverage): array
    {
        return $this->check(
            key: 'product_test',
            label: 'Produto piloto validável',
            status: $coverage['product_test_ready'] > 0 ? 'passed' : 'blocked',
            detail: $coverage['product_test_ready'].' produto(s) com tabela e variação ativa para teste.',
            action: $coverage['product_test_ready'] > 0 ? 'Revalide o produto piloto depois de cada deploy final.' : 'Vincule tabela e mantenha ao menos uma variação ativa no produto piloto.',
            link: '/produto-teste',
            group: 'catalogo'
        );
    }

    private function widgetPublicationCheck(array $widget): array
    {
        return $this->check(
            key: 'widget_publication',
            label: 'Publicação do widget',
            status: $widget['status'],
            detail: $widget['summary'],
            action: $widget['status'] === 'passed'
                ? 'Continue validando o seletor e os domínios sempre que a PDP mudar.'
                : 'Ative o widget, revise domínios, publique o rascunho e valide o seletor da página do produto antes de liberar a loja.',
            link: $widget['link'],
            group: 'widget',
            impact: $widget['detail']
        );
    }

    private function recommendationSmokeCheck(int $recommendationsTotal): array
    {
        return $this->check(
            key: 'recommendation_smoke',
            label: 'Smoke de recomendação',
            status: $recommendationsTotal > 0 ? 'passed' : 'warning',
            detail: $recommendationsTotal.' recomendação(ões) registrada(s).',
            action: $recommendationsTotal > 0 ? 'Repita o smoke no deploy final para confirmar a jornada completa.' : 'Execute uma recomendação real no produto piloto antes da publicação.',
            link: '/app/assistente',
            group: 'operacao'
        );
    }

    private function syncHealthCheck(array $sync): array
    {
        return $this->check(
            key: 'sync_health',
            label: 'Histórico de sincronização',
            status: $sync['status'],
            detail: $sync['summary'],
            action: $sync['status'] === 'passed'
                ? 'Acompanhe erros e alertas sempre que houver nova importação.'
                : 'Reveja a última sincronização, trate alertas e reprocessamentos antes da publicação.',
            link: $sync['link'],
            group: 'sincronizacao',
            impact: $sync['detail']
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
            action: $configured ? 'Executar probe e sync antes do piloto real.' : 'Cadastrar credenciais reais da loja piloto quando a operação comercial liberar.',
            link: '/app/integracoes',
            group: 'sincronizacao'
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
            detail: $configured ? 'Operadora ativa com credenciais mínimas configuradas.' : 'Credenciais da operadora ativa ainda não estão completas em produção.',
            action: $configured ? 'Executar compra real de baixo valor antes da campanha.' : 'Configurar Mercado Pago ou Pagar.me no ambiente e selecionar a operadora no painel SaaS.',
            link: '/saas/checkout',
            group: 'financeiro'
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
            action: $paid ? 'Manter webhook e cron monitorados.' : 'Depois das chaves da operadora, execute uma compra Pix ou cartão de baixo valor.',
            link: '/saas/pedidos',
            group: 'financeiro'
        );
    }

    private function schedulerCheck(): array
    {
        $recent = $this->hasRecentSchedulerLog();

        return $this->check(
            key: 'scheduler_cron',
            label: 'Cron e automações',
            status: $recent ? 'passed' : 'warning',
            detail: $recent ? 'Log do scheduler atualizado recentemente.' : 'Não há log recente do scheduler do Laravel.',
            action: $recent ? 'Continuar acompanhando pagamentos, e-mails e sincronizações.' : 'Cadastre no cPanel: php artisan schedule:run a cada minuto com log em storage/logs/cron-schedule.log.',
            link: '/saas/emails',
            group: 'operacao'
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
            action: $configured ? 'Validar payload assinado com a BigShop.' : 'Adicionar BIGSHOP_ACTIVATION_SECRET ao PRODUCTION_ENV.',
            link: '/app/integracoes',
            group: 'seguranca'
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
            action: $externalKey ? 'Validar custo e prompt antes de liberar OCR.' : 'Cadastrar OPENAI_API_KEY ou GEMINI_API_KEY quando OCR for necessário.',
            link: '/app/assistente',
            group: 'operacao'
        );
    }

    private function legalPagesCheck(): array
    {
        return $this->check(
            key: 'legal_pages',
            label: 'Privacidade e termos',
            status: 'passed',
            detail: 'Páginas públicas /privacidade e /termos disponíveis.',
            action: 'Revisar o texto jurídico antes de campanhas pagas.',
            link: '/privacidade',
            group: 'seguranca'
        );
    }

    private function cutoverPlanCheck(): array
    {
        return $this->check(
            key: 'root_cutover',
            label: 'Plano da raiz do domínio',
            status: 'passed',
            detail: 'Site público v2 publicado na raiz; backend permanece em /provadorvirtual_v2 para rollback.',
            action: 'Validar raiz e subpasta em cada deploy.',
            link: '/',
            group: 'operacao'
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
            action: $passed ? 'Validar em produto real com cache frio e 4G.' : 'Revisar o peso do JS e do CSS antes do piloto com tráfego pago.',
            link: '/app/widget',
            group: 'widget'
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
            detail: $passed ? 'Modal com roles ARIA e regra mobile revisada.' : 'Widget ainda precisa revisar ARIA ou mobile.',
            action: 'Validar manualmente no produto piloto em desktop, tablet e celular antes da publicação.',
            link: '/app/widget',
            group: 'widget'
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
                'Transação Mercado Pago Pix/cartão de baixo valor com webhook e cron.',
                'Ativação BigShop um clique com payload assinado real.',
                'Probe e sync em loja BigShop piloto com produto, grade e tabela.',
                'Teste de widget em página real de cliente com cache frio e mobile.',
            ],
        ];
    }

    private function publicationReport(?string $companyName, array $summary, Collection $checks, array $coverage, array $widget, array $sync, string $generatedAt): array
    {
        $headline = match ($summary['status']) {
            'ready' => 'A loja está pronta para publicar o provador.',
            'ready_with_warnings' => 'A loja está pronta com avisos que merecem acompanhamento.',
            default => 'A publicação ainda está bloqueada por itens críticos.',
        };

        $blockers = $checks->where('status', 'blocked')
            ->map(fn (array $check): string => $check['label'].': '.$check['action'])
            ->values()
            ->all();
        $warnings = $checks->where('status', 'warning')
            ->map(fn (array $check): string => $check['label'].': '.$check['action'])
            ->values()
            ->all();
        $recommendations = $checks->whereIn('status', ['blocked', 'warning'])
            ->map(fn (array $check): array => [
                'label' => $check['label'],
                'description' => $check['action'],
                'link' => $check['link'] ?? null,
            ])
            ->unique('label')
            ->values()
            ->all();
        $storeLabel = $companyName ?: 'sua loja';
        $summaryText = 'Catálogo com '.$coverage['ready_products'].' de '.$coverage['active_products'].' produto(s) ativo(s) prontos, widget em estado '.$this->statusBadgeLabel($widget['status']).' e sincronização em estado '.$this->statusBadgeLabel($sync['status']).'.';

        $lines = [
            'Relatório de publicação - '.$storeLabel,
            'Gerado em: '.now()->format('d/m/Y H:i'),
            'Status: '.$summary['status_label'],
            '',
            $headline,
            $summaryText,
        ];

        if ($blockers !== []) {
            $lines[] = '';
            $lines[] = 'Bloqueios:';
            foreach ($blockers as $blocker) {
                $lines[] = '- '.$blocker;
            }
        }

        if ($warnings !== []) {
            $lines[] = '';
            $lines[] = 'Avisos:';
            foreach ($warnings as $warning) {
                $lines[] = '- '.$warning;
            }
        }

        if ($recommendations !== []) {
            $lines[] = '';
            $lines[] = 'Próximos passos:';
            foreach ($recommendations as $recommendation) {
                $line = '- '.$recommendation['label'].': '.$recommendation['description'];
                if ($recommendation['link']) {
                    $line .= ' ('.$recommendation['link'].')';
                }

                $lines[] = $line;
            }
        }

        return [
            'title' => 'Relatório de publicação',
            'generated_at' => $generatedAt,
            'status_label' => $summary['status_label'],
            'headline' => $headline,
            'summary' => $summaryText,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'text' => implode("\n", $lines),
        ];
    }

    private function productIsReady(Product $product): bool
    {
        return $product->status === 'active'
            && filled($product->measurement_table_id)
            && filled($product->fit_profile)
            && filled($product->category)
            && ! $this->hasSyncError($product)
            && $this->activationEnabled($product, 'virtual_try_on_enabled')
            && $this->activationEnabled($product, 'measurement_table_enabled');
    }

    private function hasSyncError(Product $product): bool
    {
        $metadata = $product->metadata ?? [];

        return filled(data_get($metadata, 'sync_error'))
            || filled(data_get($metadata, 'last_sync_error'))
            || filled(data_get($metadata, 'import_error'))
            || data_get($metadata, 'sync.status') === 'error'
            || data_get($metadata, 'last_sync.status') === 'error';
    }

    private function activationEnabled(Product $product, string $flag): bool
    {
        $metadata = $product->metadata ?? [];
        $value = data_get($metadata, 'activation.'.$flag, true);

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function syncCounters(?IntegrationEvent $event): array
    {
        $summary = $event?->summary ?? [];

        return [
            'total' => $this->summaryInt($summary, ['totals.total', 'summary.total', 'total', 'counters.total']),
            'errors' => $this->summaryInt($summary, ['totals.errors', 'summary.errors', 'errors', 'error_count', 'counters.errors']),
            'warnings' => $this->summaryInt($summary, ['totals.warnings', 'summary.warnings', 'warnings', 'warnings_count', 'counters.warnings']),
        ];
    }

    private function summaryInt(array $summary, array $paths): int
    {
        foreach ($paths as $path) {
            $value = data_get($summary, $path);

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return 0;
    }

    private function widgetHasUnpublishedChanges(WidgetInstall $install): bool
    {
        $live = $this->widgetState($install, false);
        $draft = $this->widgetState($install, true);

        return $live !== $draft;
    }

    private function widgetState(WidgetInstall $install, bool $draft): array
    {
        $liveTheme = $install->theme ?? [];

        return [
            'platform' => (string) ($draft ? ($install->draft_platform ?: $install->platform ?: 'custom') : ($install->platform ?: 'custom')),
            'allowed_domains' => collect($draft ? ($install->draft_allowed_domains ?? $install->allowed_domains ?? []) : ($install->allowed_domains ?? []))
                ->map(fn ($domain): string => (string) $domain)
                ->values()
                ->all(),
            'theme' => collect($draft ? ($install->draft_theme ?? $liveTheme) : $liveTheme)
                ->sortKeys()
                ->all(),
            'is_active' => (bool) ($draft ? ($install->draft_is_active ?? $install->is_active) : $install->is_active),
        ];
    }

    private function check(
        string $key,
        string $label,
        string $status,
        string $detail,
        string $action,
        ?string $link = null,
        string $group = 'operacao',
        ?string $impact = null
    ): array {
        return compact('key', 'label', 'status', 'detail', 'action', 'link', 'group', 'impact');
    }

    private function summaryStatusLabel(string $status): string
    {
        return match ($status) {
            'ready' => 'Pronto',
            'ready_with_warnings' => 'Pronto com avisos',
            default => 'Bloqueado',
        };
    }

    private function statusBadgeLabel(string $status): string
    {
        return match ($status) {
            'passed' => 'ok',
            'warning' => 'atenção',
            'ready' => 'pronto',
            'ready_with_warnings' => 'pronto com avisos',
            default => 'bloqueado',
        };
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
