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
4. Se produto estiver configurado, mostra botao "Qual meu tamanho?".
5. Abre modal/drawer.
6. Coleta dados em etapas.
7. Retorna recomendacao.
8. Coleta feedback.

Status Sprint 4: implementado em `/widget/v1/provador-virtual.js` com CSS escopado em `/widget/v1/provador-virtual.css`. A pagina `/produto-teste` carrega o widget real por snippet dinamico.

Status Sprint 5: o painel `/app/widget` gera o snippet a partir de `/api/v1/widget-install`, com tema, dominios liberados e produto de exemplo.

Status Sprint 11: as rotas publicas de recomendacao validam `Origin` contra `allowed_domains` da instalacao ativa. Requisicoes sem `Origin` continuam liberadas para smokes e chamadas server-to-server; dominios nao cadastrados recebem `403`.

## Contrato publico atual

Endpoints usados pelo widget:

- `POST /api/v1/public/recommendations/config-check`
- `POST /api/v1/public/recommendations`
- `POST /api/v1/public/recommendations/{id}/feedback`

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

Plataformas catalogadas: `bigshop`, `shopify`, `woocommerce`, `nuvemshop`, `vtex`, `tray` e `custom`.

Credenciais de plataforma devem ser salvas apenas por endpoints protegidos e persistidas criptografadas. A API retorna somente flags como `has_access_token` e `has_webhook_secret`.
