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
use Illuminate\Support\Carbon;

class MerchantOverviewController extends Controller
{
    use ResolvesMerchant;

    public function __invoke(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $products = Product::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->select(['id', 'measurement_table_id', 'category', 'fit_profile', 'status', 'metadata', 'created_at', 'updated_at'])
            ->get();

        $totalProducts = $products->count();
        $activeProducts = $products->where('status', 'active')->count();
        $inactiveProducts = $products->where('status', '!=', 'active')->count();
        $withoutTable = $products->whereNull('measurement_table_id')->count();
        $withoutModeling = $products->filter(fn (Product $product): bool => blank($product->fit_profile))->count();
        $withoutCategory = $products->filter(fn (Product $product): bool => blank($product->category))->count();
        $syncErrors = $products->filter(fn (Product $product): bool => $this->hasSyncError($product))->count();
        $coveredProducts = $products
            ->filter(fn (Product $product): bool => $this->isCoveredProduct($product))
            ->count();
        $pendingProducts = max(0, $totalProducts - $coveredProducts);
        $widgetReady = $this->widgetReady($merchant->id, $company?->id);
        $installationNotValidated = $widgetReady ? 0 : $activeProducts;

        $summary = [
            'products' => $totalProducts,
            'measurement_tables' => $this->measurementTablesCount($merchant->id, $company?->id),
            'widget_status' => $widgetReady ? 'active' : 'pending',
            'widget_active' => $widgetReady,
            'integrations_configured' => $this->integrationsCount($merchant->id, $company?->id),
            'recommendations_today' => $this->recommendationsToday($merchant->id, $company?->id),
        ];

        $coverage = [
            'total_products' => $totalProducts,
            'covered_products' => $coveredProducts,
            'active_products' => $activeProducts,
            'pending_products' => $pendingProducts,
            'inactive_products' => $inactiveProducts,
            'without_measurement_table' => $withoutTable,
            'without_modeling' => $withoutModeling,
            'without_category' => $withoutCategory,
            'sync_errors' => $syncErrors,
            'installation_not_validated' => $installationNotValidated,
            'coverage_rate' => $totalProducts > 0 ? round(($coveredProducts / $totalProducts) * 100, 1) : 0.0,
        ];

        return response()->json([
            'summary' => $summary,
            'coverage' => $coverage,
            'next_actions' => $this->nextActions($coverage, $summary),
            'coverage_trend' => $this->coverageTrend($products),
        ]);
    }

    private function isCoveredProduct(Product $product): bool
    {
        return $product->status === 'active'
            && filled($product->measurement_table_id)
            && filled($product->category)
            && filled($product->fit_profile)
            && ! $this->hasSyncError($product);
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

    private function widgetReady(int $merchantId, ?int $companyId): bool
    {
        $install = WidgetInstall::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->where('is_active', true)
            ->first();

        return $install && collect($install->allowed_domains ?? [])->filter()->isNotEmpty();
    }

    private function measurementTablesCount(int $merchantId, ?int $companyId): int
    {
        return MeasurementTable::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->count();
    }

    private function integrationsCount(int $merchantId, ?int $companyId): int
    {
        return PlatformConnection::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->whereIn('status', ['configured', 'connected'])
            ->count();
    }

    private function recommendationsToday(int $merchantId, ?int $companyId): int
    {
        return RecommendationLog::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompanyId($query, $companyId))
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    private function nextActions(array $coverage, array $summary): array
    {
        $actions = [];

        if ($coverage['total_products'] === 0) {
            $actions[] = $this->action('products', 'Cadastrar produtos', 'Comece pelo catálogo para liberar tabelas, provador e publicação.', '/app/produtos/novo', 'high');
        }

        if ($coverage['without_measurement_table'] > 0) {
            $actions[] = $this->action('without_table', 'Vincular tabelas', $coverage['without_measurement_table'].' produto(s) ainda sem tabela de medidas.', '/app/produtos?filtro=sem_tabela', 'high');
        }

        if ($coverage['without_modeling'] > 0) {
            $actions[] = $this->action('without_modeling', 'Completar modelagens', $coverage['without_modeling'].' produto(s) sem modelagem para orientar o caimento.', '/app/produtos?filtro=sem_modelagem', 'medium');
        }

        if ($coverage['without_category'] > 0) {
            $actions[] = $this->action('without_category', 'Revisar categorias', $coverage['without_category'].' produto(s) sem categoria para regras e relatórios.', '/app/produtos?filtro=sem_categoria', 'medium');
        }

        if ($coverage['sync_errors'] > 0) {
            $actions[] = $this->action('sync_errors', 'Resolver erros de sincronização', $coverage['sync_errors'].' produto(s) com erro vindo da origem.', '/app/sincronizacao', 'high');
        }

        if (! $summary['widget_active']) {
            $actions[] = $this->action('widget', 'Validar instalação', 'Ative o provador e confirme os domínios antes de publicar.', '/app/widget', 'high');
        }

        if ($actions === []) {
            $actions[] = $this->action('publish', 'Revisar publicação', 'Cobertura operacional pronta. Confira o checklist final antes de liberar a loja.', '/app/go-live', 'low');
        }

        return array_slice($actions, 0, 5);
    }

    private function action(string $key, string $title, string $description, string $to, string $priority): array
    {
        return compact('key', 'title', 'description', 'to', 'priority');
    }

    private function coverageTrend($products): array
    {
        $days = collect(range(6, 0))
            ->map(fn (int $offset) => now()->subDays($offset)->toDateString())
            ->push(now()->toDateString());
        $firstProductAt = $products->min('created_at');
        $hasEnoughHistory = $firstProductAt instanceof Carbon
            && $firstProductAt->lt(now()->subDay())
            && $products->count() >= 2;

        return [
            'available' => $hasEnoughHistory,
            'period_days' => 7,
            'message' => $hasEnoughHistory ? null : 'A evolução aparece quando houver histórico de pelo menos dois dias.',
            'series' => $days->map(function (string $date) use ($products): array {
                $productsUntilDay = $products->filter(fn (Product $product): bool => $product->created_at?->toDateString() <= $date);
                $coveredUntilDay = $productsUntilDay->filter(fn (Product $product): bool => $this->isCoveredProduct($product))->count();
                $totalUntilDay = $productsUntilDay->count();

                return [
                    'date' => $date,
                    'covered_products' => $coveredUntilDay,
                    'total_products' => $totalUntilDay,
                    'coverage_rate' => $totalUntilDay > 0 ? round(($coveredUntilDay / $totalUntilDay) * 100, 1) : 0.0,
                ];
            })->values(),
        ];
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
