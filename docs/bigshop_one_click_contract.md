# Contrato BigShop Um Clique

Atualizado em: 2026-05-23

Status: publicado e validado em producao no GitHub Actions run `26339426665`. A ativacao real ainda depende de `BIGSHOP_ACTIVATION_SECRET` oficial, loja piloto BigShop, `store_id` e token `x-api`.

## Objetivo

Definir o contrato final do lado Provador Virtual para a ativacao nativa de lojas BigShop, deixando pronto o que depende do nosso SaaS e separando o que ainda depende de credenciais/ajuste no codigo BigShop.

## Endpoint

```http
POST /api/v1/public/bigshop/activate
```

Headers obrigatorios:

- `X-BigShop-Timestamp`: Unix timestamp;
- `X-BigShop-Signature`: `sha256=<hmac>`.

Assinatura:

```txt
hmac_sha256(BIGSHOP_ACTIVATION_SECRET, timestamp + "." + raw_body)
```

Tolerancia: 600 segundos.

## Payload

Campos obrigatorios:

```json
{
  "store_id": "123",
  "store_name": "Loja Exemplo",
  "merchant": {
    "email": "lojista@example.com"
  }
}
```

Campos opcionais:

```json
{
  "store_domain": "loja.exemplo.com.br",
  "store_url": "https://loja.exemplo.com.br",
  "api_base_url": "https://api.bigshop.com.br",
  "access_token": "token-write-only",
  "webhook_secret": "secret-write-only",
  "install_widget": true,
  "sync_after_activation": false,
  "callback_url": "https://bigshop.com.br/callback",
  "contract_version": "2026-05-23"
}
```

## Resposta

A resposta retorna:

- `merchant_id`;
- `merchant_company_id`;
- `platform_connection_id`;
- `widget_public_key`;
- `dashboard_url`;
- `widget_url`;
- `install_snippet`;
- `integration_contract`;
- `status`.

`install_snippet` traz o HTML base que o front BigShop pode renderizar perto do seletor de tamanho:

```html
<div id="provador-virtual-container"></div>
<script
  id="provadorVirtualScript"
  src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js"
  data-platform="bigshop"
  data-store-id="STORE_ID"
  data-product-id="BIGSHOP_PRODUCT_ID"
  data-variant-id="BIGSHOP_GRADE_ID"
  data-sku="BIGSHOP_SKU"
  data-container-id="provador-virtual-container"
  defer></script>
```

## Mapeamento no front BigShop

Arquivo de referencia:

- `D:\Projetos\bigshop\172.16.151.5\front\stores\pro_store\produto.vue`

Ponto de encaixe recomendado:

- perto do seletor de tamanho/grade;
- antes do botao comprar quando a grade ja estiver selecionavel;
- atualizar `data-variant-id` e `data-sku` sempre que `productSizes`/grade ativa mudar.

Campos:

- produto BigShop -> `data-product-id`;
- grade BigShop -> `data-variant-id`;
- SKU da grade ou produto -> `data-sku`;
- `store_id` BigShop -> `data-store-id`;
- plataforma fixa -> `data-platform="bigshop"`.

## Monitoramento

Rota protegida:

```http
GET /api/v1/integrations/bigshop/activations
```

Retorna as 20 ativacoes recentes da empresa ativa, com:

- status;
- store_id;
- dominio;
- versao do contrato;
- chave publica do widget;
- empresa vinculada.

O painel `/app/integracoes` mostra essa lista quando a plataforma ativa e BigShop.

## Pendencias externas

- Receber `BIGSHOP_ACTIVATION_SECRET` oficial e cadastrar em `PRODUCTION_ENV`.
- Receber loja piloto BigShop real com `store_id` e token `x-api`.
- Aplicar o patch no front BigShop para renderizar o snippet.
- Validar sync em produto real com grade/tabela.
- Definir se a BigShop chamara `sync_after_activation` ou se o lojista fara sync manual no painel.
