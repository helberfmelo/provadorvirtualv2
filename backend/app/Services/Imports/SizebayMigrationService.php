<?php

namespace App\Services\Imports;

use App\Models\FitProfile;
use App\Models\ImportJob;
use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantBrand;
use App\Models\MerchantCategory;
use App\Models\MerchantCompany;
use App\Models\NormalizedBrand;
use App\Models\PlatformConnection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyMappingSuggestion;
use App\Models\TaxonomyVersion;
use App\Services\Catalog\BrandCatalogService;
use App\Services\Catalog\CategoryCatalogService;
use App\Services\Integrations\BigShopClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class SizebayMigrationService
{
    public function __construct(
        private readonly BrandCatalogService $brands,
        private readonly CategoryCatalogService $categories,
        private readonly BigShopClient $bigShopClient
    ) {}

    public function preview(Merchant $merchant, ?MerchantCompany $company, array $input): array
    {
        $package = $this->packageFromInput($input);
        $analysis = $this->analyzePackage($merchant, $company, $package, (bool) ($input['compare_with_bigshop'] ?? false));

        return [
            'type' => 'sizebay_migration',
            'source_format' => $input['source_format'],
            'filename' => $input['filename'] ?? null,
            'total_rows' => $analysis['total_rows'],
            'valid_rows' => $analysis['valid_rows'],
            'failed_rows' => $analysis['failed_rows'],
            'summary' => $analysis['summary'],
            'rows' => array_slice($analysis['rows'], 0, 60),
            'sections' => $analysis['sections'],
            'review_queue' => array_slice($analysis['review_queue'], 0, 80),
            'coverage' => $analysis['coverage'],
            'warnings' => $package['warnings'],
            'metadata' => [
                'section_keys' => array_keys(array_filter($package['sections'], fn (array $rows): bool => $rows !== [])),
                'package_source' => $package['package_source'],
                'compare_with_bigshop' => (bool) ($input['compare_with_bigshop'] ?? false),
                'reports_snapshot' => $analysis['reports_snapshot'],
                'import_rule_suggestions' => $analysis['import_rule_suggestions'],
            ],
        ];
    }

    public function commit(Merchant $merchant, ?MerchantCompany $company, array $input): ImportJob
    {
        $preview = $this->preview($merchant, $company, $input);
        $batchId = (string) Str::uuid();

        $job = ImportJob::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'type' => 'sizebay_migration',
            'source_format' => $input['source_format'],
            'filename' => $input['filename'] ?? null,
            'status' => 'running',
            'total_rows' => $preview['total_rows'],
            'failed_rows' => $preview['failed_rows'],
            'summary' => $preview['summary'],
            'errors' => collect($preview['rows'])
                ->where('valid', false)
                ->map(fn (array $row): array => Arr::only($row, ['section', 'line', 'errors']))
                ->values()
                ->all(),
            'metadata' => [
                'batch_id' => $batchId,
                'sections' => $preview['sections'],
                'review_queue' => $preview['review_queue'],
                'review_queue_count' => count($preview['review_queue']),
                'coverage' => $preview['coverage'],
                'warnings' => $preview['warnings'],
                'reports_snapshot' => $preview['metadata']['reports_snapshot'] ?? [],
                'import_rule_suggestions' => $preview['metadata']['import_rule_suggestions'] ?? [],
                'package_source' => $preview['metadata']['package_source'] ?? $input['source_format'],
                'compare_with_bigshop' => $preview['metadata']['compare_with_bigshop'] ?? false,
            ],
            'started_at' => now(),
        ]);

        try {
            $result = DB::transaction(function () use ($merchant, $company, $input, $batchId): array {
                $package = $this->packageFromInput($input);
                $analysis = $this->analyzePackage($merchant, $company, $package, (bool) ($input['compare_with_bigshop'] ?? false));

                return $this->applyPreview($merchant, $company, $analysis, $batchId);
            });

            $job->update([
                'status' => $preview['failed_rows'] > 0 || count($preview['review_queue'] ?? []) > 0
                    ? 'completed_with_warnings'
                    : 'completed',
                'imported_rows' => $result['imported_rows'],
                'summary' => array_merge($preview['summary'], [
                    'applied_products' => $result['applied']['products'] ?? 0,
                    'applied_measurement_tables' => $result['applied']['measurement_tables'] ?? 0,
                    'applied_fit_profiles' => $result['applied']['fit_profiles'] ?? 0,
                    'applied_brands' => $result['applied']['brands'] ?? 0,
                    'applied_categories' => $result['applied']['categories'] ?? 0,
                ]),
                'metadata' => array_merge($job->metadata ?? [], [
                    'batch_id' => $batchId,
                    'review_queue' => $preview['review_queue'],
                    'review_queue_count' => count($preview['review_queue']),
                    'post_apply_review_queue' => $result['review_queue'],
                    'rollback' => $result['rollback'],
                    'applied' => $result['applied'],
                    'created_suggestions' => $result['created_suggestions'],
                    'rolled_back_at' => null,
                ]),
                'finished_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            $job->update([
                'status' => 'failed',
                'errors' => array_merge($job->errors ?? [], [['line' => null, 'errors' => [$exception->getMessage()]]]),
                'finished_at' => now(),
            ]);
        }

        return $job->refresh();
    }

    public function rollback(Merchant $merchant, ?MerchantCompany $company, ImportJob $job): ImportJob
    {
        if ($job->type !== 'sizebay_migration') {
            throw new RuntimeException('Rollback disponível apenas para migração Sizebay.');
        }

        $rollback = $job->metadata['rollback'] ?? null;

        if (! is_array($rollback) || $rollback === []) {
            throw new RuntimeException('Nenhum snapshot de rollback foi registrado para este lote.');
        }

        if (($job->metadata['rolled_back_at'] ?? null) !== null || $job->status === 'rolled_back') {
            throw new RuntimeException('Este lote já foi desfeito.');
        }

        DB::transaction(function () use ($merchant, $company, $rollback): void {
            $this->rollbackTaxonomySuggestions($merchant, $rollback['taxonomy_suggestions'] ?? []);
            $this->rollbackProducts($merchant, $company, $rollback['products'] ?? []);
            $this->rollbackMeasurementTables($merchant, $company, $rollback['measurement_tables'] ?? []);
            $this->rollbackFitProfiles($merchant, $company, $rollback['fit_profiles'] ?? []);
            $this->rollbackMerchantBrands($merchant, $company, $rollback['brands'] ?? []);
            $this->rollbackMerchantCategories($merchant, $company, $rollback['categories'] ?? []);
        });

        $job->update([
            'status' => 'rolled_back',
            'metadata' => array_merge($job->metadata ?? [], [
                'rolled_back_at' => now()->toISOString(),
            ]),
        ]);

        return $job->refresh();
    }

    private function analyzePackage(Merchant $merchant, ?MerchantCompany $company, array $package, bool $compareWithBigShop): array
    {
        $tableAnalysis = $this->analyzeMeasurementTables($merchant, $company, $package['sections']['measurement_tables'] ?? []);
        $profileAnalysis = $this->analyzeFitProfiles($merchant, $company, $package['sections']['fit_profiles'] ?? []);
        $brandAnalysis = $this->analyzeBrands($merchant, $company, $package['sections']['brands'] ?? []);
        $categoryAnalysis = $this->analyzeCategories($merchant, $company, $package['sections']['categories'] ?? []);
        $productAnalysis = $this->analyzeProducts(
            $merchant,
            $company,
            $package['sections']['products'] ?? [],
            $tableAnalysis['lookup'],
            $profileAnalysis['lookup'],
            $brandAnalysis['lookup'],
            $categoryAnalysis['lookup']
        );
        $ruleAnalysis = $this->analyzeImportRules($package['sections']['import_rules'] ?? []);
        $reportAnalysis = $this->analyzeReports($package['sections']['reports'] ?? []);

        $sections = [
            $tableAnalysis['section'],
            $profileAnalysis['section'],
            $brandAnalysis['section'],
            $categoryAnalysis['section'],
            $productAnalysis['section'],
            $ruleAnalysis['section'],
            $reportAnalysis['section'],
        ];
        $rows = array_merge(
            $tableAnalysis['rows'],
            $profileAnalysis['rows'],
            $brandAnalysis['rows'],
            $categoryAnalysis['rows'],
            $productAnalysis['rows'],
            $ruleAnalysis['rows'],
            $reportAnalysis['rows']
        );
        $reviewQueue = array_merge(
            $brandAnalysis['review_queue'],
            $categoryAnalysis['review_queue'],
            $productAnalysis['review_queue'],
            $ruleAnalysis['review_queue'],
            $reportAnalysis['review_queue']
        );
        $validRows = collect($rows)->where('valid', true)->count();
        $failedRows = collect($rows)->where('valid', false)->count();
        $summary = [
            'measurement_tables' => $tableAnalysis['section']['rows'],
            'products' => $productAnalysis['section']['rows'],
            'variants' => $productAnalysis['section']['variants'],
            'brands' => $brandAnalysis['section']['rows'],
            'categories' => $categoryAnalysis['section']['rows'],
            'fit_profiles' => $profileAnalysis['section']['rows'],
            'import_rules' => $ruleAnalysis['section']['rows'],
            'reports' => $reportAnalysis['section']['rows'],
            'created' => collect($rows)->where('action', 'create')->count(),
            'updated' => collect($rows)->where('action', 'update')->count(),
            'ignored' => collect($rows)->where('action', 'ignore')->count(),
            'conflicts' => collect($reviewQueue)->where('severity', 'conflict')->count(),
            'low_confidence' => collect($reviewQueue)->where('severity', 'low_confidence')->count(),
            'affected_products' => $productAnalysis['section']['affected_products'],
            'review_queue' => count($reviewQueue),
        ];

        return [
            'rows' => $rows,
            'sections' => array_values(array_filter($sections, fn (array $section): bool => $section['rows'] > 0)),
            'review_queue' => $reviewQueue,
            'coverage' => $this->coveragePayload($merchant, $company, $productAnalysis['identifiers'], $tableAnalysis['names'], $productAnalysis['sizes'], count($reviewQueue), $compareWithBigShop),
            'summary' => $summary,
            'valid_rows' => $validRows,
            'failed_rows' => $failedRows,
            'total_rows' => count($rows),
            'reports_snapshot' => $reportAnalysis['snapshot'],
            'import_rule_suggestions' => $ruleAnalysis['suggestions'],
        ];
    }

    private function applyPreview(Merchant $merchant, ?MerchantCompany $company, array $analysis, string $batchId): array
    {
        $rowsBySection = collect($analysis['rows'])->groupBy('section');
        $rollback = [
            'measurement_tables' => [],
            'fit_profiles' => [],
            'brands' => [],
            'categories' => [],
            'products' => [],
            'taxonomy_suggestions' => [],
        ];
        $applied = [
            'measurement_tables' => 0,
            'fit_profiles' => 0,
            'brands' => 0,
            'categories' => 0,
            'products' => 0,
        ];
        $createdSuggestions = [];

        $tableLookup = $this->applyMeasurementTables($merchant, $company, $rowsBySection->get('measurement_tables', collect())->all(), $batchId, $rollback, $applied);
        $profileLookup = $this->applyFitProfiles($merchant, $company, $rowsBySection->get('fit_profiles', collect())->all(), $batchId, $rollback, $applied);
        $brandLookup = $this->applyBrands($merchant, $company, $rowsBySection->get('brands', collect())->all(), $batchId, $rollback, $applied, $createdSuggestions);
        $categoryLookup = $this->applyCategories($merchant, $company, $rowsBySection->get('categories', collect())->all(), $batchId, $rollback, $applied, $createdSuggestions);
        $productResult = $this->applyProducts(
            $merchant,
            $company,
            $rowsBySection->get('products', collect())->all(),
            $batchId,
            $tableLookup,
            $profileLookup,
            $brandLookup,
            $categoryLookup,
            $rollback,
            $applied,
            $createdSuggestions
        );

        return [
            'rollback' => $rollback,
            'applied' => $applied,
            'review_queue' => $productResult['review_queue'],
            'created_suggestions' => $createdSuggestions,
            'imported_rows' => array_sum($applied),
        ];
    }

    private function analyzeMeasurementTables(Merchant $merchant, ?MerchantCompany $company, array $rows): array
    {
        $existingTables = MeasurementTable::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->get()
            ->keyBy(fn (MeasurementTable $table): string => $this->cleanKey($table->name));

        $grouped = collect($rows)->groupBy(fn (array $row): string => $this->cleanKey((string) ($row['table_name'] ?? '')));
        $previewRows = [];
        $lookup = [];
        $names = [];
        $created = 0;
        $updated = 0;

        foreach ($grouped as $key => $tableRows) {
            if ($key === '') {
                foreach ($tableRows as $row) {
                    $previewRows[] = [
                        'section' => 'measurement_tables',
                        'line' => $row['_line'] ?? null,
                        'valid' => false,
                        'errors' => ['Informe o nome da tabela.'],
                        'action' => 'ignore',
                        'confidence' => 'high',
                        'data' => $this->normalizeMeasurementTableRow($row),
                    ];
                }

                continue;
            }

            $duplicateSizes = [];
            $seenSizes = [];
            $first = $tableRows->first();
            $tableName = (string) ($first['table_name'] ?? '');
            $existing = $existingTables->get($key);
            $action = $existing ? 'update' : 'create';
            $created += $existing ? 0 : 1;
            $updated += $existing ? 1 : 0;
            $names[] = $tableName;

            foreach ($tableRows as $row) {
                $sizeLabel = trim((string) ($row['size_label'] ?? ''));
                $errors = [];

                if ($tableName === '') {
                    $errors[] = 'Informe o nome da tabela.';
                }

                if ($sizeLabel === '') {
                    $errors[] = 'Informe o tamanho.';
                }

                if ($sizeLabel !== '') {
                    if (isset($seenSizes[$sizeLabel])) {
                        $duplicateSizes[$sizeLabel] = true;
                        $errors[] = 'Tamanho duplicado na mesma tabela.';
                    }

                    $seenSizes[$sizeLabel] = true;
                }

                $data = $this->normalizeMeasurementTableRow($row);
                $previewRows[] = [
                    'section' => 'measurement_tables',
                    'line' => $row['_line'] ?? null,
                    'valid' => $errors === [],
                    'errors' => $errors,
                    'action' => $action,
                    'confidence' => 'high',
                    'data' => $data,
                ];
            }

            $lookup[$key] = [
                'status' => 'resolved',
                'action' => $action,
                'existing_id' => $existing?->id,
                'table_name' => $tableName,
            ];
        }

        return [
            'rows' => $previewRows,
            'lookup' => $lookup,
            'names' => array_values(array_unique(array_filter($names))),
            'section' => [
                'key' => 'measurement_tables',
                'label' => 'Tabelas de medidas',
                'rows' => count($previewRows),
                'valid' => collect($previewRows)->where('valid', true)->count(),
                'create' => $created,
                'update' => $updated,
                'conflicts' => collect($previewRows)->where('valid', false)->count(),
                'low_confidence' => 0,
            ],
        ];
    }

    private function analyzeFitProfiles(Merchant $merchant, ?MerchantCompany $company, array $rows): array
    {
        $profiles = FitProfile::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->get();

        $previewRows = [];
        $lookup = [];
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $data = $this->normalizeFitProfileRow($row);
            $profile = $profiles->first(function (FitProfile $candidate) use ($data): bool {
                return $this->cleanKey($candidate->code) === $this->cleanKey($data['code'])
                    || $this->cleanKey($candidate->name) === $this->cleanKey($data['name']);
            });
            $errors = [];

            if ($data['name'] === '') {
                $errors[] = 'Informe o nome da modelagem.';
            }

            $action = $profile ? 'update' : 'create';
            $created += $profile ? 0 : 1;
            $updated += $profile ? 1 : 0;

            $previewRows[] = [
                'section' => 'fit_profiles',
                'line' => $row['_line'] ?? null,
                'valid' => $errors === [],
                'errors' => $errors,
                'action' => $action,
                'confidence' => 'high',
                'data' => $data,
            ];

            if ($errors === []) {
                $lookup[$this->cleanKey($data['code'] ?: $data['name'])] = [
                    'status' => 'resolved',
                    'action' => $action,
                    'existing_id' => $profile?->id,
                    'code' => $data['code'],
                    'name' => $data['name'],
                ];
            }
        }

        return [
            'rows' => $previewRows,
            'lookup' => $lookup,
            'section' => [
                'key' => 'fit_profiles',
                'label' => 'Modelagens',
                'rows' => count($previewRows),
                'valid' => collect($previewRows)->where('valid', true)->count(),
                'create' => $created,
                'update' => $updated,
                'conflicts' => collect($previewRows)->where('valid', false)->count(),
                'low_confidence' => 0,
            ],
        ];
    }

    private function analyzeBrands(Merchant $merchant, ?MerchantCompany $company, array $rows): array
    {
        $merchantBrands = MerchantBrand::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->with('normalizedBrand')
            ->get();
        $normalizedBrands = NormalizedBrand::query()->get();

        $previewRows = [];
        $reviewQueue = [];
        $lookup = [];
        $created = 0;
        $updated = 0;
        $lowConfidence = 0;

        foreach ($rows as $row) {
            $data = $this->normalizeBrandRow($row);
            $brand = $merchantBrands->first(fn (MerchantBrand $candidate): bool => $this->cleanKey($candidate->name) === $this->cleanKey($data['name']));
            $normalized = $this->matchNormalizedBrand($normalizedBrands, $data['normalized_name'] ?: $data['name']);
            $errors = [];
            $confidence = $normalized['confidence'];

            if ($data['name'] === '') {
                $errors[] = 'Informe o nome da marca.';
            }

            $action = $brand ? 'update' : 'create';
            $created += $brand ? 0 : 1;
            $updated += $brand ? 1 : 0;

            if ($errors === [] && $normalized['status'] !== 'resolved') {
                $lowConfidence++;
                $reviewQueue[] = [
                    'section' => 'brands',
                    'severity' => 'low_confidence',
                    'target' => 'normalized_brand',
                    'source_value' => $data['name'],
                    'suggested_value' => $data['normalized_name'] ?: $data['name'],
                    'confidence' => $confidence,
                    'reason' => 'A marca local pode ser criada agora, mas a marca normalizada precisa de revisão humana antes de virar aprendizado.',
                ];
            }

            $previewRows[] = [
                'section' => 'brands',
                'line' => $row['_line'] ?? null,
                'valid' => $errors === [],
                'errors' => $errors,
                'action' => $action,
                'confidence' => $confidence,
                'data' => $data,
            ];

            if ($errors === []) {
                $lookup[$this->cleanKey($data['name'])] = [
                    'status' => 'resolved',
                    'action' => $action,
                    'existing_id' => $brand?->id,
                    'normalized_brand_id' => $normalized['brand']?->id,
                    'normalized_status' => $normalized['status'],
                    'name' => $data['name'],
                ];
            }
        }

        return [
            'rows' => $previewRows,
            'lookup' => $lookup,
            'review_queue' => $reviewQueue,
            'section' => [
                'key' => 'brands',
                'label' => 'Marcas',
                'rows' => count($previewRows),
                'valid' => collect($previewRows)->where('valid', true)->count(),
                'create' => $created,
                'update' => $updated,
                'conflicts' => collect($previewRows)->where('valid', false)->count(),
                'low_confidence' => $lowConfidence,
            ],
        ];
    }

    private function analyzeCategories(Merchant $merchant, ?MerchantCompany $company, array $rows): array
    {
        $merchantCategories = MerchantCategory::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->with('taxonomyCategory.parent')
            ->get();
        $taxonomyCategories = TaxonomyCategory::query()->with('parent')->where('status', 'active')->get();

        $previewRows = [];
        $reviewQueue = [];
        $lookup = [];
        $created = 0;
        $updated = 0;
        $lowConfidence = 0;

        foreach ($rows as $row) {
            $data = $this->normalizeCategoryRow($row);
            $category = $merchantCategories->first(fn (MerchantCategory $candidate): bool => $this->cleanKey($candidate->name) === $this->cleanKey($data['name']));
            $taxonomy = $this->matchTaxonomyCategory($taxonomyCategories, $data['taxonomy_name'] ?: $data['name'], $data['category_type']);
            $errors = [];
            $confidence = $taxonomy['confidence'];

            if ($data['name'] === '') {
                $errors[] = 'Informe o nome da categoria.';
            }

            $action = $category ? 'update' : 'create';
            $created += $category ? 0 : 1;
            $updated += $category ? 1 : 0;

            if ($errors === [] && $taxonomy['status'] !== 'resolved') {
                $lowConfidence++;
                $reviewQueue[] = [
                    'section' => 'categories',
                    'severity' => 'low_confidence',
                    'target' => 'taxonomy_category',
                    'source_value' => $data['name'],
                    'suggested_value' => $data['taxonomy_name'] ?: $data['name'],
                    'confidence' => $confidence,
                    'reason' => 'A categoria local pode ser criada agora, mas a categoria normalizada precisa de revisão antes de alimentar aprendizado e relatórios.',
                ];
            }

            $previewRows[] = [
                'section' => 'categories',
                'line' => $row['_line'] ?? null,
                'valid' => $errors === [],
                'errors' => $errors,
                'action' => $action,
                'confidence' => $confidence,
                'data' => $data,
            ];

            if ($errors === []) {
                $lookup[$this->cleanKey($data['name'])] = [
                    'status' => 'resolved',
                    'action' => $action,
                    'existing_id' => $category?->id,
                    'taxonomy_category_id' => $taxonomy['category']?->id,
                    'taxonomy_status' => $taxonomy['status'],
                    'name' => $data['name'],
                ];
            }
        }

        return [
            'rows' => $previewRows,
            'lookup' => $lookup,
            'review_queue' => $reviewQueue,
            'section' => [
                'key' => 'categories',
                'label' => 'Categorias',
                'rows' => count($previewRows),
                'valid' => collect($previewRows)->where('valid', true)->count(),
                'create' => $created,
                'update' => $updated,
                'conflicts' => collect($previewRows)->where('valid', false)->count(),
                'low_confidence' => $lowConfidence,
            ],
        ];
    }

    private function analyzeProducts(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $rows,
        array $tableLookup,
        array $profileLookup,
        array $brandLookup,
        array $categoryLookup
    ): array {
        $products = Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->get();

        $previewRows = [];
        $reviewQueue = [];
        $productIdentifiers = [];
        $tableNames = [];
        $sizes = [];
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $data = $this->normalizeProductRow($row);
            $product = $products->first(function (Product $candidate) use ($data): bool {
                return ($data['sku'] !== '' && $candidate->sku === $data['sku'])
                    || ($data['external_product_id'] !== '' && $candidate->external_product_id === $data['external_product_id']);
            });
            $errors = [];
            $confidence = 'high';

            if ($data['sku'] === '' && $data['external_product_id'] === '') {
                $errors[] = 'Informe SKU ou ID externo.';
            }

            if ($data['name'] === '') {
                $errors[] = 'Informe o nome do produto.';
            }

            $tableKey = $this->cleanKey($data['measurement_table']);
            if ($tableKey !== '') {
                $tableNames[] = $data['measurement_table'];
                if (! isset($tableLookup[$tableKey])) {
                    $reviewQueue[] = [
                        'section' => 'products',
                        'severity' => 'conflict',
                        'target' => 'measurement_table',
                        'source_value' => $data['measurement_table'],
                        'suggested_value' => null,
                        'confidence' => 'low',
                        'reason' => 'A tabela informada no produto não veio no pacote nem existe hoje na empresa. O vínculo ficará pendente.',
                        'sku' => $data['sku'] ?: $data['external_product_id'],
                    ];
                    $confidence = 'medium';
                }
            }

            foreach ([
                'brand' => [$brandLookup, 'Marca importada sem revisão normalizada. O produto entra com a marca local, mas a normalização fica pendente.'],
                'category' => [$categoryLookup, 'Categoria importada sem revisão normalizada. O produto entra com a categoria local, mas a taxonomia fica pendente.'],
                'fit_profile' => [$profileLookup, 'Modelagem informada no produto não foi encontrada entre as modelagens existentes ou do pacote. O vínculo ficará pendente.'],
            ] as $field => [$lookup, $reason]) {
                $value = $data[$field];
                if ($value === '') {
                    continue;
                }

                $key = $this->cleanKey($value);

                if (! isset($lookup[$key])) {
                    $reviewQueue[] = [
                        'section' => 'products',
                        'severity' => 'low_confidence',
                        'target' => $field,
                        'source_value' => $value,
                        'suggested_value' => null,
                        'confidence' => 'low',
                        'reason' => $reason,
                        'sku' => $data['sku'] ?: $data['external_product_id'],
                    ];
                    $confidence = 'medium';
                }
            }

            if ($data['sku'] !== '') {
                $productIdentifiers[] = $data['sku'];
            } elseif ($data['external_product_id'] !== '') {
                $productIdentifiers[] = $data['external_product_id'];
            }

            if ($data['size_label'] !== '') {
                $sizes[] = Str::upper($data['size_label']);
            }

            $action = $product ? 'update' : 'create';
            $created += $product ? 0 : 1;
            $updated += $product ? 1 : 0;

            $previewRows[] = [
                'section' => 'products',
                'line' => $row['_line'] ?? null,
                'valid' => $errors === [],
                'errors' => $errors,
                'action' => $action,
                'confidence' => $confidence,
                'data' => $data,
            ];
        }

        return [
            'rows' => $previewRows,
            'review_queue' => $reviewQueue,
            'identifiers' => array_values(array_unique(array_filter($productIdentifiers))),
            'sizes' => array_values(array_unique(array_filter($sizes))),
            'section' => [
                'key' => 'products',
                'label' => 'Produtos e vínculos',
                'rows' => count($previewRows),
                'variants' => collect($previewRows)->filter(fn (array $row): bool => filled($row['data']['size_label']))->count(),
                'valid' => collect($previewRows)->where('valid', true)->count(),
                'create' => $created,
                'update' => $updated,
                'conflicts' => collect($reviewQueue)->where('section', 'products')->where('severity', 'conflict')->count(),
                'low_confidence' => collect($reviewQueue)->where('section', 'products')->where('severity', 'low_confidence')->count(),
                'affected_products' => count(array_values(array_unique(array_filter($productIdentifiers)))),
            ],
        ];
    }

    private function analyzeImportRules(array $rows): array
    {
        $previewRows = [];
        $reviewQueue = [];
        $suggestions = [];

        foreach ($rows as $row) {
            $data = $this->normalizeImportRuleRow($row);
            $errors = [];

            if ($data['field'] === '' || $data['match_value'] === '' || $data['target_value'] === '') {
                $errors[] = 'Informe campo, valor de origem e valor de destino da regra.';
            }

            if ($errors === []) {
                $reviewQueue[] = [
                    'section' => 'import_rules',
                    'severity' => 'low_confidence',
                    'target' => 'import_rule',
                    'source_value' => $data['match_value'],
                    'suggested_value' => $data['target_value'],
                    'confidence' => 'medium',
                    'reason' => 'A regra foi importada como sugestão revisável. Ela não entra em produção sem revisão humana.',
                ];
                $suggestions[] = $data;
            }

            $previewRows[] = [
                'section' => 'import_rules',
                'line' => $row['_line'] ?? null,
                'valid' => $errors === [],
                'errors' => $errors,
                'action' => 'review',
                'confidence' => 'medium',
                'data' => $data,
            ];
        }

        return [
            'rows' => $previewRows,
            'review_queue' => $reviewQueue,
            'suggestions' => $suggestions,
            'section' => [
                'key' => 'import_rules',
                'label' => 'Regras de importação',
                'rows' => count($previewRows),
                'valid' => collect($previewRows)->where('valid', true)->count(),
                'create' => 0,
                'update' => 0,
                'conflicts' => collect($previewRows)->where('valid', false)->count(),
                'low_confidence' => count($reviewQueue),
            ],
        ];
    }

    private function analyzeReports(array $rows): array
    {
        $previewRows = [];
        $reviewQueue = [];
        $snapshot = [];

        foreach ($rows as $row) {
            $data = $this->normalizeReportRow($row);
            $errors = [];

            if ($data['dimension'] === '' || $data['metric'] === '' || $data['value'] === null) {
                $errors[] = 'Informe dimensão, métrica e valor agregado do relatório.';
            }

            foreach (['customer_name', 'email', 'phone', 'cpf', 'document', 'session', 'cookie', 'token'] as $blockedKey) {
                if (filled($row[$blockedKey] ?? null)) {
                    $errors[] = 'O arquivo traz dado identificável ou segredo bloqueado para migração.';
                    $reviewQueue[] = [
                        'section' => 'reports',
                        'severity' => 'conflict',
                        'target' => 'report_data',
                        'source_value' => $blockedKey,
                        'suggested_value' => null,
                        'confidence' => 'low',
                        'reason' => 'Relatórios da Sizebay aceitam apenas agregados minimizados. Remova qualquer dado pessoal, cookie, sessão ou token antes de aplicar.',
                    ];
                }
            }

            if ($errors === []) {
                $snapshot[] = $data;
            }

            $previewRows[] = [
                'section' => 'reports',
                'line' => $row['_line'] ?? null,
                'valid' => $errors === [],
                'errors' => array_values(array_unique($errors)),
                'action' => 'review',
                'confidence' => 'medium',
                'data' => $data,
            ];
        }

        return [
            'rows' => $previewRows,
            'review_queue' => $reviewQueue,
            'snapshot' => $snapshot,
            'section' => [
                'key' => 'reports',
                'label' => 'Relatórios agregados',
                'rows' => count($previewRows),
                'valid' => collect($previewRows)->where('valid', true)->count(),
                'create' => 0,
                'update' => 0,
                'conflicts' => count($reviewQueue),
                'low_confidence' => 0,
            ],
        ];
    }

    private function applyMeasurementTables(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $rows,
        string $batchId,
        array &$rollback,
        array &$applied
    ): array {
        $grouped = collect($rows)
            ->where('valid', true)
            ->groupBy(fn (array $row): string => $this->cleanKey($row['data']['table_name'] ?? ''));
        $lookup = [];

        foreach ($grouped as $key => $tableRows) {
            $first = $tableRows->first()['data'];
            $table = MeasurementTable::query()
                ->where('merchant_id', $merchant->id)
                ->when($company, function ($query) use ($company): void {
                    $query->where(function ($innerQuery) use ($company): void {
                        $innerQuery->where('merchant_company_id', $company->id)
                            ->orWhereNull('merchant_company_id');
                    });
                })
                ->where('name', $first['table_name'])
                ->with('rows')
                ->first();

            $snapshot = $table ? $this->measurementTableSnapshot($table) : null;
            $created = false;

            if (! $table) {
                $created = true;
                $table = new MeasurementTable([
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => $company?->id,
                ]);
            }

            $table->fill([
                'name' => $first['table_name'],
                'product_type' => $first['product_type'] ?: 'other',
                'gender' => $first['gender'] ?: null,
                'fit_profile' => $first['fit_profile'] ?: null,
                'measurement_target' => $first['measurement_target'] ?: 'body',
                'size_system' => $first['size_system'] ?: 'br_alpha',
                'range_mode' => $first['range_mode'] ?: 'min_max',
                'unit' => $first['unit'] ?: 'cm',
                'status' => $first['status'] ?: 'active',
                'source' => 'sizebay_migration',
                'notes' => $first['notes'] ?: null,
                'metadata' => array_filter([
                    'batch_id' => $batchId,
                    'sizebay_migration' => true,
                    'observation' => $first['observation'] ?: null,
                    'source_file' => $first['source_file'] ?: null,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
            ]);
            $table->save();

            $table->rows()->delete();

            foreach ($tableRows->values() as $index => $row) {
                $data = $row['data'];
                $table->rows()->create([
                    'size_label' => $data['size_label'],
                    'sort_order' => $index,
                    'bust_min' => $data['bust_min'],
                    'bust_max' => $data['bust_max'],
                    'waist_min' => $data['waist_min'],
                    'waist_max' => $data['waist_max'],
                    'hip_min' => $data['hip_min'],
                    'hip_max' => $data['hip_max'],
                    'height_min' => $data['height_min'],
                    'height_max' => $data['height_max'],
                    'weight_min' => $data['weight_min'],
                    'weight_max' => $data['weight_max'],
                    'length_min' => $data['length_min'],
                    'length_max' => $data['length_max'],
                    'shoulder_min' => $data['shoulder_min'],
                    'shoulder_max' => $data['shoulder_max'],
                    'measurements' => $data['measurements'],
                    'composite_measurements' => $data['composite_measurements'],
                    'metadata' => [
                        'batch_id' => $batchId,
                        'source_file' => $data['source_file'] ?: null,
                    ],
                ]);
            }

            $rollback['measurement_tables'][] = [
                'state' => $created ? 'created' : 'updated',
                'id' => $table->id,
                'before' => $snapshot,
            ];
            $lookup[$key] = $table->id;
            $applied['measurement_tables']++;
        }

        return $lookup;
    }

    private function applyFitProfiles(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $rows,
        string $batchId,
        array &$rollback,
        array &$applied
    ): array {
        $lookup = [];

        foreach (collect($rows)->where('valid', true) as $row) {
            $data = $row['data'];
            $profile = FitProfile::query()
                ->where('merchant_id', $merchant->id)
                ->when($company, function ($query) use ($company): void {
                    $query->where(function ($innerQuery) use ($company): void {
                        $innerQuery->where('merchant_company_id', $company->id)
                            ->orWhereNull('merchant_company_id');
                    });
                })
                ->where(function ($query) use ($data): void {
                    $query->where('code', $data['code'])
                        ->orWhere('name', $data['name']);
                })
                ->first();

            $snapshot = $profile ? $this->modelSnapshot($profile) : null;
            $created = false;

            if (! $profile) {
                $created = true;
                $profile = new FitProfile([
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => $company?->id,
                ]);
            }

            $profile->fill([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?: null,
                'product_type' => $data['product_type'] ?: null,
                'gender' => $data['gender'] ?: null,
                'fit_intensity' => $data['fit_intensity'] ?: 'regular',
                'stretch_level' => $data['stretch_level'] ?: 'medium',
                'status' => $data['status'] ?: 'draft',
                'metadata' => [
                    'batch_id' => $batchId,
                    'sizebay_migration' => true,
                ],
            ]);
            $profile->save();

            $rollback['fit_profiles'][] = [
                'state' => $created ? 'created' : 'updated',
                'id' => $profile->id,
                'before' => $snapshot,
            ];
            $lookup[$this->cleanKey($data['code'] ?: $data['name'])] = $profile->code;
            $applied['fit_profiles']++;
        }

        return $lookup;
    }

    private function applyBrands(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $rows,
        string $batchId,
        array &$rollback,
        array &$applied,
        array &$createdSuggestions
    ): array {
        $lookup = [];

        foreach (collect($rows)->where('valid', true) as $row) {
            $data = $row['data'];
            $brand = MerchantBrand::query()
                ->where('merchant_id', $merchant->id)
                ->when($company, function ($query) use ($company): void {
                    $query->where(function ($innerQuery) use ($company): void {
                        $innerQuery->where('merchant_company_id', $company->id)
                            ->orWhereNull('merchant_company_id');
                    });
                })
                ->where('name', $data['name'])
                ->with('normalizedBrand')
                ->first();
            $snapshot = $brand ? $this->modelSnapshot($brand) : null;
            $created = false;

            if (! $brand) {
                $created = true;
                $brand = new MerchantBrand([
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => $company?->id,
                ]);
            }

            $normalized = $this->matchNormalizedBrand(NormalizedBrand::query()->get(), $data['normalized_name'] ?: $data['name']);
            $brand->fill([
                'normalized_brand_id' => $normalized['status'] === 'resolved' ? $normalized['brand']?->id : null,
                'name' => $data['name'],
                'slug' => $this->brands->slug($data['name']),
                'source' => 'sizebay_migration',
                'status' => $data['status'] ?: 'active',
                'metadata' => [
                    'batch_id' => $batchId,
                    'sizebay_migration' => true,
                    'normalized_name_requested' => $data['normalized_name'] ?: null,
                ],
            ]);
            $brand->save();

            if ($normalized['status'] !== 'resolved') {
                $createdSuggestions[] = $this->createBrandSuggestion($merchant, $company, $brand, $data['normalized_name'] ?: $data['name'], $normalized['confidence']);
                $rollback['taxonomy_suggestions'][] = end($createdSuggestions);
            }

            $rollback['brands'][] = [
                'state' => $created ? 'created' : 'updated',
                'id' => $brand->id,
                'before' => $snapshot,
            ];
            $lookup[$this->cleanKey($data['name'])] = [
                'name' => $brand->name,
                'normalized_brand_id' => $brand->normalized_brand_id,
            ];
            $applied['brands']++;
        }

        return $lookup;
    }

    private function applyCategories(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $rows,
        string $batchId,
        array &$rollback,
        array &$applied,
        array &$createdSuggestions
    ): array {
        $lookup = [];

        foreach (collect($rows)->where('valid', true) as $row) {
            $data = $row['data'];
            $category = MerchantCategory::query()
                ->where('merchant_id', $merchant->id)
                ->when($company, function ($query) use ($company): void {
                    $query->where(function ($innerQuery) use ($company): void {
                        $innerQuery->where('merchant_company_id', $company->id)
                            ->orWhereNull('merchant_company_id');
                    });
                })
                ->where('name', $data['name'])
                ->with('taxonomyCategory')
                ->first();
            $snapshot = $category ? $this->modelSnapshot($category) : null;
            $created = false;

            if (! $category) {
                $created = true;
                $category = new MerchantCategory([
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => $company?->id,
                ]);
            }

            $taxonomy = $this->matchTaxonomyCategory(
                TaxonomyCategory::query()->with('parent')->where('status', 'active')->get(),
                $data['taxonomy_name'] ?: $data['name'],
                $data['category_type']
            );
            $category->fill([
                'taxonomy_category_id' => $taxonomy['status'] === 'resolved' ? $taxonomy['category']?->id : null,
                'name' => $data['name'],
                'slug' => $this->categories->slug($data['name']),
                'source' => 'sizebay_migration',
                'status' => $data['status'] ?: 'active',
                'metadata' => [
                    'batch_id' => $batchId,
                    'sizebay_migration' => true,
                    'taxonomy_name_requested' => $data['taxonomy_name'] ?: null,
                    'category_type_requested' => $data['category_type'] ?: null,
                ],
            ]);
            $category->save();

            if ($taxonomy['status'] !== 'resolved') {
                $createdSuggestions[] = $this->createCategorySuggestion($merchant, $company, $category, $data['taxonomy_name'] ?: $data['name'], $data['category_type'], $taxonomy['confidence']);
                $rollback['taxonomy_suggestions'][] = end($createdSuggestions);
            }

            $rollback['categories'][] = [
                'state' => $created ? 'created' : 'updated',
                'id' => $category->id,
                'before' => $snapshot,
            ];
            $lookup[$this->cleanKey($data['name'])] = [
                'name' => $category->name,
                'taxonomy_category_id' => $category->taxonomy_category_id,
            ];
            $applied['categories']++;
        }

        return $lookup;
    }

    private function applyProducts(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $rows,
        string $batchId,
        array $tableLookup,
        array $profileLookup,
        array $brandLookup,
        array $categoryLookup,
        array &$rollback,
        array &$applied,
        array &$createdSuggestions
    ): array {
        $reviewQueue = [];

        foreach (collect($rows)->where('valid', true) as $row) {
            $data = $row['data'];
            $product = Product::query()
                ->where('merchant_id', $merchant->id)
                ->when($company, function ($query) use ($company): void {
                    $query->where(function ($innerQuery) use ($company): void {
                        $innerQuery->where('merchant_company_id', $company->id)
                            ->orWhereNull('merchant_company_id');
                    });
                })
                ->where(function ($query) use ($data): void {
                    if ($data['sku'] !== '') {
                        $query->orWhere('sku', $data['sku']);
                    }

                    if ($data['external_product_id'] !== '') {
                        $query->orWhere('external_product_id', $data['external_product_id']);
                    }
                })
                ->with('variants')
                ->first();

            $snapshot = $product ? $this->productSnapshot($product) : null;
            $created = false;

            if (! $product) {
                $created = true;
                $product = new Product([
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => $company?->id,
                    'slug' => Str::slug($data['name']) ?: Str::random(10),
                ]);
            }

            $measurementTableId = null;
            if ($data['measurement_table'] !== '') {
                $measurementTableId = $tableLookup[$this->cleanKey($data['measurement_table'])] ?? null;
                if ($measurementTableId === null) {
                    $reviewQueue[] = [
                        'section' => 'products',
                        'severity' => 'conflict',
                        'target' => 'measurement_table',
                        'source_value' => $data['measurement_table'],
                        'suggested_value' => null,
                        'confidence' => 'low',
                        'reason' => 'O lote foi aplicado sem vincular esta tabela porque ela ainda precisa ser revisada.',
                        'sku' => $data['sku'] ?: $data['external_product_id'],
                    ];
                }
            }

            $fitProfileCode = null;
            if ($data['fit_profile'] !== '') {
                $fitProfileCode = $profileLookup[$this->cleanKey($data['fit_profile'])] ?? null;
                if ($fitProfileCode === null) {
                    $reviewQueue[] = [
                        'section' => 'products',
                        'severity' => 'low_confidence',
                        'target' => 'fit_profile',
                        'source_value' => $data['fit_profile'],
                        'suggested_value' => null,
                        'confidence' => 'low',
                        'reason' => 'A modelagem não foi aplicada automaticamente porque não existe correspondência segura.',
                        'sku' => $data['sku'] ?: $data['external_product_id'],
                    ];
                }
            }

            $product->fill([
                'merchant_company_id' => $company?->id,
                'measurement_table_id' => $measurementTableId ?: $product->measurement_table_id,
                'external_product_id' => $data['external_product_id'] ?: $product->external_product_id,
                'sku' => $data['sku'] ?: $product->sku,
                'name' => $data['name'],
                'description' => $data['description'] ?: $product->description,
                'category' => $data['category'] ?: $product->category,
                'gender' => $data['gender'] ?: $product->gender,
                'fit_profile' => $fitProfileCode ?: $product->fit_profile,
                'status' => $data['status'] ?: $product->status ?: 'active',
                'image_url' => $data['image_url'] ?: $product->image_url,
                'metadata' => array_merge($product->metadata ?? [], array_filter([
                    'batch_id' => $batchId,
                    'sizebay_migration' => true,
                    'source_file' => $data['source_file'] ?: null,
                    'public_url' => $data['public_url'] ?: null,
                    'brand' => $data['brand'] ?: null,
                    'age_group' => $data['age_group'] ?: null,
                    'last_imported_at' => now()->toISOString(),
                ], fn (mixed $value): bool => $value !== null && $value !== '')),
            ]);
            $product->save();

            if ($data['brand'] !== '') {
                $this->brands->syncProductBrand($merchant, $company, $product, $data['brand'], 'import');
            }

            if ($data['category'] !== '') {
                $this->categories->syncProductCategory($merchant, $company, $product, $data['category'], 'import');
            }

            if ($data['size_label'] !== '') {
                $variant = $this->variantForProduct($product, $data);
                $variantSnapshot = $variant ? $this->modelSnapshot($variant) : null;
                $variantCreated = false;

                if (! $variant) {
                    $variantCreated = true;
                    $variant = new ProductVariant([
                        'merchant_id' => $merchant->id,
                        'merchant_company_id' => $company?->id,
                        'product_id' => $product->id,
                    ]);
                }

                $variant->fill([
                    'external_variant_id' => $data['external_variant_id'] ?: $variant->external_variant_id,
                    'sku' => $data['variant_sku'] ?: $variant->sku,
                    'size_label' => $data['size_label'],
                    'color' => $data['color'] ?: $variant->color,
                    'price' => $data['price'] ?? $variant->price,
                    'stock_quantity' => $data['stock_quantity'] ?? $variant->stock_quantity,
                    'is_active' => $data['is_active'] ?? $variant->is_active ?? true,
                    'metadata' => array_filter([
                        'batch_id' => $batchId,
                        'public_url' => $data['public_url'] ?: null,
                    ], fn (mixed $value): bool => $value !== null && $value !== ''),
                ]);
                $variant->save();

                $snapshot['variants'] = $snapshot['variants'] ?? [];
                $snapshot['variants'][$variant->id] = $variantSnapshot;

                if ($variantCreated && ! isset($snapshot['created_variant_ids'])) {
                    $snapshot['created_variant_ids'] = [];
                }

                if ($variantCreated) {
                    $snapshot['created_variant_ids'][] = $variant->id;
                }
            }

            $rollback['products'][] = [
                'state' => $created ? 'created' : 'updated',
                'id' => $product->id,
                'before' => $snapshot,
            ];
            $applied['products']++;
        }

        return [
            'review_queue' => $reviewQueue,
        ];
    }

    private function coveragePayload(
        Merchant $merchant,
        ?MerchantCompany $company,
        array $productIdentifiers,
        array $tableNames,
        array $sizes,
        int $conflicts,
        bool $compareWithBigShop
    ): array {
        $coverage = [
            'mode' => 'current_catalog',
            'products_in_package' => count($productIdentifiers),
            'products_in_reference' => 0,
            'products_matched' => 0,
            'tables_in_package' => count(array_unique($tableNames)),
            'tables_matched' => 0,
            'sizes_in_package' => count(array_unique($sizes)),
            'sizes_matched' => 0,
            'conflicts' => $conflicts,
            'warnings' => [],
        ];

        $catalogProducts = Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->with(['measurementTable', 'variants'])
            ->get();

        $catalogIdentifiers = collect();
        $catalogSizes = collect();
        $catalogTables = collect();

        foreach ($catalogProducts as $product) {
            if (filled($product->sku)) {
                $catalogIdentifiers->push((string) $product->sku);
            }

            if (filled($product->external_product_id)) {
                $catalogIdentifiers->push((string) $product->external_product_id);
            }

            if ($product->measurementTable?->name) {
                $catalogTables->push($product->measurementTable->name);
            }

            foreach ($product->variants as $variant) {
                if (filled($variant->size_label)) {
                    $catalogSizes->push(Str::upper((string) $variant->size_label));
                }
            }
        }

        $coverage['products_in_reference'] = $catalogIdentifiers->unique()->count();
        $coverage['products_matched'] = collect($productIdentifiers)
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->intersect($catalogIdentifiers->unique())
            ->count();
        $coverage['tables_matched'] = collect($tableNames)->unique()->intersect($catalogTables->unique())->count();
        $coverage['sizes_matched'] = collect($sizes)->unique()->intersect($catalogSizes->unique())->count();

        if (! $compareWithBigShop || ($company?->platform !== 'bigshop' && $company?->platform !== null)) {
            return $coverage;
        }

        $connection = PlatformConnection::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function ($query) use ($company): void {
                $query->where(function ($innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->where('platform', 'bigshop')
            ->first();

        if (! $connection || blank($connection->external_store_id) || blank($connection->access_token_encrypted)) {
            $coverage['warnings'][] = 'Comparação BigShop indisponível porque a conexão da loja ainda não está completa.';

            return $coverage;
        }

        try {
            $products = $this->bigShopClient->products($connection);
            $grids = $this->bigShopClient->productGrids($connection);
            $referenceIdentifiers = collect();
            $referenceSizes = collect();

            foreach ($products as $product) {
                if (! is_array($product)) {
                    continue;
                }

                foreach (['id', 'produtoid', 'sku', 'referencia', 'codigo_referencia', 'codexterno'] as $key) {
                    if (filled($product[$key] ?? null)) {
                        $referenceIdentifiers->push((string) $product[$key]);
                    }
                }
            }

            foreach ($grids as $grid) {
                if (! is_array($grid)) {
                    continue;
                }

                $size = $this->extractGridSize($grid);
                if ($size !== null) {
                    $referenceSizes->push($size);
                }
            }

            $coverage['mode'] = 'bigshop_feed';
            $coverage['products_in_reference'] = $referenceIdentifiers->unique()->count();
            $coverage['products_matched'] = collect($productIdentifiers)->unique()->intersect($referenceIdentifiers->unique())->count();
            $coverage['sizes_matched'] = collect($sizes)->unique()->intersect($referenceSizes->unique())->count();
        } catch (\Throwable $exception) {
            $coverage['warnings'][] = 'Comparação BigShop falhou durante a leitura do catálogo: '.$exception->getMessage();
        }

        return $coverage;
    }

    private function packageFromInput(array $input): array
    {
        $format = $input['source_format'];

        return match ($format) {
            'json' => $this->packageFromJson($input['content'] ?? ''),
            'zip' => $this->packageFromZip($this->decodeBase64Content($input['content_base64'] ?? null)),
            'xlsx' => $this->packageFromSingleSheetSpreadsheet($this->decodeBase64Content($input['content_base64'] ?? null), $input['section'] ?? null, $input['filename'] ?? 'migration.xlsx'),
            'csv' => $this->packageFromSingleCsv($input['content'] ?? '', $input['section'] ?? null, $input['filename'] ?? 'migration.csv'),
            default => throw new RuntimeException('Formato de migração Sizebay não suportado.'),
        };
    }

    private function packageFromJson(string $content): array
    {
        $decoded = json_decode(trim($content), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('JSON de migração inválido.');
        }

        $sections = $decoded['sections'] ?? $decoded;

        return [
            'package_source' => 'json',
            'warnings' => [],
            'sections' => [
                'measurement_tables' => $this->rowsWithLines($sections['measurement_tables'] ?? $sections['tables'] ?? [], 'json:measurement_tables'),
                'products' => $this->rowsWithLines($sections['products'] ?? [], 'json:products'),
                'brands' => $this->rowsWithLines($sections['brands'] ?? [], 'json:brands'),
                'categories' => $this->rowsWithLines($sections['categories'] ?? [], 'json:categories'),
                'fit_profiles' => $this->rowsWithLines($sections['fit_profiles'] ?? $sections['modelings'] ?? [], 'json:fit_profiles'),
                'import_rules' => $this->rowsWithLines($sections['import_rules'] ?? [], 'json:import_rules'),
                'reports' => $this->rowsWithLines($sections['reports'] ?? [], 'json:reports'),
            ],
        ];
    }

    private function packageFromZip(string $binary): array
    {
        if ($binary === '') {
            throw new RuntimeException('Arquivo ZIP vazio.');
        }

        $file = tempnam(sys_get_temp_dir(), 'pv-sizebay-zip');
        file_put_contents($file, $binary);
        $zip = new ZipArchive;

        if ($zip->open($file) !== true) {
            @unlink($file);
            throw new RuntimeException('Não foi possível abrir o pacote ZIP da migração.');
        }

        $sections = [
            'measurement_tables' => [],
            'products' => [],
            'brands' => [],
            'categories' => [],
            'fit_profiles' => [],
            'import_rules' => [],
            'reports' => [],
        ];
        $warnings = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            if (! $entryName || str_ends_with($entryName, '/')) {
                continue;
            }

            $content = $zip->getFromIndex($index);
            if ($content === false) {
                continue;
            }

            $section = $this->inferSectionFromFilename($entryName);
            if ($section === null) {
                $warnings[] = 'Arquivo ignorado no pacote: '.$entryName;

                continue;
            }

            $lowerName = Str::lower($entryName);

            if (str_ends_with($lowerName, '.json')) {
                $rows = $this->rowsWithLines(json_decode((string) $content, true) ?? [], 'zip:'.$entryName);
            } elseif (str_ends_with($lowerName, '.xlsx')) {
                $rows = $this->parseSpreadsheetRows((string) $content, 'zip:'.$entryName);
            } else {
                $rows = $this->parseCsvRows((string) $content, 'zip:'.$entryName);
            }

            $sections[$section] = array_merge($sections[$section], $rows);
        }

        $zip->close();
        @unlink($file);

        return [
            'package_source' => 'zip',
            'warnings' => $warnings,
            'sections' => $sections,
        ];
    }

    private function packageFromSingleSheetSpreadsheet(string $binary, ?string $section, string $filename): array
    {
        if (! $section) {
            throw new RuntimeException('Informe a seção do pacote ao importar um arquivo XLSX isolado.');
        }

        return [
            'package_source' => 'xlsx',
            'warnings' => [],
            'sections' => [
                'measurement_tables' => [],
                'products' => [],
                'brands' => [],
                'categories' => [],
                'fit_profiles' => [],
                'import_rules' => [],
                'reports' => [],
                $section => $this->parseSpreadsheetRows($binary, 'xlsx:'.$filename),
            ],
        ];
    }

    private function packageFromSingleCsv(string $content, ?string $section, string $filename): array
    {
        if (! $section) {
            throw new RuntimeException('Informe a seção do pacote ao importar um arquivo CSV isolado.');
        }

        return [
            'package_source' => 'csv',
            'warnings' => [],
            'sections' => [
                'measurement_tables' => [],
                'products' => [],
                'brands' => [],
                'categories' => [],
                'fit_profiles' => [],
                'import_rules' => [],
                'reports' => [],
                $section => $this->parseCsvRows($content, 'csv:'.$filename),
            ],
        ];
    }

    private function parseSpreadsheetRows(string $binary, string $sourceFile): array
    {
        $file = tempnam(sys_get_temp_dir(), 'pv-sizebay-xlsx');
        file_put_contents($file, $binary);
        $zip = new ZipArchive;

        if ($zip->open($file) !== true) {
            @unlink($file);
            throw new RuntimeException('Não foi possível abrir a planilha XLSX.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml);
            if ($xml instanceof SimpleXMLElement) {
                foreach ($xml->si as $item) {
                    $sharedStrings[] = trim((string) ($item->t ?? implode('', array_map(fn ($part): string => (string) $part->t, iterator_to_array($item->r ?? [])))));
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            @unlink($file);
            throw new RuntimeException('Planilha XLSX sem a primeira aba utilizável.');
        }

        $xml = simplexml_load_string($sheetXml);
        if (! $xml instanceof SimpleXMLElement) {
            $zip->close();
            @unlink($file);
            throw new RuntimeException('Conteúdo XLSX inválido.');
        }

        $rows = [];
        $headers = [];

        foreach ($xml->sheetData->row as $rowIndex => $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                $column = preg_replace('/\d+/', '', $reference) ?: '';
                $value = $this->spreadsheetCellValue($cell, $sharedStrings);
                $cells[$column] = $value;
            }

            ksort($cells);
            $values = array_values($cells);

            if ((int) $row['r'] === 1) {
                $headers = array_map(fn (string $header): string => $this->normalizeHeader($header), $values);

                continue;
            }

            $record = [];
            foreach ($headers as $position => $header) {
                if ($header === '') {
                    continue;
                }

                $record[$header] = trim((string) ($values[$position] ?? ''));
            }

            $record['_line'] = (int) ($row['r'] ?? ($rowIndex + 1));
            $record['_source_file'] = $sourceFile;
            $rows[] = $record;
        }

        $zip->close();
        @unlink($file);

        return $rows;
    }

    private function spreadsheetCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');
        $rawValue = (string) ($cell->v ?? '');

        if ($type === 's') {
            return (string) ($sharedStrings[(int) $rawValue] ?? '');
        }

        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        return trim($rawValue);
    }

    private function parseCsvRows(string $content, string $sourceFile): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', trim($content));
        if ($content === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $delimiter = $this->detectDelimiter($lines[0] ?? '');
        $headers = array_map(fn (string $header): string => $this->normalizeHeader($header), str_getcsv((string) array_shift($lines), $delimiter));
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            $row = [];

            foreach ($headers as $position => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($values[$position] ?? ''));
                }
            }

            $row['_line'] = $index + 2;
            $row['_source_file'] = $sourceFile;
            $rows[] = $row;
        }

        return $rows;
    }

    private function rowsWithLines(array $rows, string $sourceFile): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->values()
            ->map(function (array $row, int $index) use ($sourceFile): array {
                return [
                    ...$row,
                    '_line' => $row['_line'] ?? $index + 1,
                    '_source_file' => $row['_source_file'] ?? $sourceFile,
                ];
            })
            ->all();
    }

    private function inferSectionFromFilename(string $filename): ?string
    {
        $name = Str::lower($filename);

        return match (true) {
            str_contains($name, 'measurement') || str_contains($name, 'table') || str_contains($name, 'tabela') => 'measurement_tables',
            str_contains($name, 'product') || str_contains($name, 'produto') => 'products',
            str_contains($name, 'brand') || str_contains($name, 'marca') => 'brands',
            str_contains($name, 'categor') => 'categories',
            str_contains($name, 'model') || str_contains($name, 'fit_profile') || str_contains($name, 'modelagem') => 'fit_profiles',
            str_contains($name, 'rule') || str_contains($name, 'regra') => 'import_rules',
            str_contains($name, 'report') || str_contains($name, 'relatorio') => 'reports',
            default => null,
        };
    }

    private function decodeBase64Content(?string $content): string
    {
        if (! filled($content)) {
            throw new RuntimeException('Arquivo binário não informado.');
        }

        $decoded = base64_decode($content, true);

        if ($decoded === false) {
            throw new RuntimeException('Arquivo binário inválido.');
        }

        return $decoded;
    }

    private function detectDelimiter(string $line): string
    {
        return collect([',', ';', "\t"])
            ->sortByDesc(fn (string $delimiter): int => substr_count($line, $delimiter))
            ->first();
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->trim()->lower()->replace([' ', '-', '.'], '_')->ascii()->toString();
    }

    private function normalizeMeasurementTableRow(array $row): array
    {
        return [
            'table_name' => $this->value($row, ['table_name', 'name', 'tabela']),
            'product_type' => $this->value($row, ['product_type', 'category_type', 'tipo']) ?: 'other',
            'gender' => $this->value($row, ['gender', 'genero']),
            'fit_profile' => $this->value($row, ['fit_profile', 'modeling', 'modelagem']),
            'measurement_target' => $this->value($row, ['measurement_target', 'target', 'alvo']) ?: 'body',
            'size_system' => $this->value($row, ['size_system', 'sizing_system', 'sistema_tamanho']) ?: 'br_alpha',
            'range_mode' => $this->value($row, ['range_mode', 'interval_mode', 'modo_intervalo']) ?: 'min_max',
            'unit' => $this->value($row, ['unit', 'unidade']) ?: 'cm',
            'status' => $this->value($row, ['status']),
            'notes' => $this->value($row, ['notes', 'observations', 'observacoes']),
            'observation' => $this->value($row, ['observation', 'observacao']),
            'size_label' => $this->value($row, ['size_label', 'size', 'tamanho']),
            'bust_min' => $this->decimal($this->value($row, ['bust_min', 'busto_min'])),
            'bust_max' => $this->decimal($this->value($row, ['bust_max', 'busto_max'])),
            'waist_min' => $this->decimal($this->value($row, ['waist_min', 'cintura_min'])),
            'waist_max' => $this->decimal($this->value($row, ['waist_max', 'cintura_max'])),
            'hip_min' => $this->decimal($this->value($row, ['hip_min', 'quadril_min'])),
            'hip_max' => $this->decimal($this->value($row, ['hip_max', 'quadril_max'])),
            'height_min' => $this->decimal($this->value($row, ['height_min', 'altura_min'])),
            'height_max' => $this->decimal($this->value($row, ['height_max', 'altura_max'])),
            'weight_min' => $this->decimal($this->value($row, ['weight_min', 'peso_min'])),
            'weight_max' => $this->decimal($this->value($row, ['weight_max', 'peso_max'])),
            'length_min' => $this->decimal($this->value($row, ['length_min', 'comprimento_min'])),
            'length_max' => $this->decimal($this->value($row, ['length_max', 'comprimento_max'])),
            'shoulder_min' => $this->decimal($this->value($row, ['shoulder_min', 'ombro_min'])),
            'shoulder_max' => $this->decimal($this->value($row, ['shoulder_max', 'ombro_max'])),
            'measurements' => $this->jsonValue($row['measurements'] ?? null),
            'composite_measurements' => $this->jsonValue($row['composite_measurements'] ?? null),
            'source_file' => $row['_source_file'] ?? null,
        ];
    }

    private function normalizeFitProfileRow(array $row): array
    {
        $name = $this->value($row, ['name', 'modeling_name', 'modelagem']) ?: '';

        return [
            'name' => $name,
            'code' => $this->value($row, ['code', 'slug']) ?: (Str::slug($name) ?: substr(sha1($name), 0, 12)),
            'description' => $this->value($row, ['description', 'descricao']),
            'product_type' => $this->value($row, ['product_type', 'category_type']),
            'gender' => $this->value($row, ['gender', 'genero']),
            'fit_intensity' => $this->value($row, ['fit_intensity', 'intensity']) ?: 'regular',
            'stretch_level' => $this->value($row, ['stretch_level', 'elasticity']) ?: 'medium',
            'status' => $this->value($row, ['status']) ?: 'draft',
            'source_file' => $row['_source_file'] ?? null,
        ];
    }

    private function normalizeBrandRow(array $row): array
    {
        return [
            'name' => $this->value($row, ['name', 'brand', 'marca']) ?: '',
            'normalized_name' => $this->value($row, ['normalized_name', 'canonical_name', 'marca_normalizada']),
            'status' => $this->value($row, ['status']) ?: 'active',
            'source_file' => $row['_source_file'] ?? null,
        ];
    }

    private function normalizeCategoryRow(array $row): array
    {
        return [
            'name' => $this->value($row, ['name', 'category', 'categoria']) ?: '',
            'taxonomy_name' => $this->value($row, ['taxonomy_name', 'normalized_name', 'categoria_normalizada']),
            'category_type' => $this->value($row, ['category_type', 'tipo']) ?: 'other',
            'status' => $this->value($row, ['status']) ?: 'active',
            'source_file' => $row['_source_file'] ?? null,
        ];
    }

    private function normalizeProductRow(array $row): array
    {
        return [
            'external_product_id' => $this->value($row, ['external_product_id', 'product_id', 'produtoid', 'id']) ?: '',
            'external_variant_id' => $this->value($row, ['external_variant_id', 'variant_id', 'grid_id']) ?: '',
            'sku' => $this->value($row, ['sku', 'product_sku', 'referencia']) ?: '',
            'variant_sku' => $this->value($row, ['variant_sku', 'grid_sku']) ?: '',
            'name' => $this->value($row, ['name', 'title', 'product_name']) ?: '',
            'description' => $this->value($row, ['description', 'descricao']) ?: '',
            'category' => $this->value($row, ['category', 'categoria']) ?: '',
            'brand' => $this->value($row, ['brand', 'marca']) ?: '',
            'gender' => $this->value($row, ['gender', 'genero']) ?: '',
            'age_group' => $this->value($row, ['age_group', 'faixa_etaria']) ?: '',
            'fit_profile' => $this->value($row, ['fit_profile', 'modeling', 'modelagem']) ?: '',
            'measurement_table' => $this->value($row, ['measurement_table', 'table_name', 'tabela']) ?: '',
            'size_label' => $this->value($row, ['size_label', 'size', 'tamanho']) ?: '',
            'color' => $this->value($row, ['color', 'cor']) ?: '',
            'status' => $this->value($row, ['status']) ?: 'active',
            'image_url' => $this->value($row, ['image_url', 'imagem']) ?: '',
            'public_url' => $this->value($row, ['public_url', 'url', 'link']) ?: '',
            'price' => $this->decimal($this->value($row, ['price', 'preco'])),
            'stock_quantity' => $this->integer($this->value($row, ['stock_quantity', 'stock', 'estoque'])),
            'is_active' => $this->booleanValue($this->value($row, ['is_active', 'ativo'])),
            'source_file' => $row['_source_file'] ?? null,
        ];
    }

    private function normalizeImportRuleRow(array $row): array
    {
        return [
            'field' => $this->value($row, ['field', 'campo']) ?: '',
            'match_type' => $this->value($row, ['match_type', 'operator', 'operador']) ?: 'equals',
            'match_value' => $this->value($row, ['match_value', 'source_value', 'origem']) ?: '',
            'target_value' => $this->value($row, ['target_value', 'destination_value', 'destino']) ?: '',
            'source_file' => $row['_source_file'] ?? null,
        ];
    }

    private function normalizeReportRow(array $row): array
    {
        return [
            'period' => $this->value($row, ['period', 'date', 'data']) ?: '',
            'dimension' => $this->value($row, ['dimension', 'dimension_value', 'produto', 'categoria', 'marca']) ?: '',
            'metric' => $this->value($row, ['metric', 'metric_name', 'metrica']) ?: '',
            'device' => $this->value($row, ['device', 'dispositivo']) ?: '',
            'value' => $this->decimal($this->value($row, ['value', 'metric_value', 'valor'])),
            'source_file' => $row['_source_file'] ?? null,
        ];
    }

    private function matchNormalizedBrand(Collection $normalizedBrands, string $name): array
    {
        $key = $this->cleanKey($name);
        $brand = $normalizedBrands->first(fn (NormalizedBrand $candidate): bool => $this->cleanKey($candidate->name) === $key || $candidate->slug === Str::slug($name));

        if ($brand) {
            return [
                'status' => 'resolved',
                'confidence' => 'high',
                'brand' => $brand,
            ];
        }

        return [
            'status' => 'review',
            'confidence' => 'medium',
            'brand' => null,
        ];
    }

    private function matchTaxonomyCategory(Collection $taxonomyCategories, string $name, ?string $type): array
    {
        $key = $this->cleanKey($name);
        $slug = Str::slug($name);

        $category = $taxonomyCategories->first(function (TaxonomyCategory $candidate) use ($key, $slug, $type): bool {
            if ($type && $candidate->category_type !== $type) {
                return false;
            }

            return $this->cleanKey($candidate->name) === $key || $candidate->slug === $slug;
        });

        if ($category) {
            return [
                'status' => 'resolved',
                'confidence' => 'high',
                'category' => $category,
            ];
        }

        return [
            'status' => 'review',
            'confidence' => 'medium',
            'category' => null,
        ];
    }

    private function createBrandSuggestion(Merchant $merchant, ?MerchantCompany $company, MerchantBrand $brand, string $suggestedName, string $confidence): array
    {
        $suggestion = TaxonomyMappingSuggestion::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'taxonomy_version_id' => TaxonomyVersion::query()->where('status', 'active')->value('id'),
            'merchant_brand_id' => $brand->id,
            'suggestion_type' => 'brand',
            'source' => 'sizebay_migration',
            'original_value' => $brand->name,
            'suggested_target_type' => 'normalized_brand',
            'suggested_name' => $suggestedName,
            'confidence_score' => $confidence === 'high' ? 0.95 : 0.61,
            'confidence_level' => $confidence,
            'status' => 'pending',
            'reasons' => ['Migração Sizebay importou a marca local, mas a normalização precisa de revisão humana.'],
            'impact' => [
                'products_count' => Product::query()->where('merchant_id', $merchant->id)->where('metadata->brand', $brand->name)->count(),
            ],
            'context' => [
                'mapping_type' => 'sizebay_migration',
                'local_value' => $brand->name,
                'suggested_value' => $suggestedName,
            ],
        ]);

        return [
            'id' => $suggestion->id,
            'type' => 'brand',
        ];
    }

    private function createCategorySuggestion(Merchant $merchant, ?MerchantCompany $company, MerchantCategory $category, string $suggestedName, string $categoryType, string $confidence): array
    {
        $suggestion = TaxonomyMappingSuggestion::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'taxonomy_version_id' => TaxonomyVersion::query()->where('status', 'active')->value('id'),
            'merchant_category_id' => $category->id,
            'suggestion_type' => 'category',
            'source' => 'sizebay_migration',
            'original_value' => $category->name,
            'suggested_target_type' => 'taxonomy_category',
            'suggested_name' => $suggestedName,
            'confidence_score' => $confidence === 'high' ? 0.95 : 0.61,
            'confidence_level' => $confidence,
            'status' => 'pending',
            'reasons' => ['Migração Sizebay importou a categoria local, mas a taxonomia ainda precisa de revisão humana.'],
            'impact' => [
                'products_count' => Product::query()->where('merchant_id', $merchant->id)->where('category', $category->name)->count(),
            ],
            'context' => [
                'mapping_type' => 'sizebay_migration',
                'local_value' => $category->name,
                'suggested_value' => $suggestedName,
                'category_type' => $categoryType,
            ],
        ]);

        return [
            'id' => $suggestion->id,
            'type' => 'category',
        ];
    }

    private function rollbackTaxonomySuggestions(Merchant $merchant, array $items): void
    {
        foreach ($items as $item) {
            if (! isset($item['id'])) {
                continue;
            }

            TaxonomyMappingSuggestion::query()
                ->where('merchant_id', $merchant->id)
                ->whereKey($item['id'])
                ->delete();
        }
    }

    private function rollbackProducts(Merchant $merchant, ?MerchantCompany $company, array $items): void
    {
        foreach (array_reverse($items) as $item) {
            $product = Product::query()
                ->withTrashed()
                ->with('variants')
                ->where('merchant_id', $merchant->id)
                ->when($company, function ($query) use ($company): void {
                    $query->where(function ($innerQuery) use ($company): void {
                        $innerQuery->where('merchant_company_id', $company->id)
                            ->orWhereNull('merchant_company_id');
                    });
                })
                ->find($item['id']);

            if (! $product) {
                continue;
            }

            if (($item['state'] ?? '') === 'created') {
                $product->delete();

                continue;
            }

            $before = $item['before'] ?? null;
            if (! is_array($before)) {
                continue;
            }

            $product->fill(Arr::except($before['attributes'] ?? [], ['id', 'created_at', 'updated_at', 'deleted_at']));
            $product->save();
            $product->variants()->delete();

            foreach ($before['variants'] ?? [] as $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                $product->variants()->create(Arr::except($variant, ['id', 'created_at', 'updated_at', 'deleted_at']));
            }
        }
    }

    private function rollbackMeasurementTables(Merchant $merchant, ?MerchantCompany $company, array $items): void
    {
        foreach (array_reverse($items) as $item) {
            $table = MeasurementTable::query()
                ->withTrashed()
                ->with('rows')
                ->where('merchant_id', $merchant->id)
                ->when($company, function ($query) use ($company): void {
                    $query->where(function ($innerQuery) use ($company): void {
                        $innerQuery->where('merchant_company_id', $company->id)
                            ->orWhereNull('merchant_company_id');
                    });
                })
                ->find($item['id']);

            if (! $table) {
                continue;
            }

            if (($item['state'] ?? '') === 'created') {
                $table->delete();

                continue;
            }

            $before = $item['before'] ?? null;
            if (! is_array($before)) {
                continue;
            }

            $table->fill(Arr::except($before['attributes'] ?? [], ['id', 'created_at', 'updated_at', 'deleted_at']));
            $table->save();
            $table->rows()->delete();

            foreach ($before['rows'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $table->rows()->create(Arr::except($row, ['id', 'created_at', 'updated_at']));
            }
        }
    }

    private function rollbackFitProfiles(Merchant $merchant, ?MerchantCompany $company, array $items): void
    {
        $this->rollbackSimpleModels(FitProfile::class, $merchant, $company, $items);
    }

    private function rollbackMerchantBrands(Merchant $merchant, ?MerchantCompany $company, array $items): void
    {
        $this->rollbackSimpleModels(MerchantBrand::class, $merchant, $company, $items);
    }

    private function rollbackMerchantCategories(Merchant $merchant, ?MerchantCompany $company, array $items): void
    {
        $this->rollbackSimpleModels(MerchantCategory::class, $merchant, $company, $items);
    }

    private function rollbackSimpleModels(string $modelClass, Merchant $merchant, ?MerchantCompany $company, array $items): void
    {
        foreach (array_reverse($items) as $item) {
            $model = $modelClass::query()
                ->withTrashed()
                ->where('merchant_id', $merchant->id)
                ->when($company, function ($query) use ($company): void {
                    $query->where(function ($innerQuery) use ($company): void {
                        $innerQuery->where('merchant_company_id', $company->id)
                            ->orWhereNull('merchant_company_id');
                    });
                })
                ->find($item['id']);

            if (! $model) {
                continue;
            }

            if (($item['state'] ?? '') === 'created') {
                $model->delete();

                continue;
            }

            $before = $item['before'] ?? null;
            if (! is_array($before)) {
                continue;
            }

            $model->fill(Arr::except($before['attributes'] ?? [], ['id', 'created_at', 'updated_at', 'deleted_at']));
            $model->save();
        }
    }

    private function measurementTableSnapshot(MeasurementTable $table): array
    {
        return [
            'attributes' => Arr::only($table->getAttributes(), [
                'merchant_company_id', 'name', 'product_type', 'gender', 'fit_profile', 'measurement_target',
                'size_system', 'range_mode', 'unit', 'status', 'source', 'notes', 'metadata',
            ]),
            'rows' => $table->rows->map(fn ($row): array => Arr::only($row->getAttributes(), [
                'measurement_table_id', 'size_label', 'sort_order', 'bust_min', 'bust_max', 'waist_min', 'waist_max',
                'hip_min', 'hip_max', 'height_min', 'height_max', 'weight_min', 'weight_max', 'length_min', 'length_max',
                'shoulder_min', 'shoulder_max', 'measurements', 'composite_measurements', 'metadata',
            ]))->all(),
        ];
    }

    private function productSnapshot(Product $product): array
    {
        return [
            'attributes' => Arr::only($product->getAttributes(), [
                'merchant_company_id', 'measurement_table_id', 'external_product_id', 'sku', 'name', 'slug',
                'description', 'category', 'gender', 'fit_profile', 'status', 'image_url', 'metadata',
            ]),
            'variants' => $product->variants->map(fn ($variant): array => Arr::only($variant->getAttributes(), [
                'merchant_id', 'merchant_company_id', 'product_id', 'external_variant_id', 'sku', 'size_label',
                'color', 'price', 'stock_quantity', 'is_active', 'metadata',
            ]))->all(),
        ];
    }

    private function modelSnapshot(mixed $model): array
    {
        return [
            'attributes' => Arr::except($model->getAttributes(), ['id', 'created_at', 'updated_at', 'deleted_at']),
        ];
    }

    private function variantForProduct(Product $product, array $data): ?ProductVariant
    {
        return $product->variants->first(function (ProductVariant $variant) use ($data): bool {
            return ($data['external_variant_id'] !== '' && $variant->external_variant_id === $data['external_variant_id'])
                || ($data['variant_sku'] !== '' && $variant->sku === $data['variant_sku'])
                || ($variant->size_label === $data['size_label'] && ($data['color'] === '' || $variant->color === $data['color']));
        });
    }

    private function extractGridSize(array $grid): ?string
    {
        foreach (['size_label', 'size', 'tamanho'] as $key) {
            if (filled($grid[$key] ?? null)) {
                return Str::upper(trim((string) $grid[$key]));
            }
        }

        foreach (['caracteristicas', 'characteristics', 'attributes'] as $key) {
            if (! is_array($grid[$key] ?? null) && ! is_string($grid[$key] ?? null)) {
                continue;
            }

            $serialized = is_string($grid[$key]) ? $grid[$key] : json_encode($grid[$key]);
            if ($serialized && preg_match('/(?:tamanho|size)\s*[:=\-]?\s*([[:alnum:]\/+.-]{1,24})/iu', $serialized, $matches)) {
                return Str::upper(trim($matches[1]));
            }
        }

        return null;
    }

    private function cleanKey(?string $value): string
    {
        return trim(Str::of((string) $value)->lower()->ascii()->replace(['_', '-'], ' ')->replaceMatches('/\s+/', ' ')->toString());
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function decimal(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $value));
    }

    private function integer(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) preg_replace('/[^0-9-]/', '', $value);
    }

    private function jsonValue(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function booleanValue(?string $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = Str::lower(Str::ascii($value));

        return match ($normalized) {
            '1', 'true', 'sim', 'yes', 'ativo', 'active' => true,
            '0', 'false', 'nao', 'no', 'inativo', 'inactive' => false,
            default => null,
        };
    }
}
