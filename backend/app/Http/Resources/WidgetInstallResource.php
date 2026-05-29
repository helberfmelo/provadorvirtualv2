<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\PlatformCatalog;
use App\Support\WidgetModalCatalog;
use App\Support\WidgetPlacementCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WidgetInstallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scriptUrl = url('/widget/v1/provador-virtual.js');
        $cssUrl = url('/widget/v1/provador-virtual.css');
        $product = $this->sampleProduct();
        $platformGuides = $this->platformGuides($scriptUrl, $cssUrl, $product);
        $platformKey = (string) ($this->platform ?: 'custom');
        $platformGuide = $platformGuides[$platformKey] ?? $platformGuides['custom'];
        $liveState = $this->liveState();
        $draftState = $this->draftState($liveState);

        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'merchant_company_id' => $this->merchant_company_id,
            'public_key' => $this->public_key,
            'platform' => $this->platform,
            'allowed_domains' => $this->allowed_domains ?? [],
            'theme' => $liveState['theme'],
            'is_active' => $this->is_active,
            'draft' => [
                ...$draftState,
                'has_unpublished_changes' => $this->normalizeState($draftState) !== $this->normalizeState($liveState),
            ],
            'published_at' => ($this->published_at ?: $this->updated_at)?->toISOString(),
            'script_url' => $scriptUrl,
            'css_url' => $cssUrl,
            'container_id' => 'provador-virtual-container',
            'snippet' => data_get($platformGuide, 'guide.snippet'),
            'platform_guide' => $platformGuide,
            'platform_guides' => array_values($platformGuides),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'domain' => $this->company?->domain,
                'platform' => $this->company?->platform,
                'external_store_id' => $this->company?->external_store_id,
            ]),
            'sample_product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'external_product_id' => $product->external_product_id,
            ] : null,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function liveState(): array
    {
        return [
            'platform' => $this->platform ?: 'custom',
            'allowed_domains' => $this->allowed_domains ?? [],
            'theme' => $this->themeWithDefaults($this->theme ?? []),
            'is_active' => (bool) $this->is_active,
        ];
    }

    private function draftState(array $liveState): array
    {
        return [
            'platform' => $this->draft_platform ?: $liveState['platform'],
            'allowed_domains' => $this->draft_allowed_domains ?? $liveState['allowed_domains'],
            'theme' => $this->themeWithDefaults($this->draft_theme ?? $liveState['theme']),
            'is_active' => $this->draft_is_active ?? $liveState['is_active'],
        ];
    }

    private function normalizeState(array $state): array
    {
        return [
            'platform' => (string) ($state['platform'] ?? 'custom'),
            'allowed_domains' => collect($state['allowed_domains'] ?? [])->map(fn ($domain) => (string) $domain)->values()->all(),
            'theme' => collect($state['theme'] ?? [])->sortKeys()->all(),
            'is_active' => (bool) ($state['is_active'] ?? false),
        ];
    }

    private function sampleProduct(): ?Product
    {
        return Product::query()
            ->where('merchant_id', $this->merchant_id)
            ->where('status', 'active')
            ->orderByDesc('measurement_table_id')
            ->orderBy('id')
            ->first();
    }

    private function platformGuides(string $scriptUrl, string $cssUrl, ?Product $product): array
    {
        $guides = [];

        foreach (PlatformCatalog::all() as $key => $entry) {
            $guides[$key] = [
                'key' => $key,
                'name' => $entry['name'],
                'icon' => $entry['icon'],
                'summary' => $entry['summary'],
                'install_mode' => $entry['install_mode'],
                'guide' => [
                    'steps' => $this->platformSteps($key, $entry['guide']['steps'] ?? []),
                    'data_support' => $entry['guide']['data_support'] ?? [],
                    'placement_label' => $this->placementLabel($key),
                    'placement_suggestions' => WidgetPlacementCatalog::suggestions($key),
                    'snippet' => $this->snippetForPlatform($key, $scriptUrl, $cssUrl, $product),
                    'reload_snippet' => $this->reloadSnippetForPlatform($key),
                ],
            ];
        }

        return $guides;
    }

    private function snippetForPlatform(string $platform, string $scriptUrl, string $cssUrl, ?Product $product): string
    {
        $theme = htmlspecialchars(json_encode($this->themeWithDefaults($this->theme ?? []), JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $sampleProductId = $product?->external_product_id ?? $product?->id ?? 'ID_DO_PRODUTO';
        $sampleSku = $product?->sku ?? 'SKU_DO_PRODUTO';

        return match ($platform) {
            'bigshop' => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => $this->company?->external_store_id ?: 'BIGSHOP_STORE_ID',
                'product_id' => 'BIGSHOP_PRODUCT_ID',
                'variant_id' => 'BIGSHOP_VARIANT_ID',
                'sku' => 'SKU_DA_GRADE',
                'platform' => 'bigshop',
            ]),
            'shopify' => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => '{{ shop.permanent_domain }}',
                'product_id' => '{{ product.id }}',
                'variant_id' => '{{ product.selected_or_first_available_variant.id }}',
                'sku' => '{{ product.selected_or_first_available_variant.sku }}',
                'platform' => 'shopify',
            ]),
            'woocommerce' => implode("\n", [
                '<?php global $product; ?>',
                $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                    'store_id' => '<?php echo esc_attr(home_url()); ?>',
                    'product_id' => '<?php echo esc_attr($product->get_id()); ?>',
                    'variant_id' => '',
                    'sku' => '<?php echo esc_attr($product->get_sku()); ?>',
                    'platform' => 'woocommerce',
                ]),
            ]),
            'nuvemshop' => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => '{{ store.domain }}',
                'product_id' => '{{ product.id }}',
                'variant_id' => '{{ product.selected_or_first_available_variant.id | default(product.variants[0].id) }}',
                'sku' => '{{ product.selected_or_first_available_variant.sku | default(product.sku) }}',
                'platform' => 'nuvemshop',
            ]),
            'vtex' => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => 'VTEX_ACCOUNT_OR_STORE_ID',
                'product_id' => 'PRODUCT_CONTEXT_PRODUCT_ID',
                'variant_id' => 'PRODUCT_CONTEXT_SELECTED_ITEM_ID',
                'sku' => 'PRODUCT_CONTEXT_REF_ID_OR_SKU',
                'platform' => 'vtex',
            ]),
            'tray' => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => '{{ store.id | default(store.name) }}',
                'product_id' => '{{ product.id }}',
                'variant_id' => '{{ selected_variant.id | default(product.id) }}',
                'sku' => '{{ selected_variant.reference | default(product.reference) | default(product.id) }}',
                'platform' => 'tray',
            ]),
            'loja_integrada' => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => 'LOJA_INTEGRADA_DOMINIO_OU_ID',
                'product_id' => 'ID_DO_PRODUTO_NA_PAGINA',
                'variant_id' => 'ID_DA_VARIACAO_ESCOLHIDA',
                'sku' => 'SKU_OU_REFERENCIA_DO_PRODUTO',
                'platform' => 'loja_integrada',
            ]),
            'magento' => implode("\n", [
                '<?php $product = $block->getProduct(); ?>',
                $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                    'store_id' => '<?php echo $block->escapeHtmlAttr($block->getBaseUrl()); ?>',
                    'product_id' => '<?php echo (int) $product->getId(); ?>',
                    'variant_id' => '',
                    'sku' => '<?php echo $block->escapeHtmlAttr($product->getSku()); ?>',
                    'platform' => 'magento',
                ]),
            ]),
            'opencart' => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => '{{ store }}',
                'product_id' => '{{ product_id }}',
                'variant_id' => "{{ option_value_id|default('') }}",
                'sku' => '{{ sku|default(model) }}',
                'platform' => 'opencart',
            ]),
            'xml_feed' => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => $this->company?->domain ?: 'DOMINIO_OU_STORE_ID',
                'product_id' => 'FEED_PRODUCT_ID',
                'variant_id' => 'FEED_VARIANT_ID',
                'sku' => 'SKU_DA_VARIANTE',
                'platform' => 'xml_feed',
            ]),
            'api' => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => $this->company?->external_store_id ?: 'API_STORE_ID',
                'product_id' => 'API_PRODUCT_ID',
                'variant_id' => 'API_VARIANT_ID',
                'sku' => 'SKU_DA_VARIANTE',
                'platform' => 'api',
            ]),
            default => $this->scriptSnippet($scriptUrl, $cssUrl, $theme, [
                'store_id' => $this->merchant_company_id ?: 'STORE_ID',
                'product_id' => $sampleProductId,
                'variant_id' => 'ID_DA_VARIACAO',
                'sku' => $sampleSku,
                'platform' => 'custom',
            ]),
        };
    }

    private function scriptSnippet(string $scriptUrl, string $cssUrl, string $theme, array $values): string
    {
        $attributes = [
            'id' => 'provadorVirtualScript',
            'src' => $scriptUrl,
            'data-public-key' => $this->public_key,
            'data-merchant-id' => (string) $this->merchant_id,
            'data-store-id' => (string) $values['store_id'],
            'data-product-id' => (string) $values['product_id'],
            'data-variant-id' => (string) $values['variant_id'],
            'data-sku' => (string) $values['sku'],
            'data-platform' => (string) $values['platform'],
            'data-container-id' => 'provador-virtual-container',
            'data-css-url' => $cssUrl,
            'data-theme' => $theme,
        ];

        $lines = ['<div id="provador-virtual-container"></div>', '<script'];

        foreach ($attributes as $attribute => $value) {
            if ($value === '') {
                continue;
            }

            $lines[] = '  '.$attribute.'="'.$value.'"';
        }

        $lines[] = '  defer></script>';

        return implode("\n", $lines);
    }

    private function platformSteps(string $platform, array $fallbackSteps): array
    {
        return match ($platform) {
            'bigshop' => [
                'Use a instalação nativa quando o app BigShop estiver liberado; como fallback, cole o container na PDP da model3 pro, junto da grade.',
                'Preencha store-id, produto, grade e SKU com os valores reais expostos pelo produto.vue.',
                'Ao trocar grade/tamanho, chame reload com o novo productId, variantId e sku antes do cliente abrir o provador.',
                'Mantenha o domínio da loja cadastrado em Domínios liberados antes de publicar.',
            ],
            'shopify' => [
                'Cole o container no template/section de produto Liquid, perto do seletor de variantes e antes do botão Comprar.',
                'Use product.id, selected_or_first_available_variant.id e selected_or_first_available_variant.sku no snippet.',
                'Se o tema troca variante sem recarregar a página, dispare reload no evento de mudança de variante.',
                'Publique primeiro em uma cópia do tema e valide o domínio publicado.',
            ],
            'woocommerce' => [
                'Insira o container no hook woocommerce_before_add_to_cart_button ou no template single-product/add-to-cart.',
                'Use $product->get_id(), $product->get_sku() e o evento found_variation para variações.',
                'Quando found_variation retornar variation_id ou sku, chame reload para atualizar o provador.',
                'Teste produtos simples e variáveis antes de publicar no tema ativo.',
            ],
            'nuvemshop' => [
                'Cole o container no product.tpl ou snipplet equivalente da página de produto.',
                'Use as variáveis do produto e da variante selecionada disponíveis no tema.',
                'Atualize variantId e sku quando o comprador trocar a opção de tamanho/cor.',
                'Valide em tema duplicado e cadastre o domínio final no painel.',
            ],
            'vtex' => [
                'Instale dentro do template store.product, no bloco/área da coluna de compra da PDP.',
                'Leia productId, selectedItem.itemId e refId/SKU a partir do contexto de produto da VTEX.',
                'Em lojas VTEX IO, prefira encapsular o snippet em um bloco customizado que receba o product context.',
                'Recarregue o provador quando a seleção de SKU mudar.',
            ],
            'tray' => [
                'Edite o template de produto da Tray e posicione o container perto de productHelper.variants() e antes de productHelper.form().',
                'Use product.id e, quando houver variação, o id/reference da variação selecionada.',
                'Se o tema só expõe a variação por JavaScript, preencha os atributos no evento de seleção e chame reload.',
                'Rode a validação em produto com grade e tabela vinculada.',
            ],
            'loja_integrada' => [
                'Use o editor do tema ou o campo de HTML/JavaScript personalizado da página de produto.',
                'Posicione o container perto do seletor de variação/tamanho do tema.',
                'Mapeie produto, variação e SKU a partir do DOM ou das variáveis disponíveis no tema.',
                'Chame reload sempre que o comprador alterar a variação.',
            ],
            'magento' => [
                'Adicione o bloco no layout XML da página catalog_product_view ou no template de produto do tema.',
                'Use $block->getProduct() para produto e SKU base.',
                'Para configuráveis, atualize variantId/sku via JavaScript quando a opção simples mudar.',
                'Limpe cache, valide CSP e publique em staging antes de produção.',
            ],
            'opencart' => [
                'Edite catalog/view/theme/{tema}/template/product/product.twig.',
                'Posicione o container antes do botão de compra e após as opções de tamanho.',
                'Use product_id e model/sku; se o SKU não estiver no template, exponha-o no controller.',
                'Atualize option_value_id ou SKU no evento de opção selecionada e chame reload.',
            ],
            'xml_feed' => [
                'Mantenha o feed público sincronizado com os mesmos IDs usados na página de produto.',
                'Cole o container perto da seleção de tamanho e use FEED_PRODUCT_ID, FEED_VARIANT_ID e SKU_DA_VARIANTE no snippet.',
                'Quando o comprador trocar a variação, atualize os IDs para bater com o item do XML/feed.',
                'Sincronize o catálogo no portal antes de validar o widget na loja.',
            ],
            'api' => [
                'Use no snippet os mesmos IDs retornados pela API autorizada do cliente.',
                'Cole o container na PDP e atualize produto, variação e SKU quando a seleção mudar.',
                'Antes de publicar, valide que o conector API importou os produtos e que cada produto tem tabela revisada.',
                'Webhooks de pedido/devolução exigem segredo salvo e nunca devem aparecer no HTML.',
            ],
            default => $fallbackSteps,
        };
    }

    private function placementLabel(string $platform): string
    {
        return match ($platform) {
            'shopify' => 'Template Liquid de produto',
            'woocommerce' => 'Hook da página de produto',
            'nuvemshop' => 'product.tpl',
            'vtex' => 'store.product',
            'tray' => 'Template de produto Tray',
            'loja_integrada' => 'Editor HTML/JS do tema',
            'magento' => 'catalog_product_view',
            'opencart' => 'product.twig',
            'xml_feed' => 'PDP usando IDs do feed',
            'api' => 'PDP usando IDs da API',
            'bigshop' => 'produto.vue / PDP BigShop',
            default => 'Página de produto',
        };
    }

    private function reloadSnippetForPlatform(string $platform): string
    {
        return match ($platform) {
            'shopify' => <<<'JS'
document.addEventListener('variant:change', function (event) {
  const variant = event.detail?.variant
  if (!variant) return

  window.ProvadorVirtual?.reload({
    productId: '{{ product.id }}',
    variantId: String(variant.id),
    sku: variant.sku || ''
  })
})
JS,
            'woocommerce' => <<<'JS'
jQuery(function ($) {
  $('form.variations_form').on('found_variation', function (_, variation) {
    window.ProvadorVirtual?.reload({
      productId: '<?php echo esc_js($product->get_id()); ?>',
      variantId: String(variation.variation_id || ''),
      sku: variation.sku || ''
    })
  })
})
JS,
            'nuvemshop' => <<<'JS'
LS.registerOnChangeVariant(function (variant) {
  window.ProvadorVirtual?.reload({
    productId: '{{ product.id }}',
    variantId: String(variant?.id || ''),
    sku: String(variant?.sku || '')
  })
})
JS,
            'vtex' => <<<'JS'
window.ProvadorVirtual?.reload({
  productId: 'productContext.product.productId',
  variantId: 'productContext.selectedItem.itemId',
  sku: 'productContext.selectedItem.referenceId[0].Value'
})
JS,
            'tray' => <<<'JS'
document.addEventListener('change', function (event) {
  if (!event.target.closest('.product-variations')) return

  window.ProvadorVirtual?.reload({
    productId: '{{ product.id }}',
    variantId: String(window.selectedVariant?.id || ''),
    sku: String(window.selectedVariant?.reference || '')
  })
})
JS,
            'magento' => <<<'JS'
document.addEventListener('change', function () {
  window.ProvadorVirtual?.reload({
    productId: String(window.PRODUCT_ID || ''),
    variantId: String(window.SELECTED_SIMPLE_PRODUCT_ID || ''),
    sku: String(window.SELECTED_SIMPLE_PRODUCT_SKU || '')
  })
})
JS,
            'opencart' => <<<'JS'
document.querySelectorAll('#product input, #product select').forEach(function (field) {
  field.addEventListener('change', function () {
    window.ProvadorVirtual?.reload({
      productId: '{{ product_id }}',
      variantId: String(field.value || ''),
      sku: '{{ sku|default(model) }}'
    })
  })
})
JS,
            default => <<<'JS'
window.ProvadorVirtual?.reload({
  productId: 'ID_DO_PRODUTO',
  variantId: 'ID_DA_VARIACAO',
  sku: 'SKU_DA_VARIACAO'
})
JS,
        };
    }

    private function themeWithDefaults(array $theme): array
    {
        $theme['presentation_mode'] = in_array($theme['presentation_mode'] ?? null, ['drawer', 'modal'], true)
            ? $theme['presentation_mode']
            : 'drawer';
        $theme['modal'] = WidgetModalCatalog::normalize(
            is_array($theme['modal'] ?? null) ? $theme['modal'] : null
        );
        $theme['placement'] = WidgetPlacementCatalog::normalize(
            is_array($theme['placement'] ?? null) ? $theme['placement'] : null
        );

        return $theme;
    }
}
