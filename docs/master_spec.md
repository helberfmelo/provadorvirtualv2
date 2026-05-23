# Master Spec - Provador Virtual

Atualizado em: 2026-05-23

## Produto

O Provador Virtual recomenda tamanho de roupas em lojas virtuais usando:

- cadastro de produtos;
- grade/variacoes;
- tabelas de medidas;
- dados corporais informados pelo consumidor;
- motor de recomendacao deterministico;
- IA opcional para acelerar criacao e manutencao das tabelas.

## Escopo do v2

O v2 nao deve ser um MVP simplista, mas tambem nao deve virar uma plataforma inchada. O alvo e um produto SaaS enxuto, confiavel e comercializavel.

### Inclui

- autenticacao e multiempresa;
- painel do lojista;
- administracao SaaS basica;
- CRUD de produtos e tabelas de medidas;
- importacao inicial por CSV/XML/feed/API;
- widget universal;
- pagina de produto ficticia testavel;
- integracao BigShop nativa;
- logs de recomendacao e feedback;
- analytics inicial;
- deploy com migrations e smoke tests;
- documentacao por plataforma.

### Fora do primeiro ciclo

- marketplace proprio de apps;
- app nativo mobile;
- machine learning treinado com grande volume real;
- billing completo com antifraude complexo;
- integracoes profundas com todas as plataformas no primeiro release.

## Entidades principais

- `users`: usuarios autenticados do SaaS e lojistas.
- `merchants`: lojistas/contas.
- `merchant_user`: vinculo do usuario com lojista, status por empresa e permissoes do portal.
- `merchant_companies`: empresas ou lojas vinculadas ao lojista.
- `platform_connections`: conexoes com BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray e custom.
- `products`: produtos canonicos no Provador Virtual.
- `product_variants`: variacoes/grades, com SKU, tamanho, cor e identificador externo.
- `measurement_tables`: tabelas de medidas do lojista.
- `measurement_table_rows`: tamanhos e faixas por medida.
- `measurement_templates`: modelos padrao por genero, tipo de peca e modelagem.
- `widget_installs`: configuracao por loja/canal.
- `recommendation_sessions`: sessoes anonimas do consumidor.
- `recommendation_logs`: recomendacoes geradas.
- `recommendation_feedbacks`: feedback de utilidade.
- `import_jobs`: importacoes por feed/API.
- `integration_events`: eventos de sync/webhook.
- `audit_logs`: acoes sensiveis.

## Regras de recomendacao

1. Produto sem tabela de medidas nao deve exibir promessa de recomendacao.
2. O widget pode fazer `config_check` antes de aparecer.
3. A recomendacao deve priorizar medidas reais informadas pelo usuario.
4. Altura/peso servem como estimativa quando medidas detalhadas faltarem.
5. Cada resultado deve trazer tamanho, confianca, motivo curto e alertas de ajuste.
6. Empates devem ser tratados com regra explicavel.
7. O sistema nunca deve inventar disponibilidade, estoque, preco ou prazo.
8. Logs devem ser anonimizados o suficiente para LGPD e analytics.

## APIs internas

Base interna: `/api/v1`

Endpoints esperados:

- `POST /auth/login`
- `POST /auth/logout`
- `GET /me`
- `GET /merchant/overview`
- `GET|POST|PATCH /merchant/users`
- `GET|POST|PATCH /saas/users`
- `GET|POST|PUT|DELETE /products`
- `GET|POST|PUT|DELETE /products/{id}/variants`
- `GET|POST|PUT|DELETE /measurement-tables`
- `POST /measurement-tables/import`
- `POST /recommendations`
- `POST /recommendations/config-check`
- `POST /recommendations/{id}/feedback`
- `GET|POST|PUT /integrations/bigshop`
- `POST /integrations/bigshop/sync`
- `POST /webhooks/bigshop`
- `GET /analytics/recommendations`
- `GET /health`

## Widget publico

Base publica sugerida:

- `GET /widget/v1/provador-virtual.js`
- `GET /widget/v1/provador-virtual.css`, quando CSS nao estiver embutido.
- `POST /api/v1/public/recommendations/config-check`
- `POST /api/v1/public/recommendations`
- `POST /api/v1/public/recommendations/{id}/feedback`

O widget deve aceitar atributos:

- `data-merchant-id`
- `data-store-id`
- `data-product-id`
- `data-variant-id`
- `data-sku`
- `data-platform`
- `data-container-id`
- `data-theme`

## Pagina de produto ficticia

Rota obrigatoria:

- `/produto-teste`

Ela deve:

- parecer uma pagina real de e-commerce de moda;
- usar produto, variacoes e tabela de medidas reais seedados;
- carregar o mesmo widget publico usado por lojas externas;
- permitir testar recomendacao, feedback e config-check;
- servir como smoke funcional depois do deploy.

## Integracoes por plataforma

### BigShop

Prioridade 1. Deve ter:

- cadastro de conexao com `store_id`, base da API e token;
- sync de produtos, variacoes e tabelas de medidas quando disponivel;
- snippet automatico ou modulo nativo no front da BigShop;
- objetivo de um clique para lojas BigShop.

### Plataformas default

Guias e snippets:

- Shopify;
- WooCommerce;
- Nuvemshop;
- VTEX;
- Tray;
- custom/universal.

## IA

IA e opcional no nucleo da recomendacao e obrigatoria apenas quando uma sprint pedir:

- OCR de imagem de tabela de medidas;
- extracao de tabela colada em texto/PDF;
- sugestao de modelo de tabela;
- analise de feedback;
- copy assistida de guias.

Provider recomendado: OpenAI por alinhamento com os projetos recentes. Gemini pode ser suportado por legado do v1.

## Regras comerciais iniciais

- Sem comeco gratuito no checkout publico atual.
- Plano comercial publico unico: anual, `R$ 189,90/mes`, cartao em ate 12x ou Pix a vista com 5% de desconto.
- Cliente BigShop tem preco especial: `R$ 129,90/mes` no plano anual.
- Painel deve mostrar bloqueio funcional quando assinatura estiver inativa.

## Criterios de pronto do release inicial

- app Laravel/Vue rodando local;
- banco com migrations e seeders;
- produto teste funcional;
- widget funcional em pagina propria e via snippet externo;
- CRUD de produtos/tabelas;
- recomendacao com confianca;
- guia de instalacao por plataforma;
- BigShop com pelo menos sync/probe e plano de um clique validado;
- deploy Actions verde;
- smoke publico verde.
