# Master Spec - Provador Virtual

Atualizado em: 2026-05-23

## Produto

O Provador Virtual recomenda tamanho de roupas em lojas virtuais usando:

- cadastro de produtos;
- grade/variações;
- tabelas de medidas;
- dados corporais informados pelo consumidor;
- motor de recomendação determinístico;
- IA opcional para acelerar criação e manutencao das tabelas.

## Escopo do v2

O v2 não deve ser um MVP simplista, mas também não deve virar uma plataforma inchada. O alvo e um produto SaaS enxuto, confiavel e comercializavel.

### Inclui

- autenticacao e multiempresa;
- painel do lojista;
- administracao SaaS basica;
- CRUD de produtos e tabelas de medidas;
- importacao inicial por CSV/XML/feed/API;
- widget universal;
- página de produto ficticia testavel;
- integração BigShop nativa;
- logs de recomendação e feedback;
- analytics inicial;
- deploy com migrations e smoke tests;
- documentação por plataforma.

### Fora do primeiro ciclo

- marketplace próprio de apps;
- app nativo mobile;
- machine learning treinado com grande volume real;
- billing completo com antifraude complexo;
- integrações profundas com todas as plataformas no primeiro release.

## Entidades principais

- `users`: usuários autenticados do SaaS e lojistas.
- `merchants`: lojistas/contas.
- `merchant_user`: vinculo do usuário com lojista, status por empresa e permissões do portal.
- `merchant_companies`: empresas ou lojas vinculadas ao lojista.
- `platform_connections`: conexões com BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray e custom.
- `products`: produtos canonicos no Provador Virtual.
- `product_variants`: variações/grades, com SKU, tamanho, cor e identificador externo.
- `measurement_tables`: tabelas de medidas do lojista.
- `measurement_table_rows`: tamanhos e faixas por medida.
- `measurement_templates`: modelos padrão por gênero, tipo de peça e modelagem.
- `widget_installs`: configuração por loja/canal.
- `recommendation_sessions`: sessões anonimas do consumidor.
- `recommendation_logs`: recomendações geradas.
- `recommendation_feedbacks`: feedback de utilidade.
- `import_jobs`: importacoes por feed/API.
- `integration_events`: eventos de sync/webhook.
- `audit_logs`: ações sensíveis.

## Regras de recomendação

1. Produto sem tabela de medidas não deve exibir promessa de recomendação.
2. O widget pode fazer `config_check` antes de aparecer.
3. A recomendação deve priorizar medidas reais informadas pelo usuário.
4. Altura/peso servem como estimativa quando medidas detalhadas faltarem.
5. Cada resultado deve trazer tamanho, confiança, motivo curto e alertas de ajuste.
6. Empates devem ser tratados com regra explicavel.
7. O sistema nunca deve inventar disponibilidade, estoque, preço ou prazo.
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

## Widget público

Base pública sugerida:

- `GET /widget/v1/provador-virtual.js`
- `GET /widget/v1/provador-virtual.css`, quando CSS não estiver embutido.
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

## Página de produto ficticia

Rota obrigatória:

- `/produto-teste`

Ela deve:

- parecer uma página real de e-commerce de moda;
- usar produto, variações e tabela de medidas reais seedados;
- carregar o mesmo widget público usado por lojas externas;
- permitir testar recomendação, feedback e config-check;
- servir como smoke funcional depois do deploy.

## Integrações por plataforma

### BigShop

Prioridade 1. Deve ter:

- cadastro de conexão com `store_id`, base da API e token;
- sync de produtos, variações e tabelas de medidas quando disponível;
- snippet automático ou módulo nativo no front da BigShop;
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

IA e opcional no nucleo da recomendação e obrigatória apenas quando uma sprint pedir:

- OCR de imagem de tabela de medidas;
- extracao de tabela colada em texto/PDF;
- sugestão de modelo de tabela;
- análise de feedback;
- copy assistida de guias.

Provider recomendado: OpenAI por alinhamento com os projetos recentes. Gemini pode ser suportado por legado do v1.

## Regras comerciais iniciais

- Sem comeco gratuito no checkout público atual.
- Planos públicos por ciclo e plataforma:
  - qualquer plataforma mensal: `R$ 489,80/mes`;
  - cliente BigShop mensal: `R$ 389,80/mes`;
  - qualquer plataforma anual: `R$ 449,80/mes`, com total anual e economia versus mensal;
  - cliente BigShop anual: `R$ 349,90/mes`, com total anual e economia versus mensal.
- Em todo plano anual, o preço em destaque deve ser o valor mensal equivalente; o total anual e o percentual de economia devem aparecer próximos ao destaque.
- Checkout público deve exigir aceite dos termos e da política de privacidade, salvar prova técnica com IP, user-agent, usuário, empresa, data/hora, versões legais e contexto do pedido.
- Site e sistema devem exibir aviso discreto de cookies técnicos/localStorage até o usuário confirmar.
- Painel deve mostrar bloqueio funcional quando assinatura estiver inativa.

## Criterios de pronto do release inicial

- app Laravel/Vue rodando local;
- banco com migrations e seeders;
- produto teste funcional;
- widget funcional em página própria e via snippet externo;
- CRUD de produtos/tabelas;
- recomendação com confiança;
- guia de instalação por plataforma;
- BigShop com pelo menos sync/probe e plano de um clique validado;
- deploy Actions verde;
- smoke público verde.
