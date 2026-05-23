# Pesquisa de integracoes de plataformas e roadmap

Data da pesquisa: 2026-05-23.

Este documento consolida a pesquisa sobre como a Sizebay integra o provador em plataformas de e-commerce e traduz isso para o plano do Provador Virtual. Nao registrar tokens, segredos ou credenciais neste arquivo.

## Conclusao executiva

Os inputs nao sao iguais para todas as plataformas. O contrato funcional e parecido, mas a forma de obter cada dado muda:

- PDP/widget: todas precisam expor um container na pagina de produto e enviar product_id, variant_id ou SKU para o script.
- Catalogo: pode vir de API nativa, XML Google Merchant, CSV/importacao manual ou leitura on-page.
- Variantes: cada plataforma modela grade/tamanho/cor de um jeito. O Provador Virtual precisa normalizar para product, variant, sku, size_label, color, gender, category, image e permalink.
- Tracking: para ficar 100% funcional como a Sizebay, precisamos captar view de produto, abertura do provador/tabela, add-to-cart, pedido e devolucao/troca.
- Tabelas de medidas: a integracao de catalogo nao substitui a tabela de medidas. Ela identifica produtos/variantes; a recomendacao depende de tabela vinculada ou gerada/revisada.

## Padrao Sizebay observado

A documentacao publica da Sizebay divide a implementacao em quatro camadas:

- Script no produto: criar um elemento ancora/container na PDP no local dos botoes e carregar um prescript fornecido pela Sizebay.
- API de servico: obter SID, consultar se o produto existe por permalink ou SKU/feed product id, mostrar ou esconder botoes, abrir VFR/tabela por iframe/WebView e enviar device/country.
- Dados de produto: aceitar XML Feed, API e OnPage. O XML recomenda campos como g:id, title, g:product_type, g:brand, link, g:image_link, g:gender, g:size e g:age_group; tambem aceita variacoes em um item ou separadas.
- Tracking: script/API para produto, carrinho, pedido e retornos. Para plataformas especificas, existem apps/modulos de tracking em Shopify, WooCommerce, Magento, VTEX IO e PrestaShop.

Referencias principais:

- Sizebay overview: https://docs.sizebay.com/
- Sizebay script PDP: https://docs.sizebay.com/size-and-fit-implementation/service-implementation-script
- Sizebay API PDP: https://docs.sizebay.com/size-and-fit-implementation/service-implementation-api
- Sizebay XML feed: https://docs.sizebay.com/size-and-fit-data-integration/product-integration-xml-feed
- Sizebay API de produtos: https://docs.sizebay.com/size-and-fit-data-integration/product-integration-api
- Sizebay OnPage: https://docs.sizebay.com/size-and-fit-data-integration/product-integration-onpage
- Sizebay tracking API: https://docs.sizebay.com/size-and-fit-order-tracking/order-tracking-api
- Sizebay Shopify tracking: https://docs.sizebay.com/size-and-fit-order-tracking/platforms/shopify
- Sizebay WooCommerce tracking: https://docs.sizebay.com/size-and-fit-order-tracking/platforms/woocommerce
- Sizebay Magento tracking: https://docs.sizebay.com/size-and-fit-order-tracking/platforms/magento
- Parcerias Sizebay/plataformas: https://sizebay.com/pt/parcerias/

## XML/Google Merchant

O XML e o caminho universal mais rapido para importar catalogo em plataformas sem API pronta. Para moda, o minimo que deixa o Provador Virtual util e:

- product_id pai: g:item_group_id quando existir; fallback para g:id.
- variant_id: g:id.
- SKU da variante: g:mpn ou g:id.
- nome, descricao, categoria/tipo, marca, genero, idade, tamanho, cor, preco, imagem e link.

Google Merchant usa item_group_id para agrupar variantes e recomenda que variantes compartilhem o mesmo grupo e diferenciem por size, color, material, pattern, age_group ou gender.

Referencias:

- Google product data specification: https://support.google.com/merchants/answer/7052112
- Google item_group_id: https://support.google.com/merchants/answer/6324507

## BigShop

Estado validado em 2026-05-23:

- A API V3 da BigShop respondeu para getEndPoints, products e product_grids na loja Luna Moda Festa.
- O feed publico `https://www.lunamodafesta.com.br/feed.xml` respondeu como RSS Google Merchant e trouxe g:id, g:item_group_id, link, g:image_link, g:gender, g:product_type, g:color e g:size.
- Para BigShop, o caminho mais automatico deve ser API V3 primeiro e XML como fallback/atalho.

