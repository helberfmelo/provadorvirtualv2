# Arquitetura Tecnica

Atualizado em: 2026-05-23

## Estrutura alvo do repositorio

```txt
provadorvirtual_v2/
  backend/                  # Laravel
    app/
    database/
    routes/
    tests/
    public/
  frontend/                 # Vue 3 + Vite
    src/
    public/
  docs/
  .github/workflows/
```

## Backend

Stack:

- Laravel 11+;
- Sanctum;
- MySQL/MariaDB;
- Queue database ou sync inicialmente, com interface para trocar;
- Mail SMTP;
- HTTP client Laravel para integracoes.

Modulos:

- Auth;
- Merchants/Companies;
- Products/Variants;
- MeasurementTables/Templates;
- Recommendation;
- Widget/Public API;
- PlatformIntegrations;
- BigShop;
- Analytics;
- SaaS Admin;
- Audit/Logs.

APIs protegidas ja implementadas:

- `GET /api/v1/merchant/overview`
- `GET|POST|PATCH|DELETE /api/v1/products`
- `POST|PATCH|DELETE /api/v1/products/{product}/variants`
- `GET|POST|PATCH|DELETE /api/v1/measurement-tables`
- `GET /api/v1/measurement-templates`
- `GET|PATCH /api/v1/widget-install`
- `POST /api/v1/public/bigshop/activate`
- `GET /api/v1/integrations`
- `PATCH /api/v1/integrations/{platform}`
- `POST /api/v1/integrations/bigshop/probe`
- `POST /api/v1/integrations/bigshop/sync`
- `GET|POST /api/v1/imports`
- `POST /api/v1/imports/preview`
- `GET /api/v1/imports/{importJob}`
- `GET /api/v1/ai/status`
- `POST /api/v1/ai/measurement-table-suggestions`
- `GET /api/v1/analytics/recommendations`
- `GET /api/v1/audit-logs`
- `GET /api/v1/saas/overview`
- `GET /api/v1/saas/merchants`

## Frontend

Stack:

- Vue 3;
- TypeScript;
- Vite;
- Pinia;
- Vue Router;
- Axios;
- Font Awesome e/ou lucide.

Rotas iniciais:

- `/`
- `/como-funciona`
- `/planos`
- `/login`
- `/cadastro`
- `/produto-teste`
- `/app`
- `/app/produtos`
- `/app/tabelas-de-medidas`
- `/app/assistente`
- `/app/analytics`
- `/app/integracoes`
- `/app/widget`
- `/saas`

## Widget

O widget deve ser buildado de forma separada ou exposto pelo backend em `public/widget/v1`.

Requisitos:

- bundle pequeno;
- escopo CSS proprio;
- sem dependencia global;
- API base configuravel;
- fallback visual seguro;
- suporte a container inline ou botao fixo;
- compatibilidade com SPA de terceiros.

## Banco de dados inicial

Tabelas propostas:

- `users`
- `merchants`
- `merchant_users`
- `merchant_companies`
- `platform_connections`
- `products`
- `product_variants`
- `measurement_templates`
- `measurement_tables`
- `measurement_table_rows`
- `widget_installs`
- `recommendation_sessions`
- `recommendation_logs`
- `recommendation_feedbacks`
- `import_jobs`
- `integration_events`
- `ai_usage_logs`
- `audit_logs`

Chaves comuns:

- `merchant_id`
- `company_id`
- `platform`
- `external_store_id`
- `external_product_id`
- `external_variant_id`
- `sku`

## Fluxo de recomendacao

1. Pagina de produto carrega o widget com dados de produto/loja.
2. Widget chama `config-check`.
3. Se produto estiver configurado, mostra botao.
4. Consumidor informa medidas.
5. API normaliza entrada e busca tabela.
6. Motor calcula pontuacao por tamanho.
7. API salva log anonimo e retorna resultado.
8. Widget exibe recomendacao e coleta feedback.

## Motor de recomendacao

Entradas:

- medidas do consumidor;
- genero e formato corporal;
- tipo de peca;
- tabela de medidas;
- tolerancias por medida;
- prioridade das medidas por categoria.

Saidas:

- `recommended_size`;
- `confidence`;
- `fit_notes`;
- `warnings`;
- `score_breakdown`;
- `needs_more_data`.

## Integracao BigShop

Conector deve mapear:

- produto BigShop -> `products`;
- grade BigShop -> `product_variants`;
- tabela de medidas BigShop -> `measurement_tables` quando houver dados estruturados;
- imagem de tabela -> backlog de OCR/assistencia;
- estoque/status -> campos informativos, sem promessa no widget se nao for necessario.

## Ambientes

Local:

- `http://localhost:8000` backend;
- `http://localhost:5173` frontend;
- MySQL local `provadorvirtual_v2`.

Producao inicial:

- `https://provadorvirtual.online/provadorvirtual_v2/`;
- pasta remota: `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`;
- banco: `opents62_provadorvirtual_v2`.

## Observabilidade

- `GET /api/v1/health`;
- `GET /up` do Laravel;
- logs Laravel;
- tabela `integration_events`;
- tabela `recommendation_logs`;
- tabela `ai_usage_logs`;
- tabela `audit_logs` para acoes sensiveis.
