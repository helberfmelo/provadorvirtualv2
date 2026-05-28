# Integração BigShop

Atualizado em: 2026-05-28

## Objetivo

Criar o caminho mais simples possível para lojas BigShop usarem o Provador Virtual, idealmente em um clique.

## Contrato observado

Fonte pública verificada: `https://documenter.getpostman.com/view/4253101/2s93sdYrsi#1d6f5eeb-5cb7-49c7-aa64-7c03d458ae45`

Análise cruzada com `bigshop360` e código BigShop:

- host observado: `https://api.bigshop.com.br`;
- autenticacao observada: header `x-api`;
- identificacao de loja: header ou query `Store-Id`; em 2026-05-28 a Zak/loja `124` retornou erro com `store-id` minusculo e respondeu HTTP 200 com `Store-Id`;
- front também usa endpoint `https://api.bigshop.com.br/v3/front/products`;
- busca usa `https://api.bigshop.com.br/v3/search/products/{term}` com `Store-Id`.

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

## Dados necessários por loja

- `store_id`;
- token `x-api`;
- base URL, default `https://api.bigshop.com.br`;
- domínio público da loja;
- webhook secret, se a BigShop expuser webhooks;
- permissão para leitura de produtos/grades/tabelas.

## Modelo de conexão

Campos em `platform_connections`:

- `platform = bigshop`;
- `external_store_id`;
- `api_base_url`;
- `feed_url`, fallback recomendado `https://domínio-da-loja/feed.xml`;
- `feed_format`, inicialmente `google_xml`;
- `access_token_encrypted`;
- `webhook_secret_encrypted`;
- `status`;
- `last_sync_at`;
- `last_error`;

O token deve ser write-only: salvar criptografado, nunca retornar em texto.

## Mapeamento canonico

Premissas BigShop confirmadas pelo dono da plataforma em 2026-05-23:

- no XML, `g:id` e sempre o ID da grade/variação;
- no XML, `g:item_group_id` e sempre o ID do produto pai;
- a BigShop consegue garantir `g:size`, `g:color`, `g:gender`, `g:product_type`, estoque/disponibilidade e `link` em 100% dos feeds de moda;
- o ponto oficial para instalação automática será `produto.vue` da model3 plano pro, em sprint futura no repositório BigShop correto.
- Sprint 58 estudou a cópia local do modelo pro e definiu a instalação no `produto.vue` compartilhado, com configuração por loja em `bbs.template_model3_apps` e resolução automática por `platform=bigshop` + `external_store_id`.

BigShop produto:

- `id` -> `external_product_id`;
- `nome` -> `name`;
- `slug` -> `public_url` ou slug parcial;
- `descricao1/descricao2` -> descrição;
- `gênero` -> gênero/tipo;
- `tabela_de_medidas` ou `medidas` -> fonte de medidas quando disponível.

BigShop grade:

- `grade.id` -> `external_variant_id`;
- `sku` -> `sku`;
- `tamanho` ou característica tamanho -> `size_label`;
- `cor`/`cornome` -> cor;
- `estoque` -> estoque informativo;
- `preço` -> preço informativo.

BigShop XML Google Merchant:

- `g:item_group_id` -> `external_product_id` e SKU pai quando existir;
- `g:id` -> `external_variant_id` e SKU da variante quando `g:mpn` não existir;
- `g:size` -> `size_label`;
- `g:color` -> cor;
- `g:gender` -> gênero;
- `g:product_type` ou `g:google_product_category` -> categoria;
- `g:availability` ou campo de estoque BigShop -> disponibilidade da variação;
- `g:image_link` -> imagem;
- `link` -> URL pública do produto em metadata.

## Validação Luna Moda Festa

Em 2026-05-23, a loja Luna Moda Festa foi usada como piloto controlado sem registrar credenciais neste documento:

- feed público `https://www.lunamodafesta.com.br/feed.xml` respondeu HTTP 200;
- o XML veio como RSS Google Merchant com namespace `g`;
- os itens continham `g:id`, `g:item_group_id`, `g:gender`, `g:product_type`, `g:color`, `g:size`, `g:image_link` e `link`;
- a API V3 respondeu para `getEndPoints`, `products` e `product_grids` com store_id/token informados fora da documentação.

## Validação Zak

Em 2026-05-28, a Zak foi cadastrada como cliente BigShop no Provador Virtual local e em producao, sem registrar token em documento versionado:

