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
                summary: 'Integracao preferencial com ativacao assinada, sync de produtos e caminho para instalacao nativa.',
                steps: [
                    'No painel da BigShop, autorize o app Provador Virtual ou informe store_id e token x-api.',
                    'Teste a conexao e sincronize produtos, grades e tabelas disponiveis.',
                    'Revise as tabelas de medidas e publique o widget no tema da loja.',
                    'Valide a pagina de produto com os botoes Descubra seu tamanho e Tabela de Medidas.',
                ],
                dataSupport: [
                    'product_id' => 'Nativo via API V3',
                    'variant_id' => 'Nativo por grade',
                    'sku' => 'Nativo por grade/SKU',
                    'size_change' => 'Nativo no front BigShop',
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
                summary: 'Instalacao por tema Liquid na pagina de produto, usando produto, variante e SKU selecionados.',
                steps: [
                    'Abra o tema Shopify e edite o template principal de produto.',
                    'Cole o container perto do seletor de tamanho ou botao Comprar.',
                    'Use product.id, selected_or_first_available_variant.id e SKU da variante no snippet.',
                    'Publique o tema e rode a validacao nesta tela.',
                ],
                dataSupport: [
                    'product_id' => 'Liquid product.id',
                    'variant_id' => 'Liquid variant.id',
                    'sku' => 'Liquid variant.sku',
                    'size_change' => 'JS no tema',
                    'feed_api' => 'Manual/API futura',
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
                summary: 'Instalacao por hook/shortcode no template de produto do WordPress/WooCommerce.',
                steps: [
                    'Adicione o container no hook woocommerce_before_add_to_cart_button.',
                    'Use o ID do produto e SKU do WooCommerce como identificadores.',
                    'Quando houver variacao, atualize data-variant-id e data-sku via evento found_variation.',
                    'Cadastre o dominio no Widget e valide a pagina publicada.',
                ],
                dataSupport: [
                    'product_id' => 'PHP $product->get_id()',
                    'variant_id' => 'JS found_variation',
                    'sku' => 'PHP/JS get_sku',
                    'size_change' => 'JS WooCommerce',
                    'feed_api' => 'CSV/XML/API futura',
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
                summary: 'Instalacao no layout da pagina de produto usando identificadores da variante ativa.',
                steps: [
                    'Edite o layout da pagina de produto no editor de codigo da loja.',
                    'Insira o container junto do seletor de tamanho.',
                    'Preencha produto, variante e SKU com as variaveis disponiveis no template.',
                    'Atualize os atributos quando o comprador trocar a variante.',
                ],
                dataSupport: [
                    'product_id' => 'Template produto',
                    'variant_id' => 'Template/JS variante',
                    'sku' => 'Template/JS SKU',
                    'size_change' => 'JS da loja',
                    'feed_api' => 'Manual/API futura',
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
                summary: 'Instalacao por bloco/app de storefront ou tag no template da PDP.',
                steps: [
                    'Adicione o container no bloco de produto da PDP.',
                    'Passe productId, selectedItem.itemId e refId/SKU quando disponiveis.',
                    'Garanta que a troca de SKU atualize os atributos do script.',
                    'Valide em ambiente de homologacao antes da publicacao.',
                ],
                dataSupport: [
                    'product_id' => 'productId',
                    'variant_id' => 'selectedItem.itemId',
                    'sku' => 'referenceId/SKU',
                    'size_change' => 'Storefront event',
                    'feed_api' => 'API futura',
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
                summary: 'Instalacao por edicao de tema na pagina do produto, usando SKU e ID da variacao.',
                steps: [
                    'Abra o editor de tema Tray e localize o template de produto.',
                    'Cole o container perto das opcoes de tamanho.',
                    'Mapeie produto, variacao e SKU usando as variaveis do tema.',
                    'Publique e rode a validacao do snippet instalado.',
                ],
                dataSupport: [
                    'product_id' => 'Template produto',
                    'variant_id' => 'Variacao/grade',
                    'sku' => 'SKU do produto/grade',
                    'size_change' => 'JS do tema',
                    'feed_api' => 'CSV/XML/API futura',
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
                summary: 'Instalacao por HTML/JS personalizado no tema da pagina de produto.',
                steps: [
                    'Adicione o container pelo editor de tema ou campo de scripts da pagina de produto.',
                    'Mapeie ID do produto e SKU usando as variaveis/atributos disponiveis no tema.',
                    'Atualize os atributos do widget quando a variacao for alterada.',
                    'Teste em produto com tabela ativa antes de publicar em todos os produtos.',
                ],
                dataSupport: [
                    'product_id' => 'Template/DOM',
                    'variant_id' => 'JS da variacao',
                    'sku' => 'Template/DOM',
                    'size_change' => 'JS custom',
                    'feed_api' => 'Manual/feed futuro',
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
                summary: 'Instalacao por bloco/template de produto, compativel com Magento/Open Source e Adobe Commerce.',
                steps: [
                    'Crie um bloco no template catalog_product_view.',
                    'Passe product id, selected configurable option e SKU.',
                    'Atualize o snippet quando a opcao configuravel mudar.',
                    'Valide cache, CSP e dominio liberado no painel do widget.',
                ],
                dataSupport: [
                    'product_id' => 'Product ID',
                    'variant_id' => 'Simple product/option',
                    'sku' => 'Product SKU',
                    'size_change' => 'JS configurable',
                    'feed_api' => 'API futura',
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
                summary: 'Instalacao por template Twig da pagina de produto e JS de opcao/tamanho.',
                steps: [
                    'Edite o template product/product.twig do tema ativo.',
                    'Inclua o container antes do botao de compra.',
                    'Use product_id, SKU/model e opcao selecionada como variante.',
                    'Limpe o cache de tema e valide a pagina publicada.',
                ],
                dataSupport: [
                    'product_id' => 'product_id',
                    'variant_id' => 'Opcao selecionada',
                    'sku' => 'SKU/model',
                    'size_change' => 'JS custom',
                    'feed_api' => 'Manual/API futura',
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
                summary: 'Snippet universal para qualquer loja que consiga expor produto, variacao e SKU.',
                steps: [
                    'Cole o container perto do seletor de tamanho ou botao comprar.',
                    'Preencha data-product-id, data-variant-id e data-sku com dados reais da loja.',
                    'Atualize os atributos por JS quando a variante mudar.',
                    'Cadastre o dominio no Widget e rode a validacao.',
                ],
                dataSupport: [
                    'product_id' => 'Manual',
                    'variant_id' => 'Manual/JS',
                    'sku' => 'Manual/JS',
                    'size_change' => 'JS custom',
                    'feed_api' => 'CSV/XML',
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
            ['key' => 'domain_configured', 'label' => 'Dominio cadastrado no widget'],
            ['key' => 'page_reachable', 'label' => 'Pagina de produto publicada'],
            ['key' => 'container_found', 'label' => 'Container do Provador Virtual encontrado'],
            ['key' => 'script_found', 'label' => 'Script do widget carregado'],
            ['key' => 'platform_hint', 'label' => 'Plataforma informada no snippet'],
            ['key' => 'product_identifiers', 'label' => 'Produto, variacao ou SKU informados'],
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
