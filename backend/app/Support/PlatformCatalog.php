<?php

namespace App\Support;

class PlatformCatalog
{
    public static function all(): array
    {
        return [
            'bigshop' => self::entry(
                key: 'bigshop',
                name: 'BigShop',
                icon: 'fa-bolt',
                installMode: 'one_click',
                priority: true,
                summary: 'Integração preferencial com ativação assinada, sync de produtos por API ou XML e caminho para instalação nativa.',
                steps: [
                    'No painel da BigShop, autorize o app Provador Virtual ou informe store_id, token x-api e/ou URL do XML domínio/feed.xml.',
                    'Teste a conexão e sincronize produtos, grades e tabelas disponíveis por API ou XML.',
                    'Revise as tabelas de medidas e publique o widget no tema da loja.',
                    'Valide a página de produto com os botões Descubra seu tamanho e Tabela de Medidas.',
                ],
                dataSupport: [
                    'product_id' => 'Nativo via API V3',
                    'variant_id' => 'Nativo por grade',
                    'sku' => 'Nativo por grade/SKU',
                    'size_change' => 'Nativo no front BigShop',
                    'xml_feed' => 'Nativo domínio/feed.xml',
                    'feed_api' => 'Sync API',
                    'orders_returns' => 'Pendente contrato BigShop',
                ],
                snippet: self::snippet('bigshop', 'BIGSHOP_PRODUCT_ID', 'BIGSHOP_VARIANT_ID', 'SKU_DA_GRADE')
            ),
            'shopify' => self::entry(
                key: 'shopify',
                name: 'Shopify',
                icon: 'fa-bag-shopping',
                installMode: 'manual',
                priority: false,
                summary: 'Instalação por tema Liquid na página de produto, usando produto, variante e SKU selecionados.',
                steps: [
                    'Abra o tema Shopify e edite o template principal de produto.',
                    'Cole o container perto do seletor de tamanho ou botão Comprar.',
                    'Use product.id, selected_or_first_available_variant.id e SKU da variante no snippet.',
                    'Publique o tema e rode a validação nesta tela.',
                ],
                dataSupport: [
                    'product_id' => 'Liquid product.id',
                    'variant_id' => 'Liquid variant.id',
                    'sku' => 'Liquid variant.sku',
                    'size_change' => 'JS no tema',
                    'xml_feed' => 'Merchant/feed por app',
                    'feed_api' => 'Admin API/app',
                    'orders_returns' => 'Pendente app/API',
                ],
                snippet: <<<'HTML'
<div id="provador-virtual-container"></div>
<script
  id="provadorVirtualScript"
  src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js"
  data-platform="shopify"
  data-store-id="{{ shop.permanent_domain }}"
  data-product-id="{{ product.id }}"
  data-variant-id="{{ product.selected_or_first_available_variant.id }}"
  data-sku="{{ product.selected_or_first_available_variant.sku }}"
  data-container-id="provador-virtual-container"
  defer></script>
HTML
            ),
            'woocommerce' => self::entry(
                key: 'woocommerce',
                name: 'WooCommerce',
                icon: 'fa-cart-shopping',
                installMode: 'manual',
                priority: false,
                summary: 'Instalação por hook/shortcode no template de produto do WordPress/WooCommerce.',
                steps: [
                    'Adicione o container no hook woocommerce_before_add_to_cart_button.',
                    'Use o ID do produto e SKU do WooCommerce como identificadores.',
                    'Quando houver variação, atualize data-variant-id e data-sku via evento found_variation.',
                    'Cadastre o domínio no Widget e valide a página publicada.',
                ],
                dataSupport: [
                    'product_id' => 'PHP $product->get_id()',
                    'variant_id' => 'JS found_variation',
                    'sku' => 'PHP/JS get_sku',
                    'size_change' => 'JS WooCommerce',
                    'xml_feed' => 'Google XML via plugin',
                    'feed_api' => 'REST API',
                    'orders_returns' => 'Pendente plugin/API',
                ],
                snippet: <<<'HTML'
<div id="provador-virtual-container"></div>
<script
  id="provadorVirtualScript"
  src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js"
  data-platform="woocommerce"
  data-store-id="<?php echo esc_attr(home_url()); ?>"
  data-product-id="<?php echo esc_attr($product->get_id()); ?>"
  data-sku="<?php echo esc_attr($product->get_sku()); ?>"
  data-container-id="provador-virtual-container"
  defer></script>
HTML
            ),
            'nuvemshop' => self::entry(
                key: 'nuvemshop',
                name: 'Nuvemshop',
                icon: 'fa-cloud',
                installMode: 'manual',
                priority: false,
                summary: 'Instalação no layout da página de produto usando identificadores da variante ativa.',
                steps: [
                    'Edite o layout da página de produto no editor de código da loja.',
                    'Insira o container junto do seletor de tamanho.',
                    'Preencha produto, variante e SKU com as variáveis disponíveis no template.',
                    'Atualize os atributos quando o comprador trocar a variante.',
                ],
                dataSupport: [
                    'product_id' => 'Template produto',
                    'variant_id' => 'Template/JS variante',
                    'sku' => 'Template/JS SKU',
                    'size_change' => 'JS da loja',
                    'xml_feed' => 'Feed/canal quando habilitado',
                    'feed_api' => 'Catalog API',
                    'orders_returns' => 'Pendente API',
                ],
                snippet: self::snippet('nuvemshop', 'PRODUCT_ID', 'VARIANT_ID', 'SKU_DA_VARIANTE')
            ),
            'vtex' => self::entry(
                key: 'vtex',
                name: 'VTEX',
                icon: 'fa-layer-group',
                installMode: 'manual',
                priority: false,
                summary: 'Instalação por bloco/app de storefront ou tag no template da PDP.',
                steps: [
                    'Adicione o container no bloco de produto da PDP.',
                    'Passe productId, selectedItem.itemId e refId/SKU quando disponíveis.',
                    'Garanta que a troca de SKU atualize os atributos do script.',
                    'Valide em ambiente de homologação antes da publicação.',
                ],
                dataSupport: [
                    'product_id' => 'productId',
                    'variant_id' => 'selectedItem.itemId',
                    'sku' => 'referenceId/SKU',
                    'size_change' => 'Storefront event',
                    'xml_feed' => 'Feed por app/export',
                    'feed_api' => 'Catalog API',
                    'orders_returns' => 'Pendente OMS/API',
                ],
                snippet: self::snippet('vtex', 'PRODUCT_ID', 'ITEM_ID', 'SKU_OU_REFID')
            ),
            'tray' => self::entry(
                key: 'tray',
                name: 'Tray',
                icon: 'fa-store',
                installMode: 'manual',
                priority: false,
                summary: 'Instalação por edição de tema na página do produto, usando SKU e ID da variação.',
                steps: [
                    'Abra o editor de tema Tray e localize o template de produto.',
                    'Cole o container perto das opções de tamanho.',
                    'Mapeie produto, variação e SKU usando as variáveis do tema.',
                    'Publique e rode a validação do snippet instalado.',
                ],
                dataSupport: [
                    'product_id' => 'Template produto',
                    'variant_id' => 'Variação/grade',
                    'sku' => 'SKU do produto/grade',
                    'size_change' => 'JS do tema',
                    'xml_feed' => 'Feed/marketplace quando habilitado',
                    'feed_api' => 'Tray API',
                    'orders_returns' => 'Pendente API',
                ],
                snippet: self::snippet('tray', 'PRODUCT_ID', 'VARIANT_ID', 'SKU_DA_GRADE')
            ),
            'loja_integrada' => self::entry(
                key: 'loja_integrada',
                name: 'Loja Integrada',
                icon: 'fa-shop',
                installMode: 'manual',
                priority: false,
                summary: 'Instalação por HTML/JS personalizado no tema da página de produto.',
                steps: [
                    'Adicione o container pelo editor de tema ou campo de scripts da página de produto.',
                    'Mapeie ID do produto e SKU usando as variáveis/atributos disponíveis no tema.',
                    'Atualize os atributos do widget quando a variação for alterada.',
                    'Teste em produto com tabela ativa antes de publicar em todos os produtos.',
                ],
                dataSupport: [
                    'product_id' => 'Template/DOM',
                    'variant_id' => 'JS da variação',
                    'sku' => 'Template/DOM',
                    'size_change' => 'JS custom',
                    'xml_feed' => 'Feed/exportação quando habilitado',
                    'feed_api' => 'API/feed',
                    'orders_returns' => 'Pendente API',
                ],
                snippet: self::snippet('loja_integrada', 'PRODUCT_ID', 'VARIANT_ID', 'SKU')
            ),
            'magento' => self::entry(
                key: 'magento',
                name: 'Magento',
                icon: 'fa-cubes',
                installMode: 'manual',
                priority: false,
                summary: 'Instalação por bloco/template de produto, compatível com Magento/Open Source e Adobe Commerce.',
                steps: [
                    'Crie um bloco no template catalog_product_view.',
                    'Passe product id, selected configurable option e SKU.',
                    'Atualize o snippet quando a opção configurável mudar.',
                    'Valide cache, CSP e domínio liberado no painel do widget.',
                ],
                dataSupport: [
                    'product_id' => 'Product ID',
                    'variant_id' => 'Simple product/option',
                    'sku' => 'Product SKU',
                    'size_change' => 'JS configurable',
                    'xml_feed' => 'Google XML via módulo',
                    'feed_api' => 'REST API',
                    'orders_returns' => 'Pendente API',
                ],
                snippet: self::snippet('magento', 'PRODUCT_ID', 'VARIANT_ID', 'SKU')
            ),
            'opencart' => self::entry(
                key: 'opencart',
                name: 'OpenCart',
                icon: 'fa-box-open',
                installMode: 'manual',
                priority: false,
                summary: 'Instalação por template Twig da página de produto e JS de opção/tamanho.',
                steps: [
                    'Edite o template product/product.twig do tema ativo.',
                    'Inclua o container antes do botão de compra.',
                    'Use product_id, SKU/model e opção selecionada como variante.',
                    'Limpe o cache de tema e valide a página publicada.',
                ],
                dataSupport: [
                    'product_id' => 'product_id',
                    'variant_id' => 'Opção selecionada',
                    'sku' => 'SKU/model',
                    'size_change' => 'JS custom',
                    'xml_feed' => 'Google Base/Sitemap',
                    'feed_api' => 'API/módulo',
                    'orders_returns' => 'Pendente API',
                ],
                snippet: self::snippet('opencart', '{{ product_id }}', 'OPTION_VALUE_ID', '{{ sku }}')
            ),
            'custom' => self::entry(
                key: 'custom',
                name: 'Personalizada',
                icon: 'fa-code',
                installMode: 'manual',
                priority: false,
                summary: 'Snippet universal para qualquer loja que consiga expor produto, variação e SKU.',
                steps: [
                    'Cole o container perto do seletor de tamanho ou botão comprar.',
                    'Preencha data-product-id, data-variant-id e data-sku com dados reais da loja.',
                    'Atualize os atributos por JS quando a variante mudar.',
                    'Cadastre o domínio no Widget e rode a validação.',
                ],
                dataSupport: [
                    'product_id' => 'Manual',
                    'variant_id' => 'Manual/JS',
                    'sku' => 'Manual/JS',
                    'size_change' => 'JS custom',
                    'xml_feed' => 'Google XML/RSS/Atom',
                    'feed_api' => 'CSV/XML/API',
                    'orders_returns' => 'API futura',
                ],
                snippet: self::snippet('custom', 'PRODUCT_ID', 'VARIANT_ID', 'SKU')
            ),
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function find(string $platform): ?array
    {
        return self::all()[$platform] ?? null;
    }

    private static function entry(
        string $key,
        string $name,
        string $icon,
        string $installMode,
        bool $priority,
        string $summary,
        array $steps,
        array $dataSupport,
        string $snippet
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'priority' => $priority,
            'icon' => $icon,
            'install_mode' => $installMode,
            'status' => 'draft',
            'summary' => $summary,
            'guide' => [
                'steps' => $steps,
                'snippet' => $snippet,
                'checklist' => self::checklist(),
                'data_support' => $dataSupport,
            ],
        ];
    }

    private static function checklist(): array
    {
        return [
            ['key' => 'domain_configured', 'label' => 'Domínio cadastrado no widget'],
            ['key' => 'page_reachable', 'label' => 'Página de produto publicada'],
            ['key' => 'container_found', 'label' => 'Container do Provador Virtual encontrado'],
            ['key' => 'script_found', 'label' => 'Script do widget carregado'],
            ['key' => 'platform_hint', 'label' => 'Plataforma informada no snippet'],
            ['key' => 'product_identifiers', 'label' => 'Produto, variação ou SKU informados'],
        ];
    }

    private static function snippet(string $platform, string $productId, string $variantId, string $sku): string
    {
        return <<<HTML
<div id="provador-virtual-container"></div>
<script
  id="provadorVirtualScript"
  src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js"
  data-platform="{$platform}"
  data-store-id="STORE_ID"
  data-product-id="{$productId}"
  data-variant-id="{$variantId}"
  data-sku="{$sku}"
  data-container-id="provador-virtual-container"
  defer></script>
HTML;
    }
}