- loja BigShop `124`;
- CNPJ `15.179.121/0006-24`;
- razao social `RSP COMERCIO DE ROUPAS LTDA EPP`;
- dominio `zak.com.br`;
- feed `https://www.zak.com.br/feed.xml`;
- token salvo criptografado em `platform_connections.access_token_encrypted`;
- widget configurado para `zak.com.br`, `www.zak.com.br` e `provadorvirtual.online`.

Validacoes de leitura:

- `GET /v3/getEndPoints` respondeu HTTP 200;
- `GET /v3/products` com `store-id` minusculo respondeu erro `key not found - store-id`;
- `GET /v3/products` com `Store-Id` respondeu HTTP 200;
- `GET /v3/product_grids` com `Store-Id` respondeu HTTP 200;
- o feed publico da Zak respondeu HTTP 200 apos redirecionar para `www.zak.com.br`.

Achados tecnicos:

- `products` retorna envelope paginado, nao apenas uma lista direta de produtos;
- `product_grids` precisa ser sincronizado em endpoint separado e ligado a produtos por `produtoid`;
- o tamanho da grade aparece em `caracteristicas` e precisa de extracao especifica;
- antes de alimentar tabelas finais da Zak, a importacao deve passar por dry-run, mapeamento de marca/categoria/modelagem e revisao de medidas.

Status Sprint 109:

- `POST /api/v1/integrations/bigshop/dry-run` executa leitura segura sem gravar produtos, variações ou tabelas;
- o cliente BigShop pagina `products` e `product_grids` usando `Store-Id`;
- o dry-run cruza grades por `produtoid`, extrai tamanho de `caracteristicas` e devolve contadores, amostras e erros/alertas por produto;
- `/app/integracoes` mostra a ação `Prévia segura` e um painel de revisão antes da sincronização real.

Status Sprint 110:

- `/app/sincronizacao` centraliza histórico de sync e dry-run em tela list-first;
- `GET /api/v1/integrations/sync-history` consolida eventos `dry_run_import`, `sync_products` e `xml_feed_sync`;
- a tela mostra status, contadores, amostra de produtos e erros por produto/grade vindos de `integration_events` e `import_jobs`.
- publicado no commit `efe87b8`, com deploy green no run `26605323289` e validação de produção incluindo `/app/sincronizacao`.

Status Sprint 111:

- `platform_connections.import_rules` guarda regras visuais por conexão;
- `/app/regras-de-importacao` permite configurar origem, obrigatoriedade, fallback e normalizações para categoria, marca, gênero, faixa etária, status e modelagem;
- `BigShopDryRunService` exibe campos mapeados e alertas de regra obrigatória sem gravar catálogo;
- `BigShopSyncService` aplica as regras em produtos, metadados e tabelas criadas pela API;
- `XmlFeedSyncService` repassa as mesmas regras para importações Google XML/feed.

Detalhes completos do benchmark Sizebay/Zak: `docs/sizebay_zak_hyper_benchmark.md`.

## Integração de um clique

Fluxo desejado:

1. Lojista BigShop abre Provador Virtual no painel BigShop.
2. BigShop envia usuário/loja autenticados para o Provador Virtual ou abre iframe/admin interno.
3. Provador Virtual cria/atualiza `merchant`, `company` e `platform_connection`.
4. Token e `store_id` são recebidos por OAuth/app interno ou secret seguro.
5. Sistema roda probe `GET /v3/getEndPoints`.
6. Sistema sincroniza produtos e grades.
7. Lojista escolhe/cria tabela de medidas.
8. BigShop ativa o widget no tema sem colar código manual.

## Encaixe no front BigShop

O front da loja BigShop possui página de produto em:

- `bigshop/front/stores/pro_store/produto.vue`
- ponto confirmado para sprint futura: `produto.vue` da model3 plano pro, no repositório BigShop correto.
- cópia local aplicada na Sprint 58: `D:\Projetos\bigshop\172.16.151.2\bigshop\model3\stores\pro_store\produto.vue`.

Pontos relevantes:

- grade/tamanho aparece em `productSizes`;
- produto tem `tabela_de_medidas`;
- ha area de tabela de medidas perto da compra;
- o widget pode ser renderizado perto do seletor de tamanho ou antes do botão de compra;
- o container deve ficar exatamente onde os botões "Descubra seu tamanho" e "Tabela de Medidas" devem aparecer;
- para cada troca de grade, atualizar `data-product-id`, `data-variant-id` e `data-sku`;
- depois da troca de grade, chamar `window.ProvadorVirtual?.reload(...)` para executar novo `config-check`.
- não fixar IDs internos do SaaS no front compartilhado; enviar `data-store-id` com o `loja.id` BigShop e deixar o Provador Virtual resolver a empresa pelo `external_store_id`;
- esconder a tabela nativa BigShop somente quando o widget responder `configured=true`.

