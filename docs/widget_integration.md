# Widget e Integracao Universal

Atualizado em: 2026-05-23

## Objetivo

Permitir que qualquer e-commerce instale o Provador Virtual com um snippet simples, sem depender da plataforma.

## Snippet padrao

```html
<div id="provador-virtual-container"></div>
<script
  id="provadorVirtualScript"
  src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js"
  data-merchant-id="MERCHANT_ID"
  data-store-id="STORE_ID"
  data-product-id="PRODUCT_ID"
  data-variant-id="VARIANT_ID"
  data-sku="SKU_DO_PRODUTO"
  data-platform="custom"
  data-container-id="provador-virtual-container"
  defer>
</script>
```

## Atributos

- `data-merchant-id`: conta do lojista no Provador Virtual.
- `data-store-id`: loja/empresa dentro do lojista.
- `data-product-id`: produto na plataforma origem.
- `data-variant-id`: variacao/grade na plataforma origem.
- `data-sku`: SKU usado como fallback de identificacao.
- `data-platform`: `bigshop`, `shopify`, `woocommerce`, `nuvemshop`, `vtex`, `tray` ou `custom`.
- `data-container-id`: container onde o botao inline deve aparecer.

## Comportamento esperado

1. Widget carrega sem bloquear a loja.
2. Executa config-check.
3. Se produto nao estiver configurado, nao mostra botao ou mostra aviso discreto apenas em modo debug.
4. Se produto estiver configurado, mostra dois botoes: `Descubra seu tamanho` e `Tabela de Medidas`.
5. `Descubra seu tamanho` abre modal/drawer de recomendacao.
6. `Tabela de Medidas` abre a tabela do produto com as faixas cadastradas.
7. Coleta dados em etapas e reusa medidas salvas localmente no navegador quando houver.
8. Retorna recomendacao.
9. Coleta consentimento para salvar medidas no perfil anonimo.
10. Coleta feedback.
11. Exibe `desenvolvido por provadorvirtual.online` com link para o site publico.

Status Sprint 4: implementado em `/widget/v1/provador-virtual.js` com CSS escopado em `/widget/v1/provador-virtual.css`. A pagina `/produto-teste` carrega o widget real por snippet dinamico.

Status Sprint 5: o painel `/app/widget` gera o snippet a partir de `/api/v1/widget-install`, com tema, dominios liberados e produto de exemplo.

Status Sprint 11: as rotas publicas de recomendacao validam `Origin` contra `allowed_domains` da instalacao ativa. Requisicoes sem `Origin` continuam liberadas para smokes e chamadas server-to-server; dominios nao cadastrados recebem `403`.

Status Sprint 24/25: o widget agora segue o padrao comercial de pagina de produto com os botoes `Descubra seu tamanho` e `Tabela de Medidas`, modal de tabela, assinatura do Provador Virtual e tema ampliado. O painel `/app/widget` permite personalizar primaria, secundaria, destaque, fundo, texto, fonte, tamanho, peso e raio, com visualizador em tempo real.

Status Sprint 36: o widget usa `pv_shopper_profile_v2` em `localStorage`, envia `profile_id`/token quando houver consentimento, permite limpar medidas salvas, mostra precisao do perfil e envia genero, formato corporal e preferencia de caimento para melhorar recomendacoes futuras.

## Evolucao inteligente prevista

Benchmark Sizebay/Zak em `docs/sizebay_benchmark.md` confirmou que o widget deve evoluir para:

- carregar de forma assincrona;
- esconder o botao quando produto/tabela nao estiver pronto;
- reconhecer consumidor anonimo por cookie/localStorage;
- reusar medidas anteriores com aviso claro;
- abrir edicao de medidas em modal;
- mostrar recomendacao rapida com altura/peso/idade;
- permitir refinamento por formato corporal e medidas detalhadas;
- registrar eventos de carrinho, pedido e devolucao quando a plataforma permitir.

## Contrato publico atual

Endpoints usados pelo widget:

- `POST /api/v1/public/recommendations/config-check`
- `POST /api/v1/public/recommendations`
- `POST /api/v1/public/recommendations/{id}/feedback`
- `POST /api/v1/public/recommendations/{id}/signal`
- `POST /api/v1/public/shopper-profiles/forget`

