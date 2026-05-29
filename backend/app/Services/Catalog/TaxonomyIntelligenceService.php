<?php

namespace App\Services\Catalog;

use App\Models\Merchant;
use App\Models\MerchantBrand;
use App\Models\MerchantCategory;
use App\Models\MerchantCompany;
use App\Models\NormalizedBrand;
use App\Models\Product;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyLearningEvent;
use App\Models\TaxonomyMappingSuggestion;
use App\Models\TaxonomyVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TaxonomyIntelligenceService
{
    public function __construct(
        private readonly CategoryCatalogService $categories,
        private readonly BrandCatalogService $brands
    ) {}

    public function dashboard(Merchant $merchant, ?MerchantCompany $company): array
    {
        $this->discoverCatalog($merchant, $company);
        $version = $this->activeVersion();
        $suggestions = $this->suggestionQuery($merchant, $company)
            ->with([
                'version',
                'merchantCategory.taxonomyCategory.parent',
                'merchantBrand.normalizedBrand',
                'taxonomyCategory.parent',
                'normalizedBrand',
            ])
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 when 'applied' then 2 else 3 end")
            ->latest('updated_at')
            ->limit(120)
            ->get();
        $pending = $suggestions->where('status', 'pending');
        $products = $this->productQuery($merchant, $company)->get();
        $categoryCount = $products->filter(fn (Product $product): bool => filled($product->category))->count();
        $brandCount = $products->filter(fn (Product $product): bool => filled(data_get($product->metadata ?? [], 'brand')))->count();
        $learningCount = $this->learningQuery($merchant, $company)->count();
        $learningEvents = $this->learningQuery($merchant, $company)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (TaxonomyLearningEvent $event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'target_type' => $event->target_type,
                'original_value' => $event->original_value,
                'normalized_value' => $event->normalized_value,
                'confidence_score' => $event->confidence_score,
                'created_at' => $event->created_at?->toISOString(),
            ])
            ->values();

        $summary = [
            'active_version' => $version->version,
            'taxonomy_categories' => TaxonomyCategory::query()->where('status', 'active')->count(),
            'normalized_brands' => NormalizedBrand::query()->where('status', 'active')->count(),
            'local_categories' => $this->categoryQuery($merchant, $company)->count(),
            'local_brands' => $this->brandQuery($merchant, $company)->count(),
            'candidate_mappings' => $this->candidateMappings($merchant, $company),
            'pending_suggestions' => $pending->count(),
            'high_confidence' => $pending->where('confidence_level', 'high')->count(),
            'medium_confidence' => $pending->where('confidence_level', 'medium')->count(),
            'low_confidence' => $pending->where('confidence_level', 'low')->count(),
            'review_required' => $pending->where('confidence_level', 'low')->count(),
            'products_impacted' => $pending
                ->sum(fn (TaxonomyMappingSuggestion $suggestion): int => (int) data_get($suggestion->impact ?? [], 'products_count', 0)),
            'learning_events' => $learningCount,
            'products_with_category' => $categoryCount,
            'products_with_normalized_category' => $products
                ->filter(fn (Product $product): bool => filled(data_get($product->metadata ?? [], 'normalized_category.name')))
                ->count(),
            'products_with_brand' => $brandCount,
            'products_with_normalized_brand' => $products
                ->filter(fn (Product $product): bool => filled(data_get($product->metadata ?? [], 'normalized_brand.name')))
                ->count(),
        ];

        return [
            'summary' => $summary,
            'active_version' => $version,
            'suggestions' => $suggestions,
            'learning_events' => $learningEvents,
            'signals' => [
                'rules' => ['category', 'brand', 'gender', 'age_group', 'fit_profile', 'size_system'],
                'ai' => ['taxonomy_category', 'normalized_brand', 'modeling_context', 'confidence'],
                'reports' => ['category', 'brand', 'gender', 'age_group'],
                'imports' => ['merchant_category', 'merchant_brand', 'approved_mapping'],
            ],
        ];
    }

    public function generateSuggestions(Merchant $merchant, ?MerchantCompany $company, string $type = 'all'): array
    {
        $this->discoverCatalog($merchant, $company);
        $version = $this->activeVersion();
        $created = 0;
        $updated = 0;

        if (in_array($type, ['all', 'category', 'categories'], true)) {
            [$categoryCreated, $categoryUpdated] = $this->generateCategorySuggestions($merchant, $company, $version);
            $created += $categoryCreated;
            $updated += $categoryUpdated;
        }

        if (in_array($type, ['all', 'brand', 'brands'], true)) {
            [$brandCreated, $brandUpdated] = $this->generateBrandSuggestions($merchant, $company, $version);
            $created += $brandCreated;
            $updated += $brandUpdated;
        }

        $this->refreshVersionSummary($version);

        $suggestions = $this->suggestionQuery($merchant, $company)
            ->with([
                'version',
                'merchantCategory.taxonomyCategory.parent',
                'merchantBrand.normalizedBrand',
                'taxonomyCategory.parent',
                'normalizedBrand',
            ])
            ->where('status', 'pending')
            ->latest('updated_at')
            ->get();

        return [
            'summary' => [
                'created' => $created,
                'updated' => $updated,
                'pending' => $suggestions->count(),
                'review_required' => $suggestions->where('confidence_level', 'low')->count(),
            ],
            'suggestions' => $suggestions,
        ];
    }

    public function approveSuggestion(
        TaxonomyMappingSuggestion $suggestion,
        Merchant $merchant,
        ?MerchantCompany $company,
        ?int $reviewerId,
        array $input
    ): array {
        $this->scopedSuggestion($merchant, $company, $suggestion);

        abort_if($suggestion->status !== 'pending', 422, 'Esta sugestão já foi revisada.');
        abort_if(
            $suggestion->confidence_level === 'low' && empty($input['confirm_low_confidence']),
            422,
            'Sugestões de baixa confiança exigem confirmação explícita antes de aplicar.'
        );

        return DB::transaction(function () use ($suggestion, $merchant, $company, $reviewerId, $input): array {
            $applyToProducts = (bool) ($input['apply_to_products'] ?? true);
            $targetName = null;
            $targetId = null;
            $productSummary = ['matched' => 0, 'updated' => 0, 'product_ids' => []];

            if ($suggestion->suggestion_type === 'category') {
                [$targetId, $targetName, $productSummary] = $this->approveCategorySuggestion($suggestion, $merchant, $company, $input, $applyToProducts);
            } else {
                [$targetId, $targetName, $productSummary] = $this->approveBrandSuggestion($suggestion, $merchant, $company, $input, $applyToProducts);
            }

            $suggestion->fill([
                'status' => $applyToProducts ? 'applied' : 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'applied_at' => $applyToProducts ? now() : null,
                'impact' => array_merge($suggestion->impact ?? [], [
                    'applied_products' => $productSummary['updated'],
                    'approved_target_id' => $targetId,
                    'approved_target_name' => $targetName,
                    'requires_confirmation' => false,
                ]),
            ])->save();

            $this->recordLearningEvent($merchant, $company, $suggestion, $applyToProducts ? 'mapping_applied' : 'suggestion_approved', $targetName, [
                'reviewed_by' => $reviewerId,
                'products_updated' => $productSummary['updated'],
            ]);

            return [
                'summary' => [
                    'status' => $suggestion->status,
                    'target_id' => $targetId,
                    'target_name' => $targetName,
                    'products_matched' => $productSummary['matched'],
                    'products_updated' => $productSummary['updated'],
                ],
                'suggestion' => $suggestion->fresh([
                    'version',
                    'merchantCategory.taxonomyCategory.parent',
                    'merchantBrand.normalizedBrand',
                    'taxonomyCategory.parent',
                    'normalizedBrand',
                ]),
            ];
        });
    }

    public function rejectSuggestion(
        TaxonomyMappingSuggestion $suggestion,
        Merchant $merchant,
        ?MerchantCompany $company,
        ?int $reviewerId,
        ?string $reason = null
    ): array {
        $this->scopedSuggestion($merchant, $company, $suggestion);

        abort_if($suggestion->status !== 'pending', 422, 'Esta sugestão já foi revisada.');

        $context = array_merge($suggestion->context ?? [], [
            'rejection_reason' => $reason ?: 'Rejeitada na fila de revisão.',
        ]);
        $suggestion->fill([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'context' => $context,
        ])->save();

        $this->recordLearningEvent($merchant, $company, $suggestion, 'suggestion_rejected', null, [
            'reviewed_by' => $reviewerId,
            'reason' => $reason,
        ]);

        return [
            'summary' => [
                'status' => 'rejected',
                'target_name' => $suggestion->suggested_name,
            ],
            'suggestion' => $suggestion->fresh([
                'version',
                'merchantCategory.taxonomyCategory.parent',
                'merchantBrand.normalizedBrand',
                'taxonomyCategory.parent',
                'normalizedBrand',
            ]),
        ];
    }

    public function activeVersion(): TaxonomyVersion
    {
        $version = TaxonomyVersion::query()
            ->where('status', 'active')
            ->latest('published_at')
            ->latest('id')
            ->first();

        if ($version) {
            return $version;
        }

        return TaxonomyVersion::query()->create([
            'version' => '2026.05.29-sprint138',
            'label' => 'Taxonomia inteligente inicial',
            'status' => 'active',
            'summary' => [
                'source' => 'fallback_runtime',
                'feeds' => ['categorias', 'marcas', 'genero', 'faixa_etaria', 'modelagem', 'sistema_tamanho'],
            ],
            'published_at' => now(),
            'metadata' => ['sensitive_data' => false],
        ]);
    }

    private function generateCategorySuggestions(Merchant $merchant, ?MerchantCompany $company, TaxonomyVersion $version): array
    {
        $taxonomyOptions = TaxonomyCategory::query()
            ->with('parent')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $merchantCategories = $this->categoryQuery($merchant, $company)
            ->with('taxonomyCategory.parent')
            ->get();
        $productGroups = $this->categories->productsByCategory($merchant, $company);
        $created = 0;
        $updated = 0;

        foreach ($merchantCategories->whereNull('taxonomy_category_id') as $category) {
            $products = $productGroups->get($category->name, collect());
            $analysis = $this->analyzeCategorySuggestion($category, $taxonomyOptions, $merchantCategories, $products);
            $suggestion = $this->storeSuggestion($merchant, $company, $version, [
                'merchant_category_id' => $category->id,
                'suggestion_type' => 'category',
                'original_value' => $category->name,
                'suggested_target_type' => $analysis['taxonomy_category_id'] ? 'taxonomy_category' : 'proposed_category',
                'taxonomy_category_id' => $analysis['taxonomy_category_id'],
                'suggested_name' => $analysis['taxonomy_name'],
                'confidence_score' => $analysis['confidence_score'],
                'confidence_level' => $analysis['confidence_level'],
                'reasons' => $analysis['reasons'],
                'impact' => $analysis['impact'],
                'context' => $analysis['context'],
            ]);
            $suggestion->wasRecentlyCreated ? $created++ : $updated++;
        }

        return [$created, $updated];
    }

    private function generateBrandSuggestions(Merchant $merchant, ?MerchantCompany $company, TaxonomyVersion $version): array
    {
        $normalizedOptions = NormalizedBrand::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $merchantBrands = $this->brandQuery($merchant, $company)
            ->with('normalizedBrand')
            ->get();
        $productGroups = $this->brands->productsByBrand($merchant, $company);
        $created = 0;
        $updated = 0;

        foreach ($merchantBrands->whereNull('normalized_brand_id') as $brand) {
            $products = $productGroups->get($brand->name, collect());
            $analysis = $this->analyzeBrandSuggestion($brand, $normalizedOptions, $merchantBrands, $products);
            $suggestion = $this->storeSuggestion($merchant, $company, $version, [
                'merchant_brand_id' => $brand->id,
                'suggestion_type' => 'brand',
                'original_value' => $brand->name,
                'suggested_target_type' => $analysis['normalized_brand_id'] ? 'normalized_brand' : 'proposed_brand',
                'normalized_brand_id' => $analysis['normalized_brand_id'],
                'suggested_name' => $analysis['normalized_name'],
                'confidence_score' => $analysis['confidence_score'],
                'confidence_level' => $analysis['confidence_level'],
                'reasons' => $analysis['reasons'],
                'impact' => $analysis['impact'],
                'context' => $analysis['context'],
            ]);
            $suggestion->wasRecentlyCreated ? $created++ : $updated++;
        }

        return [$created, $updated];
    }

    private function analyzeCategorySuggestion(
        MerchantCategory $category,
        Collection $taxonomyOptions,
        Collection $merchantCategories,
        Collection $products
    ): array {
        $fingerprint = data_get($category->metadata ?? [], 'fingerprint') ?: $this->categories->fingerprint($category->name);
        $exactTaxonomy = $taxonomyOptions->first(fn (TaxonomyCategory $taxonomy): bool => $this->taxonomyMatches($taxonomy, $category->name, $fingerprint));
        $mappedSibling = $merchantCategories
            ->first(fn (MerchantCategory $sibling): bool => (int) $sibling->id !== (int) $category->id
                && filled($sibling->taxonomy_category_id)
                && (data_get($sibling->metadata ?? [], 'fingerprint') ?: $this->categories->fingerprint($sibling->name)) === $fingerprint);
        $target = $exactTaxonomy ?: $mappedSibling?->taxonomyCategory ?: $this->inferredTaxonomy($category->name, $taxonomyOptions);
        $mode = 'create';
        $score = count((array) data_get($category->metadata ?? [], 'aliases', [])) > 1 ? 0.66 : 0.42;
        $reasons = [
            'IA sugeriu a partir da categoria local, histórico de importação e sinais do produto.',
            'Revisão humana preserva filtros, regras, relatórios e recomendações.',
        ];

        if ($exactTaxonomy) {
            $mode = 'existing';
            $score = 0.94;
            $reasons = ['Nome igual ou equivalente a uma categoria ativa da taxonomia versionada.'];
        } elseif ($mappedSibling?->taxonomyCategory) {
            $mode = 'existing';
            $score = 0.91;
            $reasons = ['Outra variação local com a mesma impressão digital já foi revisada.'];
        } elseif ($target) {
            $mode = 'existing';
            $score = 0.78;
            $reasons = ['Inferência por tipo de peça, categoria do feed e vocabulário da taxonomia.'];
        }

        $name = $target?->name ?: $this->displayName($category->name, $fingerprint);
        $context = $this->contextForProducts($products, [
            'mapping_type' => 'category',
            'local_value' => $category->name,
            'suggested_value' => $name,
            'category_type' => $target?->category_type ?: $this->categoryTypeFromName($name),
            'mode' => $mode,
        ]);

        return [
            'taxonomy_category_id' => $target?->id,
            'taxonomy_name' => $name,
            'confidence_score' => $score,
            'confidence_level' => $this->confidenceLevel($score),
            'reasons' => [
                ...$reasons,
                sprintf('%d produto(s) seriam impactados por este mapeamento.', $products->count()),
            ],
            'impact' => $this->impactForProducts($products, $score, $target !== null),
            'context' => $context,
        ];
    }

    private function analyzeBrandSuggestion(
        MerchantBrand $brand,
        Collection $normalizedOptions,
        Collection $merchantBrands,
        Collection $products
    ): array {
        $fingerprint = data_get($brand->metadata ?? [], 'fingerprint') ?: $this->brands->fingerprint($brand->name);
        $displayName = $this->displayName($brand->name, $fingerprint);
        $slug = $this->brands->slug($displayName);
        $exactNormalized = $normalizedOptions
            ->first(fn (NormalizedBrand $normalized): bool => $normalized->slug === $brand->slug || $normalized->slug === $slug);
        $mappedSibling = $merchantBrands
            ->first(fn (MerchantBrand $sibling): bool => (int) $sibling->id !== (int) $brand->id
                && filled($sibling->normalized_brand_id)
                && (data_get($sibling->metadata ?? [], 'fingerprint') ?: $this->brands->fingerprint($sibling->name)) === $fingerprint);
        $target = $exactNormalized ?: $mappedSibling?->normalizedBrand;
        $mode = 'create';
        $score = count((array) data_get($brand->metadata ?? [], 'aliases', [])) > 1 ? 0.67 : 0.44;
        $reasons = [
            'IA sugeriu a partir da marca local, variações do feed e histórico local.',
            'Revisão humana evita mesclar marcas diferentes por acidente.',
        ];

        if ($exactNormalized) {
            $mode = 'existing';
            $score = 0.95;
            $reasons = ['Nome igual ou equivalente a uma marca normalizada existente.'];
        } elseif ($mappedSibling?->normalizedBrand) {
            $mode = 'existing';
            $score = 0.91;
            $reasons = ['Outra variação local com a mesma impressão digital já foi revisada.'];
        }

        $name = $target?->name ?: $displayName;
        $context = $this->contextForProducts($products, [
            'mapping_type' => 'brand',
            'local_value' => $brand->name,
            'suggested_value' => $name,
            'mode' => $mode,
        ]);

        return [
            'normalized_brand_id' => $target?->id,
            'normalized_name' => $name,
            'confidence_score' => $score,
            'confidence_level' => $this->confidenceLevel($score),
            'reasons' => [
                ...$reasons,
                sprintf('%d produto(s) seriam impactados por este mapeamento.', $products->count()),
            ],
            'impact' => $this->impactForProducts($products, $score, $target !== null),
            'context' => $context,
        ];
    }

    private function storeSuggestion(Merchant $merchant, ?MerchantCompany $company, TaxonomyVersion $version, array $payload): TaxonomyMappingSuggestion
    {
        $suggestion = TaxonomyMappingSuggestion::query()->firstOrNew([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'suggestion_type' => $payload['suggestion_type'],
            'original_value' => $payload['original_value'],
            'status' => 'pending',
        ]);
        $suggestion->fill([
            'taxonomy_version_id' => $version->id,
            'source' => 'learning',
            'product_id' => data_get($payload, 'impact.affected_product_ids.0'),
            'merchant_category_id' => $payload['merchant_category_id'] ?? null,
            'merchant_brand_id' => $payload['merchant_brand_id'] ?? null,
            'suggested_target_type' => $payload['suggested_target_type'],
            'taxonomy_category_id' => $payload['taxonomy_category_id'] ?? null,
            'normalized_brand_id' => $payload['normalized_brand_id'] ?? null,
            'suggested_name' => $payload['suggested_name'],
            'confidence_score' => $payload['confidence_score'],
            'confidence_level' => $payload['confidence_level'],
            'reasons' => $payload['reasons'],
            'impact' => $payload['impact'],
            'context' => $payload['context'],
        ])->save();

        return $suggestion;
    }

    private function approveCategorySuggestion(
        TaxonomyMappingSuggestion $suggestion,
        Merchant $merchant,
        ?MerchantCompany $company,
        array $input,
        bool $applyToProducts
    ): array {
        $taxonomyCategory = ! empty($input['taxonomy_category_id'])
            ? TaxonomyCategory::query()->findOrFail((int) $input['taxonomy_category_id'])
            : $suggestion->taxonomyCategory;

        if (! $taxonomyCategory) {
            $name = $input['taxonomy_name'] ?? $suggestion->suggested_name ?? $suggestion->original_value;
            $taxonomyCategory = $this->categories->ensureTaxonomyCategory($name, data_get($suggestion->context ?? [], 'category_type', 'other'), null, [
                'gender' => data_get($suggestion->context ?? [], 'signals.gender'),
                'age_group' => data_get($suggestion->context ?? [], 'signals.age_group'),
                'metadata' => [
                    'source' => 'taxonomy_intelligence',
                    'taxonomy_version' => $suggestion->version?->version,
                    'confidence_score' => $suggestion->confidence_score,
                ],
            ]);
        }

        $category = $suggestion->merchantCategory ?: $this->categories->ensureMerchantCategory($merchant, $company, $suggestion->original_value);
        $category->fill([
            'taxonomy_category_id' => $taxonomyCategory->id,
            'source' => 'ai_review',
            'status' => 'active',
            'metadata' => array_merge($category->metadata ?? [], [
                'fingerprint' => $this->categories->fingerprint($category->name),
                'reviewed_at' => now()->toISOString(),
                'taxonomy_intelligence' => $this->mappingMetadata($suggestion),
            ]),
        ])->save();

        $summary = $applyToProducts
            ? $this->categories->applyToProducts($merchant, $company, $category->fresh(), $taxonomyCategory, 'ai_review')
            : ['matched' => (int) data_get($suggestion->impact ?? [], 'products_count', 0), 'updated' => 0, 'product_ids' => []];

        $suggestion->forceFill([
            'taxonomy_category_id' => $taxonomyCategory->id,
            'suggested_target_type' => 'taxonomy_category',
            'suggested_name' => $taxonomyCategory->name,
        ])->save();

        return [$taxonomyCategory->id, $taxonomyCategory->name, $summary];
    }

    private function approveBrandSuggestion(
        TaxonomyMappingSuggestion $suggestion,
        Merchant $merchant,
        ?MerchantCompany $company,
        array $input,
        bool $applyToProducts
    ): array {
        $normalizedBrand = ! empty($input['normalized_brand_id'])
            ? NormalizedBrand::query()->findOrFail((int) $input['normalized_brand_id'])
            : $suggestion->normalizedBrand;

        if (! $normalizedBrand) {
            $normalizedBrand = $this->brands->ensureNormalizedBrand(
                $input['normalized_name'] ?? $suggestion->suggested_name ?? $suggestion->original_value,
                [
                    'source' => 'taxonomy_intelligence',
                    'taxonomy_version' => $suggestion->version?->version,
                    'confidence_score' => $suggestion->confidence_score,
                ]
            );
        }

        $brand = $suggestion->merchantBrand ?: $this->brands->ensureMerchantBrand($merchant, $company, $suggestion->original_value);
        $brand->fill([
            'normalized_brand_id' => $normalizedBrand->id,
            'source' => 'ai_review',
            'status' => 'active',
            'metadata' => array_merge($brand->metadata ?? [], [
                'fingerprint' => $this->brands->fingerprint($brand->name),
                'reviewed_at' => now()->toISOString(),
                'taxonomy_intelligence' => $this->mappingMetadata($suggestion),
            ]),
        ])->save();

        $summary = $applyToProducts
            ? $this->brands->applyToProducts($merchant, $company, $brand->fresh(), $normalizedBrand, 'ai_review')
            : ['matched' => (int) data_get($suggestion->impact ?? [], 'products_count', 0), 'updated' => 0, 'product_ids' => []];

        $suggestion->forceFill([
            'normalized_brand_id' => $normalizedBrand->id,
            'suggested_target_type' => 'normalized_brand',
            'suggested_name' => $normalizedBrand->name,
        ])->save();

        return [$normalizedBrand->id, $normalizedBrand->name, $summary];
    }

    private function discoverCatalog(Merchant $merchant, ?MerchantCompany $company): void
    {
        foreach ($this->categories->productsByCategory($merchant, $company) as $categoryName => $products) {
            $taxonomyId = $products
                ->map(fn (Product $product) => data_get($product->metadata ?? [], 'normalized_category.id') ?: data_get($product->metadata ?? [], 'normalized_category_id'))
                ->filter()
                ->first();
            $this->categories->ensureMerchantCategory($merchant, $company, $categoryName, [
                'taxonomy_category_id' => $taxonomyId ? (int) $taxonomyId : null,
                'source' => $this->sourceForProducts($products),
                'metadata' => [
                    'product_count_detected' => $products->count(),
                    'sources' => $products->map(fn (Product $product) => $this->sourceForProduct($product))->unique()->values()->all(),
                    'aliases' => $this->aliasesFor($products, 'category'),
                ],
            ]);
        }

        foreach ($this->brands->productsByBrand($merchant, $company) as $brandName => $products) {
            $normalizedId = $products
                ->map(fn (Product $product) => data_get($product->metadata ?? [], 'normalized_brand.id') ?: data_get($product->metadata ?? [], 'normalized_brand_id'))
                ->filter()
                ->first();
            $this->brands->ensureMerchantBrand($merchant, $company, $brandName, [
                'normalized_brand_id' => $normalizedId ? (int) $normalizedId : null,
                'source' => $this->sourceForProducts($products),
                'metadata' => [
                    'product_count_detected' => $products->count(),
                    'sources' => $products->map(fn (Product $product) => $this->sourceForProduct($product))->unique()->values()->all(),
                    'aliases' => $this->aliasesFor($products, 'brand'),
                ],
            ]);
        }
    }

    private function impactForProducts(Collection $products, float $score, bool $hasExistingTarget): array
    {
        return [
            'products_count' => $products->count(),
            'affected_product_ids' => $products->pluck('id')->take(20)->values()->all(),
            'uses' => ['imports', 'rules', 'ai', 'recommendations', 'analytics'],
            'can_auto_apply' => $score >= 0.9 && $hasExistingTarget,
            'requires_confirmation' => $score < 0.7,
            'critical_fields' => ['category', 'brand', 'gender', 'age_group', 'fit_profile', 'size_system'],
        ];
    }

    private function contextForProducts(Collection $products, array $base): array
    {
        $products->each(fn (Product $product): Product => $product->loadMissing('variants'));
        $gender = $this->topSignal($products->pluck('gender'));
        $ageGroup = $this->topSignal($products->map(fn (Product $product) => data_get($product->metadata ?? [], 'age_group')));
        $fitProfile = $this->topSignal($products->pluck('fit_profile'));
        $sources = $products
            ->map(fn (Product $product): string => $this->sourceForProduct($product))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_merge($base, [
            'signals' => [
                'gender' => $gender,
                'age_group' => $ageGroup,
                'fit_profile' => $fitProfile,
                'size_system' => $this->sizeSystemForProducts($products),
                'sources' => $sources,
            ],
            'sample' => [
                'products_count' => $products->count(),
                'product_ids' => $products->pluck('id')->take(8)->values()->all(),
            ],
            'sensitive_data' => false,
        ]);
    }

    private function recordLearningEvent(
        Merchant $merchant,
        ?MerchantCompany $company,
        TaxonomyMappingSuggestion $suggestion,
        string $eventType,
        ?string $normalizedValue,
        array $metadata = []
    ): void {
        TaxonomyLearningEvent::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'taxonomy_mapping_suggestion_id' => $suggestion->id,
            'event_type' => $eventType,
            'source' => 'taxonomy_intelligence',
            'target_type' => $suggestion->suggestion_type,
            'original_value' => $suggestion->original_value,
            'normalized_value' => $normalizedValue,
            'confidence_score' => $suggestion->confidence_score,
            'context' => [
                'confidence_level' => $suggestion->confidence_level,
                'reasons' => $suggestion->reasons ?? [],
                'sensitive_data' => false,
            ],
            'metadata' => array_merge([
                'products_count' => (int) data_get($suggestion->impact ?? [], 'products_count', 0),
                'taxonomy_version' => $suggestion->version?->version,
            ], array_filter($metadata, fn (mixed $value): bool => $value !== null)),
        ]);
    }

    private function mappingMetadata(TaxonomyMappingSuggestion $suggestion): array
    {
        return [
            'suggestion_id' => $suggestion->id,
            'taxonomy_version' => $suggestion->version?->version,
            'confidence_score' => $suggestion->confidence_score,
            'confidence_level' => $suggestion->confidence_level,
            'approved_at' => now()->toISOString(),
            'reasons' => $suggestion->reasons ?? [],
        ];
    }

    private function refreshVersionSummary(TaxonomyVersion $version): void
    {
        $version->update([
            'summary' => array_merge($version->summary ?? [], [
                'taxonomy_categories' => TaxonomyCategory::query()->where('status', 'active')->count(),
                'normalized_brands' => NormalizedBrand::query()->where('status', 'active')->count(),
                'suggestions_total' => TaxonomyMappingSuggestion::query()->count(),
                'learning_events' => TaxonomyLearningEvent::query()->count(),
                'updated_at' => now()->toISOString(),
            ]),
        ]);
    }

    private function scopedSuggestion(Merchant $merchant, ?MerchantCompany $company, TaxonomyMappingSuggestion $suggestion): TaxonomyMappingSuggestion
    {
        if ((int) $suggestion->merchant_id !== (int) $merchant->id) {
            throw new NotFoundHttpException('Sugestão não encontrada.');
        }

        if ($company && $suggestion->merchant_company_id && (int) $suggestion->merchant_company_id !== (int) $company->id) {
            throw new NotFoundHttpException('Sugestão não encontrada.');
        }

        return $suggestion;
    }

    private function taxonomyMatches(TaxonomyCategory $taxonomy, string $name, string $fingerprint): bool
    {
        return $taxonomy->slug === $this->categories->slug($name)
            || $this->categories->fingerprint($taxonomy->name) === $fingerprint;
    }

    private function inferredTaxonomy(string $name, Collection $taxonomyOptions): ?TaxonomyCategory
    {
        $fingerprint = $this->categories->fingerprint($name);
        $dictionary = [
            'camisa' => 'Camisas',
            'camiseta' => 'Camisetas',
            'blusa' => 'Blusas',
            'casaco' => 'Casacos',
            'calca' => 'Calças',
            'bermuda' => 'Bermudas',
            'short' => 'Shorts',
            'saia' => 'Saias',
            'vestido' => 'Vestidos',
            'macacao' => 'Macacões',
            'conjunto' => 'Conjuntos',
            'tenis' => 'Tênis',
            'sapato' => 'Sapatos',
            'sandalia' => 'Sandálias',
            'bota' => 'Botas',
        ];

        foreach ($dictionary as $needle => $target) {
            if (str_contains($fingerprint, $needle)) {
                return $taxonomyOptions->firstWhere('name', $target);
            }
        }

        return null;
    }

    private function categoryTypeFromName(string $name): string
    {
        $fingerprint = $this->categories->fingerprint($name);

        return match (true) {
            Str::contains($fingerprint, ['camisa', 'camiseta', 'blusa', 'casaco', 'top']) => 'top',
            Str::contains($fingerprint, ['calca', 'bermuda', 'short', 'saia']) => 'bottom',
            Str::contains($fingerprint, ['vestido', 'macacao', 'conjunto']) => 'full_body',
            Str::contains($fingerprint, ['tenis', 'sapato', 'sandalia', 'bota']) => 'shoe',
            default => 'other',
        };
    }

    private function confidenceLevel(float $score): string
    {
        if ($score >= 0.9) {
            return 'high';
        }

        if ($score >= 0.7) {
            return 'medium';
        }

        return 'low';
    }

    private function displayName(string $original, string $fingerprint): string
    {
        $original = trim($original);

        if (Str::upper($original) === $original && strlen($original) <= 8) {
            return $original;
        }

        return Str::of($fingerprint ?: $original)->title()->toString();
    }

    private function topSignal(Collection $values): ?string
    {
        return $values
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): string => (string) $value)
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }

    private function sizeSystemForProducts(Collection $products): string
    {
        $labels = $products
            ->flatMap(fn (Product $product) => $product->variants->pluck('size_label'))
            ->filter()
            ->map(fn (mixed $label): string => Str::of((string) $label)->upper()->trim()->toString())
            ->unique()
            ->values();

        if ($labels->isEmpty()) {
            return 'unknown';
        }

        $numeric = $labels->filter(fn (string $label): bool => preg_match('/^\d{2,3}$/', $label) === 1)->count();
        $alpha = $labels->filter(fn (string $label): bool => in_array($label, ['PP', 'P', 'M', 'G', 'GG', 'XG', 'XGG', 'XS', 'S', 'L', 'XL', 'XXL'], true))->count();

        if ($numeric > 0 && $alpha > 0) {
            return 'mixed';
        }

        if ($numeric > 0) {
            return 'br_numeric';
        }

        if ($alpha > 0) {
            return 'br_alpha';
        }

        return 'custom';
    }

    private function aliasesFor(Collection $products, string $field): array
    {
        return $products
            ->map(fn (Product $product): string => $field === 'brand'
                ? $this->brands->cleanName((string) data_get($product->metadata ?? [], 'brand'))
                : $this->categories->cleanName((string) $product->category))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function candidateMappings(Merchant $merchant, ?MerchantCompany $company): int
    {
        return $this->categoryQuery($merchant, $company)->whereNull('taxonomy_category_id')->count()
            + $this->brandQuery($merchant, $company)->whereNull('normalized_brand_id')->count();
    }

    private function sourceForProducts(Collection $products): string
    {
        $sources = $products->map(fn (Product $product): string => $this->sourceForProduct($product))->unique()->values();

        if ($sources->contains('bigshop')) {
            return 'bigshop';
        }

        if ($sources->contains('import')) {
            return 'import';
        }

        return $sources->first() ?: 'manual';
    }

    private function sourceForProduct(Product $product): string
    {
        $metadata = $product->metadata ?? [];
        $source = data_get($metadata, 'source') ?: data_get($metadata, 'data_source');

        if ($source) {
            return (string) $source;
        }

        if (filled(data_get($metadata, 'bigshop_last_sync_at'))) {
            return 'bigshop';
        }

        if (filled(data_get($metadata, 'last_imported_at'))) {
            return 'import';
        }

        return 'manual';
    }

    private function productQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            });
    }

    private function categoryQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return MerchantCategory::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            });
    }

    private function brandQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return MerchantBrand::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            });
    }

    private function suggestionQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return TaxonomyMappingSuggestion::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            });
    }

    private function learningQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return TaxonomyLearningEvent::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            });
    }
}