Detalhes operacionais do modelo pro estão em `docs/bigshop_model3_pro_widget.md`.

Exemplo de recarregamento no front BigShop:

```js
window.ProvadorVirtual?.reload({
  productId: product.id,
  variantId: selectedGrade.id,
  sku: selectedGrade.sku
})
```

## Fallback por snippet

Enquanto o um clique não existir, gerar snippet no painel Provador Virtual com:

- `data-platform="bigshop"`;
- `data-store-id` BigShop;
- `data-product-id` BigShop;
- `data-variant-id` grade BigShop;
- `data-sku` SKU da grade.

## Lacunas a confirmar na BigShop

- contrato formal do token e escopos;
- expiracao/rotação do token;
- paginação e filtros oficiais;
- endpoint claro para grades por produto;
- payload estruturado de tabela de medidas;
- webhook de produto/grade alterados, se quisermos sync quase em tempo real;
- sandbox;
- limites/rate limit;
- formato padrão de erros.

## Pedidos, trocas e devolucoes

Pedidos, trocas e devolucoes não são obrigatórios para o Provador Virtual funcionar no PDP. O fluxo essencial e:

1. catálogo de produto/grade;
2. tabela de medidas;
3. widget na página de produto com produto, variação e SKU corretos.

A integração de pedidos/trocas/devolucoes entra em uma fase posterior de analytics e aprendizado, parecida com a abordagem da Sizebay. Ela permite medir conversão, add-to-cart, compra, troca/devolucao por tamanho e qualidade da recomendação. Portanto, para BigShop piloto, isso pode ficar fora do escopo inicial.

## Padrão simples de assinatura

Para ativação one-click e qualquer webhook futuro, usar o padrão simples já documentado:

- `X-BigShop-Timestamp`: Unix timestamp;
- `X-BigShop-Signature`: `sha256=<hmac>`;
- base da assinatura: `timestamp + "." + raw_body`;
- algoritmo: HMAC-SHA256;
- segredo: um único secret configurado no SaaS e na BigShop.

## Primeiro teste recomendado

Usar uma loja BigShop controlada, com um produto de moda e pelo menos tres variações de tamanho. Validar:

- probe remoto;
- sync de produto/grade;
- criação de tabela no Provador Virtual;
- página `/produto-teste` usando dados sincronizados;
- widget no front BigShop por snippet;
- depois planejar um clique.

## Sprint 7 implementada

Rotas protegidas:

- `POST /api/v1/integrations/bigshop/probe`
- `POST /api/v1/integrations/bigshop/sync`

Comportamento:

- probe chama `GET /v3/getEndPoints` com headers `x-api` e `Store-Id`;
- sync chama `GET /v3/products` e normaliza retornos diretos ou envelopes paginados;
- produtos BigShop são upsertados em `products`;
- grades são upsertadas em `product_variants`;
- quando o payload traz `measurement_table`, `tabela_de_medidas` ou `medidas` estruturado, o sistema cria/atualiza `measurement_tables`;
- cada probe/sync registra `integration_events`;
- relatório retorna produtos, variações, tabelas sincronizadas e lacunas.

Sem credencial real, validação automatizada usa `Http::fake`. Para teste real ainda falta loja controlada, `store_id` e token `x-api`.

## Sprint 8 implementada - ativação um clique

Rota pública assinada:

- `POST /api/v1/public/bigshop/activate`

Headers obrigatórios:

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

- cria ou atualiza usuário, lojista, empresa, conexão BigShop e instalação do widget;
- aceita `store_domain` ou `store_url`, derivando o domínio quando vier URL completa;
- salva token e webhook secret criptografados, sem retornar esses valores em claro;
- cria `widget_public_key` para uso no snippet;
- registra evento `one_click_activation` em `integration_events`;
- retorna `dashboard_url`, `widget_url`, `widget_public_key` e status da ativação.
- Sprint 35 adicionou `install_snippet`, `integration_contract` versao `2026-05-23` e monitor protegido `GET /api/v1/integrations/bigshop/activations`.

Quando `BIGSHOP_ACTIVATION_SECRET` não estiver configurado, a rota retorna `503` e não processa o payload.

Para teste real ainda falta cadastrar `BIGSHOP_ACTIVATION_SECRET` em `PRODUCTION_ENV` e receber da BigShop o payload/secret oficial do app interno.

Contrato final detalhado: `docs/bigshop_one_click_contract.md`.