`config-check` retorna tambem a tabela de medidas normalizada para o modal publico, quando o produto estiver configurado.

`recommendations` retorna `shopper_profile` com `id`, token inicial, qualidade do perfil e mensagem para o consumidor. O token nunca fica em log ou HTML do lojista; fica somente no navegador do comprador.

`signal` registra eventos `add_to_cart`, `purchase`, `return` e `exchange` para aprendizado estatistico. Plataformas que ainda nao tiverem integracao automatica podem enviar esses sinais depois pelo proprio front ou por conector server-to-server.

O widget resolve a base da API a partir do proprio `src`. Em producao, chamadas para `/provadorvirtual_v2/api/...` passam por redirect 307 para a entrada Laravel funcional em `/provadorvirtual_v2/public/api/...`.

Em navegadores, o CORS permitido e calculado por lojista a partir do dominio da pagina de origem. O painel deve manter `allowed_domains` atualizado antes de instalar o widget em producao.

## Guias por plataforma

### BigShop

Preferencialmente usar integracao nativa de um clique. Fallback por snippet:

- inserir container perto do seletor de tamanho ou do botao de comprar;
- usar grade atual como `data-variant-id`;
- usar SKU ou grade id como `data-sku`;
- manter `data-platform="bigshop"`.

### WooCommerce

Usar hook/shortcode em pagina de produto:

- `woocommerce_before_add_to_cart_button`;
- `global $product`;
- SKU em `$product->get_sku()`;
- variacao escolhida enviada pelo JS quando aplicavel.

### Shopify

Inserir no template de produto:

- `product.id` em `data-product-id`;
- variant atual em `data-variant-id`;
- `product.selected_or_first_available_variant.sku` em `data-sku`.

### Nuvemshop

Inserir no template de produto:

- id do produto;
- id/SKU da variante selecionada;
- atualizar atributo quando a variante mudar.

### Custom

Usar SKU fixo ou atualizar dinamicamente com JS proprio da loja.

## Pagina de produto ficticia

`/produto-teste` deve usar o mesmo snippet e chamar os endpoints reais. Essa pagina sera usada para:

- validacao local;
- smoke de deploy;
- demonstracao comercial;
- debug de recomendacao sem depender de loja externa.

## Smoke externo Sprint 12

Arquivo:

- `tools/widget-external-smoke.html`

Servir por `localhost` para simular uma loja externa usando o widget de producao.
Para dominios reais, cadastrar o dominio em `/app/widget` antes do teste.

## Compatibilidade com v1

Enquanto houver migracao, o widget pode aceitar aliases:

- `data-lojista-id` -> `data-merchant-id`;
- `data-produto-id-grade` -> `data-sku`;
- `data-sku-grade` -> `data-sku`.

O codigo novo deve gerar somente os atributos padrao em ingles.

## Painel do lojista

Rotas protegidas:

- `GET /api/v1/widget-install`
- `PATCH /api/v1/widget-install`
- `GET /api/v1/integrations`
- `PATCH /api/v1/integrations/{platform}`

Plataformas catalogadas: `bigshop`, `shopify`, `woocommerce`, `nuvemshop`, `vtex`, `tray`, `loja_integrada`, `magento`, `opencart` e `custom`.

Regra comercial Sprint 32: empresas que contrataram como BigShop recebem o desconto BigShop e, no painel, podem visualizar/configurar apenas instalacao BigShop. O backend tambem bloqueia tentativas de salvar Shopify, WooCommerce, Nuvemshop, VTEX, Tray ou custom para esse contrato.

Status Sprint 34: o catalogo de integracoes passou a incluir `loja_integrada`, `magento` e `opencart`, alem das plataformas anteriores. `GET /api/v1/integrations` retorna guia, snippet, checklist e matriz de dados por plataforma. `POST /api/v1/integrations/{platform}/validate-install` valida dominio publico, container, script, plataforma e identificadores do produto sem salvar o HTML da loja.

Credenciais de plataforma devem ser salvas apenas por endpoints protegidos e persistidas criptografadas. A API retorna somente flags como `has_access_token` e `has_webhook_secret`.
