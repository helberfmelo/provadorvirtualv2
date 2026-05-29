# Arquitetura Tecnica

Atualizado em: 2026-05-25

## Estrutura alvo do repositório

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
- HTTP client Laravel para integrações.

Módulos:

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

APIs protegidas já implementadas:

- `GET /api/v1/merchant/overview`: agrega resumo do painel, cobertura do catálogo, pendências operacionais, próximas ações e evolução de cobertura por empresa ativa.
- `GET /api/v1/billing/subscription`
- `PATCH /api/v1/billing/subscription/auto-renewal`
- `GET|POST|PATCH|DELETE /api/v1/products`: a listagem é paginada no backend e aceita filtros por busca, status, tabela, categoria, marca, gênero, faixa etária, modelagem, origem, erro de sincronização e prontidão; a resposta inclui contadores por aba e opções de filtros. O detalhe expõe ativação individual, origem por campo, snapshot importado, overrides manuais, diagnóstico e histórico; updates preservam dados importados em `metadata`, registram ajustes manuais e geram auditoria para ativação/override. O vínculo em massa de tabela usa `PATCH /api/v1/products/bulk-measurement-table` com `action=preview|apply|undo`, prévia de produtos afetados, conflitos, recomendação de tabela por categoria/marca/gênero/modelagem/tamanhos, confirmação para substituir vínculo existente, `batch_id` reversível e auditoria.
- `POST|PATCH|DELETE /api/v1/products/{product}/variants`
- `GET|POST|PATCH|DELETE /api/v1/measurement-tables`: a listagem aceita filtros por busca, status, base de medida, tipo de produto, modelagem e uso em produtos; a resposta inclui resumo e opções de filtro.
- `GET /api/v1/measurement-tables/export`: exporta CSV ou XLSX respeitando os filtros da listagem.
- `GET /api/v1/measurement-tables/template`: baixa modelo CSV ou XLSX para tabelas de corpo, peça ou mistas.
- `POST /api/v1/measurement-tables/import/preview`: valida CSV/XLSX antes de gravar e retorna erros com linha, coluna, campo e sugestão.
- `POST /api/v1/measurement-tables/import`: cria ou atualiza tabelas por nome no escopo da empresa ativa, substitui linhas somente após prévia sem falhas e audita `measurement_table.imported`.
- `measurement_tables.metadata`: guarda `activation.virtual_try_on_enabled`, `custom_variations` e futuras configurações avançadas do editor sem alterar o contrato antigo das linhas.
- `GET /api/v1/measurement-templates`: retorna templates inteligentes normalizados a partir de `backend/database/data/default_measurement_tables_data.json`, herdado do v1, com base brasileira por gênero, tipo de produto, altura, peso, idade e formato corporal.
- `GET|PATCH /api/v1/widget-install`
- `POST /api/v1/public/bigshop/activate`
- `GET /api/v1/integrations`
- `PATCH /api/v1/integrations/{platform}`
- `POST /api/v1/integrations/{platform}/validate-install`
- `GET /api/v1/integrations/bigshop/activations`
- `POST /api/v1/integrations/bigshop/probe`
- `POST /api/v1/integrations/bigshop/sync`
- `GET|POST /api/v1/imports`
- `POST /api/v1/imports/preview`
- `GET /api/v1/imports/{importJob}`
- `GET /api/v1/ai/status`
- `POST /api/v1/ai/measurement-table-suggestions`
- `GET /api/v1/analytics/recommendations`
- `GET /api/v1/audit-logs`
- `GET /api/v1/go-live/readiness`
- `GET /api/v1/saas/overview`
- `GET /api/v1/saas/merchants`
- `GET|POST /api/v1/saas/companies`
- `PATCH /api/v1/saas/companies/{company}`
- `GET|PATCH /api/v1/saas/email-settings`
- `GET|PATCH /api/v1/saas/checkout-settings`
- `GET|POST /api/v1/saas/transactional-emails`
- `PATCH /api/v1/saas/transactional-emails/{transactionalEmail}`
- `GET /api/v1/saas/transactional-email-sends`
- `GET /api/v1/ops/status`

