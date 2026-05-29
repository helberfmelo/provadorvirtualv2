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
                    'Revise as tabelas de medidas e publique o widget na página de produto, perto do seletor de tamanho/grade.',
                    'Quando o comprador trocar a grade, atualize produto, variação e SKU e recarregue o widget.',
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
                    'Quando a variante mudar, atualize os dados do snippet e recarregue o widget.',
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
                    'Quando houver variação, atualize data-variant-id e data-sku via evento found_variation e recarregue o widget.',
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
                    'Atualize os atributos e recarregue o widget quando o comprador trocar a variante.',
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
                    'Garanta que a troca de SKU atualize os atributos do script e recarregue o widget.',
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
                    'Recarregue o widget quando a escolha de tamanho/grade mudar.',
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
                    'Atualize os atributos e recarregue o widget quando a variação for alterada.',
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
                    'Atualize o snippet e recarregue o widget quando a opção configurável mudar.',
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
                    'Recarregue o widget quando a opção selecionada mudar.',
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
            'xml_feed' => self::entry(
                key: 'xml_feed',
                name: 'XML/feed',
                icon: 'fa-file-code',
                installMode: 'manual',
                priority: false,
                summary: 'Importação por catálogo Google XML, RSS ou feed público quando a plataforma não tem API pronta.',
                steps: [
                    'Confirme que o feed público traz produto, variação, SKU, tamanho, cor, categoria, marca, gênero e imagem quando disponíveis.',
                    'Informe a URL do XML/feed e salve a integração antes da primeira sincronização.',
                    'Rode a sincronização, revise produtos sem tamanho e vincule tabelas de medidas antes de publicar o provador.',
                    'Instale o snippet na página de produto usando produto, variação e SKU compatíveis com o feed.',
                ],
                dataSupport: [
                    'product_id' => 'g:item_group_id ou g:id',
                    'variant_id' => 'g:id',
                    'sku' => 'g:mpn ou g:id',
                    'size_change' => 'JS da loja',
                    'xml_feed' => 'Fonte principal',
                    'feed_api' => 'Não exige API',
                    'orders_returns' => 'CSV/API futura',
                ],
                snippet: self::snippet('xml_feed', 'FEED_PRODUCT_ID', 'FEED_VARIANT_ID', 'SKU_DA_VARIANTE')
            ),
            'api' => self::entry(
                key: 'api',
                name: 'API',
                icon: 'fa-code-branch',
                installMode: 'manual',
                priority: false,
                summary: 'Integração por API própria do lojista ou conector customizado, com credenciais salvas de forma criptografada.',
                steps: [
                    'Informe a base da API, identificador da loja e token de leitura fornecido pelo cliente.',
                    'Mapeie produto, variação, SKU, tamanho, categoria, marca, gênero e imagem para o contrato canônico do Provador.',
                    'Instale o widget na página de produto usando os mesmos identificadores retornados pela API.',
                    'Configure webhooks assinados somente quando a loja puder enviar eventos de pedido/devolução com segurança.',
                ],
                dataSupport: [
                    'product_id' => 'Endpoint de produtos',
                    'variant_id' => 'Endpoint de variações',
                    'sku' => 'Campo SKU da API',
                    'size_change' => 'JS da loja',
                    'xml_feed' => 'Opcional fora desta opção',
                    'feed_api' => 'Fonte principal',
                    'orders_returns' => 'Webhook/API futura',
                ],
                snippet: self::snippet('api', 'API_PRODUCT_ID', 'API_VARIANT_ID', 'SKU_DA_VARIANTE')
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
                    'Atualize os atributos por JS e recarregue o widget quando a variante mudar.',
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
            'setup' => self::setup($key),
            'guide' => [
                'steps' => $steps,
                'snippet' => $snippet,
                'checklist' => self::checklist(),
                'data_support' => $dataSupport,
                'api_examples' => self::apiExamples($key),
                'webhook' => self::webhookGuide($key),
                'gtm' => self::gtmGuide($key, $installMode),
            ],
        ];
    }

    private static function setup(string $key): array
    {
        $setup = match ($key) {
            'bigshop' => [
                'connection_fields' => ['Store ID BigShop', 'Token x-api', 'URL da API V3', 'URL XML/feed'],
                'catalog_flow' => 'Ler produtos pela API V3, cruzar product_grids por produto e complementar com Google XML/feed quando existir.',
                'product_page' => 'Instalação nativa um clique ou snippet no produto.vue/model3 pro, perto do seletor de tamanho.',
                'tracking' => 'Pedidos, trocas e devoluções entram por contrato BigShop/API quando habilitado para aprendizado.',
            ],
            'shopify' => [
                'connection_fields' => ['Domínio permanente da loja', 'Admin/API token ou app', 'Posição da opção de tamanho', 'Feed opcional'],
                'catalog_flow' => 'Catálogo por Admin API/app ou XML; gênero, faixa etária e modelagem devem vir por tags, metafields ou regras.',
                'product_page' => 'Instalação no tema Liquid da PDP usando product.id, variant.id e variant.sku.',
                'tracking' => 'Tracking por app ou API, com atenção à posição da variação de tamanho nos variants.',
            ],
            'woocommerce' => [
                'connection_fields' => ['Domínio WordPress', 'Consumer key/secret ou XML', 'Atributo de tamanho', 'Feed opcional'],
                'catalog_flow' => 'Catálogo por REST API, plugin de feed Google ou XML público.',
                'product_page' => 'Instalação por hook/shortcode no produto, idealmente antes do botão Comprar.',
                'tracking' => 'Tracking por plugin ou API, mapeando atributo de tamanho do WooCommerce.',
            ],
            'nuvemshop' => [
                'connection_fields' => ['Domínio da loja', 'Token/API ou feed', 'Variável de variante', 'SKU da grade'],
                'catalog_flow' => 'Catálogo por API ou feed; regras visuais normalizam categoria, gênero e status.',
                'product_page' => 'Instalação no layout da PDP com recarga ao trocar variante.',
                'tracking' => 'Pedidos e devoluções por API quando disponível no plano da loja.',
            ],
            'vtex' => [
                'connection_fields' => ['Account/store', 'Credenciais Catalog API', 'Product Context', 'Feed opcional'],
                'catalog_flow' => 'Catálogo por VTEX Catalog API ou feed, usando productId e itemId como produto/variação.',
                'product_page' => 'Instalação por app/bloco de storefront ou template da PDP com selectedItem atualizado.',
                'tracking' => 'Pedidos por OMS/API ou app VTEX IO quando autorizado.',
            ],
            'tray' => [
                'connection_fields' => ['Domínio Tray', 'Token/API ou feed', 'ID da variação', 'SKU da grade'],
                'catalog_flow' => 'Catálogo por API Tray, feed ou exportação de marketplace.',
                'product_page' => 'Instalação no tema de produto, perto de productHelper.variants/form.',
                'tracking' => 'Pedidos e trocas por API Tray quando o cliente autorizar.',
            ],
            'loja_integrada' => [
                'connection_fields' => ['Domínio da loja', 'HTML/JS do tema', 'SKU/variação no DOM', 'Feed/exportação'],
                'catalog_flow' => 'Catálogo por feed/exportação ou integração customizada.',
                'product_page' => 'Instalação por HTML/JS personalizado na página de produto.',
                'tracking' => 'Eventos de carrinho/pedido por script ou API futura.',
            ],
            'magento' => [
                'connection_fields' => ['Base URL Magento', 'REST/Admin token ou módulo', 'Atributo de tamanho', 'Store view'],
                'catalog_flow' => 'Catálogo por REST API, feed Google ou módulo, preservando produto configurável e simples.',
                'product_page' => 'Instalação por bloco/layout catalog_product_view e recarga em opção configurável.',
                'tracking' => 'Tracking por módulo/API, com atributo de tamanho configurado por store view.',
            ],
            'opencart' => [
                'connection_fields' => ['Domínio OpenCart', 'Template Twig', 'Opção de tamanho', 'SKU/model'],
                'catalog_flow' => 'Catálogo por feed, módulo ou API customizada.',
                'product_page' => 'Instalação no product.twig e recarga quando opção/tamanho mudar.',
                'tracking' => 'Eventos por script ou conector customizado.',
            ],
            'xml_feed' => [
                'connection_fields' => ['Identificador da loja', 'URL XML/feed', 'Status'],
                'catalog_flow' => 'Catálogo por Google XML/RSS/feed público, com prévia e erros por linha antes de virar recomendação.',
                'product_page' => 'Snippet universal na PDP usando os mesmos IDs do feed para produto, variação e SKU.',
                'tracking' => 'Pedidos e devoluções entram depois por CSV/API, sem depender do feed de catálogo.',
            ],
            'api' => [
                'connection_fields' => ['Identificador da loja', 'URL base da API', 'Token de leitura', 'Webhook secret opcional'],
                'catalog_flow' => 'Catálogo por API autorizada do cliente ou conector customizado, mapeado para o contrato canônico.',
                'product_page' => 'Snippet universal na PDP usando os mesmos IDs retornados pela API.',
                'tracking' => 'Webhooks ou API de pedidos/devoluções quando houver contrato assinado e segredo rotacionável.',
            ],
            default => [
                'connection_fields' => ['Domínio público', 'Produto', 'Variação', 'SKU'],
                'catalog_flow' => 'Catálogo por XML, CSV ou API conforme disponibilidade da loja.',
                'product_page' => 'Snippet universal na PDP com recarga quando a variação mudar.',
                'tracking' => 'Eventos de carrinho, pedido e devolução por script/API conforme contrato.',
            ],
        };

        return array_merge($setup, [
            'fields' => self::fields($key),
        ]);
    }

    private static function fields(string $key): array
    {
        $fields = [
            'external_store_id' => self::field(
                'Loja',
                'Identificador da loja nesta plataforma.',
                'loja-ou-dominio'
            ),
            'api_base_url' => self::field(
                'URL da API',
                'Base da API quando houver leitura autenticada de catálogo.',
                'https://api.loja.com.br'
            ),
            'feed_url' => self::field(
                'URL do XML/feed',
                'Catálogo público em Google XML, RSS ou formato compatível.',
                'https://loja.com.br/feed.xml'
            ),
            'access_token' => self::field(
                'Token',
                'Token/chave de leitura. Fica criptografado e não volta em claro.',
                '',
                secret: true
            ),
            'webhook_secret' => self::field(
                'Webhook secret',
                'Segredo para validar webhooks assinados quando a plataforma enviar eventos.',
                '',
                secret: true
            ),
        ];

        return match ($key) {
            'bigshop' => [
                'external_store_id' => self::field('Store ID BigShop', 'ID da loja BigShop usado também pelo widget público.', '124', required: true),
                'api_base_url' => self::field('URL da API V3', 'Base da API BigShop para leitura de products e product_grids.', 'https://api.bigshop.com.br'),
                'access_token' => self::field('Token x-api', 'Token BigShop de leitura. O valor é write-only e criptografado.', '', secret: true),
                'feed_url' => self::field('URL XML/feed', 'Feed Google Merchant da loja para enriquecer link, imagem, categoria e grade.', 'https://www.loja.com.br/feed.xml'),
                'webhook_secret' => self::field('Webhook secret', 'Segredo para eventos BigShop futuros de pedido/devolução.', '', secret: true),
            ],
            'shopify' => [
                'external_store_id' => self::field('Domínio permanente', 'Use o domínio permanente ou identificador da loja Shopify.', 'loja.myshopify.com'),
                'api_base_url' => self::field('Admin API', 'Base da Admin API ou endpoint do app Shopify.', 'https://loja.myshopify.com/admin/api'),
                'access_token' => self::field('Access token', 'Token do app Shopify com escopos de leitura aprovados.', '', secret: true),
                'feed_url' => self::field('Feed opcional', 'Feed Google Merchant quando a loja preferir sincronização por XML.', 'https://loja.com/products.xml'),
                'webhook_secret' => self::field('Webhook secret', 'Segredo do app para eventos de pedidos/devoluções.', '', secret: true),
            ],
            'woocommerce' => [
                'external_store_id' => self::field('Domínio WordPress', 'Domínio da instalação WooCommerce.', 'loja.com.br'),
                'api_base_url' => self::field('REST API', 'Base da REST API WooCommerce.', 'https://loja.com.br/wp-json/wc/v3'),
                'access_token' => self::field('Consumer key/secret', 'Credencial de leitura da REST API. Salvar sem expor em claro.', '', secret: true),
                'feed_url' => self::field('Feed opcional', 'XML de plugin Google Merchant quando a API não estiver disponível.', 'https://loja.com.br/feed.xml'),
                'webhook_secret' => self::field('Webhook secret', 'Segredo de webhooks WooCommerce quando configurados.', '', secret: true),
            ],
            'nuvemshop', 'vtex', 'tray', 'magento' => [
                'external_store_id' => $fields['external_store_id'],
                'api_base_url' => $fields['api_base_url'],
                'access_token' => $fields['access_token'],
                'feed_url' => $fields['feed_url'],
                'webhook_secret' => $fields['webhook_secret'],
            ],
            'loja_integrada' => [
                'external_store_id' => self::field('Domínio ou ID', 'Identificador público da loja ou domínio usado no snippet.', 'loja.com.br'),
                'feed_url' => self::field('Feed/exportação', 'URL de feed ou exportação de catálogo quando disponível.', 'https://loja.com.br/feed.xml'),
            ],
            'opencart' => [
                'external_store_id' => self::field('Domínio OpenCart', 'Domínio da loja ou store id configurado no OpenCart.', 'loja.com.br'),
                'api_base_url' => self::field('API/módulo', 'Base de API ou módulo quando existir integração de catálogo.', 'https://loja.com.br/index.php?route=api'),
                'access_token' => $fields['access_token'],
                'feed_url' => $fields['feed_url'],
            ],
            'xml_feed' => [
                'external_store_id' => self::field('Identificador da loja', 'Domínio, tenant ou código interno usado para localizar o feed.', 'loja.com.br'),
                'feed_url' => self::field('URL do XML/feed', 'URL pública do catálogo que será sincronizado.', 'https://loja.com.br/feed.xml', required: true),
            ],
            'api' => [
                'external_store_id' => self::field('Identificador da loja', 'Store id, tenant ou domínio usado no conector customizado.', 'STORE_ID'),
                'api_base_url' => self::field('URL base da API', 'Endpoint base autorizado pelo cliente para leitura de catálogo.', 'https://api.loja.com.br', required: true),
                'access_token' => self::field('Token de leitura', 'Token, chave ou bearer do conector. Fica criptografado.', '', secret: true),
                'webhook_secret' => self::field('Webhook secret', 'Segredo para eventos assinados, quando houver webhook.', '', secret: true),
            ],
            default => $fields,
        };
    }

    private static function field(
        string $label,
        string $help,
        string $placeholder = '',
        bool $secret = false,
        bool $required = false
    ): array {
        return [
            'label' => $label,
            'help' => $help,
            'placeholder' => $placeholder,
            'secret' => $secret,
            'required' => $required,
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
            ['key' => 'product_id_found', 'label' => 'Produto informado'],
            ['key' => 'variant_id_found', 'label' => 'Variação informada'],
            ['key' => 'sku_found', 'label' => 'SKU informado'],
            ['key' => 'buttons_rendered', 'label' => 'Botões renderizados'],
        ];
    }

    private static function apiExamples(string $key): array
    {
        return match ($key) {
            'bigshop' => [
                [
                    'label' => 'Produtos BigShop',
                    'method' => 'GET',
                    'path' => '/products',
                    'description' => 'Ler catálogo com Store-Id e token x-api, sem gravar antes do dry-run.',
                ],
                [
                    'label' => 'Grades BigShop',
                    'method' => 'GET',
                    'path' => '/product_grids',
                    'description' => 'Cruzar grades por produto para variação, tamanho, SKU e disponibilidade.',
                ],
            ],
            'xml_feed' => [
                [
                    'label' => 'Feed Google XML',
                    'method' => 'GET',
                    'path' => 'https://loja.com.br/feed.xml',
                    'description' => 'Usar g:item_group_id, g:id, g:size, g:brand, g:product_type e imagem como fonte de catálogo.',
                ],
            ],
            'api' => [
                [
                    'label' => 'Lista de produtos',
                    'method' => 'GET',
                    'path' => '/products?updated_since=2026-05-01T00:00:00Z',
                    'description' => 'Retornar produto, variações, SKU, tamanhos, categoria, marca, gênero, imagem e status.',
                ],
                [
                    'label' => 'Detalhe de produto',
                    'method' => 'GET',
                    'path' => '/products/{product_id}',
                    'description' => 'Permitir reprocessar um produto específico quando o widget apontar divergência.',
                ],
                [
                    'label' => 'Pedido/devolução autorizado',
                    'method' => 'POST',
                    'path' => '/webhooks/orders',
                    'description' => 'Enviar apenas eventos contratados e minimizados, assinados com HMAC.',
                ],
            ],
            default => [
                [
                    'label' => 'Catálogo autorizado',
                    'method' => 'GET',
                    'path' => '/products',
                    'description' => 'Quando houver API, mapear produto, variação, SKU, tamanho, categoria, marca e imagem para o contrato canônico.',
                ],
                [
                    'label' => 'Evento assinado',
                    'method' => 'POST',
                    'path' => '/webhooks/orders',
                    'description' => 'Usar somente quando a plataforma puder assinar payloads e o cliente autorizar tracking.',
                ],
            ],
        };
    }

    private static function webhookGuide(string $key): array
    {
        return [
            'enabled' => ! in_array($key, ['xml_feed', 'loja_integrada'], true),
            'test_endpoint' => "/api/v1/integrations/{$key}/test-webhook",
            'signature_header' => 'X-Provador-Signature',
            'signature_algorithm' => 'HMAC-SHA256',
            'secret_storage' => 'write-only encrypted',
            'events' => ['catalog.updated', 'order.paid', 'return.created'],
            'notes' => 'Webhook é opcional e só deve ser ativado quando houver contrato, autorização e segredo rotacionável.',
        ];
    }

    private static function gtmGuide(string $key, string $installMode): array
    {
        return [
            'default' => false,
            'recommended' => $installMode !== 'one_click' && ! in_array($key, ['bigshop'], true),
            'trigger' => 'Somente páginas de produto',
            'required_data' => ['data-product-id', 'data-variant-id', 'data-sku', 'data-platform'],
            'when_to_use' => $installMode === 'one_click'
                ? 'Fallback assistido quando app, tema oficial ou instalação nativa não estiverem disponíveis.'
                : 'Alternativa para lojas sem app nativo ou sem edição simples do template da página de produto.',
            'validation' => 'Validar no Preview/Tag Assistant e depois no validador de instalação do Provador Virtual.',
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