Premissas BigShop confirmadas:

- `g:id` e sempre a grade/variacao;
- `g:item_group_id` e sempre o produto pai;
- os feeds de moda conseguem garantir tamanho, cor, genero, tipo de produto, estoque/disponibilidade e link;
- a instalacao automatica do widget sera feita futuramente no arquivo `produto.vue` da model3 plano pro, no repositorio BigShop correto.

O script do widget deve ser instalado na pagina de produto. O container precisa ficar exatamente no ponto visual onde os botoes "Descubra seu tamanho" e "Tabela de Medidas" devem aparecer, normalmente perto do seletor de tamanho/grade e antes ou perto do botao Comprar. O script pode ser carregado no head ou fim do body, desde que o container exista e os atributos do produto/variacao/SKU sejam atualizados quando a grade mudar.

O que falta/vale confirmar ou implementar do lado BigShop:

- Confirmar contrato oficial do feed: g:id e sempre id da variante/grade? g:item_group_id e sempre id do produto pai?
- Garantir feed com g:size, g:color, g:gender, g:product_type, g:image_link e link para 100% dos produtos de moda.
- Retornar estoque por grade no feed ou API, para o widget filtrar tamanhos disponiveis.
- Disponibilizar endpoint ou webhook para pedidos, itens vendidos, trocas e devolucoes apenas na fase de analytics/aprendizado, pois nao e requisito para o provador funcionar no PDP.
- Padronizar HMAC para webhooks e one-click activation.
- Criar no painel BigShop uma autorizacao one-click que envie store_id, dominio, token de API e versao de contrato ao SaaS.
- Criar ponto nativo no tema/PDP para inserir container e script sem editar codigo manualmente.
- Confirmar limites, paginacao e filtros incrementais da API V3.

Referencia BigShop:

- Postman API V3: https://documenter.getpostman.com/view/4253101/2s93sdYrsi

## Matriz por plataforma

| Plataforma | Catalogo preferido | XML/feed | Widget/PDP | Tracking necessario |
| --- | --- | --- | --- | --- |
| BigShop | API V3 products + product_grids | Nativo em `dominio/feed.xml` | Integracao nativa no tema da PDP | Pedidos/retornos via API/webhook a criar |
| Shopify | Admin API/app + Liquid | Feed por app/Google Merchant; nao e o caminho nativo universal | App embed/theme block ou Liquid no produto | App/webhooks de orders e returns |
| WooCommerce | REST API products/variations | Plugins Google Merchant geram XML | Plugin WP ou hook na PDP | Plugin/webhooks WooCommerce |
| Nuvemshop | Catalog API com products/variants | Feed/canal quando habilitado | App/script no layout de produto | API/webhooks de pedidos |
| VTEX | Catalog API + SKU + OMS | Feed por app/exportacao | App VTEX IO/bloco PDP | OMS/API e eventos storefront |
| Tray | Tray API | Feed de marketplace/canal quando habilitado | Tema Tray com JS de variacao | API de pedidos |
| Loja Integrada | API/feed conforme plano | Feed/exportacao quando habilitado | Script no tema/campo de scripts | API/exportacao de pedidos |
| Magento | REST API configurable/simple products | Modulo Google Merchant/XML | Modulo/bloco no catalog_product_view | Modulo/API de orders/returns |
| OpenCart | Modulo/API | Google Base/Sitemap por extensao | Twig product/product.twig | Modulo/API de pedidos |
| Personalizada | API do lojista ou ETL | Google XML/RSS/CSV | Snippet universal | Endpoint ou pixel de eventos |

Referencias de plataforma:

- Shopify variant API: https://shopify.dev/docs/api/admin-rest/latest/resources/product-variant
- WooCommerce REST API: https://woocommerce.github.io/woocommerce-rest-api-docs/
- Nuvemshop products/variants: https://dev.nuvemshop.com.br/docs/erp-guide/catalog/products
- VTEX Catalog API: https://developers.vtex.com/docs/guides/catalog-api-overview
- VTEX Catalog architecture: https://developers.vtex.com/docs/guides/catalog-overview
- Adobe Commerce/Magento configurable product: https://developer.adobe.com/commerce/webapi/rest/tutorials/configurable-product/
- OpenCart product feeds: https://docs.opencart.com/en-gb/extension/feed/
- Tray developers: https://developers.tray.com.br/

## Roadmap por sprints

Sprint 1 - BigShop piloto Luna Moda Festa

