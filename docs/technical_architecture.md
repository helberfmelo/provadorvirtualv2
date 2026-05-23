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
- `GET|POST /api/v1/saas/transactional-emails`
- `PATCH /api/v1/saas/transactional-emails/{transactionalEmail}`
- `GET /api/v1/saas/transactional-email-sends`
- `GET /api/v1/ops/status`

APIs publicas adicionais:

- `POST /api/v1/public/company-access`
- `GET /api/v1/public/checkout/config`
- `POST /api/v1/public/checkout`
- `GET /api/v1/public/checkout/{reference}`
- `POST /api/v1/webhooks/pagarme`

## Autenticacao e multiempresa

- `POST /api/v1/auth/login` aceita `login` com e-mail ou CPF, alem do alias legado `email`.
- O portal da empresa deve enviar `company_access` com codigo da loja ou CNPJ.
- Usuarios admin/suporte podem entrar sem empresa para operar o SaaS.
- Usuarios lojistas com mais de uma empresa precisam informar `company_access`; usuario de uma unica empresa segue com fallback automatico para compatibilidade.
- O token Sanctum recebe abilities `merchant:{id}` e `company:{id}` quando a empresa e resolvida.
- APIs do portal usam `ActiveTenant` para respeitar o lojista ativo do token, em vez de assumir o primeiro vinculo do usuario.
- Checkout e cadastro interno reaproveitam usuario por e-mail ou CPF, permitindo que a mesma pessoa participe de varias empresas sem duplicar cadastro.

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
- `/app/tabelas-de-medidas`
- `/app/assistente`
- `/app/analytics`
- `/app/integracoes`
- `/app/widget`
- `/saas`
- `/saas/empresas`
- `/saas/empresas/nova`
- `/saas/empresas/:id/editar`
- `/saas/usuarios`
- `/saas/usuarios/novo`
- `/saas/usuarios/:id/editar`
- `/saas/emails`
- `/saas/emails/configuracoes`
- `/saas/emails/novo`
- `/saas/emails/:id/editar`

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
- `shopper_profiles`
- `recommendation_learning_events`
- `import_jobs`
- `integration_events`
- `ai_usage_logs`
- `audit_logs`
- `checkout_sessions`
- `payment_events`
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

## Fluxo de recomendacao

1. Pagina de produto carrega o widget com dados de produto/loja.
2. Widget chama `config-check`.
3. Se produto estiver configurado, mostra `Descubra seu tamanho` e `Tabela de Medidas`.
4. Consumidor pode abrir tabela do produto ou informar medidas.
5. Widget reusa medidas salvas localmente, com aviso e edicao livre.
6. API normaliza entrada e busca tabela.
7. Motor calcula pontuacao por tamanho.
8. API resolve ou cria perfil anonimo com consentimento e token local.
9. API salva log anonimo, calcula `outlier_score` e cria evento de aprendizado.
10. Widget exibe recomendacao, permite editar medidas e coleta feedback.
11. Feedback, compra, devolucao e troca entram como `recommendation_learning_events`.

## Checkout Pagar.me

- Cartao e tokenizado no navegador usando `PAGARME_PUBLIC_KEY`.
- Backend nunca recebe PAN/CVV; recebe apenas `card_token`.
- Backend cria pedido transparente em `POST /orders` na API Core v5 da Pagar.me.
- Pix retorna instrucoes na tela `/checkout/sucesso`; boleto nao e oferecido no checkout atual.
- Empresa nasce como `pending_payment` e e ativada por retorno imediato pago ou webhook.
- O comando `php artisan pv:payments-sync --limit=50` consulta pedidos pendentes na Pagar.me e ativa empresas pagas quando webhook falhar ou atrasar.
- O scheduler executa o monitor de pagamentos a cada 5 minutos.

## E-mails transacionais

- `email_settings` guarda as credenciais SMTP do SaaS; senha usa cast `encrypted` e nunca volta em claro pela API.
- `transactional_emails` guarda templates editaveis para cadastro, pagamento confirmado, aguardando pagamento, erro de pagamento, recuperacao de senha e renovacao.
- A tela `/saas` possui configuracao SMTP e CRUD de templates com listagem, novo, editar e ativar/desativar.
- Sprint 31 criou `TransactionalEmailService`, historico `transactional_email_sends`, disparos por checkout/pagamento e comando `php artisan pv:emails-dispatch --limit=50`.
- O scheduler executa o dispatcher de e-mails a cada 10 minutos; Pix pendente pode ser reenviado apos 6 horas sem duplicar confirmacoes/erros ja enviados.

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

Camada de aprendizado Sprint 36:

- `shopper_profiles` guarda medidas/preferencias pseudonimizadas, somente com consentimento;
- `RecommendationLearningEvent` registra recomendacao, feedback, compra, devolucao e troca;
- `LearningSignalService` calcula `outlier_score`, `learning_status` e `learning_weight`;
- sinais `blocked_outlier` ficam disponiveis no analytics, mas nao devem alimentar ajustes automaticos sem revisao.

## Integracao BigShop

Conector deve mapear:

- produto BigShop -> `products`;
- grade BigShop -> `product_variants`;
- tabela de medidas BigShop -> `measurement_tables` quando houver dados estruturados;
- imagem de tabela -> backlog de OCR/assistencia;
- estoque/status -> campos informativos, sem promessa no widget se nao for necessario.

Regra comercial da Sprint 32:

- quando a empresa ativa/contratada tiver `platform=bigshop`, `GET /integrations` retorna somente BigShop;
- `PATCH /integrations/{platform}` bloqueia plataformas diferentes de `bigshop`;
- `POST /integrations/{platform}/validate-install` tambem respeita a trava BigShop e valida somente URL publica;
- `GET|PATCH /widget-install` mantem `platform=bigshop` para esse contrato;
- o front do painel tambem mostra apenas a opcao BigShop nesses casos.

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
- `GET /api/v1/ops/status`;
- `GET /up` do Laravel;
- logs Laravel;
- tabela `integration_events`;
- tabela `recommendation_logs`;
- tabela `recommendation_learning_events`;
- tabela `shopper_profiles`;
- tabela `ai_usage_logs`;
- tabela `audit_logs` para acoes sensiveis.

## Evolucao inteligente planejada

Documentos de referencia:

- `docs/v1_intelligence_migration.md`
- `docs/sizebay_benchmark.md`
- `docs/data_learning_lgpd_outliers.md`
- `docs/intelligent_sizing_roadmap.md`

Novos modulos previstos:

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
- Rotas publicas do widget usam middleware proprio para validar `Origin` contra dominios ativos do lojista.
- Rate limit inicial aplicado em login, recomendacao publica, feedback, ativacao BigShop e status operacional.
- Comandos `pv:privacy-anonymize` e `pv:privacy-prune` fazem retencao operacional sem apagar analytics de recomendacao.

## Go-live Sprint 12

- `/api/v1/go-live/readiness` consolida checks de produto, tabela, widget, recomendacao, BigShop, IA, legal e cutover.
- `/app/go-live` exibe status operacional para o lojista antes de liberar trafego real.
- `scripts/validate-production.ps1` roda smokes publicos e autenticados contra producao.
