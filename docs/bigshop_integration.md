# Integracao BigShop

Atualizado em: 2026-05-23

## Objetivo

Criar o caminho mais simples possivel para lojas BigShop usarem o Provador Virtual, idealmente em um clique.

## Contrato observado

Fonte publica verificada: `https://documenter.getpostman.com/view/4253101/2s93sdYrsi#1d6f5eeb-5cb7-49c7-aa64-7c03d458ae45`

Analise cruzada com `bigshop360` e codigo BigShop:

- host observado: `https://api.bigshop.com.br`;
- autenticacao observada: header `x-api`;
- identificacao de loja: header ou query `store-id`;
- front tambem usa endpoint `https://api.bigshop.com.br/v3/front/products`;
- busca usa `https://api.bigshop.com.br/v3/search/products/{term}` com `store-id`.

Rotas observadas/documentadas:

- `GET /v3/getEndPoints`
- `GET /v3/{endPointName}`
- `GET /v3/products/{id}`
- `GET /v3/search/products/{term}`
- `POST /v3/save_product`
- `GET /v3/brands`
- `GET /v3/categories`
- `GET /v3/characteristics`
- `GET /v3/grids/{id}`
- `GET /v3/clients`
- `GET /v3/clients/{id}`
- `POST /v3/clients/batch`
- `GET /v3/sales/{id}`
- `POST /v3/sales/{id}`
- `GET /v3/sales_items/{id}`

## Dados necessarios por loja

- `store_id`;
- token `x-api`;
- base URL, default `https://api.bigshop.com.br`;
- dominio publico da loja;
- webhook secret, se a BigShop expuser webhooks;
- permissao para leitura de produtos/grades/tabelas.

## Modelo de conexao

Campos em `platform_connections`:

- `platform = bigshop`;
- `external_store_id`;
- `api_base_url`;
- `access_token_encrypted`;
- `webhook_secret_encrypted`;
- `status`;
- `last_sync_at`;
- `last_error`;

O token deve ser write-only: salvar criptografado, nunca retornar em texto.

## Mapeamento canonico

BigShop produto:

- `id` -> `external_product_id`;
- `nome` -> `name`;
- `slug` -> `public_url` ou slug parcial;
- `descricao1/descricao2` -> descricao;
- `genero` -> genero/tipo;
- `tabela_de_medidas` ou `medidas` -> fonte de medidas quando disponivel.

BigShop grade:

- `grade.id` -> `external_variant_id`;
- `sku` -> `sku`;
- `tamanho` ou caracteristica tamanho -> `size_label`;
- `cor`/`cornome` -> cor;
- `estoque` -> estoque informativo;
- `preco` -> preco informativo.

## Integracao de um clique

Fluxo desejado:

1. Lojista BigShop abre Provador Virtual no painel BigShop.
2. BigShop envia usuario/loja autenticados para o Provador Virtual ou abre iframe/admin interno.
3. Provador Virtual cria/atualiza `merchant`, `company` e `platform_connection`.
4. Token e `store_id` sao recebidos por OAuth/app interno ou secret seguro.
5. Sistema roda probe `GET /v3/getEndPoints`.
6. Sistema sincroniza produtos e grades.
7. Lojista escolhe/cria tabela de medidas.
8. BigShop ativa o widget no tema sem colar codigo manual.

## Encaixe no front BigShop

O front da loja BigShop possui pagina de produto em:

- `bigshop/front/stores/pro_store/produto.vue`

Pontos relevantes:

- grade/tamanho aparece em `productSizes`;
- produto tem `tabela_de_medidas`;
- ha area de tabela de medidas perto da compra;
- o widget pode ser renderizado perto do seletor de tamanho ou antes do botao de compra;
- para cada troca de grade, atualizar `data-variant-id` e `data-sku`.

## Fallback por snippet

Enquanto o um clique nao existir, gerar snippet no painel Provador Virtual com:

- `data-platform="bigshop"`;
- `data-store-id` BigShop;
- `data-product-id` BigShop;
- `data-variant-id` grade BigShop;
- `data-sku` SKU da grade.

## Lacunas a confirmar na BigShop

- contrato formal do token e escopos;
- expiracao/rotacao do token;
- paginacao e filtros oficiais;
- endpoint claro para grades por produto;
- payload estruturado de tabela de medidas;
- webhook de produto/grade alterados;
- sandbox;
- limites/rate limit;
- formato padrao de erros.

## Primeiro teste recomendado

Usar uma loja BigShop controlada, com um produto de moda e pelo menos tres variacoes de tamanho. Validar:

- probe remoto;
- sync de produto/grade;
- criacao de tabela no Provador Virtual;
- pagina `/produto-teste` usando dados sincronizados;
- widget no front BigShop por snippet;
- depois planejar um clique.

## Sprint 7 implementada

Rotas protegidas:

- `POST /api/v1/integrations/bigshop/probe`
- `POST /api/v1/integrations/bigshop/sync`

Comportamento:

- probe chama `GET /v3/getEndPoints` com headers `x-api` e `store-id`;
- sync chama `GET /v3/products`;
- produtos BigShop sao upsertados em `products`;
- grades sao upsertadas em `product_variants`;
- quando o payload traz `measurement_table`, `tabela_de_medidas` ou `medidas` estruturado, o sistema cria/atualiza `measurement_tables`;
- cada probe/sync registra `integration_events`;
- relatorio retorna produtos, variacoes, tabelas sincronizadas e lacunas.

Sem credencial real, validacao automatizada usa `Http::fake`. Para teste real ainda falta loja controlada, `store_id` e token `x-api`.

## Sprint 8 implementada - ativacao um clique

Rota publica assinada:

- `POST /api/v1/public/bigshop/activate`

Headers obrigatorios:

- `X-BigShop-Timestamp`: Unix timestamp, com tolerancia de 10 minutos;
- `X-BigShop-Signature`: assinatura no formato `sha256=<hmac>`.

Assinatura:

```txt
hmac_sha256(BIGSHOP_ACTIVATION_SECRET, timestamp + "." + raw_body)
```

Payload esperado:

```json
{
  "store_id": "123",
  "store_name": "Loja Exemplo",
  "store_url": "https://loja.exemplo.com.br",
  "store_domain": "loja.exemplo.com.br",
  "api_base_url": "https://api.bigshop.com.br",
  "access_token": "token-write-only",
  "webhook_secret": "secret-write-only",
  "merchant": {
    "name": "Nome do lojista",
    "email": "lojista@example.com"
  }
}
```

Comportamento:

- cria ou atualiza usuario, lojista, empresa, conexao BigShop e instalacao do widget;
- aceita `store_domain` ou `store_url`, derivando o dominio quando vier URL completa;
- salva token e webhook secret criptografados, sem retornar esses valores em claro;
- cria `widget_public_key` para uso no snippet;
- registra evento `one_click_activation` em `integration_events`;
- retorna `dashboard_url`, `widget_url`, `widget_public_key` e status da ativacao.
- Sprint 35 adicionou `install_snippet`, `integration_contract` versao `2026-05-23` e monitor protegido `GET /api/v1/integrations/bigshop/activations`.

Quando `BIGSHOP_ACTIVATION_SECRET` nao estiver configurado, a rota retorna `503` e nao processa o payload.

Para teste real ainda falta cadastrar `BIGSHOP_ACTIVATION_SECRET` em `PRODUCTION_ENV` e receber da BigShop o payload/secret oficial do app interno.

Contrato final detalhado: `docs/bigshop_one_click_contract.md`.