- Salvar feed_url por integracao e sincronizar XML Google Merchant por URL.
- Mapear g:item_group_id como produto pai e g:id como variacao.
- Validar API V3 BigShop com store_id e token ja cadastrados no SaaS.
- Sincronizar products/product_grids e comparar API vs XML para divergencias.
- Publicar snippet no tema BigShop da Luna e validar PDP real.
- Manter pedidos/trocas/devolucoes fora do MVP do provador, salvo necessidade de analytics.

Sprint 2 - BigShop automatico/one-click

- Implementar ativacao BigShop assinada com HMAC e replay protection.
- Criar no lado BigShop botao "Ativar Provador Virtual".
- Enviar store_id, dominio, token escopado, URL feed e versao de contrato.
- Criar app/ponto nativo de tema para container e script.
- Criar checklist automatico: dominio, PDP, container, script, product_id, variant_id, SKU.

Sprint 3 - Core multi-plataforma

- Criar camada de conectores com contrato unico: catalog, variants, inventory, orders, returns, storefrontSnippet.
- Normalizar catalogo: product, variant, sku, size_label, color, gender, age_group, category, brand, image, permalink.
- Criar fila de sincronizacao, logs por item, retry e agendamento.
- Adicionar validador de XML com relatorio de campos obrigatorios/recomendados.
- Criar tela de mapeamento de atributos de tamanho/cor/genero.

Sprint 4 - Widget e tracking universal

- Evoluir widget para atualizar atributos quando a variacao muda.
- Captar eventos: PDP view, open VFR, open table, recommendation, add-to-cart.
- Criar endpoint de tracking server-side para pedido/devolucao.
- Exibir status de tracking na tela de integracoes.
- Garantir LGPD: sem PII desnecessaria nos eventos.

Sprint 5 - Shopify

- Implementar app/OAuth Shopify com scopes de products, variants, inventory, script/theme app extension e orders quando aprovado.
- Importar catalogo via Admin API e suportar fallback XML.
- Criar app embed/theme block para PDP.
- Mapear posicao do atributo tamanho, pois Shopify nao define semanticamente qual option e tamanho.
- Webhooks: products/update, orders/create, refunds/returns conforme permissao.

Sprint 6 - WooCommerce

- Criar plugin WordPress com settings: tenant/store, dominio, atributo de tamanho, feed URL.
- Importar via WooCommerce REST API e aceitar XML de plugin Google Merchant.
- Instalar container por hook e atualizar variacao por found_variation.
- Captar pedido/carrinho por hooks WooCommerce.
- Publicar pacote zip e guia de instalacao.

Sprint 7 - Nuvemshop

- Criar app Nuvemshop OAuth.
- Importar products/variants pela Catalog API.
- Injetar script no layout permitido pela plataforma/app.
- Mapear atributo Tamanho/Cor em variants.
- Captar pedidos por webhook/API.

Sprint 8 - VTEX

- Criar app VTEX IO/bloco PDP.
- Integrar Catalog API para Product/SKU e especificacoes.
- Integrar OMS para orders e returns.
- Suportar feed XML/exportacao como fallback.
- Validar CSP, ambiente de homologacao e publicacao em workspace.

Sprint 9 - Tray e Loja Integrada

- Implementar conectores por API quando o lojista tiver token/app.
- Fallback XML/feed/exportacao para catalogo.
- Criar snippets por tema e guia por plataforma.
- Captar pedidos por API/exportacao conforme disponibilidade.
- Criar assistente de mapeamento para stores sem variacao padronizada.

Sprint 10 - Magento e OpenCart

- Magento: modulo Composer ou guia de bloco; REST API para configurable/simple products.
- Magento: order/return tracking por modulo/API.
- OpenCart: extensao Twig + Google Base/Sitemap feed.
- OpenCart: modulo de tracking para pedidos.
- Documentar instalacao e rollback por versao.

Sprint 11 - Plataformas personalizadas

- Publicar SDK/snippet universal com contrato claro.
- Aceitar XML, CSV, API pull e API push.
- Criar pagina de diagnostico com exemplos de payload.
- Adicionar webhooks assinados para catalogo, pedido e devolucao.
- Criar homologacao self-service com checklist e sandbox.

Sprint 12 - Qualidade, escala e operacao

- Jobs incrementais, deduplicacao, monitoramento e alertas.
- Dashboard de integracoes por loja/plataforma.
- Relatorios de cobertura: produtos com tabela, produtos sem tamanho, variantes sem SKU, imagens ausentes.
- SLA de sincronizacao e rotinas de reprocessamento.
- Playbooks de suporte por plataforma.