APIs públicas adicionais:

- `POST /api/v1/public/company-access`
- `GET /api/v1/public/checkout/config`
- `POST /api/v1/public/checkout`
- `GET /api/v1/public/checkout/{reference}`
- `POST /api/v1/webhooks/pagarme`
- `POST /api/v1/webhooks/mercado-pago`

O config-check e a recomendação pública do widget respeitam `products.status`, `products.metadata.activation.virtual_try_on_enabled`, `products.metadata.activation.measurement_table_enabled`, `measurement_tables.metadata.activation.virtual_try_on_enabled` e o vínculo de tabela. Quando bloqueado por produto, retornam `configured=false` com `reason` explícito, como `virtual_try_on_disabled`, `measurement_table_disabled`, `product_inactive` ou `measurement_table_missing`. Quando a tabela vinculada desativa apenas o provador, `config-check` retorna `configured=true`, `virtual_try_on_enabled=false` e a tabela normalizada; o widget mostra somente `Tabela de Medidas` e `POST /public/recommendations` retorna `table_virtual_try_on_disabled`.

## Autenticacao e multiempresa

- `POST /api/v1/auth/login` aceita `login` com e-mail ou CPF, além do alias legado `email`.
- O portal da empresa deve enviar `company_access` com código da loja ou CNPJ.
- Usuários admin/suporte podem entrar sem empresa para operar o SaaS.
- Usuários lojistas com mais de uma empresa precisam informar `company_access`; usuário de uma unica empresa segue com fallback automático para compatibilidade.
- O token Sanctum recebe abilities `merchant:{id}` e `company:{id}` quando a empresa e resolvida.
- APIs do portal usam `ActiveTenant` para respeitar o lojista ativo do token, em vez de assumir o primeiro vinculo do usuário.
- Checkout e cadastro interno reaproveitam usuário por e-mail ou CPF, permitindo que a mesma pessoa participe de varias empresas sem duplicar cadastro.

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
- `/saas/login`
- `/cadastro`
- `/produto-teste`
- `/produto-teste/:slug`
- `/checkout`
- `/checkout/sucesso`
- `/app`
- `/app/produtos`
- `/app/produtos/novo`
- `/app/produtos/:id/editar`
- `/app/tabelas-de-medidas`
- `/app/tabelas-de-medidas/nova`
- `/app/tabelas-de-medidas/:id/editar`
- `/app/assistente`
- `/app/analytics`
- `/app/integracoes`
- `/app/widget`
- `/app/usuarios`
- `/app/usuarios/novo`
- `/app/usuarios/:id/editar`
- `/saas`
- `/saas/empresas`
- `/saas/empresas/nova`
- `/saas/empresas/:id/editar`
- `/saas/usuarios`
- `/saas/usuarios/novo`
- `/saas/usuarios/:id/editar`
- `/saas/usuarios-empresas`
- `/saas/usuarios-empresas/novo`
- `/saas/usuarios-empresas/:id/editar`
- `/saas/checkout`
- `/saas/emails`
- `/saas/emails/configuracoes`
- `/saas/emails/novo`
- `/saas/emails/:id/editar`

## Widget

O widget deve ser buildado de forma separada ou exposto pelo backend em `public/widget/v1`.

Requisitos:

- bundle pequeno;
- escopo CSS próprio;
- sem dependencia global;
- API base configuravel;
- fallback visual seguro;
- suporte a container inline ou botão fixo;
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
- `shopper_profiles`
- `recommendation_learning_events`
- `import_jobs`
- `integration_events`
- `ai_usage_logs`
- `audit_logs`
- `checkout_sessions`
- `checkout_acceptances`
- `billing_subscriptions`
- `payment_events`
- `saas_settings`
- `email_settings`
- `transactional_emails`
- `transactional_email_sends`

Chaves comuns:

- `merchant_id`
- `company_id`
- `platform`
- `external_store_id`
- `external_product_id`
- `external_variant_id`
- `sku`

## Fluxo de recomendação

