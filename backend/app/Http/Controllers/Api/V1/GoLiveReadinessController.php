<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Models\MeasurementTable;
use App\Models\PlatformConnection;
use App\Models\Product;
use App\Models\RecommendationLog;
use App\Models\WidgetInstall;
use Illuminate\Http\Request;

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
            $this->bigShopPilotCheck($merchant->id, $company?->id),
            $this->oneClickSecretCheck(),
            $this->aiProviderCheck(),
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
                'product_test' => rtrim((string) config('app.url'), '/').'/produto-teste',
                'widget_js' => rtrim((string) config('app.url'), '/').'/widget/v1/provador-virtual.js',
                'api_health' => rtrim((string) config('app.url'), '/').'/api/v1/health',
                'ops_status' => rtrim((string) config('app.url'), '/').'/api/v1/ops/status',
            ],
            'missing_credentials' => [
                'bigshop_activation_secret' => ! filled(config('services.bigshop.activation_secret')),
                'bigshop_test_store' => ! $this->hasConfiguredBigShop($merchant->id, $company?->id),
                'external_ai_key' => ! $this->hasExternalAiKey(),
            ],
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
            detail: $count.' produto(s) com tabela e variacao ativa.',
            action: $count > 0 ? 'Validar recomendacao em /produto-teste apos cada deploy.' : 'Vincular tabela e variacoes ao produto teste.'
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
            label: 'Widget e dominios',
            status: $install && $domains->isNotEmpty() ? 'passed' : 'blocked',
            detail: $install ? $domains->count().' dominio(s) liberado(s).' : 'Widget inativo.',
            action: $install && $domains->isNotEmpty() ? 'Testar snippet no dominio final antes do cutover.' : 'Ativar widget e cadastrar dominios liberados.'
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
            label: 'Smoke de recomendacao',
            status: $count > 0 ? 'passed' : 'warning',
            detail: $count.' recomendacao(oes) registrada(s).',
            action: $count > 0 ? 'Repetir smoke no deploy final.' : 'Rodar uma recomendacao real no produto teste.'
        );
    }

    private function bigShopPilotCheck(int $merchantId, ?int $companyId): array
    {
        $configured = $this->hasConfiguredBigShop($merchantId, $companyId);

        return $this->check(
            key: 'bigshop_pilot',
            label: 'Loja BigShop piloto',
            status: $configured ? 'passed' : 'warning',
            detail: $configured ? 'Conexao BigShop configurada.' : 'Aguardando loja, store_id e token x-api reais.',
            action: $configured ? 'Executar probe e sync antes do piloto.' : 'Cadastrar credenciais reais da loja piloto.'
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
            detail: $externalKey ? 'Provider externo configuravel.' : 'Parser local ativo; OCR de imagem real pendente.',
            action: $externalKey ? 'Validar custo e prompt antes de liberar OCR.' : 'Cadastrar OPENAI_API_KEY ou GEMINI_API_KEY quando OCR for necessario.'
        );
    }

    private function legalPagesCheck(): array
    {
        return $this->check(
            key: 'legal_pages',
            label: 'Privacidade e termos',
            status: 'passed',
            detail: 'Paginas publicas /privacidade e /termos disponiveis.',
            action: 'Revisar texto juridico antes de campanhas pagas.'
        );
    }

    private function cutoverPlanCheck(): array
    {
        return $this->check(
            key: 'root_cutover',
            label: 'Plano da raiz do dominio',
            status: 'passed',
            detail: 'Site publico v2 publicado na raiz; backend permanece em /provadorvirtual_v2 para rollback.',
            action: 'Validar raiz e subpasta em cada deploy.'
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