1. Página de produto carrega o widget com dados de produto/loja.
2. Widget chama `config-check`.
3. Se produto estiver configurado, mostra `Descubra seu tamanho` e `Tabela de Medidas`.
4. Consumidor pode abrir tabela do produto ou informar medidas.
5. Widget reusa medidas salvas localmente, com aviso e edição livre.
6. API normaliza entrada e busca tabela.
7. Motor calcula pontuacao por tamanho.
8. API resolve ou cria perfil anônimo com consentimento e token local.
9. API salva log anônimo, calcula `outlier_score` e cria evento de aprendizado.
10. Widget exibe recomendação, permite editar medidas e coleta feedback.
11. Feedback, compra, devolucao e troca entram como `recommendation_learning_events`.

## Checkout transparente

- `CheckoutPaymentManager` escolhe a operadora ativa por `saas_settings.checkout.payment_provider`, com fallback em `CHECKOUT_PAYMENT_PROVIDER`.
- O painel SaaS em `/saas/checkout` permite alternar entre `mercado_pago` e `pagarme`.
- Mercado Pago e a operadora ativa de produção: cartão usa MercadoPago.js/CardForm no navegador, Pix usa `POST /v1/payments` e webhook em `POST /api/v1/webhooks/mercado-pago`.
- Pagar.me permanece preservada como operadora alternativa: cartão e tokenizado no navegador com `PAGARME_PUBLIC_KEY`, backend cria pedido em `POST /orders` Core v5 e webhook segue em `POST /api/v1/webhooks/pagarme`.
- Backend nunca recebe PAN/CVV; recebe apenas token de cartão da operadora.
- Checkout público oferece planos mensal e anual por plataforma. Valores atuais: qualquer plataforma mensal `R$ 489,80`, BigShop mensal `R$ 389,80`, qualquer plataforma anual `R$ 449,80/mes` e BigShop anual `R$ 349,90/mes`; nos anuais, o total anual e a economia percentual são retornados pela API.
- Cartão permanece priorizado quando disponível; o anual pode parcelar em até 10x sem juros e Pix à vista mantém desconto operacional quando aplicável.
- Boleto fica oculto por padrão e depende de `saas_settings.checkout.boleto_enabled`; quando habilitado com Mercado Pago, usa `payment_method_id=bolbradesco`.
- Pix retorna QR Code/copia e cola/ticket na tela `/checkout/sucesso`; boleto retorna link/linha digitável quando a operadora envia esses dados.
- Empresa nasce como `pending_payment` e e ativada por retorno imediato pago, webhook ou sincronização.
- O comando `php artisan pv:payments-sync --limit=50` consulta pagamentos pendentes nas operadoras e ativa empresas pagas quando webhook falhar ou atrasar.
- O scheduler executa o monitor de pagamentos a cada 5 minutos.
- O comando `php artisan pv:integrations-sync-feeds --limit=50` sincroniza XML/feed de integrações configuradas 4 vezes por dia pelo scheduler.

## E-mails transacionais

- `email_settings` guarda as credenciais SMTP do SaaS; senha usa cast `encrypted` e nunca volta em claro pela API.
- `transactional_emails` guarda templates editaveis para cadastro, pagamento confirmado, aguardando pagamento, erro de pagamento, recuperacao de senha e renovacao.
- As telas `/saas/emails`, `/saas/emails/configuracoes`, `/saas/emails/novo` e `/saas/emails/:id/editar` separam listagem, credenciais SMTP e formulários de templates.
- Sprint 31 criou `TransactionalEmailService`, histórico `transactional_email_sends`, disparos por checkout/pagamento e comando `php artisan pv:emails-dispatch --limit=50`.
- O scheduler executa o dispatcher de e-mails a cada 10 minutos; Pix pendente pode ser reenviado após 6 horas sem duplicar confirmacoes/erros já enviados.

## Diretrizes de UX autenticada

- CRUDs devem seguir o padrão list-first: listagem na rota base, cadastro em `/novo` e edição em `/:id/editar`.
- SaaS e portal da empresa usam menus separados; links entre contextos são secundarios.
- O menu autenticado deve abrir em drawer no mobile.
- Documento de referência: `docs/portal_ui_guidelines.md`.

## Motor de recomendação

Entradas:

- medidas do consumidor;
- gênero e formato corporal;
- tipo de peça;
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

Camada de aprendizado Sprint 36:

- `shopper_profiles` guarda medidas/preferencias pseudonimizadas, somente com consentimento;
- `RecommendationLearningEvent` registra recomendação, feedback, compra, devolucao e troca;
- `LearningSignalService` calcula `outlier_score`, `learning_status` e `learning_weight`;
- sinais `blocked_outlier` ficam disponíveis no analytics, mas não devem alimentar ajustes automaticos sem revisão.

## Integração BigShop

Conector deve mapear:

- produto BigShop -> `products`;
- grade BigShop -> `product_variants`;
- tabela de medidas BigShop -> `measurement_tables` quando houver dados estruturados;
- imagem de tabela -> backlog de OCR/assistencia;
- estoque/status -> campos informativos, sem promessa no widget se não for necessário.

Regra comercial atual:

- `merchant_companies.platform` define a plataforma operacional exibida nos guias e snippets;
- `merchant_companies.bigshop_discount_active` define se a loja tem benefício comercial BigShop;
- empresas sem benefício BigShop podem trocar a plataforma operacional diretamente em `/app/integracoes`, inclusive para BigShop sem desconto;
- empresas BigShop com benefício ativo não trocam para outra plataforma diretamente: o portal cria `integration_change_requests` com aceite dos termos, e o SaaS revisa pagamento/diferença antes de aplicar a troca;
- enquanto a plataforma operacional for BigShop, `GET /integrations`, validação e widget exibem o guia BigShop; após a troca, a página se adapta ao novo catálogo da plataforma.

## Ambientes

Local:

- `http://localhost:8000` backend;
- `http://localhost:5173` frontend;
- MySQL local `provadorvirtual_v2`.

Produção inicial:

- `https://provadorvirtual.online/provadorvirtual_v2/`;
- pasta remota: `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`;
- banco: `opents62_provadorvirtual_v2`.

## Observabilidade

- `GET /api/v1/health`;
- `GET /api/v1/ops/status`;
- `GET /up` do Laravel;
- logs Laravel;
- tabela `integration_events`;
- tabela `recommendation_logs`;
- tabela `recommendation_learning_events`;
- tabela `shopper_profiles`;
- tabela `ai_usage_logs`;
- tabela `audit_logs` para ações sensíveis.

## Evolucao inteligente planejada

Documentos de referência:

- `docs/v1_intelligence_migration.md`
- `docs/sizebay_benchmark.md`
- `docs/data_learning_lgpd_outliers.md`
- `docs/intelligent_sizing_roadmap.md`

Novos módulos previstos:

- `StandardMeasurementCatalog`
- `MeasurementQuality`
- `ShopperProfiles`
- `LearningSignals`
- `OutlierDetection`
- `PlatformInstallers`

Tabelas da camada inteligente:

- `shopper_profiles` - implementada na Sprint 36;
- `shopper_profile_versions`
- `recommendation_events`
- `recommendation_learning_events` - implementada na Sprint 36;
- `merchant_table_quality_checks`
- `measurement_catalog_sources`
- `learning_cohorts`

## Hardening Sprint 11

- CORS global restrito a origens locais de desenvolvimento via `CORS_ALLOWED_ORIGINS`.
- Rotas públicas do widget usam middleware próprio para validar `Origin` contra domínios ativos do lojista.
- Rate limit inicial aplicado em login, recomendação pública, feedback, ativação BigShop e status operacional.
- Comandos `pv:privacy-anonymize` e `pv:privacy-prune` fazem retenção operacional sem apagar analytics de recomendação.

## Go-live Sprint 12

- `/api/v1/go-live/readiness` consolida checks de produto, tabela, widget, recomendação, BigShop, IA, legal e cutover.
- `/app/go-live` exibe status operacional para o lojista antes de liberar trafego real.
- `scripts/validate-production.ps1` roda smokes públicos e autenticados contra produção.
