# Execution Log

## 2026-05-23 - Documentação inicial e deploy

- Estudados projetos de referência: BigShop HelpDesk, Marca Hora, BigShop360, Provador Virtual v1, BigShop front/back.
- Definido stack oficial Laravel + Vue + MySQL.
- Definida publicação inicial em `/provadorvirtual_v2/` para preservar v1.
- Criada documentação base em `docs/`.
- Criado `.gitignore` com `docs/credentials.local.md` ignorado.
- Criado workflow `.github/workflows/deploy.yml`.
- Identificados secrets faltantes para deploy SSH: `SSH_PRIVATE_KEY` ou `SSH_PRIVATE_KEY_B64`; opcional `SSH_PASSPHRASE`; recomendado `PRODUCTION_ENV`.
- Inicializado Git local em `main`, conectado ao remoto `git@github.com:helberfmelo/provadorvirtualv2.git` e publicado commit inicial `2dedd37`.
- Workflow `Deploy Production` disparou no GitHub Actions, mas o job não iniciou por bloqueio de billing/spending limit da conta GitHub.
- Cadastrados no GitHub Actions: `SSH_PRIVATE_KEY`, `SSH_PRIVATE_KEY_B64` e `PRODUCTION_ENV`.
- Validado acesso SSH local ao HostGator/opents62 com a chave cadastrada; `/home1/opents62/public_html` existe e `/home1/opents62/public_html/provadorvirtual_v2` ainda não existe.
- Reexecutado manualmente o workflow após cadastrar os secrets; o job continuou bloqueado por billing/spending limit antes de iniciar qualquer etapa.
- Repositório alterado para público pelo usuário; workflow reexecutado e finalizado com sucesso.

## 2026-05-23 - Sprint 1 Fundação Laravel/Vue

- Criado `backend/` com Laravel 12, Sanctum, rotas API versionadas e fallback SPA.
- Criado `frontend/` com Vue 3, TypeScript, Vite, Pinia, Vue Router, Axios e Font Awesome.
- Criadas migrations iniciais para users, merchants, companies, products, variants, measurement tables, widget installs, recommendation logs e feedbacks.
- Criado seed demo com lojista, loja, produto fictício, cinco variações, tabela de medidas e instalação de widget.
- Criada página `/produto-teste` com produto fictício, seletor de tamanho, tabela de medidas e recomendação inicial client-side.
- Criados endpoints `/api/v1/health`, `/api/v1/auth/login`, `/api/v1/me` e `/api/v1/demo/product-test`.
- Validações locais: `php artisan migrate:fresh --seed`, `php artisan test`, `php artisan route:list --path=api/v1`, `npm run build` e smoke integrado em `php artisan serve`.
- Primeiro Actions da Sprint 1 falhou na validação backend porque `APP_BASE_PATH=/` no `.env.example` fazia o Laravel procurar `//bootstrap/app.php`; variável removida do backend e mantida apenas como `VITE_APP_BASE_PATH` no build frontend.
- Segundo Actions da Sprint 1 passou por validação/build/deploy remoto, mas falhou no smoke público com HTTP 404 porque publicou em `/home1/opents62/public_html/provadorvirtual_v2`.
- Confirmado via SSH que `provadorvirtual_v1` esta em `/home1/opents62/provadorvirtual.online/provadorvirtual_v1`; workflow ajustado para publicar v2 em `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`.
- Deploy no docroot correto passou no Actions, mas teste manual mostrou que `/api/v1/health` estava retornando o `index.html` do Vue. Ajustado `.htaccess` para enviar `api`, `up` e `sanctum` ao front controller Laravel preservando o path, e smoke público passou a validar conteúdo JSON da API.
- Como o rewrite interno com `PATH_INFO` continuou caindo no fallback SPA no HostGator, a compatibilidade inicial passou a usar redirect 307 para endpoints Laravel limpos (`api`, `sanctum`, `up`) apontarem para a entrada pública funcional.
- Redirect 307 precisa usar URL-path absoluta (`/provadorvirtual_v2/public/...`) no HostGator; destino relativo virou caminho fisico no header `Location`.
- Run `26326675713` do GitHub Actions finalizou com sucesso para o commit `97ce033`; smokes validaram frontend e JSON real da API.

## 2026-05-23 - Sprint 2 Produtos, Variações e Tabelas

- Criados endpoints protegidos por Sanctum para produtos, variações, tabelas de medidas e templates.
- Criados Form Requests, Resources e controllers com escopo por lojista autenticado.
- Dashboard passou a consumir `/merchant/overview` com contadores reais.
- Criadas telas Vue `/app/produtos` e `/app/tabelas-de-medidas` para CRUD operacional da loja demo.
- Criados testes `ProductsApiTest` e `MeasurementTablesApiTest`.
- Validações locais: `php artisan route:list --path=api/v1`, `php artisan test`, `vendor/bin/pint` e `npm run build`.
- Run `26326950616` do GitHub Actions finalizou com sucesso para o commit `3b37c90`.
- Validado em produção: `/app/produtos`, `/app/tabelas-de-medidas`, login demo, `/api/v1/products` e `/api/v1/measurement-tables`.

## 2026-05-23 - Sprint 3 Motor de Recomendação

- Criado `RecommendationEngine` determinístico com normalizacao de medidas, scoring por faixa, confiança, notas de caimento e alertas.
- Criados endpoints públicos `/api/v1/public/recommendations/config-check`, `/api/v1/public/recommendations` e `/api/v1/public/recommendations/{id}/feedback`.
- Recomendações criam `recommendation_sessions` e `recommendation_logs` com hash de IP/user-agent, sem coletar nome, email ou telefone.
- Página `/produto-teste` passou a chamar a API real e registrar feedback.
- Smoke do GitHub Actions passou a postar uma recomendação real e validar `recommended_size = M`.
- Validações locais: `php artisan test`, `vendor/bin/pint`, `npm run build`, YAML do workflow e smoke integrado em `php artisan serve`.
- Run `26327119754` do GitHub Actions finalizou com sucesso para o commit `1c31542`.
- Validado em produção: `/produto-teste` e POST em `/api/v1/public/recommendations`, retornando `recommended_size = M`.

## 2026-05-23 - Sprint 4 Widget Universal v1

- Criados assets públicos `backend/public/widget/v1/provador-virtual.js` e `backend/public/widget/v1/provador-virtual.css`.
- Widget le atributos padrão e aliases legados, executa `config-check`, renderiza botão, abre modal responsivo, chama recomendação e registra feedback.
- Página `/produto-teste` passou a carregar o mesmo snippet público usado por lojas externas.
- Smoke do GitHub Actions passou a validar entrega do JS/CSS do widget.
- Validações locais: `php artisan test`, `npm run build`, YAML do workflow e asset test `WidgetAssetTest`.
- Run `26331199145` do GitHub Actions finalizou com sucesso para o commit `06144cf`.
- Validado em produção: JS/CSS do widget e `/produto-teste`.

## 2026-05-23 - Sprint 5 Painel do Lojista

- Criados endpoints protegidos `/api/v1/widget-install`, `/api/v1/integrations` e `/api/v1/integrations/{platform}`.
- Widget install passa a retornar URLs públicas, tema, domínios, produto de exemplo e snippet pronto para copiar.
- Catálogo de integrações inicial inclui BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray e personalizada.
- Tokens e webhook secrets de plataformas são persistidos criptografados e nunca retornam em claro.
- Dashboard do lojista passou a destacar produtos, tabelas, widget e integrações.
- Criadas telas Vue `/app/widget` e `/app/integracoes` com estados operacionais e controles responsivos.
- Criados testes `WidgetInstallApiTest` e `IntegrationsApiTest`.
- Validações locais: `php artisan test`, `vendor/bin/pint` e `npm run build`.
- Run `26331424403` do GitHub Actions publicou a Sprint 5, mas validação manual mostrou `500` em endpoint protegido quando o cliente perdia `Authorization` no redirect limpo `/api -> /public/api`.
- Ajustado build de produção para o painel usar `/provadorvirtual_v2/public/api/v1` direto e configurado Laravel para retornar `401` JSON em APIs sem token.
- Run `26331485173` do GitHub Actions finalizou com sucesso para o commit `2b9d4e9`.
- Validado em produção: `/app/widget`, `/app/integracoes`, `/public/api/v1/widget-install`, `/public/api/v1/integrations` e resposta `401` controlada sem token.

## 2026-05-23 - Sprint 6 Importacao e Templates Assistidos

- Criadas migrations `import_jobs` e `integration_events`.
- Criado service de importacao com preview e commit sincronizados.
- Criados endpoints protegidos `/api/v1/imports`, `/api/v1/imports/preview` e `/api/v1/imports/{importJob}`.
- CSV de produtos cria/atualiza produtos e variações por SKU/ID externo.
- CSV de tabelas cria/atualiza tabelas e substitui linhas da tabela importada.
- Parser inicial de Google Shopping XML cria preview/commit de produtos quando o feed informa campos basicos.
- Criada tela Vue `/app/importacoes` com amostras, leitura de arquivo, preview, commit e histórico.
- Criado documento `docs/imports_data_quality.md`.
- Criados testes `ImportsApiTest`.
- Validações locais: `php artisan test`, `vendor/bin/pint`, `npm run build` e smoke autenticado em `/api/v1/imports/preview`.
- Run `26331691701` do GitHub Actions finalizou com sucesso para o commit `2c9279b`.
- Validado em produção: `/app/importacoes`, `GET /public/api/v1/imports` e `POST /public/api/v1/imports/preview`.

## 2026-05-23 - Sprint 7 Integração BigShop Base

- Criado `BigShopClient` para chamadas `GET /v3/getEndPoints` e `GET /v3/products`.
- Criado `BigShopSyncService` para probe, sync de produtos, grades e tabelas de medidas estruturadas.
- Criados endpoints protegidos `/api/v1/integrations/bigshop/probe` e `/api/v1/integrations/bigshop/sync`.
- Sync cria/atualiza `products`, `product_variants`, `measurement_tables` e registra `integration_events`.
- Tela `/app/integracoes` passou a mostrar ações de testar e sincronizar para BigShop.
- Criados testes `BigShopIntegrationTest` com `Http::fake`.
- Validações locais: `php artisan test`, `vendor/bin/pint`, `npm run build` e smoke de erro controlado `422` sem conexão BigShop.
- Run `26331844564` do GitHub Actions finalizou com sucesso para o commit `2254a07`.
- Validado em produção: `/app/integracoes` e `POST /public/api/v1/integrations/bigshop/probe` retornando `422` controlado sem credencial real.

## 2026-05-23 - Sprint 8 BigShop Um Clique

- Criada configuração `BIGSHOP_ACTIVATION_SECRET` para controlar a ativação nativa por HMAC.
- Criado endpoint público assinado `POST /api/v1/public/bigshop/activate`.
- Endpoint valida timestamp, assinatura `sha256=<hmac>` e payload mínimo da loja BigShop.
- Ativação cria/atualiza usuário, lojista, empresa, conexão BigShop e instalação do widget.
- Tokens recebidos são salvos criptografados e nunca retornam em claro.
- Resposta retorna `dashboard_url`, `widget_url`, `widget_public_key` e status operacional.
- Criados testes `BigShopActivationTest`.
- Validações locais: `php artisan test`, `vendor/bin/pint`, `npm run build`, `git diff --check` e smoke local retornando `503` quando o secret não esta configurado.
- Run `26332055677` do GitHub Actions finalizou com sucesso para o commit `729e1c3`.
- Validado em produção: `POST /public/api/v1/public/bigshop/activate` retorna `503` controlado enquanto `BIGSHOP_ACTIVATION_SECRET` não esta configurado.

## 2026-05-23 - Sprint 9 IA para OCR e Tabelas

- Criada tabela `ai_usage_logs` para registrar uso, provider, modelo, tokens estimados, custo estimado e resumo sem conteúdo bruto.
- Criados endpoints protegidos `/api/v1/ai/status` e `/api/v1/ai/measurement-table-suggestions`.
- Criado parser local para texto/CSV de tabela de medidas.
- Imagens ficam preparadas no front; enquanto provider externo não estiver ativo, a API retorna `needs_provider` em vez de simular OCR.
- Sugestoes sempre retornam `review_required=true` e `status=draft`.
- Criada tela Vue `/app/assistente` para colar texto/CSV, enviar imagem, revisar medidas e criar rascunho.
- Criado documento `docs/ai_assistant.md`.
- Criados testes `AiMeasurementAssistantTest`.
- Validações locais: `php artisan test`, `vendor/bin/pint`, `npm run build`, `php artisan route:list --path=api/v1/ai`, `git diff --check` e smoke autenticado local com 2 linhas sugeridas.
- Run `26332326042` do GitHub Actions finalizou com sucesso para o commit `b7a88d1`.
- Validado em produção: `/app/assistente`, `GET /public/api/v1/ai/status` e `POST /public/api/v1/ai/measurement-table-suggestions` com 2 linhas sugeridas.

## 2026-05-23 - Sprint 10 Analytics e SaaS Admin

- Criada tabela `audit_logs`.
- Criado `AuditLogger` com hash de IP/user-agent e filtro de tokens/secrets/senhas em metadata.
- Criados endpoints protegidos `/api/v1/analytics/recommendations` e `/api/v1/audit-logs`.
- Criados endpoints admin `/api/v1/saas/overview` e `/api/v1/saas/merchants`, restritos a `admin` ou `support`.
- Analytics retorna recomendações, feedback positivo, produtos sem tabela, alertas, series diarias, tamanhos e produtos.
- Criadas telas Vue `/app/analytics` e `/saas`.
- Auditoria inicial cobre tabelas de medidas, widget e integrações.
- Criado documento `docs/analytics_admin.md`.
- Criados testes `AnalyticsApiTest` e `SaasAdminApiTest`.
- Validações locais: `php artisan test`, `vendor/bin/pint`, `npm run build`, `php artisan route:list --path=api/v1/analytics`, `php artisan route:list --path=api/v1/saas`, `git diff --check` e smoke local com analytics 200 e SaaS 403 para lojista comum.
- Run `26332544138` do GitHub Actions finalizou com sucesso para o commit `4f93032`.
- Validado em produção: `/app/analytics`, `/saas`, `GET /public/api/v1/analytics/recommendations` e `GET /public/api/v1/saas/overview` retornando `403` para lojista comum.

## 2026-05-23 - Sprint 11 Hardening, LGPD e Observabilidade

- Criadas páginas públicas `/privacidade` e `/termos`.
- Criado endpoint público `GET /api/v1/ops/status`.
- Adicionados rate limits em login, recomendações públicas, feedback, ativação BigShop e status operacional.
- Criado middleware de origem do widget, validando `Origin` contra `widget_installs.allowed_domains`.
- Configuração CORS global passou a atender apenas origens locais de desenvolvimento; o widget usa validação dinâmica por domínio.
- `AuditLogger` passou a mascarar metadata sensível de forma recursiva e auth passou a registrar login/logout.
- Criados comandos `pv:privacy-anonymize` e `pv:privacy-prune`.
- Criado documento `docs/hardening_lgpd_observability.md`.
- Validações locais: `php artisan test --filter=HardeningApiTest`, `vendor/bin/pint`, `php artisan test`, `npm run build` e `git diff --check`.
- Run `26332960822` do GitHub Actions finalizou com sucesso para o commit `ac1025f`.
- Validado em produção: `/privacidade`, `/termos`, `GET /public/api/v1/ops/status`, bloqueio de origem não cadastrada com `403` e origem `https://provadorvirtual.online` com CORS correto.
- Rollback readiness validado pelo backup criado no deploy: `provadorvirtual-v2-backup-20260523-094207-ac1025f2a2469b9876d93764652ce87acd0e7174.tar.gz`.

## 2026-05-23 - Sprint 12 Go-live Assistido

- Criado endpoint protegido `GET /api/v1/go-live/readiness`.
- Criada tela `/app/go-live` com checklist de prontidão, URLs de produção e credenciais pendentes.
- Criado script `scripts/validate-production.ps1` para smoke público/autenticado em produção.
- Criado `tools/widget-external-smoke.html` para validar widget de produção servido por HTTP local.
- Criado documento `docs/go_live_cutover.md`.
- Validações locais: `php artisan test --filter=GoLiveReadinessApiTest`, `vendor/bin/pint`, `php artisan test`, `npm run build`, `php artisan route:list --path=api/v1/go-live` e `git diff --check`.
- Run `26333179219` do GitHub Actions finalizou com sucesso para o commit `f96bc4f`.
- Ajustado `scripts/validate-production.ps1` para normalizar header CORS retornado como array no PowerShell.
- Run `26333226813` do GitHub Actions finalizou com sucesso para o commit `e657a75`.
- Validado em produção: `/app/go-live`, `scripts/validate-production.ps1` com `PRODUCTION VALIDATION OK` e backup `provadorvirtual-v2-backup-20260523-095421-e657a75c92163ab29eae19a8cec5b5d5d1b6cd5c.tar.gz`.

## Pendências abertas

- Repositório esta público para manter a cota do GitHub Actions disponível.
- `GEMINI_API_KEY` foi encontrada no v1 e copiada para `docs/credentials.local.md`; ainda falta cadastrar/ativar em produção quando aprovado.
- Opcional: cadastrar `OPENAI_API_KEY` como provider alternativo.
- Cadastrar `BIGSHOP_ACTIVATION_SECRET` em `PRODUCTION_ENV`.
- Receber loja piloto BigShop, `store_id`, token `x-api` e webhook secret, se existir.

## 2026-05-23 - Roadmap inteligente pos Sprint 12

- Estudado `D:\Projetos\provadorvirtual_v1` para migrar conceitos de catálogo padrão, Gemini, OCR, modelo por IA, wizard de tabela e widget gamificado.
- Encontrada `GEMINI_API_KEY` no `.env` do v1; valor documentado apenas em `docs/credentials.local.md`, ignorado pelo Git.
- Estudada documentação pública Sizebay sobre script, API, produto, XML, OnPage, order tracking, devolucoes, Size Tailor, Shopify e categorias.
- Capturado fluxo público Zak/Sizebay em camisa e calca: tenant `1235`, botões `DESCUBRA SEU TAMANHO`/`TABELA DE MEDIDAS`, cookies `SIZEBAY_SESSION_ID_V4` e recomendações por perfis de teste.
- Criados documentos `docs/v1_intelligence_migration.md`, `docs/sizebay_benchmark.md`, `docs/data_learning_lgpd_outliers.md` e `docs/intelligent_sizing_roadmap.md`.
- Atualizados docs obrigatórios, backlog, arquitetura, widget e IA.

## 2026-05-23 - Sprint 27 Raiz e checkout anual

- Criada landing pública v2 com estrutura inspirada no v1 e cores do v2.
- Checkout público passou para plano anual único, sem boleto, com BigShop como primeira plataforma.
- Preço padrão: `R$ 189,90/mes`; preço BigShop: `R$ 129,90/mes`.
- Cartão em até 10x sem juros; Pix a vista com 5% de desconto.
- Workflow passou a publicar build estática na raiz e manter backend/app em `/provadorvirtual_v2/`.
- Validações locais: `php artisan test`, `npm run build`, `npx vite build --outDir dist-root` e `git diff --check`.
- Run `26336510709` publicou app e raiz, mas falhou no passo `Ensure master admin` por `ssh: connect ... Connection refused` logo após os deploys remotos.
- Workflow ajustado para retry no passo de master admin e diagnostico SSH tolerante a indisponibilidade transitoria.
- Run `26336554760` finalizou com sucesso após retry SSH; `scripts/validate-production.ps1` validou raiz, v2, APIs, CORS, recomendação e go-live.

## 2026-05-23 - Sprint 28 Monitor de pagamentos e e-mails

- Criadas tabelas `email_settings` e `transactional_emails`.
- Criado painel SaaS para configurar SMTP com senha criptografada e templates transacionais.
- Criados templates iniciais: cadastro realizado, pagamento confirmado, aguardando pagamento, erro no pagamento, recuperacao de senha e renovacao de plano.
- Criado comando `php artisan pv:payments-sync --limit=50` para consultar pedidos pendentes na Pagar.me e ativar empresas pagas.
- Scheduler configurado para rodar monitor de pagamentos a cada 5 minutos, anonimização diaria e limpeza operacional semanal.
- Documentado cron cPanel com log em `docs/deploy_runbook.md`.
- Validações locais focadas: `php artisan test --filter=SaasEmailApiTest`, `php artisan test --filter=PaymentSyncCommandTest` e `npm run build`.
- Validações locais completas: `php artisan test`, `npm run build`, build raiz com `VITE_APP_BASE_PATH=/`, `git diff --check` e `vendor/bin/pint --dirty`.
- Run `26336899986` do GitHub Actions finalizou com sucesso para o commit `ce65f82`.
- Validado em produção: raiz, páginas públicas, API health/ops/demo/recommendation, CORS, login demo e go-live com `scripts/validate-production.ps1`.

## 2026-05-23 - Sprint 29 Login contextual e multiempresa

- Login passou a aceitar `login` por e-mail ou CPF, mantendo alias legado `email`.
- Portal da empresa passou a receber `company_access` por código da loja ou CNPJ.
- Usuários multiempresa precisam informar empresa; usuários de uma unica empresa seguem com fallback automático para compatibilidade.
- Token Sanctum passa a carregar contexto `merchant:{id}` e `company:{id}`.
- APIs do portal usam `ActiveTenant` para resolver o lojista ativo pelo token.
- Checkout público e cadastro interno SaaS reaproveitam usuário existente por e-mail/CPF, permitindo uma pessoa em varias empresas.
- Tela `/login` agora mostra campo `E-mail ou CPF` e `Código da loja ou CNPJ`.
- Validações locais focadas: `php artisan test --filter=AuthTest`, `php artisan test --filter=PublicCheckoutFlowTest` e `npm run build`.
- Validações locais completas: `php artisan test`, `npm run build`, build raiz com `VITE_APP_BASE_PATH=/`, `git diff --check` e `vendor/bin/pint --dirty`.
- Run `26337158927` aplicou backend/raiz, mas falhou no smoke por falso negativo de `curl | grep -q` com `pipefail` e exit `23`.
- Workflow ajustado no commit `08547b8` para validar respostas HTTP em variável antes do `grep`, evitando SIGPIPE falso.
- Run `26337254520` finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 30 Usuários e permissões por módulo

- Criada migration para `users.status`, `users.permissions`, `merchant_user.status`, `merchant_user.permissions` e vinculo opcional com `merchant_company_id`.
- Criado catálogo canonico de módulos/permissões para portal da empresa e portal SaaS.
- Criadas APIs protegidas `/api/v1/merchant/users` e `/api/v1/saas/users`.
- Login passa a bloquear usuário globalmente inativo e acesso de empresa desativado.
- Menu do frontend passa a respeitar permissões de visualizacao.
- Criadas telas `/app/usuarios` e `/saas/usuarios` com listagem, novo, editar e ativar/desativar.
- CRUD de empresas no SaaS ganhou editar e ativar/desativar na listagem.
- Criado documento `docs/user_access_permissions.md`.
- Validações locais: `php artisan test --filter=UserAccessApiTest`, `php artisan test`, `npm run build`.
- Run `26337792120` do GitHub Actions finalizou com sucesso para o commit `87e9577`.
- Validado em produção: `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 31 Automações de e-mail e ciclo financeiro

- Criada tabela `transactional_email_sends` para histórico de disparos.
- Criado `TransactionalEmailService` com renderizacao de templates, configuração dinâmica de mailer e controle de duplicidade.
- Checkout público registra `cadastro_realizado` e eventos de status registram `aguardando_pagamento`, `pagamento_confirmado` ou `erro_pagamento`.
- Cadastro interno de empresa no SaaS envia `cadastro_realizado` quando ha owner.
- Criado comando `php artisan pv:emails-dispatch --limit=50` para reprocessar pendências e reenviar Pix pendente após 6 horas.
- Scheduler passou a rodar o dispatcher de e-mails a cada 10 minutos.
- SaaS ganhou histórico de envios em `/api/v1/saas/transactional-email-sends` e listagem na tela `/saas`.
- Criado documento `docs/transactional_email_automation.md`.
- Validações locais focadas: `php artisan test --filter=TransactionalEmailDispatchTest`, `php artisan test --filter=SaasEmailApiTest`, `php artisan test --filter=PublicCheckoutFlowTest`, `php artisan test --filter=PaymentSyncCommandTest` e `npm run build`.
- Validações locais completas: `php artisan test`, `npm run build`, build raiz com `VITE_APP_BASE_PATH=/`, `git diff --check` e `vendor/bin/pint --dirty`.
- Run `26338061259` do GitHub Actions finalizou com sucesso para o commit `62e0830`.
- Validado em produção: `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 32 Oferta BigShop travada, site público e mobile

- Criada sprint adicional para impedir que desconto BigShop seja usado com integração de plataforma mais cara.
- Backend passou a filtrar catálogo de integrações para BigShop quando a empresa ativa e BigShop.
- Backend passou a bloquear `PATCH /integrations/{platform}` e `PATCH /widget-install` para plataformas diferentes de BigShop nesses contratos.
- Painel do lojista passou a mostrar aviso de contrato BigShop e apenas a opção BigShop no widget.
- Checkout público passou a aceitar query `platform` para CTA direto do plano padrão ou BigShop.
- Landing ganhou duas colunas de preço com CTAs exclusivos, WhatsApp oficial, secao BigShop, footer refinado, favicon PV e metatags OG/Twitter.
- Loja teste passou a usar fotos externas que representam vestido, blusa, camiseta e calca jeans.
- Menu mobile do Vue passou a abrir em drawer com botão de barras.
- Validações locais focadas: `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=WidgetInstallApiTest` e `npm run build`.
- Validações locais completas: `php artisan test`, `npm run build`, build raiz com `VITE_APP_BASE_PATH=/`, `git diff --check` e `vendor/bin/pint --dirty`.
- Run `26338411089` do GitHub Actions finalizou com sucesso para o commit `116fcf6`.
- Primeira tentativa de validação de produção teve timeout transitorio de conexão logo após deploy; nova tentativa 15 segundos depois retornou `PRODUCTION VALIDATION OK`.
- Validações adicionais em produção: OG tags da raiz, `/favicon.svg`, imagens demo por API e precos `R$ 189,90`/`R$ 129,90`.

## 2026-05-23 - Sprint 36 Perfis, aprendizado e outliers

- Criadas tabelas `shopper_profiles` e `recommendation_learning_events`.
- Sessão/log de recomendação passaram a guardar vinculo de perfil, consentimento, snapshot, `outlier_score`, `learning_status` e `learning_reason`.
- Widget passou a salvar perfil anônimo com consentimento, token local, limpeza de perfil, gênero, formato corporal, caimento e barra de precisao.
- Criados sinais públicos `feedback`, `add_to_cart`, `purchase`, `return` e `exchange` para aprendizado.
- Analytics passou a exibir perfis, qualidade média, sinais de aprendizado, sinais comerciais e outliers bloqueados.
- Validações locais: `vendor/bin/pint --dirty`, `php artisan test`, `npm run build`, filtros `RecommendationApiTest`, `AnalyticsApiTest` e `HardeningApiTest`.
- Run `26339739429` falhou no deploy remoto porque o MySQL recusou a foreign key automática `recommendation_learning_events_recommendation_feedback_id_foreign` por exceder 64 caracteres.
- Commit `5d5b5dc` tornou a migration idempotente para recuperar a tentativa parcial e usou nomes curtos para foreign key/indices.
- Run `26339824157` finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 37 Pacote comercial e piloto assistido

- `GET /api/v1/go-live/readiness` passou a incluir checks de Pagar.me, transação real, cron, performance do widget, acessibilidade/mobile e pacote de piloto.
- `/app/go-live` passou a exibir links comerciais, onboarding, comandos de automação e pendências reais.
- Criado `docs/commercial_pilot_package.md`.
- `scripts/validate-production.ps1` passou a validar `/checkout`, widget JS/CSS, perfil consentido, esquecimento de perfil, sinal de aprendizado e pacote de piloto.
- Validações locais: `vendor/bin/pint --dirty`, `php artisan test`, `npm run build` e `php artisan test --filter=GoLiveReadinessApiTest`.
- Run `26340033238` finalizou com sucesso e o validador ampliado retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 38 UX corretiva: navegação por contexto

- Registradas as sprints 38 a 41 para corrigir arquitetura de informação, CRUDs list-first e revisão visual/responsiva.
- Shell autenticado passou a separar portal SaaS e portal da empresa.
- SaaS deixou de exibir menus de lojista; portal da empresa deixou de misturar atalhos de SaaS no menu principal.
- Areas autenticadas passaram a usar menu lateral no desktop e drawer no mobile.
- Validações locais: `npm run build`, `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=BigShopIntegrationTest` e `git diff --check`.

## 2026-05-23 - Sprint 39 SaaS list-first e subpaginas

- `/saas` foi reduzida para visão geral com métricas, atalhos e tabelas resumidas.
- Empresas SaaS foram separadas em `/saas/empresas`, `/saas/empresas/nova` e `/saas/empresas/:id/editar`.
- Usuários SaaS foram separados em `/saas/usuarios`, `/saas/usuarios/novo` e `/saas/usuarios/:id/editar`.
- E-mails foram separados em `/saas/emails`, `/saas/emails/configuracoes`, `/saas/emails/novo` e `/saas/emails/:id/editar`.
- O menu SaaS passou a listar visão geral, empresas, usuários e e-mails, sem misturar módulos do portal da empresa.
- Validações locais: `npm run build`.

## 2026-05-23 - Sprint 40 Portal da empresa list-first e subpaginas

- Produtos foram separados em `/app/produtos`, `/app/produtos/novo` e `/app/produtos/:id/editar`.
- Tabelas de medidas foram separadas em `/app/tabelas-de-medidas`, `/app/tabelas-de-medidas/nova` e `/app/tabelas-de-medidas/:id/editar`.
- Usuários da empresa foram separados em `/app/usuarios`, `/app/usuarios/novo` e `/app/usuarios/:id/editar`.
- As listagens agora ocupam a tela e as edicoes abrem em telas proprias.
- Validações locais: `npm run build`.

## 2026-05-23 - Sprint 41 Revisão visual, responsiva e QA de telas

- Registrado `docs/portal_ui_guidelines.md` como referência obrigatória para separar listagem, cadastro e edição nos CRUDs.
- Ajustadas tabelas, ações por linha, cabecalhos e largura minima de formulários/tabelas para reduzir desalinhamento e overflow espremido.
- Validador de produção passou a cobrir rotas novas do SaaS e do portal da empresa.
- Smoke do GitHub Actions passou a validar rotas autenticadas principais na raiz e na subpasta.

## 2026-05-23 - Sprint 42 Ajustes pos-inspeção visual

- Inspeção visual autenticada gerou screenshots de SaaS, portal da empresa, listagens, formulários e mobile.
- `Nova empresa` deixou de abrir preenchida com `Loja teste`.
- Cadastro interno de empresa agora inicia com BigShop como plataforma padrão.
- `Novo produto` deixou de abrir com categoria e tabela incompatibilizadas por padrão.
- Run `26343135605` do GitHub Actions finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 43 Cerebro inteligente do v1 no v2

- Reestudados `table_new.php`, `ajax_get_default_table.php`, `ajax_get_gender_and_types.php`, `ajax_ocr_table.php`, `includes/gemini-ai.php` e `default_measurement_tables_data.json` do v1.
- Confirmado que o v1 buscava primeiro modelos em `standard_models`, com medidas por gênero/produto/altura/peso/idade/formato corporal, antes de sugerir via Gemini.
- Importado o JSON padrão do v1 para `backend/database/data/default_measurement_tables_data.json`.
- Criado `StandardMeasurementCatalog` para normalizar os modelos em templates do v2 consumidos por `/api/v1/measurement-templates`.
- Tela de nova/editar tabela passou a ter seletor de modelo inteligente filtrado por produto/gênero.
- Site público e assistente IA passaram a destacar base brasileira, IA assistiva, revisão humana e aprendizado seguro.
- `docs/credentials.local.md` foi limpo localmente para exibir somente as duas URLs de login pedidas.
- Run `26343538804` do GitHub Actions finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 44 CRUD SaaS de usuários das empresas

- Criado módulo SaaS `saas_company_users` para separar usuários internos de usuários de empresas clientes.
- `/api/v1/saas/users` passou a listar usuários internos `admin`/`support`.
- Criadas APIs `/api/v1/saas/company-users` para listar, criar, editar e ativar/desativar acessos de clientes.
- Criadas telas `/saas/usuarios-empresas`, `/saas/usuarios-empresas/novo` e `/saas/usuarios-empresas/:id/editar`.
- Formulário permite selecionar empresa por código/nome/CNPJ, definir perfil, status do acesso e permissões do portal da empresa.
- `pv:create-master-admin` passou a garantir permissões SaaS completas.
- Validações locais: `php artisan test --filter=UserAccessApiTest`, `npm run build`, `vendor/bin/pint --dirty` e `git diff --check`.
- Run `26343868801` do GitHub Actions finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 45 Feedback global de salvamento

- Criado modal global pequeno e central para salvamento, sucesso e erro.
- `api.ts` passou a interceptar mutacoes autenticadas dos portais SaaS/empresa.
- Sucesso fica visível por 4 segundos e fecha automaticamente.
- Erros ficam abertos até o usuário fechar e mostram motivo amigável, inclusive `422` de validação.
- Mantidas exclusoes para login/logout, checkout público, previews e ações que não representam salvamento.
- Validações locais: `npm run build`, `php artisan test --filter=UserAccessApiTest` e `git diff --check`.
- Run `26344601240` do GitHub Actions finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 46 Recarregamento ao trocar empresa

- Corrigido o shell operacional para recriar a tela atual quando `activeCompany.id` muda.
- A troca pelo seletor de empresa no portal agora remonta a `RouterView` do portal da empresa.
- Com isso, painel e CRUDs executam novamente seus carregamentos de dados no novo contexto.
- Validações locais: `npm run build`, `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=BigShopIntegrationTest` e `git diff --check`.

## 2026-05-24 - Sprint 53 Sincronização automática de integrações

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint.
- Criado `XmlFeedSyncService` para reutilizar a mesma lógica de sync XML/feed no endpoint manual e no cron.
- Criado comando `php artisan pv:integrations-sync-feeds --limit=50`, com filtros opcionais por plataforma e empresa, além de `--dry-run`.
- Scheduler configurado para rodar o comando às `00:00`, `06:00`, `12:00` e `18:00` em `America/Sao_Paulo`.
- Sync automático registra `integration_events` com `summary.trigger=scheduled` e atualiza `last_sync_at`, `status` e `last_error` da conexão.
- Runbook do cPanel documentado com cron principal via `schedule:run` e fallback direto para feeds.
- Validações locais: `vendor/bin/pint --dirty`, `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=GoLiveReadinessApiTest`, `php artisan list pv`, `php artisan schedule:list`, `php artisan test`, `npm run build` e `git diff --check`.
- Run `26348238406` do GitHub Actions finalizou com sucesso para o commit `684ba67`, incluindo deploy remoto e smoke público.

## 2026-05-23 - Sprint 47 Integrações BigShop e XML

- Corrigida a numeração da sprint de integração para seguir a sequência real do projeto.
- Adicionados `feed_url` e `feed_format` às conexões de plataforma.
- Criada sincronização XML por URL em `/api/v1/integrations/{platform}/sync-xml`.
- Parser Google Merchant passou a mapear `g:item_group_id`, `g:id`, tamanho, cor, gênero, disponibilidade, imagem e link.
- Tela `/app/integracoes` recebeu tooltips nos labels e ação `Sincronizar XML`.
- Pesquisa Sizebay, matriz por plataforma e roadmap de conectores foram consolidados em `docs/platform_integration_research_roadmap.md`.
- Validações locais: `php artisan test --filter=IntegrationsApiTest` e `npm run build`.
- Commit enviado ao GitHub: `6fd8f46`.

## 2026-05-23 - Sprint 48 Revisão de textos e idioma

- Revisados textos visíveis do SaaS, portal da empresa, site público e mensagens de API com PT-BR correto.
- Diretrizes de desenvolvimento e UX passaram a exigir PT-BR com acentos, til e cedilha corretos.
- Aliases técnicos de API/importação foram preservados sem acento quando fazem parte do contrato de dados.
- Corrigidos textos de e-mails transacionais padrão, páginas legais, checkout, landing, integrações, instalação, usuários e mensagens operacionais.
- Validações locais: `npm run build`, `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=UserAccessApiTest` e `git diff --check`.

## 2026-05-23 - Sprint 49 Padronização visual dos controles

- Revisados inputs, selects, textareas, botões, checkboxes e ações das telas SaaS e portal da empresa.
- Estilos globais passaram a cobrir `.form`, `.admin-form`, `.inline-form`, `measure-grid` e textarea de Pix fora de formulário.
- Estados de foco e desabilitado foram padronizados para evitar controles com aparência crua.
- Checkboxes deixam de herdar largura e altura de input comum, preservando o visual compacto em permissões, widget e variações de produto.
- Validações locais: `npm run build` e `git diff --check`.

## 2026-05-23 - Sprint 50 Correção do CI pós-acentuação

- GitHub Actions dos commits `59ced6f` e `bac732d` falhou nos runs `26346764503` e `26346828756`.
- Causa: testes esperavam mensagens antigas sem acento enquanto a API passou a retornar PT-BR correto com acentos.
- Atualizadas expectativas em `HardeningApiTest` e `PublicCheckoutFlowTest`.
- Governança reforçada: toda sprint precisa conferir GitHub Actions/deploy remoto depois do push antes de ser considerada concluída.
- Run `26347139903` do GitHub Actions finalizou com sucesso para o commit `c2826a5`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 51 Roadmap e governança do ciclo de integrações

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint.
- Roadmap recebeu as Sprints 52, 53 e 54 para UX de integrações, sincronização automática e guia de instalação do widget.
- Fonte de verdade passou a explicitar que a próxima sprint só começa após commit, push e GitHub Actions/deploy verificados.

## 2026-05-24 - Sprint 52 UX da tela de integrações

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint.
- Tooltips da tela `/app/integracoes` deixaram de usar `title` nativo e passaram a abrir contidos no viewport.
- Feedbacks de testar conexão, validar instalação, sincronizar API BigShop e sincronizar XML/feed passaram para o modal central.
- Sincronização XML/feed agora orienta o usuário a acessar `/app/produtos` para visualizar e revisar os produtos sincronizados.
- Botões foram reorganizados por finalidade: configuração, catálogo XML/feed e API BigShop.
- CSS global recebeu proteção contra rolagem horizontal indevida na página e preserva rolagem interna para snippets/tabelas.
- Validações locais: `npm run build`, `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=BigShopIntegrationTest` e `git diff --check`.
- Run `26348028309` do GitHub Actions finalizou com sucesso para o commit `24520a3`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 54 Guia detalhado de instalação do widget

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint.
- Tela `/app/widget` recebeu seção "Onde instalar" explicando que o container deve ficar na página de produto, perto do seletor de tamanho/grade e antes ou próximo ao botão Comprar.
- Tela `/app/integracoes` recebeu seção equivalente por plataforma, com orientação específica para BigShop e plataformas próprias.
- Widget público passou a expor `window.ProvadorVirtual.reload(...)` para recarregar o widget quando produto, variação ou SKU mudarem sem reload da página.
- Documentação de widget, integrações e BigShop foi atualizada com o local de instalação, recarregamento por variação e ponto futuro `produto.vue` da model3 plano pro.
- Validações locais: `npm run build`, `php artisan test --filter=WidgetAssetTest`, `php artisan test --filter=IntegrationsApiTest`, `vendor/bin/pint --dirty` e `git diff --check`.
- Run `26348462160` do GitHub Actions finalizou com sucesso para o commit `7b06d4d`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 55 Feedbacks operacionais por modal

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint.
- Removidos os avisos inline `success-message` que ainda apareciam em produtos, tabelas, assistente, importações, empresas, usuários e e-mails.
- Ações operacionais de remover, ativar/desativar, criar rascunho assistido e importar dados passaram a usar `showFeedback` no modal central.
- Removida a classe CSS `.success-message`, que deixou de ser usada no frontend.
- Varredura de textos visíveis corrigiu acentos remanescentes em importações, gênero, opções de produto e destinatário.
- Validações locais: `npm run build`, busca `rg` por padrões antigos de mensagem/texto e `git diff --check`.
- Run `26348653353` do GitHub Actions finalizou com sucesso para o commit `01d0461`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 56 Registro do deploy verificado

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` com conteúdo mascarado.
- Registrado no estado atual, roadmap e log que a Sprint 55 teve push, GitHub Actions e deploy remoto verificados com sucesso.
- Corrigida a frase de governança no índice obrigatório para usar PT-BR com acentos.
- Validação local: `git diff --check`.
- Run `26348767486` do GitHub Actions finalizou com sucesso para o commit `b90cf10`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 57 Atualização dos actions do deploy

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` com conteúdo mascarado.
- Conferidas via GitHub API as versões oficiais atuais: `actions/checkout` v6.0.2 e `actions/setup-node` v6.4.0.
- Workflow `.github/workflows/deploy.yml` atualizado para `actions/checkout@v6` e `actions/setup-node@v6`.
- Motivo: o run `26348767486` passou, mas emitiu anotação de depreciação futura do runtime Node 20 dos actions oficiais.
- Validação local: `git diff --check` e conferência de `actions/checkout@v6`/`actions/setup-node@v6` no workflow.
- Run `26348869694` do GitHub Actions finalizou com sucesso para o commit `7f4a142`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 58 Widget BigShop model3 pro

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` com conteúdo mascarado.
- Estudadas as cópias locais `D:\Projetos\bigshop\172.16.151.2\bigshop\model3\stores\pro_store`, `D:\Projetos\bigshop\172.16.151.5\bigshop` e `D:\Projetos\bigbangshop2.0`.
- Confirmado que o ponto correto do modelo pro é `pro_store/produto.vue`, na página de produto, logo após seletor de cor/tamanho e antes dos blocos de compra/tabela.
- Backend do Provador Virtual ajustado para resolver BigShop por `platform=bigshop` + `external_store_id`, sem exigir IDs internos no front compartilhado.
- Widget público passa a emitir `provadorvirtual:config`, usado pelo front BigShop para esconder a tabela nativa somente quando o produto tiver tabela no Provador Virtual.
- Cópia local do `produto.vue` recebeu loader dinâmico do widget, recarregamento por troca de grade e fallback para tabela BigShop.
- Cópia local do backend BigShop passa a retornar `ref`, `type` e `cod_4` nos apps da loja.
- Cópia local do painel BigShop recebeu ajuda e defaults para o app `provador_virtual` em Apps adicionais.
- Validações locais do Provador Virtual: `vendor\bin\pint --dirty`, `npm run build`, `git diff --check`, `php artisan test --filter=RecommendationApiTest`, `php artisan test --filter=HardeningApiTest` e `php artisan test` completo com 67 testes e 502 assertions.
- Validações das cópias BigShop: conferência estrutural do `produto.vue`, conferência do SQL em `api-v2/funcoes.php` e `git diff --check` no painel `D:\Projetos\bigbangshop2.0`.
- Scripts `npm run build` do diretório local `model3` da BigShop não foram executados porque o `package.json` contém comandos de publicação/pull/redis próprios do ambiente oficial, inadequados para a cópia local.
- Run `26349330161` do GitHub Actions finalizou com sucesso para o commit `98c13a7`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 59 Fechamento manual dos modais

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` com conteúdo mascarado.
- Modal central `SaveFeedbackModal` recebeu botão `x` no canto superior direito, disponível para mensagens de salvando, sucesso, erro e informação.
- Serviço `saveFeedback` passa a respeitar fechamento manual durante o estado de salvamento, evitando reabrir sucesso automático da mesma operação após o usuário fechar o modal.
- Diretriz de UX dos portais atualizada para exigir fechamento manual visível nos modais de feedback.
- Validações locais: `npm run build`, `php artisan test --filter=HealthTest` e `git diff --check`.

## 2026-05-24 - Sprint 60 Catálogo global do app BigShop

- Releitura obrigatória dos documentos principais e de `docs/bigshop_model3_pro_widget.md` concluída antes de iniciar a correção.
- Investigado o motivo do app `Provador Virtual` não aparecer no select de Apps adicionais do painel BigShop.
- Confirmado que a lista vem de `/get_apps`, que consulta a tabela global `apps`; sem o registro `app_code='provador_virtual'`, o front não recebe a opção.
- Cópia local `D:\Projetos\bigshop\172.16.151.5\bigshop\sistema\context\get_apps.php` ajustada para criar o app global com `INSERT ... WHERE NOT EXISTS` antes do select.
- Painel BigShop em `D:\Projetos\bigbangshop2.0` ajustado para priorizar `Provador Virtual` no topo da lista quando a API retornar o app e para comparar `id/value` de forma tolerante.
- Validações locais BigShop: `php -l` no `get_apps.php`, `npx eslint src/pages/configurations/additionalAppsEdit.vue` e `git diff --check` no painel.
- Painel BigShop commitado e enviado para GitLab no commit `4c2c92b3e`, branch `hotfix/couto-integration-support`; `git ls-remote` confirmou o mesmo hash no remoto.

## 2026-05-24 - Sprint 61 Preservar tabela no sync XML

- Investigado o produto BigShop `716076` da Luna Moda Festa, cujo widget público retornava `measurement_table_missing`.
- Confirmado via página pública que o HTML já contém o debug do `produto.vue`, mas o payload da loja ainda vinha com `store.apps=[]` quando o registro BigShop estava com `deleted_at` preenchido.
- Confirmado via endpoint público `POST /api/v1/public/recommendations/config-check` que o SaaS ainda resolvia o produto como sem tabela de medidas.
- Corrigido o importador para preservar `measurement_table_id` existente quando o XML/feed não informa `measurement_table`.
- Teste de integração XML passou a simular novo sync após vínculo manual e garantir que a tabela não é removida.
- Documentada a depuração BigShop com `?pvdebug=1` e comandos de console.
- Validações locais: `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=RecommendationApiTest` e `git diff --check`.
- Run `26352328525` do GitHub Actions finalizou com sucesso para o commit `2074f03`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 62 Depuração BigShop e seleção real de tabela

- Releitura obrigatória dos documentos principais e de `docs/bigshop_model3_pro_widget.md` concluída antes de iniciar a correção.
- Cópia local `D:\Projetos\bigbangshop2.0\src\pages\configurations\additionalAppsEdit.vue` ajustada sem commit/push para remover fallback local do Provador Virtual, ID fixo e textos fixos.
- Banner explicativo do editor BigShop passa a usar `description` retornada por `bbs.apps`; labels continuam vindo de `cod_1_name`, `cod_2_name`, `cod_3_name` e `cod_4_name`.
- Campos adicionais no editor BigShop passam a depender de `json_fields` do app, sem lista fixa de IDs no front.
- Antes de salvar app ativo no editor BigShop, `deleted_at` e `last_full` são enviados como `null` para não regravar soft delete antigo carregado pelo editor genérico.
- Portal do Provador Virtual corrigido para não selecionar a primeira tabela disponível quando o produto está com `measurement_table_id=NULL`.
- Confirmado que `https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js` responde `200`.
- Confirmado que o `config-check` da Luna Moda Festa retorna `403` com `Origin: https://www.lunamodafesta.com.br`, indicando domínio ainda não liberado no widget, e retorna `measurement_table_missing` sem `Origin`, indicando que o produto `716076` continua sem tabela vinculada no banco.
- Validações locais: `npm run build`, `php artisan test --filter=ProductsApiTest`, lint de `additionalAppsEdit.vue` na cópia local BigShop e `git diff --check`.
- Run `26353363931` do GitHub Actions finalizou com sucesso para o commit `3f242ac`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 63 Resolver widget BigShop pela integração

- Releitura obrigatória dos documentos principais e de `docs/bigshop_model3_pro_widget.md` concluída antes de iniciar a correção.
- Reproduzido contra produção que `config-check` com `Origin: https://www.lunamodafesta.com.br` ainda retornava `403 Origem não autorizada para este widget`.
- Reproduzido que o mesmo `config-check` sem `Origin` ainda retornava `measurement_table_missing`, embora o produto `716076` já estivesse com `measurement_table_id=1`.
- Identificada a lacuna: o widget público BigShop resolvia empresa apenas por `merchant_companies.platform='bigshop'` e `external_store_id=53`, mas a loja piloto está configurada pela integração `platform_connections`.
- Middleware de origem do widget e resolução pública de produto passaram a aceitar fallback por `platform_connections.platform='bigshop'` + `external_store_id`, usando a empresa vinculada à conexão.
- Testes adicionados para `config-check` e CORS BigShop resolvendo pela integração.
- Validações locais: `php artisan test --filter=RecommendationApiTest`, `php artisan test --filter=HardeningApiTest`, `vendor/bin/pint --dirty`, `npm run build` e `git diff --check`.
- Run `26353804637` do GitHub Actions finalizou com sucesso para o commit `a575777`, incluindo deploy remoto e smoke público.
- Após o deploy, `config-check` em produção para a Luna Moda Festa com `Origin: https://www.lunamodafesta.com.br` e `Origin: https://lunamodafesta.com.br` retornou `configured=true`, `product_id=6`, `measurement_table_id=1` e `Access-Control-Allow-Origin` correto.

## 2026-05-24 - Sprint 64 Corrigir preflight CORS do widget

- Releitura obrigatória dos documentos principais e de `docs/bigshop_model3_pro_widget.md` concluída antes de iniciar a correção.
- Console da Luna Moda Festa mostrou `Redirect is not allowed for a preflight request` ao chamar `https://provadorvirtual.online/provadorvirtual_v2/api/v1/public/recommendations/config-check`.
- HAR local `C:\Users\helbe\Downloads\www.lunamodafesta.com.br.json` confirmou `OPTIONS` com status `307` e erro `net::ERR_INVALID_REDIRECT`; o `POST` ficou com status `0`/`net::ERR_FAILED`.
- Reproduzido por terminal que `OPTIONS /provadorvirtual_v2/api/v1/...` retorna `307`, enquanto `OPTIONS /provadorvirtual_v2/public/api/v1/...` retorna `204` com `Access-Control-Allow-Origin` correto.
- Widget público ajustado para calcular `api_base` diretamente em `/provadorvirtual_v2/public/api/v1` quando o script estiver em subpasta, evitando redirect no preflight CORS.
- Adicionado `window.ProvadorVirtual.diagnostics()` e detalhes de falha no evento `provadorvirtual:config` para depuração futura.
- Validações locais: `php artisan test --filter=WidgetAssetTest`, `php artisan test --filter=RecommendationApiTest`, `php artisan test --filter=HardeningApiTest`, `php artisan test`, `npm run build` e `git diff --check`.
- Run `26354288938` do GitHub Actions finalizou com sucesso para o commit `445e7bb`, incluindo deploy remoto e smoke público.
- Após o deploy, o JavaScript público em `https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js` continha `/public/api/v1`, `diagnostics` e os detalhes de falha.
- Após o deploy, `OPTIONS /provadorvirtual_v2/public/api/v1/public/recommendations/config-check` com `Origin: https://www.lunamodafesta.com.br` retornou `204` sem redirect.
- Após o deploy, `POST /provadorvirtual_v2/public/api/v1/public/recommendations/config-check` para `store_id=53`, `product_id=716076`, `variant_id=46125939`, `sku=2553` e `platform=bigshop` retornou `configured=true`, `product_id=6`, `measurement_table_id=1` e tamanhos disponíveis.

## 2026-05-24 - Sprint 65 Validação visual do piloto Luna Moda Festa

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint documental.
- Usuário confirmou visualmente em produção que a página `https://www.lunamodafesta.com.br/716076-vestido-longo-luna-2553-fucsia` passou a exibir os botões do Provador Virtual.
- Evidência visual: os botões `PV Descubra seu tamanho` e `cm Tabela de Medidas` aparecem abaixo dos tamanhos `38`, `40` e `42`, no ponto planejado do `produto.vue` do model3 pro.
- Fluxo validado: loja BigShop `53`, produto pai/feed `716076`, variação BigShop `46125939`, SKU/ref `2553`, integração via XML/feed e tabela de medidas vinculada no SaaS.
- A validação confirma que a sequência das Sprints 61 a 64 resolveu preservação de tabela no sync XML, resolução da loja BigShop pela integração, domínio/origem do widget e redirect do preflight CORS.
- Próxima pendência operacional fora desta sprint: remover qualquer debug temporário que ainda exista no `produto.vue` oficial da BigShop quando a validação assistida terminar, mantendo apenas a depuração condicionada a `?pvdebug=1`.
- Validação local documental: `git diff --check`.
- Run `26354617302` do GitHub Actions finalizou com sucesso para o commit `9895b34`, incluindo deploy remoto e smoke público.

## 2026-05-24 - Sprint 66 Widget v2 gamificado com lógica do v1

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` sem expor valores sensíveis.
- Testado `https://provadorvirtual.online/provadorvirtual_v1/demo.php` com Playwright: links principais retornaram `200`, o drawer abriu, as etapas de medidas básicas, gênero/formato corporal, medidas detalhadas, recomendação, confete e feedback foram percorridas.
- Estudados os arquivos do v1 em `D:\Projetos\provadorvirtual_v1\demo.php`, `widget\widget.js`, `widget\widget.css`, `widget\recomendar.php` e `widget\salvar_feedback.php`.
- Widget v2 refatorado para fluxo em drawer com etapas progressivas, barra `Nível de precisão da IA`, cards de formato corporal, medidas detalhadas por tabela, confete próprio e feedback final completo.
- Backend passa a aceitar `shopper_profile.raw_widget_data` e persistir a jornada em `recommendation_logs.raw_widget_payload`.
- Rotina `pv:privacy-anonymize` atualizada para limpar `raw_widget_payload` junto com os demais dados corporais antigos.
- Testes automatizados locais passaram: `WidgetAssetTest`, `RecommendationApiTest` e `HardeningApiTest`.
- Validação visual local com Playwright em página demo virtual na origem `http://127.0.0.1:8012`: botões, etapa 1, etapa 2, etapa 3, recomendação `M`, feedback registrado e tabela de medidas carregada.
- Validação mobile local em viewport `390x844`: sem rolagem horizontal (`documentElement.scrollWidth = window.innerWidth`).
- Evidências visuais foram salvas em `.tmp/sprint66-widget/` e não devem ser versionadas.
- Commit `f52b228` enviado para `main`, porém o run `26356327237` falhou no smoke público porque o workflow ainda verificava o marcador antigo `data-pv-submit`, removido na refatoração em etapas.
- Smoke público atualizado para validar o marcador atual `data-pv-recommend` do novo fluxo gamificado do widget v2.
- Run `26356510237` do GitHub Actions finalizou com sucesso para o commit `f1d2dbf`, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.

## 2026-05-24 - Sprint 67 Corrigir avanço sequencial do widget

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint corretiva.
- Reproduzido o problema relatado: dados salvos do navegador podiam fazer a etapa 1 exibir 100% e o botão do rodapé podia enviar recomendação sem passar por `Corpo` e `Detalhes`.
- Corrigido o rodapé do widget para usar ação sequencial própria: etapa 1 avança para `Corpo`, etapa 2 avança para `Detalhes` e somente a etapa 3 chama a recomendação.
- A barra de precisão agora é limitada pela etapa visível: até 45% em `Medidas`, até 65% em `Corpo` e até 100% apenas em `Detalhes`.
- Removido o atalho de recomendação da etapa 2 para preservar a ordem do fluxo v1.
- Confete limitado a recomendações com 100% real de precisão; recomendações básicas não disparam celebração.
- Corrigido o clique perdido no rodapé após editar inputs numéricos: `change` passa a re-renderizar apenas select/checkbox, evitando trocar o botão no blur antes do clique.
- Teste visual temporário Playwright em `.tmp/sprint67-widget-flow.spec.js` validou perfil salvo completo e recomendação básica sem confete.
- Validações locais: `node --check backend/public/widget/v1/provador-virtual.js`, `php artisan test --filter=WidgetAssetTest`, Playwright temporário da Sprint 67, `php artisan test`, `npm run build` e `git diff --check`.
- Run `26357843460` do GitHub Actions finalizou com sucesso para o commit `4284a24`, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou que `https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js` contém `v2_sprint_67`, `Continuar para corpo`, `Continuar para detalhes` e `data-pv-footer-action`.
- Playwright pós-deploy validou `https://provadorvirtual.online/produto-teste/blusa-canelada-solar` e `https://www.lunamodafesta.com.br/716076-vestido-longo-luna-2553-fucsia`: com perfil completo salvo, a etapa 1 fica em 45%, o botão do rodapé mostra `Continuar para corpo` e o clique avança para `Corpo`, sem pular para o resultado.
- Observação da validação Luna: o banner LGPD da loja (`#lgpd_info_bb`) interceptou o clique do teste automatizado; foi ocultado apenas no teste para validar o fluxo do Provador Virtual.

## 2026-05-24 - Sprint 68 Recomendações progressivas do widget

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint corretiva.
- Reestudada a dinâmica do widget v1 a partir da documentação e dos prints recentes: recomendação parcial com altura + peso, incentivo `Aumentar Precisão`, tamanho recomendado no rodapé, passos progressivos, silhuetas por gênero, confete em 100% e links pequenos de créditos/privacidade.
- Widget público v2 ajustado para não recomendar com apenas altura ou apenas peso, mas chamar a API automaticamente quando altura + peso existem.
- Rodapé fixo volta a mostrar o tamanho recomendado quando há retorno da API, enquanto os botões no corpo das etapas continuam guiando o aumento de precisão.
- Etapas 1, 2, 3 e 4 viraram botões clicáveis, com travas por pré-requisito: altura/peso, gênero/formato corporal e medidas detalhadas completas.
- Cards de silhueta agora mudam conforme `Feminino` ou `Masculino`.
- Medidas e progresso passaram a ser persistidos por tabela de medidas no `localStorage`, permitindo reuso entre produtos que usam a mesma tabela.
- Fechamento do drawer salva snapshot silencioso quando já existe recomendação e o consumidor alterou dados.
- Confete ficou configurável por `theme.confetti_enabled`, com padrão ativado nos defaults do widget, demo, checkout, SaaS e ativação BigShop.
- Validações locais: `node --check backend/public/widget/v1/provador-virtual.js`, `php artisan test --filter=WidgetAssetTest`, Playwright temporário com servidor mockado, `php artisan test` e `npm run build`.
- Run `26366746266` do GitHub Actions finalizou com sucesso para o commit `790d875`, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou que o JS público contém `v2_sprint_68`, `pv_shopper_profile_v2_table_`, `confetti_enabled`, `Seu tamanho &eacute;` e `data-pv-step`.
- Pós-deploy confirmou que o CSS público contém `.pv-recommendation-inline`, `.pv-stepper button` e `.pv-shape-male`.
- Pós-deploy validou o endpoint público de recomendação com altura + peso, retornando `recommended_size=M`.
- Playwright pós-deploy em `https://provadorvirtual.online/produto-teste/blusa-canelada-solar` validou: altura isolada sem recomendação, altura + peso com `Seu tamanho é M`, etapa 2 liberada, 5 silhuetas femininas, etapa 3 liberada, resultado final com confete e perfil salvo em `pv_shopper_profile_v2_table_3`.

## 2026-05-24 - Sprint 69 Hierarquia visual e silhuetas do widget

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint.
- Copiadas as imagens de formato corporal do v1 para `backend/public/widget/v1/assets/body-shapes/`.
- Widget v2 passou a usar as imagens do v1 como máscaras CSS, recebendo a cor do tema configurado pela loja.
- Cabeçalho do drawer passou a usar fundo em gradiente com as cores de personalização do widget.
- Botões `Aumentar precisão` e `Finalizar e ver resultado` passaram a usar a hierarquia visual de CTA principal.
- Botão fixo do rodapé ficou discreto durante as etapas intermediárias e só mantém destaque forte quando o consumidor chega ao resultado com 100% de precisão.
- Feedback final ganhou texto explicando a escala de nota: `1 = não ajudou, 5 = perfeita`.
- Validações locais: `node --check backend/public/widget/v1/provador-virtual.js`, `php artisan test --filter=WidgetAssetTest`, Playwright mockado em `.tmp/sprint69-widget-visual-check.mjs`, `php artisan test`, `npm run build` e `git diff --check`.
- Observação local: `npm run build` concluiu com sucesso, mas o Vite avisou que recomenda Node `20.19+`; a máquina local está em Node `20.18.1`.
- Commit `a53613a` enviado para `main`; o run `26368265436` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou que o JS público contém `assetBaseUrl`, `pv-main-button-subtle`, `pv-shape-image` e o texto `Nota da recomendação`.
- Pós-deploy confirmou que o CSS público contém `.pv-shape-image`, `-webkit-mask`, `.pv-main-button-subtle` e o gradiente do cabeçalho.
- Pós-deploy confirmou `200` para os 9 assets públicos de silhueta e recomendação pública com altura + peso retornando `recommended_size=M`.

## 2026-05-24 - Sprint 70 Produto teste sem tamanho padrão e identidade visual

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` sem expor valores sensíveis.
- Páginas `/produto-teste/:slug` ajustadas para iniciar sem tamanho selecionado; o preço usa a primeira variação apenas como referência visual e o estoque orienta o usuário a selecionar um tamanho.
- Link `Voltar para loja teste` separado do nome da loja em um badge de contexto, evitando o texto inline embolado na página de produto teste.
- Logo, ícone e favicon oficiais copiados para `frontend/public/images/brand/`.
- Cabeçalho global e rodapé público passaram a usar o logo oficial do Provador Virtual.
- HTML base atualizado com favicon PNG, apple touch icon, `theme-color` e tags OG/Twitter usando a imagem oficial da marca.
- Validações locais: `npm run build`, `php artisan test --filter=DemoProductTest`, Playwright local desktop/mobile em `/produto-teste/vestido-midi-aurora` e `git diff --check`.
- Commit `d5d4e69` enviado para `main`; o run `26370389245` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou `200` para `logo_provador_virtual.png`, `icon_provador_virtual.png` e `favicon_provador_virtual.png` em `/images/brand/`.
- Pós-deploy confirmou que `/produto-teste/vestido-midi-aurora` contém favicon e tags OG/Twitter apontando para os assets oficiais.
- `scripts/validate-production.ps1` passou completo após o deploy.
- Playwright pós-deploy em desktop e mobile confirmou: nenhum tamanho selecionado por padrão, texto `Selecione um tamanho para ver a disponibilidade`, logo carregado e ausência de rolagem horizontal.

## 2026-05-24 - Sprint 71 Atualizar assets oficiais da marca

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` sem expor valores sensíveis.
- Novas versões de `icon_provador_virtual.png`, `logo_provador_virtual.png` e `favicon_provador_virtual.png` copiadas de `C:\Users\helbe\Downloads\` para `frontend/public/images/brand/`.
- Hashes dos três arquivos mudaram em relação aos assets publicados na Sprint 70.
- Dimensões atuais confirmadas: ícone `312x312`, favicon `312x312` e logo `3054x261`.
- Tags OG atualizadas para refletir as dimensões reais do novo logo.
- Validações locais: `npm run build` e `git diff --check`.
- Commit `d17d412` enviado para `main`; o run `26370907476` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou `200` e `Content-Length` atualizado para os três assets públicos: logo `530990`, ícone `31679` e favicon `31682`.
- Pós-deploy confirmou tags OG em produção apontando para `logo_provador_virtual.png` com `og:image:width=3054` e `og:image:height=261`.
- `scripts/validate-production.ps1` passou completo após o deploy.

## 2026-05-24 - Sprint 72 Alinhar degrade do cabeçalho do widget

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` sem expor valores sensíveis.
- Cabeçalho do drawer do widget ajustado para usar o mesmo gradiente dos botões principais e da barra de precisão: `var(--pv-secondary)` para `var(--pv-warm)`.
- Variável `--pv-warm` passou a derivar de `--pv-accent`, mantendo a personalização da loja aplicada de forma consistente ao cabeçalho, CTAs e precisão.
- Alteração limitada ao CSS público do widget, sem mudança no JavaScript, contratos de API ou fluxo de etapas.
- Validações locais: `php artisan test --filter=WidgetAssetTest`, `git diff --check`, `npm run build` em `frontend` e `npm run build` em `backend`.
- Observação local: `npm run build` na raiz não se aplica porque o projeto não possui `package.json` na raiz; os builds corretos ficam em `frontend` e `backend`.
- Observação local: o build Vite do backend concluiu com sucesso, mas avisou que recomenda Node `20.19+`; a máquina local está em Node `20.18.1`.
- Commit `4204bf1` enviado para `main`; o run `26371467799` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou com cache bust que `/provadorvirtual_v2/widget/v1/provador-virtual.css`, `/provadorvirtual_v2/public/widget/v1/provador-virtual.css` e `/widget/v1/provador-virtual.css` contêm `--pv-warm: var(--pv-accent);` e o cabeçalho com `linear-gradient(135deg, var(--pv-secondary), var(--pv-warm))`.
- `scripts/validate-production.ps1` passou completo após o deploy.

## 2026-05-24 - Sprint 73 Aviso discreto de salvamento local no widget

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` sem expor valores sensíveis.
- Removido do passo 1 o label com checkbox `Salvar minhas medidas neste navegador para próximas recomendações`.
- Criado aviso discreto no final do corpo rolável do drawer: `Ao usar o Provador Virtual, você concorda em salvar seus dados neste navegador.`
- Novo aviso usa classe `.pv-browser-note`, fonte menor, peso normal e cor secundária, sem bold.
- Teste `WidgetAssetTest` atualizado para cobrir a nova frase, a nova classe CSS e garantir que o texto antigo não volte.
- Validações locais: `node --check backend/public/widget/v1/provador-virtual.js`, `php artisan test --filter=WidgetAssetTest`, `git diff --check`, `npm run build` em `frontend` e `npm run build` em `backend`.
- Observação local: o build Vite do backend concluiu com sucesso, mas avisou que recomenda Node `20.19+`; a máquina local está em Node `20.18.1`.
- Commit `415e68f` enviado para `main`; o run `26372104049` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou com cache bust que o JS público contém `Ao usar o Provador Virtual` e `browserStorageNoticeHtml`, que o CSS público contém `.pv-browser-note` e `font-weight: 400`, e que o texto antigo não aparece mais.
- `scripts/validate-production.ps1` passou completo após o deploy.

## 2026-05-24 - Sprint 74 Refinar microtexto de salvamento local

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint, incluindo `docs/credentials.local.md` sem expor valores sensíveis.
- Aviso `.pv-browser-note` ajustado para `font-size: 0.88em`, igual à linha `.pv-precision`.
- Aviso `.pv-browser-note` passou a usar `font-style: italic` e `font-weight: 400`.
- Margem superior reduzida para `2px`, deixando o texto mais próximo de um microtexto auxiliar.
- Teste `WidgetAssetTest` atualizado para cobrir `font-style: italic`.
- Validações locais: `php artisan test --filter=WidgetAssetTest`, `git diff --check`, `npm run build` em `frontend` e `npm run build` em `backend`.
- Observação local: o build Vite do backend concluiu com sucesso, mas avisou que recomenda Node `20.19+`; a máquina local está em Node `20.18.1`.
- Commit `0c83622` enviado para `main`; o run `26372649754` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou com cache bust que o CSS público contém `.pv-browser-note`, `font-size: 0.88em` e `font-style: italic`.
- `scripts/validate-production.ps1` passou completo após o deploy.

## 2026-05-24 - Sprint 75 Refinar widget mobile e silhuetas

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint.
- Logo e botão de menu mobile da loja teste aumentados para melhorar reconhecimento e toque.
- Stepper do widget ajustado para manter as quatro etapas em uma única linha no mobile.
- Cores dos passos claros escurecidas para melhorar contraste e leitura.
- Silhuetas de corpo passaram de máscara CSS para imagens PNG reais, corrigindo casos em que apareciam apenas como blocos coloridos.
- Etapa de corpo compactada para reduzir rolagem e exibir os cards de silhueta mais cedo.
- Validações locais: `node --check backend/public/widget/v1/provador-virtual.js`, `php artisan test --filter=WidgetAssetTest`, `php artisan test --filter=DemoProductTest`, `npm run build`, `git diff --check` e Playwright mobile em viewports de `360px` e `400px`.
- Commit `2a92a0b` enviado para `main`; o run `26377480787` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou que o JS público contém `<img class="pv-shape-image"`, `loading="lazy"` e `content.scrollTop = 0`.
- Pós-deploy confirmou que o CSS público contém `object-fit: contain;`, passos mobile em quatro colunas e cor mais escura para passos claros.
- Pós-deploy confirmou `200 image/png` para asset público de silhueta masculina.

## 2026-05-24 - Sprint 76 Remover escala de nota do widget

- Releitura obrigatória dos documentos listados em `docs/README.md` concluída antes de iniciar a sprint.
- Removida do resultado final a seção redundante `Nota da recomendação` com botões de 1 a 5.
- Mantida a avaliação principal com `Sim, ajudou` e `Não ajudou`, tamanho escolhido e comentário.
- Widget deixou de enviar `rating` no feedback novo; o endpoint público segue aceitando `rating` opcional para compatibilidade com integrações antigas.
- Removidos estilos CSS da escala de nota e atualizada a cobertura do `WidgetAssetTest`.
- Commit `6c835c8` enviado para `main`; o run `26378458765` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou que o JS público não contém `Nota da recomendação` nem `data-pv-rating`, e que o CSS público não contém `.pv-rating`.

## 2026-05-24 - Sprint 77 Posicionar aviso de salvamento na etapa inicial

- Releitura obrigatória dos documentos listados em `docs/README.md` e da governança de commit/push/Actions confirmada antes de encerrar a sprint.
- Aviso `Ao usar o Provador Virtual, você concorda em salvar seus dados neste navegador.` passou a ser renderizado somente na etapa 1 do drawer.
- Aviso mantido no fim do corpo rolável da primeira etapa, com `font-style: italic`, `font-size: 11px`, peso normal e alinhamento central.
- Teste `WidgetAssetTest` atualizado para proteger a chamada do aviso dentro do bloco `state.step === 1` e o novo tamanho da fonte.
- Validações locais: `node --check backend/public/widget/v1/provador-virtual.js`, `php artisan test --filter=WidgetAssetTest`, `vendor/bin/pint --dirty`, `npm run build`, `git diff --check` e Playwright mobile confirmando o aviso no passo 1 com `11px` e ausência no passo 2.
- Commit `2a5c055` enviado para `main`; o run `26378864592` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou que o JS público chama `browserStorageNoticeHtml()` dentro de `state.step === 1`, que o CSS público contém `.pv-widget-root .pv-browser-note`, `font-size: 11px` e `font-style: italic`, e que o Playwright mobile em produção mostra o aviso no passo 1 e remove no passo 2.

## 2026-05-25 - Sprint 78 Handoff do tamanho recomendado e demo mobile

- Releitura obrigatória dos documentos listados em `docs/README.md` e da governança de commit/push/Actions confirmada antes de encerrar a sprint.
- O tamanho recomendado passou a ser botão acionável no banner parcial, no rodapé fixo e no resultado do drawer.
- Ao aceitar a recomendação, o widget fecha antes de emitir `provadorvirtual:size-selected`, evitando conflito com re-render da loja.
- Criada proteção contra clique fantasma de touch para impedir que o drawer reabra imediatamente depois de fechar no mobile.
- A página `/produto-teste` agora explica que a vitrine é uma demonstração, que os produtos não estão à venda e que o fluxo correto é entrar em um produto e clicar no widget.
- A página `/produto-teste/:slug` mostra alerta de produto fictício, bloqueia a seleção manual como decisão de compra e marca o tamanho somente quando o widget aplica a recomendação.
- Silhuetas do widget passaram para `loading="eager"` dentro do drawer, mantendo assets PNG reais do v1 e evitando atraso de carregamento em mobile.
- Playwright local mobile validou menu, vitrine, produto, ausência de overflow horizontal, quatro etapas em uma linha, imagens de silhueta com `naturalWidth=116` e handoff `Usar tamanho M` fechando o widget e marcando `M` na página.
- Commit `003c996` enviado para `main`; o run `26381419082` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Pós-deploy confirmou que o JS público contém `provadorvirtual:size-selected`, `suppressDrawerOpenUntil`, `loading="eager"` e `Usar tamanho`; Playwright mobile em produção validou menu, demo, silhuetas e aplicação do tamanho recomendado.

## 2026-05-25 - Sprint 79 Copy comercial e benefícios mobile

- Releitura obrigatória dos documentos listados em `docs/README.md` e da governança de commit/push/Actions confirmada antes de encerrar a sprint.
- Headline da seção de planos alterado de `Preço direto, com desconto para clientes BigShop` para uma mensagem focada no melhor e mais moderno provador com IA para aumentar vendas na loja online.
- A regra mobile específica de `.landing-benefits.metric-grid` passou a sobrescrever a grade desktop, evitando cards estreitos em 3 colunas no celular.
- Cards da seção `O que o lojista e o comprador sentem na prática` passaram a usar layout mobile em linha, com ícone em destaque, título e descrição legíveis.
- Playwright local mobile validou headline sem `BigShop`, uma coluna de cards, ausência de overflow horizontal e seis cards renderizados corretamente.
- Commit `83ac2da` enviado para `main`; o run `26381750743` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Playwright pós-deploy em `https://provadorvirtual.online/` validou a nova headline, ausência da frase antiga, grid mobile de benefícios em uma coluna, seis cards renderizados e ausência de overflow horizontal.

## 2026-05-25 - Sprint 80 Linguagem do provador e ajuda do widget

- Releitura obrigatória dos documentos listados em `docs/README.md` e da governança de commit/push/Actions confirmada antes de encerrar a sprint.
- Site público, loja teste, página de produto teste, termos e privacidade passaram a usar `provador` nos textos visíveis ao usuário final, removendo `widget` da cópia pública.
- Navegação pública passou de `Teste o widget` para `Teste o provador`.
- A seção protegida `/app/widget` manteve o rótulo técnico `Widget`, agora com ícone `i` de informação e tooltip explicando que é o provador exibido na página de produto da loja.
- Título principal da seção passou para `Instalação do provador`, com texto auxiliar explicando recomendação de tamanho, tabela de medidas e identidade visual.
- Microcópias da tela de instalação foram ajustadas para `Carregando provador`, `Salvar provador`, `Provador e tabela` e `Provador público`.
- Validações locais: `npm run build`, `git diff --check` e Playwright mobile em `/`, `/produto-teste`, `/produto-teste/camiseta-essencial-marinho`, `/privacidade`, `/termos` e `/app/widget`.
- Commit `feb76e2` enviado para `main`; o run `26382678616` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Playwright pós-deploy confirmou ausência de `widget` visível nas páginas públicas testadas, presença de `provador`, ausência de overflow horizontal e seção `/app/widget` com `Widget i`, tooltip e `Instalação do provador`.

## 2026-05-25 - Sprint 81 UX mobile premium do portal

- Releitura de `docs/README.md`, `docs/development_guidelines.md`, `docs/portal_ui_guidelines.md` e `docs/sprint_governance.md` confirmada antes de iniciar a sprint; `credentials.local.md` não foi aberto porque a alteração não exigiu segredo operacional.
- Corrigida a sobreposição do header mobile autenticado: `Portal da empresa` agora fica realmente oculto no viewport mobile e o botão de sair saiu do topo.
- Drawer autenticado recebeu botão próprio de fechar, usuário e ação `Sair`; o toggle do header fica invisível enquanto qualquer drawer está aberto.
- Drawer público recebeu botão próprio de fechar e o logout passou a mostrar texto `Sair` no mobile.
- Menu do portal passou de `Widget` para `Provador`, preservando a seção `/app/widget` com o termo técnico `Widget` e o ícone informativo.
- Topo da tela `/app/widget` foi simplificado para `Instalação e visual`, com texto auxiliar curto e menos redundante.
- Textos de topo de produtos, tabelas, usuários, go-live, SaaS e e-mails foram reescritos para evitar `Listagem...` repetindo o H1.
- Tipografia, largura do workspace, botões de topo e heading do sistema foram compactados para mobile.
- Validações locais: `npm run build`, `git diff --check` e auditoria Playwright mobile em 360px e 390px cobrindo 36 checagens entre rotas públicas, rotas autenticadas e drawers.
- Commit `b82316b` enviado para `main`; o run `26383644699` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Playwright pós-deploy em produção confirmou login demo, ausência de overflow/sobreposição em `/`, `/produto-teste`, `/produto-teste/:slug`, `/app`, `/app/widget`, `/app/produtos` e um único botão de fechar nos drawers público e autenticado.

## 2026-05-25 - Sprint 82 Checkout Mercado Pago transparente

- Releitura obrigatória dos documentos do projeto concluída antes da implementação.
- Projeto `D:\Projetos\NoAzul` analisado como referência de Mercado Pago: `checkout.php`, `api/checkout.php`, `api/webhook_mp.php` e variáveis `MERCADOPAGO_*`.
- Documentação oficial Mercado Pago conferida para Checkout Transparente via Payments, Pix, CardForm/MercadoPago.js, `X-Idempotency-Key`, `notification_url` e assinatura `x-signature`.
- Criada camada `CheckoutPaymentManager` para escolher operadora ativa entre `mercado_pago` e `pagarme`.
- Implementado `MercadoPagoCheckoutService` com Pix, cartão tokenizado no frontend, webhook `/api/v1/webhooks/mercado-pago`, polling pelo comando `pv:payments-sync` e ativação automática da empresa paga.
- Pagar.me foi preservada e filtrada por `provider=pagarme` no sync, para não tentar consultar pagamentos Mercado Pago.
- Criada configuração SaaS `/saas/checkout` e API `/api/v1/saas/checkout-settings` para selecionar a operadora ativa.
- Adicionadas migrations `saas_settings` e permissão `saas_checkout` para admins/suporte existentes.
- Checkout Vue passou a carregar MercadoPago.js somente quando cartão Mercado Pago estiver ativo; Pix segue direto pelo backend e `/checkout/sucesso` mostra QR Code/copia e cola/ticket sem mencionar Pagar.me.
- Documentação atualizada para `MERCADO_PAGO_*`, com regra explícita de não versionar valores reais; chaves de produção vindas do NoAzul devem ficar apenas em `docs/credentials.local.md`, `.env` local/remoto ou secret seguro.
- `backend/.env`, `docs/credentials.local.md` e o secret GitHub Actions `PRODUCTION_ENV` foram atualizados com Mercado Pago sem exibir valores sensíveis.
- Validações focadas passaram: `PublicCheckoutFlowTest`, `SaasCheckoutSettingsApiTest`, `PaymentSyncCommandTest` e `GoLiveReadinessApiTest`.
- Validação local completa passou com `php artisan test`, `npm run build`, `vendor/bin/pint --dirty`, `git diff --check` e Playwright mobile mockado do checkout Mercado Pago sem overflow horizontal.
- Commit `e9ab2f9` enviado para `main`; o run `26384825165` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Smoke pós-deploy confirmou que `/api/v1/public/checkout/config` em produção responde com operadora `mercado_pago`, métodos `pix,credit_card`, cartão habilitado e chave pública presente sem expor valores sensíveis.

## 2026-05-25 - Sprint 83 Checkout cartão primeiro e parcelas

- Checkout público passou a abrir com `Cartão` como opção inicial quando a operadora ativa suporta cartão.
- Aba `Pix` passou a exibir tag discreta `5% off`.
- Parcelamento foi limitado a até 10x sem juros no frontend e backend, para Mercado Pago e Pagar.me.
- Select de parcelas mostra `Nx de R$ ... sem juros`; selects técnicos do Mercado Pago que o cliente não precisa escolher foram ocultados.
- Resumo do cartão não exibe total anual antes da escolha de parcelas; após a escolha, destaca o valor da parcela e deixa o total anual em segundo plano. Em 1x, o valor principal já é o total.
- Validações locais: `npm run build`, `vendor/bin/pint --dirty`, `php artisan test --filter=PublicCheckoutFlowTest`, `php artisan test --filter=SaasCheckoutSettingsApiTest`, `php artisan test`, `git diff --check` e auditoria mobile Playwright mockada em 390px sem overflow horizontal.
- Commit `7eadd35` enviado para `main`; o run `26386034325` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Smoke pós-deploy confirmou Mercado Pago ativo, cartão habilitado, métodos `pix,credit_card` e `max_installments=10` nas tabelas de preço pública.

## 2026-05-25 - Sprint 84 Cópia comercial de pagamento

- Landing pública atualizada para informar cartão em até 10x sem juros ou Pix à vista com 5% de desconto no título da seção de planos e nos cards de preço padrão/BigShop.
- Defaults de e-mails transacionais `aguardando_pagamento`, `erro_pagamento` e `renovacao_plano` passaram a citar Pix com 5% de desconto e cartão em até 10x sem juros.
- Criada migration para atualizar somente templates transacionais que ainda estejam exatamente no texto padrão antigo, preservando personalizações do SaaS.
- Spec, backlog, arquitetura e pacote comercial revisados para não manterem a regra antiga de parcelamento como orientação atual.
- Validações locais passaram com build frontend, testes backend focados, Pint e `git diff --check`.
- Commit `fe2ab48` enviado para `main`; o run `26386407174` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Smoke pós-deploy confirmou a cópia pública com `10x sem juros`, Pix com `5% de desconto` e ausência da regra antiga de 12x na landing em produção.

## 2026-05-25 - Sprint 85 Checkout mobile Mercado Pago

- Releitura obrigatória dos documentos do projeto e da governança de commit/push/Actions confirmada antes da correção.
- Corrigida a altura dos campos seguros do Mercado Pago no checkout mobile: os invólucros `Número`, `Validade` e `CVV` agora têm altura fixa de 44px, alvo de toque preservado e overflow controlado.
- O `iframe` interno do MercadoPago.js passou a ser restringido para 22px de altura visual, evitando que estilos inline do SDK estiquem os campos no Android.
- Validação local passou com `npm run build`.
- Auditoria Playwright mobile local em 390px mockou o SDK com iframes de 260px e confirmou campos em 44px, iframes em 22px, aba `Cartão` ativa, 10 parcelas carregadas e ausência de overflow horizontal.
- Commit `84ca5e6` enviado para `main`; o run `26386718075` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Smoke Playwright mobile em produção em `https://provadorvirtual.online/checkout?platform=bigshop` confirmou os três campos seguros reais do Mercado Pago em 44px, iframes em 22px, `Cartão` ativo, regra CSS publicada e ausência de overflow horizontal.

## 2026-05-25 - Sprint 86 Governança e roadmap comercial de planos

- Releitura obrigatória de toda a documentação do projeto concluída, incluindo `docs/credentials.local.md` sem expor valores sensíveis.
- Confirmado que a governança já exigia commit, push e conferência de Actions/deploy a cada sprint, mas ainda não explicitava o prefixo obrigatório no título do commit.
- `docs/README.md` e `docs/sprint_governance.md` passaram a exigir que todo commit de sprint inicie com `Sprint <numero> - `.
- `docs/roadmap_sprints.md` recebeu o roadmap das Sprints 86 a 91 para planos mensal/anual, aceite legal, cookies, recorrência, cancelamento de renovação, boleto e QA final.
- `docs/product_backlog.md` e `docs/current_platform_state.md` foram atualizados para refletir a nova trilha comercial.
- Commit `6c1186c` enviado para `main`; o run `26410963870` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.

## 2026-05-25 - Sprint 87 Planos mensal/anual e nova matriz de preços

- Criado `CheckoutPlanCatalog` como fonte única para planos e preços do checkout.
- API pública `/api/v1/public/checkout/config` passou a retornar os planos `annual` e `monthly`, preço mensal por plataforma, total anual, total Pix quando aplicável, limite de parcelas e percentual de economia.
- Valores atuais implementados: qualquer plataforma mensal `R$ 489,80`, BigShop mensal `R$ 389,80`, qualquer plataforma anual `R$ 449,80/mes` e BigShop anual `R$ 349,90/mes`.
- Landing pública e checkout passaram a exibir mensal/anual com o valor mensal em destaque, total anual e economia percentual.
- Checkout aceita query `plan=annual` ou `plan=monthly`, recalcula total/parcelas conforme ciclo e mantém cartão como meio inicial quando disponível.
- Descrições enviadas às operadoras passaram a respeitar o período contratado, evitando texto fixo de 12 meses para plano mensal.
- Validações locais: `php artisan test --filter=PublicCheckoutFlowTest`, `php artisan test --filter=PaymentSyncCommandTest`, `php artisan test --filter=TransactionalEmailDispatchTest` e `npm run build`.
- Validação completa local passou com `php artisan test`, `vendor/bin/pint --dirty` e `git diff --check`.
- Commit `e21a2f3` enviado para `main`; o run `26411375635` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.

## 2026-05-25 - Sprint 88 Termos, privacidade, aceite e cookies

- Conferidas fontes oficiais da LGPD/ANPD sobre direitos dos titulares, cookies e papéis de controlador/operador antes da revisão legal operacional.
- Páginas `/termos` e `/privacidade` foram ampliadas e versionadas em `2026-05-25`, cobrindo contratação, cobrança, recorrência, pagamentos, integrações, IA, responsabilidades, LGPD, cookies/localStorage, retenção, segurança e direitos dos titulares.
- Checkout público passou a exigir `accepted_terms=true`; o box já vem marcado e aponta para termos e política de privacidade em nova aba.
- Criada a tabela `checkout_acceptances` e o modelo `CheckoutAcceptance` para salvar prova técnica do aceite com checkout, usuário, empresa, e-mail, documento, versões legais, data/hora, IP, user-agent e contexto comercial do pedido.
- Sessões de checkout passaram a carregar metadados `legal_acceptance` com versões dos documentos e horário de aceite.
- App Vue ganhou aviso discreto no rodapé sobre cookies técnicos, localStorage e registros operacionais, com botão `OK` e persistência por cookie/localStorage para não reaparecer.
- Validações locais: `php artisan test --filter=PublicCheckoutFlowTest`, `php artisan test`, `npm run build`, `php -l` nos novos/alterados arquivos PHP, `vendor/bin/pint --dirty` e `git diff --check`.
- Commit `ae0dc2b` enviado para `main`; o run `26411780677` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.

## 2026-05-25 - Sprint 89 Recorrência mensal e cancelamento de renovação

- Documentação oficial Mercado Pago conferida: criação de assinatura via `POST /preapproval`, consulta por `GET /preapproval/{id}`, faturas em `authorized_payments` e cancelamento/pausa por `PUT /preapproval/{id}` com `status=canceled` ou `paused`.
- Plano mensal pago por cartão no Mercado Pago passa a criar assinatura recorrente sem plano associado, com `card_token_id`, frequência mensal, `status=authorized`, valor mensal e referência externa do checkout.
- Criada tabela `billing_subscriptions` para registrar provedor, ID remoto, plano, ciclo, status, próxima cobrança, aceite de renovação, cancelamento futuro e payload da operadora.
- Webhook/sincronização Mercado Pago passam a reconhecer notificações de `preapproval` e atualizar assinatura/checkout sem reverter acesso pago quando a renovação futura é cancelada.
- Portal da empresa ganhou seção discreta `Preferências do plano` no dashboard, com checkbox `Renovação automática`; ao desmarcar, o backend chama `PUT /preapproval/{id}` com `status=canceled`.
- Cancelar a renovação futura marca `auto_renewal_enabled=false`, `cancel_requested_at` e mantém `checkout_sessions.status=paid`, sem estornar pagamentos aprovados nem parcelas em andamento.
- Renovação anual automática ficou documentada como pendência operacional: o anual continua como pagamento normal no cartão/Pix até validação segura sem dupla cobrança ou conflito com parcelamento anual.
- Validações locais: `php artisan test --filter=PublicCheckoutFlowTest`, `php artisan test --filter=BillingSubscriptionApiTest`, `php artisan test`, `npm run build`, `vendor/bin/pint --dirty` e `git diff --check`.
- Commit `aec5520` enviado para `main`; o run `26412440589` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.

## 2026-05-25 - Sprint 90 Boleto habilitável pelo SaaS

- Documentação oficial Mercado Pago conferida para meios de pagamento: boleto no Brasil é método do tipo `ticket`, com integração por Checkout Transparente e `payment_method_id=bolbradesco`.
- `checkout.boleto_enabled` foi adicionado às configurações SaaS e fica desabilitado por padrão.
- Tela `/saas/checkout` ganhou checkbox `Habilitar boleto`, salvando junto com a operadora ativa.
- API pública `/api/v1/public/checkout/config` passa a incluir `boleto` em `payment_methods` somente quando o SaaS habilita e a operadora ativa é Mercado Pago.
- Validação do checkout rejeita boleto por padrão e aceita `payment_method=boleto` apenas quando habilitado.
- Mercado Pago cria boleto por `/v1/payments` com `payment_method_id=bolbradesco`, vencimento operacional de 3 dias e snapshot com `ticket_url`, linha digitável/código de barras e expiração quando retornados.
- Checkout Vue ganhou aba `Boleto`, resumo sem desconto Pix e tela de sucesso com link/linha digitável e aviso de liberação após compensação.
- Validações locais: `php artisan test --filter=PublicCheckoutFlowTest`, `php artisan test --filter=SaasCheckoutSettingsApiTest`, `php artisan test`, `npm run build`, `vendor/bin/pint --dirty` e `git diff --check`.
- Commit `6ddf1c5` enviado para `main`; o run `26412934331` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.

## 2026-05-25 - Sprint 91 QA final do pacote comercial

- Revalidada a trilha comercial criada nas Sprints 86 a 90: planos mensal/anual, preços por plataforma, aceite legal, termos, privacidade, recorrência mensal no cartão, cancelamento de renovação futura e boleto controlado pelo SaaS.
- Suíte backend completa passou com `php artisan test`: 79 testes e 635 assertions.
- Build frontend passou com `npm run build`.
- Validação de produção passou com `.\scripts\validate-production.ps1` em `https://provadorvirtual.online`, cobrindo site, checkout, termos, privacidade, rotas SaaS/app, widget JS/CSS, health, ops, recomendação, sinal de aprendizado, esquecimento LGPD, CORS, login demo e go-live readiness.
- Resultado de produção: `PRODUCTION VALIDATION OK`; go-live readiness retornou `ready_with_warnings`, mantendo apenas pendências externas conhecidas.
- Pendências externas mantidas: transação real Mercado Pago Pix/cartão de baixo valor com webhook/cron, validação de renovação anual sem dupla cobrança ou conflito com parcelamento, credenciais oficiais BigShop/piloto real e finalização Pagar.me quando chegarem os dados operacionais.
- Commit `61e8fac` enviado para `main`; o run `26413377677` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público. A validação de produção foi repetida após esse deploy e retornou novamente `PRODUCTION VALIDATION OK`.

## 2026-05-25 - Sprint 92 Modo modal central do provador

- Releitura obrigatória de `docs/README.md`, documentos listados, `docs/sprint_governance.md`, diretrizes de portais e integração do widget concluída antes da implementação.
- A personalização do widget em `/app/widget` ganhou a opção visual `Drawer lateral` ou `Modal central`.
- A preferência é salva em `theme.presentation_mode`, com padrão `drawer` para instalações existentes e novas.
- O widget público passa a abrir o mesmo fluxo de recomendação em modal central amplo no desktop quando `presentation_mode=modal`; no mobile, o modal ocupa a tela toda.
- A mudança é visual: etapas, recomendação parcial, tabela de medidas, resultado, feedback, dados salvos no navegador e evento `provadorvirtual:size-selected` permanecem preservados.
- Validações locais: `php artisan test --filter=Widget`, `php artisan test`, `npm run build`, `vendor/bin/pint --dirty` e `git diff --check`.
- Commit `3436cc5` enviado para `main`; o run `26413966332` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`; verificação dos assets públicos confirmou `presentation_mode`, `pv-recommendation-modal` e regra mobile `height: 100dvh`.

## 2026-05-25 - Sprint 93 Previa de confetes no portal do widget

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- Confirmado que `theme.confetti_enabled` já existia no contrato do widget e que a loja usa `.pv-confetti-layer` com 42 peças, cores fixas e animação `pv-confetti-fall`.
- A personalização do widget em `/app/widget` passa a exibir `Animação de confetes` com texto operacional sobre a celebração no resultado completo.
- Ao marcar a opção no portal, a tela dispara a mesma animação visual usada na loja, sem alterar a regra pública de disparo no resultado com 100% de precisão.
- A prévia remove camadas anteriores e limpa timers ao sair da tela para evitar resíduos visuais no portal.
- Validações locais: `npm run build`, `php artisan test --filter=Widget` e `git diff --check`.
- Commit `7093036` enviado para `main`; o run `26414392783` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`; verificação dos assets publicados confirmou `portal-confetti-preview`, `Animação de confetes`, `.pv-confetti-layer` e `@keyframes pv-confetti-fall`.

## 2026-05-25 - Sprint 94 Limpeza do topo da loja teste

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- O topo de `/produto-teste` deixou de repetir `Provador Virtual` no eyebrow e no H1, já que a marca permanece fixa no header.
- O bloco principal passa a usar a chamada `Teste a recomendação de tamanho`, com texto orientando o usuário a entrar em um produto fictício e clicar em `PV Descubra seu tamanho`.
- A informação operacional da vitrine foi movida para badges discretos: `Vitrine fictícia` e quantidade de produtos para teste.
- O CTA público `Teste o provador` fica oculto enquanto o usuário já está em `/produto-teste` ou `/produto-teste/:slug`, evitando link redundante para a mesma experiência.
- Validações locais: `npm run build`, `php artisan test --filter=DemoProductTest`, `git diff --check` e conferência do build confirmando a nova chamada e ausência da frase antiga `Loja teste do Provador Virtual`.
- Commit `c0985fd` enviado para `main`; o run `26414805731` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`; verificação dos assets publicados confirmou `Teste a recomendação de tamanho`, `Demonstração interativa`, ausência de `Loja teste do Provador Virtual`, regra de CTA sensível à rota e CSS `.shop-heading-meta`.

## 2026-05-25 - Sprint 95 Checkout enxuto, pedidos SaaS e primeiro acesso

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- Checkout público reorganizado: a seção de empresa coleta somente plataforma e CNPJ, e os dados cadastrais completos ficam para o primeiro acesso ao portal.
- Inputs do checkout foram agrupados por tamanho esperado: CNPJ/CPF/telefone mais estreitos, nome/e-mail/cartão mais amplos e campos de validade/CVV/UF compactos.
- Parcelas no cartão agora aparecem como opções calculadas no próprio checkout quando o SDK ainda não populou o select, deixando claro que o usuário pode escolher antes de finalizar.
- Backend do checkout cria a sessão pendente antes de chamar a operadora; se a operadora recusar, a tentativa fica salva como `failed` com motivo técnico em `metadata.failure`.
- Painel SaaS ganhou `/saas/pedidos` e `/saas/pedidos/:id`, com listagem de pedidos/tentativas, motivo de falha e detalhe completo de aceite, empresa, usuário, assinatura, IDs da operadora e payloads.
- Portal da empresa ganhou formulário de dados cadastrais no dashboard quando a empresa nasceu apenas com CNPJ no checkout; ao salvar, a empresa fica com `profile_completed=true`.
- Pagar.me foi ajustada para usar o nome interno da sessão quando o checkout não envia razão social e para omitir endereço quando ele ainda não foi preenchido.
- Validações locais: `php artisan test --filter=PublicCheckoutFlowTest`, `php artisan test --filter=SaasCheckoutOrdersApiTest`, `php artisan test --filter=MerchantCompanyProfileApiTest`, `npm run build`, `vendor/bin/pint --dirty` e `php artisan test`.
- A suíte backend completa passou com 85 testes e 678 assertions; o build frontend passou com `vue-tsc --noEmit && vite build`.
- Commit `1c029ae` enviado para `main`; o run `26415840565` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`. O script foi ampliado para cobrir também `/saas/checkout` e `/saas/pedidos`, e a repetição limpa após a janela de throttle confirmou essas rotas novas.

## 2026-05-25 - Sprint 96 Widget instalação por plataforma e visual organizado

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- Consulta técnica feita em referências primárias de Shopify, WooCommerce, VTEX, Nuvemshop e Adobe Commerce para orientar os snippets e pontos de instalação por plataforma.
- API `/api/v1/widget-install` passou a retornar `platform_guide` e `platform_guides` com snippet, passos, ponto de instalação, dados suportados e exemplo de `reload` por plataforma.
- Tela `/app/widget` foi reorganizada em blocos de instalação, domínios e personalização; preview, código e guia lateral agora mudam conforme a plataforma selecionada.
- Snippets e exemplos foram personalizados para BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart e custom.
- `scripts/validate-production.ps1` passou a cobrir também `/app/widget`.
- Validações locais passaram com `php -l backend/app/Http/Resources/WidgetInstallResource.php`, `php artisan test --filter=WidgetInstallApiTest`, `php artisan test` com 85 testes e 690 assertions, `npm run build`, `vendor/bin/pint --dirty`, `git diff --check`, `GET http://127.0.0.1:5173/app/widget` e leitura autenticada local de `/api/v1/widget-install`.
- Commit `f44d281` enviado para `main`; o run `26416798463` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo a nova rota `/app/widget`, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness `ready_with_warnings`.

## 2026-05-25 - Sprint 97 Ajuste vertical da configuração do widget

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- A área de instalação de `/app/widget` foi ajustada para exibir plataforma, chave pública e status do widget um abaixo do outro.
- Selects e inputs dentro do formulário do widget receberam altura consistente de 44px.
- O campo `Domínios liberados` ganhou tooltip explicando que a lista protege o provador contra uso não autorizado da chave pública em outras lojas.
- As cores da personalização passaram a ficar uma abaixo da outra, com campo hexadecimal em largura legível.
- Validações locais passaram com `npm run build`, `php artisan test --filter=WidgetInstallApiTest`, `php artisan test` com 85 testes e 690 assertions, e `git diff --check`.
- Commit `c188d4e` enviado para `main`; o run `26418672266` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/widget`.

## 2026-05-25 - Sprint 98 Checkout contato em duas linhas e Pix mensal sem tag

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- O bloco `Acesso e pagamento` do checkout público foi reorganizado para exibir Nome e CPF na primeira linha, E-mail e Telefone na segunda.
- CPF e telefone mantêm largura compacta, enquanto nome e e-mail ocupam a coluna maior.
- A tag `5% off` da aba Pix e o resumo `Desconto Pix` agora aparecem somente quando o plano selecionado é anual e há desconto Pix real.
- Validações locais passaram com `npm run build`, `php artisan test --filter=PublicCheckoutFlowTest`, `php artisan test` com 85 testes e 690 assertions, e `git diff --check`.
- Commit `1e0af18` enviado para `main`; o run `26419066028` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/checkout`.

## 2026-05-25 - Sprint 99 Retorno para plataforma e URLs limpas

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- O cabeçalho público passa a mostrar `Voltar ao SaaS` para usuários `admin/support` autenticados com permissão SaaS e `Voltar ao portal` para usuários autenticados de empresa.
- O retorno aparece somente após a sessão ser carregada por `/me`, evitando direcionar usuário SaaS para o portal antes de conhecer o papel.
- As rotas antigas de frontend em `/provadorvirtual_v2` passam a redirecionar para a URL limpa da raiz; API, widget, `public/` e `up` continuam preservados no caminho técnico.
- O frontend também possui fallback de canonicalização para limpar `/provadorvirtual_v2` caso uma cópia de SPA antiga ainda seja servida.
- O smoke de deploy e `scripts/validate-production.ps1` foram ampliados para validar os redirects legados para a raiz.
- Validações locais passaram com `npm run build`, `php artisan test` com 85 testes e 690 assertions, e `git diff --check`.
- Commit `360ed12` enviado para `main`; o run `26419953084` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público com checagem de URL efetiva.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo redirects de `/provadorvirtual_v2/`, `/provadorvirtual_v2/login` e `/provadorvirtual_v2/app/produtos/novo` para a raiz limpa.

## 2026-05-25 - Sprint 100 Conclusão e erros do checkout

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- Erros opacos do Mercado Pago, como `| 25-05-2026T21:37:38UTC;...`, deixam de ser exibidos como texto bruto para o cliente.
- O backend passa a responder falhas do checkout com mensagem amigável, `error_code`, referência, operadora e meio de pagamento, mantendo a mensagem técnica original em `metadata.failure.technical_message` para suporte.
- As tentativas falhas continuam salvas em pedidos SaaS, agora com mensagem amigável, código técnico e payload interno mais útil para diagnóstico.
- A integração Mercado Pago usa `X-Idempotency-Key` com UUID persistido em `metadata.mercado_pago.idempotency_key`, separado do código interno do pedido.
- O checkout público passa a mostrar modal de erro para Pix, boleto e cartão, com código de referência e opção de tentar Pix quando o método atual não for Pix.
- Ao trocar de cartão para Pix/boleto ou ao mudar plano, o CardForm do Mercado Pago é desmontado e o DOM do formulário é recriado por método de pagamento para evitar tokenização indevida no submit Pix.
- A tela `/checkout/sucesso` ganhou ações completas por método: Pix com QR Code, copia e cola e botão de copiar; boleto com abrir, baixar e copiar código de barras; cartão aprovado com bloco de sucesso; sessão falhada com mensagem e código do erro.
- Validações locais passaram com `npm run build`, `php artisan test --filter=PublicCheckoutFlowTest` com 16 testes e 90 assertions, `php artisan test` com 86 testes e 700 assertions, `vendor/bin/pint --dirty` e `git diff --check`.
- Commit `c0415bd` enviado para `main`; o run `26421412473` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/checkout`, `/saas/pedidos`, rotas públicas, SaaS, portal, widget, APIs e redirects legados para URLs limpas.

## 2026-05-25 - Sprint 101 Corrige vencimento Pix Mercado Pago

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- A falha Pix real foi isolada com testes controlados na API Mercado Pago: CPF/e-mail e valor anual geravam QR Code quando o payload não enviava `date_of_expiration`.
- O campo recusado era `date_of_expiration`; a API retornava `The following parameters must be valid date and format (yyyy-MM-dd'T'HH:mm:ssz): date_of_expiration` junto do UUID de rastreio em `cause.data`.
- Pix e boleto passam a enviar vencimento em `America/Sao_Paulo` com milissegundos e offset, por exemplo `2026-05-26T19:22:16.000-03:00`.
- A extração de erro do Mercado Pago agora preserva a mensagem principal e o UUID técnico de `cause.data`, sem promover `description` vazio ou lixo opaco como motivo público.
- O checkout público continua exibindo mensagem amigável quando a operadora devolver erro técnico de data.
- Pagamentos diagnósticos criados durante a investigação foram conferidos como `cancelled/by_collector`.
- Validações locais passaram com `npm run build`, `php artisan test --filter=PublicCheckoutFlowTest` com 17 testes e 94 assertions, `php artisan test` com 87 testes e 704 assertions e `vendor/bin/pint --dirty`.
- Commit `17fe291` enviado para `main`; o run `26422281931` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/checkout`, `/saas/pedidos`, rotas públicas, SaaS, portal, widget, APIs e redirects legados.

## 2026-05-25 - Sprint 102 Ajusta resumo da conclusão de pagamento

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- A tela `/checkout/sucesso` passa a mostrar `Pedido` no lugar de `Código da empresa`.
- O resumo remove a operadora e usa `Status do pagamento` e `Forma de pagamento`.
- Status e formas de pagamento passam a ser exibidos em português, como `Aguardando pagamento`, `Pago`, `Não aprovado`, `Pix`, `Boleto` e `Cartão de crédito`.
- Os botões `Acessar painel` e `Voltar ao site` ganharam espaçamento real no bloco final.
- Validações locais passaram com `npm run build` e `git diff --check`.
- Commit `84c383a` enviado para `main`; o run `26423505273` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/checkout`, `/saas/pedidos`, rotas públicas, SaaS, portal, widget, APIs e redirects legados.

## 2026-05-25 - Sprint 103 Ajusta copy e economia dos planos

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- A seção pública de planos remove a frase `sempre com o valor mensal em destaque`, mantendo a copy focada na escolha mensal ou anual.
- Os cards anuais passam a mostrar apenas a tag `Economize 8,2%` para qualquer plataforma e `Economize 10,2%` para Cliente BigShop.
- O texto auxiliar dos cards anuais foi simplificado para não explicar o cálculo da economia.
- A faixa BigShop passa a perguntar `Ainda não tem uma loja online ou quer mudar para uma plataforma mais inteligente?`.
- Validações locais passaram com `npm run build` e `git diff --check`.
- Commit `0fb2dfe` enviado para `main`; o run `26424134815` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/`, `/checkout`, `/app/widget`, rotas públicas, SaaS, portal, widget, APIs e redirects legados.

## 2026-05-25 - Sprint 104 Enxuga textos e tooltips do provador

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- A primeira etapa do provador público troca os textos redundantes por `Comece com altura e peso. A idade é opcional.` e `Preencha altura e peso para ver o tamanho inicial.`.
- O aviso adicional sobre altura/peso foi removido do estado padrão, deixando apenas um bloco informativo antes dos campos.
- As mensagens de cálculo e dados mínimos da recomendação inicial foram encurtadas.
- Os blocos informativos do widget tiveram menor espaçamento e entrelinha mais compacta.
- Os tooltips de medidas deixam de exibir entidades HTML escapadas e passam a mostrar acentuação correta.
- Validações locais passaram com `node --check backend/public/widget/v1/provador-virtual.js`, `npm run build` e `git diff --check`.
- Commit `9256077` enviado para `main`; o run `26424515050` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/produto-teste`, widget JS/CSS, rotas públicas, SaaS, portal, APIs e redirects legados.

## 2026-05-25 - Sprint 105 Mantem aviso unico nas medidas

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- A etapa `Suas medidas` remove a frase `Comece com altura e peso. A idade é opcional.`.
- O estado inicial passa a mostrar apenas `Preencha altura e peso para ver o tamanho inicial.` antes dos campos.
- Validações locais passaram com `node --check backend/public/widget/v1/provador-virtual.js`, `npm run build` e `git diff --check`.
- Commit `8a04ed6` enviado para `main`; o run `26425163585` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`; o JS publicado confirma o aviso único e a remoção da frase antiga.

## 2026-05-28 - Sprint 106 Botões personalizados do widget

- Releitura obrigatória da documentação e da governança de sprint/commit/push concluída antes da implementação.
- Consulta pública Sizebay refeita nas páginas de implementação por script/API e na folha de estilo pública da Zak, confirmando o padrão de botões no ponto de decisão, visual limpo com ícones e exibição condicionada ao produto.
- O contrato do tema do widget passa a aceitar `button_style`, `button_background` e `button_text`.
- O widget público ganhou estilos `gradient`, `clean`, `outline` e `soft` para os botões do provador e da tabela, com animações de brilho, elevação, sublinhado e preenchimento.
- A tela `/app/widget` ganhou lista vertical de estilos personalizados, box de cores de fundo/texto dos botões e prévia em tempo real.
- Documentação atualizada em `current_platform_state`, `widget_integration`, `sizebay_benchmark` e `roadmap_sprints`.
- Validações locais passaram com `node --check backend/public/widget/v1/provador-virtual.js`, `vendor/bin/phpunit --filter Widget`, `vendor/bin/phpunit`, `npm run build`, `vendor/bin/pint --dirty` e `git diff --check`. No Windows local, os testes com banco foram executados via PHPUnit direto com `pdo_sqlite`/`sqlite3` carregados por `-d`, pois o `php.ini` atual carrega apenas `pdo_mysql` por padrão.
- Commit `68b647a` enviado para `main`; o run `26600519176` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/widget`, widget JS/CSS, rotas públicas, SaaS, portal, APIs e redirects legados.

## 2026-05-28 - Sprint 107 Benchmark Zak Sizebay e cadastro BigShop real

- Releitura obrigatoria da documentacao e da governanca de sprint/commit/push concluida antes da implementacao.
- A Zak foi cadastrada como cliente BigShop no Provador Virtual local e em producao com loja `124`, dominio `zak.com.br`, feed `https://www.zak.com.br/feed.xml` e token salvo criptografado, sem registrar segredo em documento versionado.
- O portal do cliente Sizebay da Zak foi estudado em modo somente leitura; nenhuma alteracao foi feita na Sizebay e nenhum contato foi realizado.
- Foram mapeadas as telas de dashboard, produtos, tabelas de medidas, modelagens, marcas, categorias, fontes de dados, sincronizacao, regras de importacao, customizacao de botoes/VFR, relatorios, pedidos e devolucoes.
- A documentacao Sizebay foi revalidada nas partes de implementacao por script, integracao de produtos por XML/API, order tracking e devolucoes; a galeria publica de botoes tambem entrou no benchmark.
- Foi criado `docs/sizebay_zak_hyper_benchmark.md` com cadastro Zak, achados do portal, comparacao de modelo de dados, plano seguro de importacao e recomendacoes priorizadas para o Provador Virtual.
- A validacao BigShop real da Zak mostrou que `Store-Id` e obrigatorio para `products/product_grids`; o cliente BigShop foi ajustado para esse header e para normalizar retorno paginado/envelopado de produtos.
- A importacao final de tabelas da Zak ficou bloqueada de proposito ate implementarmos dry-run, paginacao completa, `product_grids`, mapeamentos e modelo flexivel de medidas.
- Validacoes locais passaram com `php -l backend/app/Services/Integrations/BigShopClient.php`, `vendor/bin/phpunit --filter BigShopIntegrationTest`, `vendor/bin/phpunit` com 87 testes e 717 assertions, `vendor/bin/pint --dirty` e `git diff --check`.
- Commit `931d09e` enviado para `main`; o run `26602780031` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo páginas públicas, SaaS, portal, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 108 Botões da galeria Sizebay correta

- Confirmado que a Sprint 106 ficou incompleta em relação à galeria correta `https://sizebay-buttons-gallery.vercel.app/`.
- A galeria pública foi renderizada e inspecionada em leitura para mapear os 10 modelos: texto com ícones, ícone lateral, bloco escuro, sublinhado, pílulas, linha central, editorial, pontilhado, bloco claro e selo novo com tooltip.
- A tela `/app/widget` passa a selecionar os 10 modelos em lista vertical e mantém o box de cores de fundo/texto com prévia viva.
- O widget público aceita os 10 novos valores `gallery_*`, aplica hover/animações coerentes com cada modelo e respeita redução de movimento.
- Os valores antigos `gradient`, `clean`, `outline` e `soft` continuam aceitos no backend/widget para compatibilidade, enquanto o portal converte seleções antigas para a galeria nova.
- Documentação atualizada em `widget_integration`, `sizebay_benchmark`, `sizebay_zak_hyper_benchmark`, `current_platform_state` e `roadmap_sprints`.
- Validações locais passaram com `php -l`, `node --check backend/public/widget/v1/provador-virtual.js`, `vendor/bin/phpunit --filter Widget`, `vendor/bin/phpunit` com 87 testes e 727 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, `git diff --check` e renderização Puppeteer dos 10 modelos sem botões vazios ou sobrepostos.
- Commit `482631e` enviado para `main`; o run `26603841134` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo páginas públicas, SaaS, portal, `/app/widget`, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 109 Dry-run BigShop Zak com grades

- A primeira demanda da lista pós-benchmark foi iniciada antes de importar a Zak: dry-run BigShop com paginação, `product_grids`, join por produto e extração de tamanho.
- `BigShopClient` passa a paginar `products` e `product_grids` usando `Store-Id`, mantendo suporte a retorno paginado/envelopado.
- Criado `BigShopDryRunService` para cruzar grades por `produtoid`, extrair tamanho de `caracteristicas`, contar produtos/grades/tamanhos e gerar erros/alertas por produto sem gravar catálogo.
- O endpoint protegido `POST /api/v1/integrations/bigshop/dry-run` retorna `dry_run=true` e registra evento `dry_run_import`.
- `/app/integracoes` ganhou botão `Prévia segura` e painel com contadores, amostra de produtos, tamanhos detectados e lista de erros/alertas.
- Validações locais passaram com `php -l`, `vendor/bin/phpunit --filter BigShopIntegrationTest`, `vendor/bin/phpunit` com 88 testes e 745 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty` e `git diff --check`.
- Commit `6aaf8f4` enviado para `main`; o run `26604636247` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo páginas públicas, SaaS, portal, `/app/integracoes`, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 110 Tela de sincronização e erros por produto

- A segunda demanda da lista pós-benchmark foi iniciada: tela de sincronização com histórico e erros por produto.
- Criado `GET /api/v1/integrations/sync-history`, consolidando `dry_run_import`, `sync_products` e `xml_feed_sync`.
- O endpoint normaliza contadores e agrega erros vindos de `integration_events.payload.issues`, erro geral do evento e `import_jobs.errors`.
- O portal ganhou `/app/sincronizacao`, menu próprio e tela list-first com filtros por status/tipo, detalhe da execução, amostra de produtos e seção `Erros por produto`.
- `scripts/validate-production.ps1` passa a cobrir `/app/integracoes` e `/app/sincronizacao`.
- Validações locais passaram com `php -l`, `vendor/bin/phpunit --filter IntegrationsApiTest`, `vendor/bin/phpunit` com 89 testes e 755 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty` e `git diff --check`.
- Commit `efe87b8` enviado para `main`; o run `26605323289` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/integracoes`, `/app/sincronizacao`, páginas públicas, SaaS, portal, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 111 Regras visuais de importação

- A terceira demanda da lista pós-benchmark foi iniciada: regras visuais para categoria, marca, gênero, faixa etária, status e modelagem.
- Criado `platform_connections.import_rules` e `ImportRuleMapper` para manter regras por conexão e normalizar valores antes de sincronizar.
- O dry-run BigShop passa a devolver campos mapeados, contadores de regras e alertas quando regra obrigatória fica sem origem/fallback.
- O sync BigShop e o sync XML/feed passam a aplicar o mesmo mapeamento em produtos, metadados e tabelas criadas.
- O portal ganhou `/app/regras-de-importacao`, menu `Regras`, lista vertical de regras, editor de origem/fallback/normalizações e prévia visual.
- `scripts/validate-production.ps1` passa a cobrir `/app/regras-de-importacao`.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter BigShopIntegrationTest`, PHPUnit completo com 90 testes e 772 assertions, `npm --prefix frontend run build` e `vendor/bin/pint --dirty`.
- Commit `5d938ba` enviado para `main`; o run `26606288957` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/regras-de-importacao`, `/app/integracoes`, `/app/sincronizacao`, páginas públicas, SaaS, portal, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 112 Tabelas flexíveis de medidas

- A quarta demanda da lista pós-benchmark foi iniciada: evoluir tabelas para corpo, peça, sistema de tamanho, ranges e medidas compostas.
- `measurement_tables` ganhou `measurement_target`, `size_system` e `range_mode`.
- `measurement_table_rows` ganhou `measurements` e `composite_measurements` em JSON, preservando colunas legadas usadas pelo motor atual.
- O controller passa a montar payload flexível por linha e a guardar a medida composta `fit_balance`.
- Os resources e o `config-check` do widget expõem os novos campos sem quebrar a resposta antiga.
- A tela de tabela ganhou base da tabela, sistema, modo de range, coluna de medida composta e listagem com base/sistema.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter MeasurementTablesApiTest`, PHPUnit completo com 90 testes e 777 assertions, `npm --prefix frontend run build` e `vendor/bin/pint --dirty`.
- Commit `2872cc7` enviado para `main`; o run `26606965068` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/tabelas-de-medidas`, `/app/tabelas-de-medidas/nova`, `/app/regras-de-importacao`, `/app/integracoes`, `/app/sincronizacao`, páginas públicas, SaaS, portal, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 113 Cadastro de modelagens

- A quinta demanda da lista pós-benchmark foi iniciada: criar cadastro de modelagens.
- Criada tabela `fit_profiles` com escopo por merchant/empresa, código canônico, intensidade, elasticidade, status e metadados.
- Modelagens padrão Slim, Regular, Ampla, Solta e Conforto passam a ser criadas para merchants existentes e no seed demo.
- Criado CRUD protegido `/api/v1/fit-profiles`, com contadores de produtos/tabelas, bloqueio de exclusão quando há vínculos e retarget automático quando o código da modelagem é alterado.
- O portal ganhou `/app/modelagens`, menu `Modelagens`, lista vertical e formulário limpo com uso/status.
- Formulários de produto e tabela passam a carregar modelagens cadastradas; listagens exibem a modelagem vinculada.
- `scripts/validate-production.ps1` passa a cobrir `/app/modelagens`.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter FitProfilesApiTest`, testes focados de produtos/tabelas, PHPUnit completo com 91 testes e 806 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`.
- Commit `85f7cec` enviado para `main`; o run `26607795341` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/modelagens`, `/app/tabelas-de-medidas`, `/app/produtos`, `/app/regras-de-importacao`, `/app/integracoes`, `/app/sincronizacao`, páginas públicas, SaaS, portal, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 114 Publicação e preview do widget

- A sexta demanda da lista pós-benchmark foi iniciada: ampliar personalização do widget com preview mobile/desktop, publicar/desfazer e galeria completa.
- `widget_installs` ganhou campos de rascunho para plataforma, domínios, tema e status ativo, além de `published_at`.
- `PATCH /api/v1/widget-install` passa a aceitar `mode=draft`, `mode=publish` e `mode=discard`, mantendo chamadas antigas como publicação direta.
- O recurso de widget expõe `draft` e `has_unpublished_changes`, enquanto o widget público continua lendo apenas a configuração publicada.
- `/app/widget` passou a salvar rascunho, publicar, desfazer rascunho e indicar `Publicado`, `Rascunho salvo` ou `Alterações locais`.
- O visualizador ganhou alternância Desktop/Mobile, mantendo os 10 modelos da galeria Sprint 108.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter WidgetInstallApiTest`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter WidgetAssetTest`, PHPUnit completo com 91 testes e 825 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`.
- Commit `a6e1ff1` enviado para `main`; o run `26608432348` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/widget`, páginas públicas, SaaS, portal, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 115 Analytics de uso e base de IA

- A sétima demanda da lista pós-benchmark foi iniciada: usar pedidos, devoluções e feedback para alimentar IA e melhorar sugestões de tabela.
- `POST /api/v1/public/recommendations/{id}/signal` passa a aceitar tamanho comprado/devolvido, tamanho de troca, motivo de devolução, status, quantidade, valor, plataforma de origem e data do evento.
- Referências de pedido continuam fora da base bruta: o sistema salva apenas `order_reference_hash`.
- `LearningSignalService` ganhou pesos por tipo de sinal: compra pesa mais que feedback, devolução/troca classificada vira revisão forte e outlier crítico segue com peso zero.
- Criado `MeasurementTableInsightService`, que agrupa sinais por tabela e sugere ações como revisar peça pequena, peça grande, modelagem, feedback ou coletar mais dados.
- `/api/v1/analytics/recommendations` expõe KPIs de compras/devoluções/trocas, taxa de retorno e `measurement_table_insights`.
- `/app/analytics` mostra uma lista limpa de sugestões de tabela baseadas em pedidos, devoluções, trocas e feedback.
- O Assistente de IA recebe contexto de aprendizado compatível com tipo, gênero e modelagem; `/app/assistente` exibe os insights usados antes de criar o rascunho.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter RecommendationApiTest`, `--filter AnalyticsApiTest`, `--filter AiMeasurementAssistantTest`, PHPUnit completo com 92 testes e 850 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`.
- Commit `8277337` enviado para `main`; o run `26609097848` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- `scripts/validate-production.ps1` passou a cobrir `/app/analytics` e `/app/assistente`, além das rotas já monitoradas.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/analytics`, `/app/assistente`, `/app/widget`, páginas públicas, SaaS, portal, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 116 Vínculo em lote de tabelas nos produtos

- Iniciada a melhoria de operação da listagem de produtos.
- Hoje o vínculo principal entre produto e tabela é `products.measurement_table_id -> measurement_tables.id`.
- O vínculo já podia ser feito no formulário de produto, por importação CSV/XML quando a origem traz `measurement_table`, e por sync BigShop quando o payload traz tabela/medidas estruturadas.
- Criado `PATCH /api/v1/products/bulk-measurement-table` para vincular a mesma tabela a vários produtos selecionados, respeitando merchant/empresa ativa.
- `/app/produtos` ganhou barra compacta e sticky acima da tabela com busca, filtros, seletor de tabela, botão `Vincular`, seleção de todos os itens filtrados, limpar seleção e contador.
- A listagem ganhou coluna de checkbox para seleção em massa, mantendo a associação da tabela visível sem abrir o produto.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter ProductsApiTest`, PHPUnit completo com 93 testes e 863 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`.
- Commit `e802ad6` enviado para `main`; o run `26609619782` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/produtos`, `/app/produtos/novo`, páginas públicas, SaaS, portal, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 117 Navegação contextual do logo

- Ajustado o logo principal para respeitar o contexto atual.
- Em rotas SaaS, o logo aponta para `/saas`.
- Em rotas do portal da empresa, o logo aponta para `/app`.
- Em login e páginas públicas, o logo aponta para `/`.
- Na home do site, clicar no logo rola a página para o topo sem trocar de rota.
- O logo mostrado no cabeçalho do menu mobile público passa a usar a mesma regra.
- Validação local passou com `npm --prefix frontend run build`.
- Commit `98c24b8` enviado para `main`; o run `26609952186` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo login, SaaS, portal da empresa, páginas públicas, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 118 Personalização visual dos botões

- A personalização de botões da tela `/app/widget` foi reorganizada em uma coluna única.
- O Visualizador passou para modal acionado pelo botão `Visualizar`; os cards `Código` e `Onde instalar` ficam no final da página.
- A galeria de botões passou de 10 para 12 modelos, exibidos em grade 3x4 no desktop.
- `PV` e `cm` foram substituídos por ícones configuráveis no preview e no widget público.
- Criado catálogo de ícones de medidas com cabide, régua, fita métrica, esquadro, camiseta, corpo, tabela e etiqueta.
- A escolha de cores e ícones fica abaixo da grade de modelos de botão.
- O checkbox `Animar ícone do cabide` aparece somente quando o cabide é o ícone do botão `Descubra seu tamanho`.
- A animação do cabide usa movimento pendular e respeita `prefers-reduced-motion`.
- API, validação e defaults passaram a aceitar `button_primary_icon`, `button_secondary_icon` e `button_icon_animation`.
- O widget público ganhou renderização dos ícones configuráveis e os estilos `gallery_11_icon_chips` e `gallery_12_dual_cards`.
- Validações locais passaram com `npm --prefix frontend run build`, testes focados `WidgetInstallApiTest|WidgetAssetTest`, PHPUnit completo com 93 testes e 884 assertions, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`.
- Commit `4c66327` enviado para `main`; o run `26610700834` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/widget`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-28 - Sprint 119 Integrações em seções

- A tela `/app/integracoes` foi reorganizada para uma coluna única.
- A antiga coluna lateral de plataformas foi substituída por uma seção `Plataforma` no topo.
- O seletor de plataformas aparece somente quando houver mais de uma integração disponível e o contrato não estiver travado em BigShop.
- Credenciais, URL da API, XML/feed, status, token e webhook ficam agrupados na seção `Conexão`.
- URL para validar, botão de validação, checklist e resultado técnico ficam agrupados na seção `Validação da instalação`.
- Passo a passo, local de instalação e snippet de reload ficam na seção `Instalação no produto`.
- `Dados suportados`, `Snippet`, `Ações`, resultado de sincronização, prévia BigShop e ativações um clique viraram seções independentes no mesmo fluxo vertical.
- Validações locais passaram com `npm --prefix frontend run build`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest`, varredura de segredos e `git diff --check`.
- Commit `c366754` enviado para `main`; o run `26611218335` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/integracoes`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-29 - Sprint 120 Refinamento visual das integrações

- Revisado o print da tela `/app/integracoes` após a Sprint 119: a estrutura em uma coluna ficou correta, mas o resumo da plataforma, o passo a passo, os dados suportados e o snippet podiam ficar vazios quando a API não retornava metadados completos.
- A seção `Plataforma` ganhou fallback de nome, resumo e ícone, com texto específico para BigShop quando aplicável.
- O CSS do resumo da plataforma foi refinado para o ícone não herdar regras de texto e para o status usar uma classe própria.
- A seção `Instalação no produto` ganhou passos padrão quando o guia da plataforma não trouxer etapas.
- As seções `Dados suportados` e `Snippet` passam a aparecer somente quando houver conteúdo real, evitando cards vazios no fluxo.
- Validações locais passaram com `npm --prefix frontend run build`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --configuration phpunit.xml --filter IntegrationsApiTest`, varredura de credenciais e `git diff --check`.
- Commit `c1ebf36` enviado para `main`; o run `26611893093` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/integracoes`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-29 - Sprint 121 Status e instruções adaptativas de integrações

- Investigado o `Rascunho` exibido na integração Zak: o card lia o status da conexão `platform_connections.status`; conexões antigas podiam continuar com `draft` mesmo tendo Store ID, feed e token salvos.
- A API de integrações agora calcula status efetivo: BigShop com Store ID e token ou feed aparece como `configured`; conexões `connected`, `disabled` e `error` continuam respeitadas.
- O salvamento de integração passa a transformar `draft` acidental em `configured` quando há dados mínimos, evitando regressão visual após editar credenciais.
- Criada migração para normalizar conexões antigas `draft` com dados mínimos para `configured`, cobrindo a Zak/BigShop sem expor token.
- Revisitada a documentação pública Sizebay de implementação por API, XML feed, Shopify e plataformas de order tracking. O aprendizado aplicado foi separar claramente serviço na PDP, catálogo por XML/API e tracking por plataforma.
- A seção `Plataforma` em `/app/integracoes` agora mostra, por plataforma, os campos de conexão esperados, o fluxo de catálogo, o ponto correto de instalação na página de produto e o caminho de tracking/aprendizado.
- `draft` passa a ser exibido como `Pendente` no portal para não sugerir rascunho quando o assunto é integração operacional.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest`, PHPUnit completo, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de credenciais e `git diff --check`.
- Commit `dbbe6b8` enviado para `main`; o run `26615382578` do GitHub Actions finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/integracoes`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-29 - Sprint 122 Empresa ativa e plataforma da loja

- Respondida a dúvida operacional: a plataforma da loja nasce no checkout público, pode ser definida/alterada no SaaS em `/saas/empresas/:id/editar` e, no portal, aparece no primeiro acesso em `Dados da empresa`.
- Para deixar isso evidente no fluxo de integração, `/app/integracoes` ganhou o bloco `Plataforma da loja`, com explicação de origem e seletor operacional.
- Empresas não BigShop podem trocar a plataforma entre Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart e Personalizada diretamente em `/app/integracoes`.
- BigShop continua protegido: contrato BigShop fica travado como BigShop no portal, e empresa não BigShop não consegue se autoativar como BigShop sem passar pelo cadastro SaaS.
- Criado `PATCH /api/v1/merchant/company-platform` com permissão `integrations.edit`.
- O store de autenticação agora guarda `pv_active_company_id`, reaplica a empresa selecionada após refresh e evita que o portal monte telas antes de carregar `/me`.
- O `RouterView` interno exibe carregamento de contexto enquanto a empresa ativa está sendo recuperada, evitando o fallback genérico `Plataforma` em `/app/integracoes`.
- Validações locais passaram com `npm --prefix frontend run build`, `php -l`, `vendor/bin/pint --dirty`, varredura de credenciais e `git diff --check`.
- `php artisan test --filter=MerchantCompanyProfileApiTest` ficou bloqueado localmente porque o PHP deste ambiente não tem driver SQLite (`could not find driver`); o GitHub Actions executou validação backend/deploy com sucesso.
- Commit `de6a1ef` enviado para `main`; o run `26616086732` finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/integracoes`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-29 - Sprint 123 Troca protegida de integração BigShop

- Corrigida a causa de `/app/integracoes` mostrar `Lojista não encontrado` para a Zak quando o admin SaaS entrava no portal da empresa: admin/support agora resolvem o lojista pelo escopo do token selecionado.
- Separados os conceitos de plataforma operacional e benefício comercial BigShop com `merchant_companies.bigshop_discount_active`.
- Lojas sem benefício BigShop podem trocar diretamente para qualquer plataforma no portal, inclusive BigShop sem desconto.
- Lojas BigShop com benefício ativo não mudam para outra plataforma de forma direta: o portal mostra `Mudar integração`, abre modal com explicação comercial, exige aceite dos termos e cria uma solicitação protegida.
- Criados modelo, migração e endpoints de `integration_change_requests` para portal e SaaS.
- A visão geral do SaaS mostra solicitações pendentes e a edição da empresa permite informar status, link de pagamento, observações e aplicar a troca quando a solicitação estiver concluída.
- Criada a página pública `/termos/troca-bigshop`.
- A tela `/app/integracoes` ganhou orientação de Google Tag Manager como caminho opcional para plataformas sem app/tema simples, usando container na PDP, tag HTML personalizada e validação antes de publicar.
- Documentação atualizada em guias de integração, arquitetura, widget e estado atual da plataforma.
- Validações locais passaram com `php -d extension_dir=... -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/phpunit` (102 testes, 927 assertions), `npm --prefix frontend run build`, `php -l`, `vendor/bin/pint --dirty`, varredura de credenciais e `git diff --check`.
- O primeiro push `9e16705` falhou no CI porque o teste novo não limpava o guard Sanctum entre duas requisições simuladas; corrigido em `c5b90e6`.
- O segundo push passou na validação backend, mas o deploy remoto falhou em MySQL strict mode por `timestamp not null` sem default em `requested_at`; ajustado para `dateTime` em `49c94e4`.
- Commit final `49c94e4` enviado para `main`; o run `26617845717` do GitHub Actions finalizou com sucesso, incluindo validação backend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- Validação de produção pós-deploy retornou `PRODUCTION VALIDATION OK`, incluindo `/app/integracoes`, SaaS, portal da empresa, páginas públicas, widget JS/CSS, APIs públicas, CORS, login demo e go-live readiness.

## 2026-05-29 - Sprint 127 Roadmap Sizebay para evoluir o Provador Virtual

- Mantido o comparativo Sizebay x Provador Virtual apenas local e ignorado pelo Git, conforme orientação do usuário.
- Acessado novamente o portal MySizebay da Zak em modo somente leitura, sem alterar dados, sem salvar configurações e sem registrar credenciais ou tokens.
- `docs/roadmap_sprints.md` recebeu um roadmap de implementação baseado em todos os itens do comparativo, não apenas nas prioridades finais.
- A criação do roadmap ficou registrada como Sprint 127; as sprints de implementação começam na Sprint 128.
- O roadmap define uma regra obrigatória para todas as sprints futuras: antes de codar, acessar a tela correspondente da Sizebay em modo leitura e implementar no Provador Virtual uma versão igual ou melhor.
- Foram planejadas sprints para navegação, painel de cobertura, produtos, vínculo de tabelas, tabelas avançadas, modelagens, marcas, categorias, taxonomia, integrações, BigShop, API/webhook/GTM, posicionamento do widget, sincronização, erros por produto, regras, widget, modal do provador, relatórios, pedidos, devoluções, IA, publicação, usuários, cobrança, auditoria, suporte e polimento geral.
- Validação local documental passou com varredura de segredos e `git diff --check`.
- Commit `a66236cb` enviado para `main`; o run `26623161467` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` foi tentada após o deploy, mas a máquina local não conseguiu abrir conexão TCP 443 para `provadorvirtual.online`/`108.179.241.241`; o bloqueio foi registrado como conectividade local, não como erro de aplicação.

## 2026-05-29 - Sprint 128 Navegação limpa e ajuda contextual

- Relida a documentação obrigatória antes da sprint e mantido o comparativo Sizebay local fora do Git.
- Acessado o MySizebay da Zak em modo somente leitura, sem salvar, publicar, acionar suporte ou alterar dados. O benchmark da dashboard mostrou menu lateral curto, tenant visível, cabeçalho limpo, card principal de cobertura de produtos e suporte acessível.
- O portal da empresa foi reorganizado em grupos de navegação por jornada: Operação, Catálogo, Provador, Resultados e Conta.
- A sidebar autenticada ganhou contraste operacional, estados ativos mais claros, rótulos em PT-BR voltados ao lojista e nota de contexto quando admin/support SaaS está no portal da empresa.
- Criada ajuda contextual discreta por tela crítica, com link para manual, próximo passo operacional e suporte.
- Criada a tela `/app/ajuda` com manual rápido por tópico, usando textos curtos e CTA direto para a ação relacionada.
- A navegação SaaS também foi agrupada para preservar consistência entre portais sem misturar contextos.
- Validação visual local em `http://127.0.0.1:5174` cobriu desktop, mobile e menu mobile com dados de preview injetados apenas no navegador.
- `npm --prefix frontend run build` passou. Testes backend não foram executados porque não houve alteração backend; o PHP local segue sem driver SQLite para testes que dependem do banco.
- Varredura de segredos nos arquivos versionados alterados e `git diff --check` passaram.
- Commit `001275b` enviado para `main`; o run `26625998268` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` foi tentada após o deploy, mas esta máquina local voltou a falhar ao conectar via HTTPS à produção; o bloqueio foi registrado como conectividade local, não como erro de aplicação.

## 2026-05-29 - Sprint 129 Painel de cobertura e prontidão operacional

- Relida a documentação obrigatória antes da sprint, incluindo `credentials.local.md` de forma mascarada por envolver backend/deploy.
- Acessado o MySizebay da Zak em modo somente leitura nas telas Dashboard e Products, sem alterar dados, sem salvar e sem acionar suporte. O benchmark confirmou donut de cobertura, contadores Active/Pending, abas All/Pending/Active/Inactive e tabela de produtos com categoria, chart, tamanhos, marca, faixa etária e modelagem.
- Criado `MerchantOverviewController` para consolidar `GET /api/v1/merchant/overview` no backend, com escopo por empresa ativa.
- O agregado retorna produtos totais, cobertos, ativos, pendentes, inativos, sem tabela, sem modelagem, sem categoria, com erro de sincronização, instalação pendente, taxa de cobertura, próximas ações e série de evolução quando houver histórico suficiente.
- `ProductResource` passou a expor `readiness_status`, `readiness_issues` e `has_sync_error` para filtros operacionais.
- O Painel agora mostra um placar acionável de cobertura, lista compacta de próximas ações e evolução de cobertura; os números levam para Produtos filtrados ou Publicação.
- `/app/produtos` entende filtros vindos do painel por query string, como `?filtro=sem_tabela`, `?filtro=sem_modelagem`, `?filtro=sem_categoria`, `?filtro=erro_sync`, `?filtro=pendentes` e `?filtro=prontos`.
- Validação visual local em `http://127.0.0.1:5174` cobriu dashboard desktop/mobile e lista de produtos filtrada com dados de preview injetados apenas no navegador.
- Validações locais passaram com `npm --prefix frontend run build`, `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter 'MerchantOverviewApiTest|ProductsApiTest'`, `php vendor/bin/pint --dirty`.
- Varredura de segredos nos arquivos versionados alterados, `git diff --check` e `git diff --cached --check` passaram.
- Commit `d1a71ad` enviado para `main`; o run `26627213077` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo páginas públicas, rotas SaaS, rotas do app, assets do widget, APIs, CORS, autenticação e readiness de go-live. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 130 Produtos com status, colunas e filtros superiores

- Relida a documentação obrigatória antes da sprint, incluindo `credentials.local.md` de forma mascarada por envolver produção/deploy e a sessão Sizebay.
- Acessado o MySizebay da Zak em modo somente leitura na tela Products, sem alterar dados, sem salvar e sem acionar suporte. O benchmark confirmou abas All/Pending/Active/Inactive com contadores, busca, limpeza de filtros, seleção por checkbox e colunas Product, Category, Chart, Sizes, Brand, Age group e Modeling.
- `GET /api/v1/products` passou a paginar no backend, aplicar filtros server-side e retornar resumo com contadores por aba e opções de filtros operacionais.
- A API de produtos agora filtra por busca, status, tabela, categoria, marca, gênero, faixa etária, modelagem, origem do dado, erro de sincronização e prontidão.
- `ProductResource` passou a expor marca, faixa etária, origem, rótulo de origem e tamanhos consolidados por produto, sem exigir que o frontend calcule prontidão carregando o catálogo inteiro.
- `/app/produtos` ganhou abas com contadores para Todos, Prontos, Pendentes, Sem tabela, Com erro e Desativados; filtros superiores compactos; colunas ampliadas; paginação; e preservou a seleção em massa para vínculo de tabela.
- Validação visual local rodou em `http://127.0.0.1:5175/app/produtos`, com backend local em `8001`, cobrindo desktop `1366x900` e mobile `390x844`, sem sobreposição incoerente.
- Validações locais passaram com `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit --filter 'ProductsApiTest|MerchantOverviewApiTest'`, `php vendor\bin\pint --dirty` e `npm --prefix frontend run build`.
- Varredura de segredos nos arquivos versionados alterados, `git diff --check` e `git diff --cached --check` passaram.
- Commit `84ea4be` enviado para `main`; o run `26629170275` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` passou integralmente. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 131 Detalhe do produto, origem dos dados e ativação por produto

- Relida a documentação obrigatória antes da sprint, incluindo `credentials.local.md` de forma mascarada por envolver produção/deploy, API pública e sessão Sizebay.
- Acessado o MySizebay da Zak em modo somente leitura em `/products/form/new` e no detalhe de produto existente, sem alterar dados, sem salvar, sem publicar e sem acionar suporte.
- O benchmark Sizebay confirmou formulário longo com `Integration information`, Product ID, imagem, campos principais, toggle `Enable Virtual Fitting Room`, tamanhos da loja, tabela do Size & Fit Advisor, seleção de tamanhos e botões Cancel/Save.
- O Provador Virtual evoluiu esse padrão para uma tela de detalhe com abas Resumo, Origem, Tabela, Tamanhos, Mídia, Diagnóstico e Histórico, mantendo primeira leitura limpa e ações separadas.
- `ProductResource` passou a expor ativação individual, origem por campo, snapshot importado, overrides manuais, diagnóstico acionável e histórico por metadados/auditoria.
- `PATCH /api/v1/products/{id}` agora preserva dados importados em `metadata.imported_snapshot`, registra `metadata.manual_overrides` para ajustes manuais e audita mudanças de ativação/override sem gravar segredos.
- O produto ganhou ativação individual para Provador Virtual e Tabela de Medidas em `metadata.activation`; a prontidão e os filtros passam a tratar produtos desativados individualmente como pendentes.
- A API pública de recomendação/config-check passa a respeitar `virtual_try_on_enabled`, `measurement_table_enabled` e status do produto, retornando motivo explícito quando o widget deve ficar oculto.
- Validação visual local rodou em `http://127.0.0.1:5175/app/produtos/5/editar`, com backend local em `8001`, cobrindo desktop e mobile, abas de origem/tabela/diagnóstico/histórico e sem overflow horizontal.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit --filter 'ProductsApiTest|RecommendationApiTest'`, `php vendor\bin\pint --dirty` e `npm --prefix frontend run build`.
- Varredura de segredos nos arquivos versionados alterados, `git diff --check` e `git diff --cached --check` passaram.
- Commit `1c0fc58` enviado para `main`; o run `26630698467` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 132 Vínculo de tabelas melhor que Sizebay

- Relida a documentação obrigatória antes da sprint, incluindo `credentials.local.md` de forma mascarada por envolver produção/deploy e a sessão Sizebay.
- Acessado o MySizebay da Zak em modo somente leitura em `/products` e `/table-measurements`, sem alterar dados, sem salvar, sem publicar e sem acionar suporte.
- O benchmark Sizebay confirmou coluna `Chart` diretamente na lista de produtos, `--` para produtos sem tabela, seleção por checkbox e lista de tabelas com nome, metadados e tamanhos.
- O Provador Virtual manteve a coluna clara de tabela e evoluiu a ação em massa com prévia antes de aplicar, resumo de sem tabela/conflitos/já vinculados/recomendados, recomendação por categoria, marca, gênero, modelagem e tamanhos, confirmação explícita para substituir vínculos e desfazer do último lote.
- `PATCH /api/v1/products/bulk-measurement-table` agora aceita `action=preview|apply|undo`, retorna prévia com conflitos/recomendações, bloqueia substituição sem `confirm_conflicts`, grava `batch_id` em `metadata.bulk_measurement_table`, registra histórico por produto e audita vínculo/desfazer em massa.
- `/app/produtos` ganhou fila operacional de produtos sem tabela, botão de prévia para vínculo em lote, painel de conflitos/recomendações e ação de desfazer o último lote aplicado.
- Validação visual local rodou em `http://127.0.0.1:5175/app/produtos`, com backend local em `8001`, cobrindo desktop e mobile do painel de prévia, sem sobreposição incoerente.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit --filter ProductsApiTest`, `php vendor\bin\pint --dirty` e `npm --prefix frontend run build` (com o aviso conhecido de bundle acima de 500 kB).
- Varredura de segredos nos arquivos versionados alterados, `git diff --check` e `git diff --cached --check` passaram.
- Commit `ea5b06b` enviado para `main`; o run `26632065139` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A primeira tentativa de `scripts/validate-production.ps1` sofreu reset de conexão pelo host remoto após `/login`; a repetição passou integralmente, incluindo páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 133 Tabelas com importar, exportar e observações

- Relida a documentação obrigatória antes da sprint, incluindo `credentials.local.md` de forma mascarada por envolver produção/deploy e a sessão Sizebay.
- Acessado o MySizebay da Zak em modo somente leitura em `/table-measurements` e `/modelings`, sem alterar dados, sem salvar, sem publicar e sem acionar suporte.
- O benchmark Sizebay confirmou na tela de tabelas os botões Export, Import, Create Measurement Table, busca e filtros; a rota `/modelings` não abriu uma tela própria útil nesta sessão, mas o aprendizado foi manter modelagem como filtro operacional visível.
- O Provador Virtual ganhou exportação CSV/XLSX de tabelas respeitando os filtros aplicados, modelos editáveis para bases corpo, peça e mista e importação dedicada com prévia visual antes do commit.
- A prévia de importação aponta erros por linha, coluna, campo e sugestão; bloqueia máximo menor que mínimo, valores fora de 0 a 999,99, enums inválidos e tamanhos duplicados dentro da mesma tabela.
- Observações por tabela, tamanho e medida passam a ser preservadas em `notes` e `measurement_table_rows.metadata`, aparecendo no formulário, na listagem, na exportação e no retorno da API.
- `POST /api/v1/measurement-tables/import` cria ou atualiza tabelas por nome no escopo da empresa ativa, substitui linhas somente após prévia sem falhas e registra auditoria `measurement_table.imported`.
- Validação visual local rodou em `http://127.0.0.1:5175/app/tabelas-de-medidas`, com backend local em `8001`, cobrindo desktop e mobile com prévia de CSV sem gravar dados.
- Validações locais passaram com `php -l`, `php vendor\bin\pint --dirty`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit --filter MeasurementTablesApiTest`, PHPUnit completo (`108 tests`, `1052 assertions`) e `npm --prefix frontend run build` (com o aviso conhecido de bundle acima de 500 kB).
- Varredura de segredos nos arquivos versionados alterados, `git diff --check` e `git diff --cached --check` passaram.
- Commit `3c2dda6` enviado para `main`; o run `26633856533` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 134 Editor avançado de medidas e variações

- Relida a documentação obrigatória antes da sprint, incluindo `credentials.local.md` de forma mascarada por envolver produção/deploy e a sessão Sizebay.
- Acessado o MySizebay da Zak em modo somente leitura em `/table-measurements/form/new` e `/modelings/form/new`, sem alterar dados, sem preencher formulário, sem salvar, sem publicar e sem acionar suporte.
- O benchmark Sizebay confirmou formulário longo de criação com nome, marca, categoria, tipo, gênero, faixa etária, modelagem, sistema de tamanho, medição em cm/in, ranges, medição do corpo, medição da peça, medida composta, variação personalizada e opção para desativar o provador.
- O Provador Virtual ganhou editor avançado em blocos guiados para uso público, medidas do corpo, medidas da peça, sistema de tamanhos, faixas, medidas compostas e variações customizadas.
- O formulário valida unidade, mínimo, máximo e consistência das faixas no backend e no frontend, incluindo variações restritas sem faixa e máximo menor que mínimo.
- A tabela agora guarda metadados de ativação e variações customizadas; o widget pode manter apenas `Tabela de Medidas` quando o provador virtual estiver desativado por tabela.
- A tela `/app/tabelas-de-medidas/nova` ganhou prévia pública do widget, exibindo tamanhos, medidas e tags de variação para o lojista conferir antes de salvar.
- Validação visual local rodou em `http://127.0.0.1:5175/app/tabelas-de-medidas/nova`, com backend local em `8001`, cobrindo desktop e mobile, rolagem horizontal interna da grade e sem overflow incoerente.
- Validações locais passaram com `php -l`, `php vendor\bin\pint --dirty`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit --filter 'MeasurementTablesApiTest|RecommendationApiTest'`, PHPUnit completo (`109 tests`, `1063 assertions`) e `npm --prefix frontend run build` (com o aviso conhecido de bundle acima de 500 kB).
- Varredura de segredos nos arquivos versionados alterados, `git diff --check` e `git diff --cached --check` passaram.
- Commit `d816f41` enviado para `main`; o run `26635156508` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 135 Modelagens com diagnóstico e correção guiada

- Relida a documentação obrigatória antes da sprint, incluindo `credentials.local.md` de forma mascarada por envolver produção/deploy, API pública, integrações e sessão Sizebay.
- Acessado o MySizebay da Zak em modo somente leitura em `/modelings`, `/modelings/form/new`, `/settings/sync` e `/settings/sync/importation-rules`, sem alterar dados, sem preencher formulário, sem sincronizar, sem salvar e sem acionar suporte.
- O benchmark Sizebay mostrou que `/modelings` e `/modelings/form/new` reaproveitam Measurement Table, e que o valor real está em `Settings > Sync`: erros `[API] 500: "Modeling not found"` por produto com categoria, marca, gênero, faixa etária, tamanhos e botão `See more`.
- Criados `GET /api/v1/fit-profiles/diagnostics` e `POST /api/v1/fit-profiles/diagnostics/apply` para listar modelagens ausentes, desconhecidas, inativas ou incompatíveis e aplicar correção em lote.
- O diagnóstico sempre retorna causa, ação e sugestão: aplicar modelagem existente quando há confiança ou criar a modelagem ausente recebida da sincronização.
- A aplicação guiada cria modelagem quando necessário, aplica em massa nos produtos afetados, grava `metadata.fit_profile_diagnostic`, histórico no produto e auditoria `fit_profile.diagnostic_applied`.
- `FitProfileResource` passou a expor `guidance` com contexto para regras, IA e impacto na recomendação; a API pública de recomendação/config-check passa a registrar `modeling_context`, notas e avisos quando a modelagem está ausente, desconhecida ou inativa.
- A tela `/app/modelagens` ganhou painel de diagnóstico com métricas, grupos, amostras de produtos, confiança da sugestão e botão de aplicação em massa, além de bloco de impacto da modelagem na recomendação.
- Corrigido CORS local para permitir portas Vite alternativas `5174` a `5177` e incluir `/api/v1/fit-profiles*`, necessário porque `5173` estava ocupada nesta máquina.
- Validação visual local rodou em `http://127.0.0.1:5177/app/modelagens`, com backend local em `8002`, cobrindo desktop, mobile e grupo temporário de diagnóstico restaurado em seguida, sem overflow horizontal.
- Validações locais passaram com `php -l`, `php vendor\bin\pint --dirty`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit --filter 'FitProfilesApiTest|RecommendationApiTest'`, PHPUnit completo (`111 tests`, `1097 assertions`) e `npm --prefix frontend run build` (com o aviso conhecido de bundle acima de 500 kB).
- Varredura de segredos nos arquivos versionados alterados, `git diff --check` e `git diff --cached --check` passaram.
- Commit `9a69f27` enviado para `main`; o run `26636901205` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 136 Marcas locais e marcas normalizadas

- Relida a documentação obrigatória antes da sprint, incluindo `credentials.local.md` de forma mascarada por envolver produção/deploy, API pública, integrações e sessão Sizebay.
- Acessado o MySizebay da Zak em modo somente leitura em `/brands` e `/sizebay-brands`, sem alterar dados, sem importar/exportar, sem criar marca, sem salvar e sem acionar suporte.
- O benchmark Sizebay confirmou `/brands` com Name, Associated brand e ações Export all, Import, Create brand e Clear filters; `/sizebay-brands` mostrou a lista global Sizebay Brand com status Active, filtros e Create Sizebay Brand.
- Criadas as entidades `merchant_brands` e `normalized_brands`, com API `/api/v1/brands` para descobrir marcas vindas dos produtos, criar/editar, importar/exportar CSV, mesclar duplicidades e aplicar normalização em produtos.
- A normalização preserva `metadata.brand` e grava `metadata.brand_original`, `metadata.normalized_brand`, `metadata.rules_context.brand` e `metadata.ai_context.brand`, permitindo regras, IA, relatórios e filtros usarem a marca normalizada sem perder o nome recebido da loja.
- Importações CSV/XML e sincronização BigShop passam a registrar a marca local e reaplicar automaticamente a marca normalizada quando o mapeamento já foi revisado.
- `/app/marcas` ganhou painel de saúde do catálogo, lista de pendências, sugestões revisáveis com confiança, criação/edição, mescla de variações, importação com prévia e exportação/modelo CSV.
- `/app/produtos` ganhou filtro por marca normalizada e exibição conjunta da marca original com a normalizada; analytics e config-check/recomendação pública passam a expor contexto de marca normalizada.
- O script `scripts/validate-production.ps1` passou a validar `/app/marcas`, redirect legado para `/app/marcas` e `GET /api/v1/brands`.
- Validação visual local rodou em `http://127.0.0.1:5177/app/marcas`, com backend local em `8002`, cobrindo desktop e mobile com dados temporários locais, após ajuste do painel de sugestão para eliminar sobreposição.
- Validações locais passaram com `php -l`, `php vendor\bin\pint --dirty --test`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit --filter 'BrandManagementApiTest|ProductsApiTest|RecommendationApiTest|ImportsApiTest|IntegrationsApiTest'`, PHPUnit completo (`114 tests`, `1149 assertions`) e `npm --prefix frontend run build` (com o aviso conhecido de bundle acima de 500 kB).
- Varredura de segredos nos arquivos versionados alterados, `git diff --check` e `git diff --cached --check` passaram.
- Commit `e5c3cc2` enviado para `main`; o run `26638565143` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` passou integralmente após incluir `/app/marcas` e `API brands`. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 137 Categorias locais e taxonomia normalizada

- Relida a documentação obrigatória antes da sprint, incluindo `credentials.local.md` de forma mascarada por envolver produção/deploy, API pública, integrações e sessão Sizebay.
- Acessado o MySizebay da Zak em modo somente leitura em `/categories` e `/sizebay-categories`, sem alterar dados, sem importar/exportar, sem criar categoria, sem salvar e sem acionar suporte.
- O benchmark Sizebay confirmou uma gestão separada entre categorias locais (`Name`, `Type`, ações de export/import/create) e taxonomia Sizebay (`Sizebay Category`, `Subcategories`, `Translations`, abas All/Shoes/Clothes), embora as tabelas tenham permanecido em loading nesta sessão.
- Criadas as entidades `merchant_categories` e `taxonomy_categories`, com árvore inicial de taxonomia por tipo de peça, subcategorias, gênero, faixa etária, traduções e API `/api/v1/categories` para descobrir, revisar, importar/exportar, mesclar e aplicar categorias normalizadas.
- A normalização preserva `products.category` e grava `metadata.category_original`, `metadata.normalized_category`, `metadata.rules_context.category` e `metadata.ai_context.category`, permitindo filtros, regras, IA, modelagens e relatórios usarem a taxonomia sem perder o nome recebido da loja.
- Importações CSV/XML e sincronização BigShop passam a registrar a categoria local e reaplicar automaticamente a taxonomia quando o mapeamento já foi revisado.
- `/app/categorias` ganhou painel de saúde da taxonomia, lista de pendências, sugestões revisáveis com confiança, edição de tipo/gênero/faixa etária/tradução, mescla de variações, importação com prévia e exportação/modelo CSV.
- `/app/produtos` ganhou filtro por categoria normalizada e exibição conjunta da categoria original com a normalizada; analytics e config-check/recomendação pública passam a expor contexto de categoria normalizada.
- O script `scripts/validate-production.ps1` passou a validar `/app/categorias`, redirect legado para `/app/categorias` e `GET /api/v1/categories`.
- Validação visual local rodou em `http://127.0.0.1:5177/app/categorias`, com backend local em `8002`, cobrindo desktop e viewport mobile; a captura full-page mobile também mostrou apenas o menu off-canvas fora do viewport, sem afetar a tela visível.
- Validações locais passaram com `php -l`, `php vendor\bin\pint --dirty --test`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit --filter 'CategoryManagementApiTest|ProductsApiTest|RecommendationApiTest|ImportsApiTest|IntegrationsApiTest'`, PHPUnit completo (`117 tests`, `1201 assertions`) e `npm --prefix frontend run build` (com o aviso conhecido de bundle acima de 500 kB).
- Varredura de segredos nos arquivos versionados alterados, `git diff --check` e `git diff --cached --check` passaram; os achados foram falsos positivos esperados em testes/demo e nomes técnicos de token.
- Commit `8c4d505` enviado para `main`; o run `26640876246` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` passou integralmente após incluir `/app/categorias` e `API categories`. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 138 Taxonomia inteligente e base de aprendizado

- Relida a documentação obrigatória antes de retomar a sprint, incluindo `credentials.local.md` de forma mascarada por envolver produção/deploy, banco, IA, integrações e sessão Sizebay.
- Mantido o benchmark Sizebay read-only já coletado em `.tmp/sizebay-readonly/`, sem alterar dados, salvar, publicar, acionar suporte ou registrar credenciais. As capturas cobrem `/sizebay-categories`, `/sizebay-brands`, regras de importação e relatórios.
- Criadas as tabelas `taxonomy_versions`, `taxonomy_mapping_suggestions` e `taxonomy_learning_events`, com versão ativa `2026.05.29-sprint138`, confiança decimal, contexto operacional e aprendizado sem dados sensíveis.
- Criados modelos, resources, controller e `TaxonomyIntelligenceService` para descobrir categorias/marcas locais, gerar sugestões revisáveis, medir impacto, exigir confirmação para baixa confiança, aprovar/rejeitar mapeamentos e registrar eventos de aprendizado.
- `CategoryCatalogService` e `BrandCatalogService` passam a reaplicar mapeamentos aprovados em novas importações, preservando o valor original e alimentando `rules_context` e `ai_context`.
- Criada a rota protegida `/app/taxonomia`, item `Taxonomia IA` no menu e endpoints `/api/v1/taxonomy/intelligence`, `/api/v1/taxonomy/intelligence/generate`, `/api/v1/taxonomy/suggestions/{id}/approve` e `/api/v1/taxonomy/suggestions/{id}/reject`.
- A tela mostra métricas, fila de sugestões, motivo, confiança, impacto, contexto de gênero/faixa etária/modelagem/grade, confirmação para baixa confiança e aprendizados recentes.
- O script `scripts/validate-production.ps1` passou a validar `/app/taxonomia`, redirect legado e `GET /api/v1/taxonomy/intelligence`.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter TaxonomyIntelligenceApiTest`, suíte focada de taxonomia/categorias/marcas/produtos/recomendação/importações/integrações/analytics, PHPUnit completo (`120 tests`, `1242 assertions`), `php vendor/bin/pint --dirty --test` e `npm --prefix frontend run build` com o aviso conhecido de bundle acima de 500 kB.
- Validação visual local rodou em `http://127.0.0.1:5177/app/taxonomia`, com backend local em `8002`, Chrome headless/CDP, desktop e mobile, sem overflow horizontal e sem erros de console. As capturas foram salvas em `.tmp/sprint138-taxonomia-desktop.png` e `.tmp/sprint138-taxonomia-mobile.png` e não devem ser versionadas.
- Commit principal `9bf85d9` enviado para `main`; o run `26643813668` falhou em `Run remote deploy` porque o MySQL recusou o índice automático `taxonomy_mapping_suggestions_merchant_id_merchant_company_id_status_index` por tamanho excessivo.
- Corrigida a migration no commit `66d3391`, com nomes curtos de índices e guards para o caso de deploy parcialmente interrompido já ter criado tabelas antes de registrar a migration.
- O run `26644028670` finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação local pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/taxonomia`, redirect legado `/provadorvirtual_v2/app/taxonomia` e `API taxonomy intelligence`. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Planejamento da Sprint 160 Migração Sizebay

- Relida a documentação obrigatória e os documentos de Sizebay, Zak, importações, LGPD/aprendizado e backlog antes do ajuste de roadmap; `docs/credentials.local.md` foi conferido somente em modo mascarado.
- Não houve novo acesso ao portal Sizebay, uso de credenciais, alteração remota, publicação, contato com suporte ou gravação de dados sensíveis.
- Acrescentada ao roadmap a Sprint 160, planejada para migração assistida de clientes vindos da Sizebay, com a Zak como piloto real.
- A sprint prevê importar, sempre via prévia e revisão, tabelas de medidas, produtos, variantes, vínculos produto-tabela, marcas, categorias, modelagens, regras de importação e dados agregados autorizados de relatórios/devoluções.
- Registrados bloqueios de segurança: não importar segredos, cookies, sessões, dados pessoais de consumidores, mensagens de suporte ou qualquer dado sem autorização/base legal.
- Atualizado o backlog para refletir a migração Sizebay/Zak como item futuro de integrações e benchmark.

## 2026-05-29 - Sprint 139 Integrações por plataforma melhores que Sizebay

- Relida a documentação obrigatória antes da sprint, incluindo `docs/credentials.local.md` somente em modo mascarado. Não houve novo acesso ao portal Sizebay nem uso de credenciais.
- `PlatformCatalog` ganhou plataformas dedicadas `xml_feed` e `api`, com guia, snippet, matriz de dados e metadados de campos por plataforma.
- `/app/integracoes` passou a renderizar somente os campos relevantes da plataforma escolhida: XML/feed usa identificador, feed e status; API usa identificador, URL base, token, webhook e status; ações de XML aparecem apenas quando a plataforma suporta feed.
- O widget install ganhou snippets e passos próprios para XML/feed e API.
- O SaaS admin passou a receber `integration_state` por empresa, com plataforma, status técnico, status comercial, contagem de conexões, flags de feed/API/webhook e último erro, sem expor credenciais em claro.
- CORS local passou a permitir `5178` para cobrir fallback quando `5177` já estiver ocupado durante validações locais.
- `scripts/validate-production.ps1` passou a validar `GET /api/v1/integrations`, garantindo presença de `xml_feed`, `api` e metadados críticos dos campos.
- Validações locais passaram com `php -l`, suíte focada `IntegrationsApiTest|SaasAdminApiTest|MerchantCompanyProfileApiTest|WidgetInstallApiTest|PublicCheckoutFlowTest|IntegrationChangeRequestApiTest` (`40 tests`, `342 assertions`), PHPUnit completo (`121 tests`, `1268 assertions`), `php vendor/bin/pint --dirty --test` e `npm --prefix frontend run build`.
- Validação visual local rodou em `http://127.0.0.1:5178/app/integracoes` com backend em `8002` porque `5177` já estava ocupado; Chrome headless/CDP confirmou campos de XML/feed e API no desktop/mobile sem erros de console. As capturas ficaram em `.tmp/sprint139-integracoes-*.png` e não devem ser versionadas.
- Commit `3ae241b` enviado para `main`; o run `26647308642` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/integracoes`, `GET /api/v1/integrations` com `xml_feed`/`api`, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 140 BigShop com governança comercial superior

- Relida a documentação obrigatória antes da sprint, incluindo `docs/credentials.local.md` somente em modo mascarado. Não houve novo acesso ao portal Sizebay nem uso de credenciais.
- Usado o benchmark Sizebay já documentado de Data Sources/integrações apenas como referência: no Provador Virtual, BigShop precisa ser mais forte porque é diferencial próprio e tem benefício comercial travado.
- `/app/integracoes` ganhou painel de governança BigShop explicando desconto, limitação de troca e ausência de exposição de credenciais no portal.
- O modal de troca protegida passou a mostrar resumo financeiro de referência, status da solicitação existente, link de pagamento quando enviado pelo SaaS, termos, aceite e próximos passos.
- Criado `GET /api/v1/merchant/integration-change-requests/current` para o lojista acompanhar a solicitação sem receber observações internas.
- Criada a tela SaaS dedicada `/saas/trocas-bigshop`, com filtros por status/empresa, métricas, edição de status, link de pagamento, observações internas, aplicação da troca e histórico de auditoria.
- `IntegrationChangeRequestController` passou a registrar auditoria de solicitação, aceite, atualização, pagamento solicitado, aprovação, conclusão, cancelamento e aplicação da nova plataforma.
- A aplicação da troca remove `bigshop_discount_active`, altera a plataforma da empresa e registra evento auditável sem retornar tokens ou segredos.
- `TransactionalEmailService` ganhou os templates/códigos `troca_bigshop_solicitada`, `troca_bigshop_pagamento_pendente` e `troca_bigshop_concluida`, com histórico `skipped` quando SMTP estiver inativo.
- `scripts/validate-production.ps1` passou a validar `/saas/trocas-bigshop` e `GET /api/v1/merchant/integration-change-requests/current`.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationChangeRequestApiTest`, suíte focada BigShop/integrações/SaaS/e-mails/checkout (`51 tests`, `448 assertions`), PHPUnit completo (`121 tests`, `1285 assertions`), `php vendor/bin/pint --dirty --test` e `npm --prefix frontend run build` com o aviso conhecido de bundle acima de 500 kB.
- Validação visual local rodou em `http://127.0.0.1:5178/app/integracoes` e `http://127.0.0.1:5178/saas/trocas-bigshop`, com backend local em `8002` e Chrome headless/CDP, cobrindo desktop/mobile sem erros de console. As capturas ficaram em `.tmp/sprint140-*.png` e não devem ser versionadas.
- Varredura de segredos, `git diff --check` e `git diff --cached --check` passaram; os achados foram falsos positivos em testes, documentação e textos de UI sobre não expor tokens/credenciais.
- Commit `e5cd59e` enviado para `main`; o run `26649251806` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/saas/trocas-bigshop`, `/app/integracoes`, `GET /api/v1/merchant/integration-change-requests/current`, páginas públicas, SaaS, portal, widget JS/CSS, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 141 API, webhook, GTM e validação de instalação

- Relida a documentação obrigatória antes da sprint, incluindo `docs/credentials.local.md` somente em modo mascarado por envolver integrações, produção/deploy e segredos. Não houve novo acesso ao portal Sizebay nem uso de credenciais.
- `PlatformCatalog` passou a devolver exemplos de API por plataforma, guia de webhook assinado com `X-Provador-Signature`, algoritmo HMAC-SHA256, endpoint protegido de teste e guia GTM marcado como alternativa/fallback, nunca como padrão.
- `GET /api/v1/integrations` agora inclui último diagnóstico de instalação por plataforma e logs recentes de webhook testado, sempre sanitizados e sem segredo em claro.
- `POST /api/v1/integrations/{platform}/validate-install` passou a retornar diagnóstico granular da URL validada: container, script, plataforma esperada, produto, variação, SKU, botões renderizados e indício de GTM, gravando resumo em `integration_events`.
- Criado `POST /api/v1/integrations/{platform}/test-webhook`, que usa o segredo criptografado para assinar um payload de exemplo, retorna apenas assinatura mascarada, registra log/auditoria e nunca devolve o segredo.
- `/app/integracoes` ganhou blocos de exemplos de API, webhook, logs recentes, mascaramento/rotação write-only de token/segredo, diagnóstico visual da URL validada e guia GTM opcional.
- `scripts/validate-production.ps1` passou a verificar que `GET /api/v1/integrations` expõe exemplos de API, webhook assinado, GTM não padrão e checklist granular de produto, variação, SKU e botões.
- Validações locais já executadas: `php -l` nos PHP alterados, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest` (`10 tests`, `140 assertions`), suíte focada de integrações/widget/recomendação/BigShop/SaaS/importações (`41 tests`, `443 assertions`), PHPUnit completo (`122 tests`, `1318 assertions`), `php vendor/bin/pint --dirty --test` e `npm --prefix frontend run build` com o aviso conhecido de bundle acima de 500 kB.
- Validação visual local rodou em `http://127.0.0.1:5177/app/integracoes`, com backend local em `8002`, Chrome headless/CDP, plataforma API, teste de webhook, desktop e mobile sem erros de console. As capturas ficaram em `.tmp/sprint141-integracoes-api-webhook-*.png` e não devem ser versionadas.
- Varredura de segredos, `git diff --check` e `git diff --cached --check` passaram; os achados foram falsos positivos em nomes técnicos, testes fake e textos de documentação/UI sobre segredo write-only.
- Commit `1b9be20` enviado para `main`; o run `26650581437` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/integracoes`, `GET /api/v1/integrations` com exemplos de API, webhook assinado, GTM não padrão e checklist granular, além de páginas públicas, SaaS, portal, widget JS/CSS, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 142 Posicionamento visual do botão na página de produto

- Relida a documentação obrigatória antes da sprint, incluindo `docs/credentials.local.md` somente em modo mascarado por envolver produção/deploy e validações autenticadas. Não houve novo acesso ao portal Sizebay nem uso de credenciais.
- Usado o benchmark já descrito de Settings/Service da Sizebay como referência para local do botão, âncora CSS, prévia e validação de container.
- Criado `WidgetPlacementCatalog` com modo padrão, normalização e sugestões comuns por plataforma para BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart, XML/feed, API e custom.
- `GET /api/v1/widget-install` agora devolve `theme.placement` e `guide.placement_suggestions`; o snippet passa a carregar o tema com posicionamento junto da configuração publicada.
- Criado `POST /api/v1/widget-install/placement-preview`, que aceita URL pública de PDP, modo, seletor e container, valida seletor CSS simples, bloqueia localhost/IP privado, busca a página sem salvar HTML e retorna checks de página acessível, âncora, script, container antes do script e duplicidade.
- `PATCH /api/v1/widget-install` bloqueia publicação quando o seletor CSS é inválido ou quando a última validação do posicionamento está marcada como falha.
- O script público `/widget/v1/provador-virtual.js` passou a posicionar/criar um único container em relação à âncora configurada (`inside`, `after`, `before`) e remove raízes duplicadas `data-pv-root` antes de renderizar os botões.
- `/app/widget` ganhou seção `Posição na PDP` com modo, seletor, URL para teste, sugestões por plataforma, prévia visual e checks do endpoint, salvando a configuração dentro do tema do rascunho/publicação.
- CORS local passou a liberar `api/v1/widget-install*` para cobrir a prévia de posicionamento na porta `5177`, e o feedback global deixou de tratar `placement-preview` como salvamento porque a ação é apenas diagnóstico.
- `scripts/validate-production.ps1` passou a checar `placementConfig`/`data-pv-root` no JS publicado, `GET /api/v1/widget-install` com placement/sugestões e `POST /api/v1/widget-install/placement-preview`.
- Validações locais passaram com `php -l` nos PHP novos/alterados, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter "WidgetInstallApiTest|WidgetAssetTest"`, suíte focada `WidgetInstallApiTest|WidgetAssetTest|IntegrationsApiTest|RecommendationApiTest|GoLiveReadinessApiTest|BigShopActivationTest|BigShopIntegrationTest` (`35 tests`, `508 assertions`), PHPUnit completo (`124 tests`, `1345 assertions`), `php vendor/bin/pint --dirty --test`, `npm --prefix frontend run build` com o aviso conhecido de bundle acima de 500 kB, `git diff --check`, sintaxe de `scripts/validate-production.ps1` e varredura de segredos.
- Validação visual local rodou em `http://127.0.0.1:5177/app/widget`, com backend local em `8002`, Chrome headless/CDP, desktop e mobile, teste de seletor usando `http://example.com/` e seletor `body`, sem erros de console, sem overflow horizontal e sem sobreposição de cookie/toast. As capturas ficaram em `.tmp/sprint142-widget-placement-*.png` e não devem ser versionadas.
- Commit `af2b70b` enviado para `main`; o run `26652392667` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/widget`, widget JS/CSS com posicionamento, `GET /api/v1/widget-install`, `POST /api/v1/widget-install/placement-preview`, páginas públicas, SaaS, portal, APIs, CORS, login demo, go-live readiness, integrações e taxonomia. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 143 Histórico de sincronização e contadores por execução

- Relida a documentação obrigatória antes da sprint, incluindo `docs/credentials.local.md` somente em modo mascarado por envolver produção/deploy e validações autenticadas. Não houve novo acesso ao portal Sizebay nem uso de credenciais.
- Usado o benchmark já registrado de Settings/Sync da Sizebay como referência para histórico por execução, contadores, origem e erros acionáveis.
- `GET /api/v1/integrations/sync-history` agora retorna até 60 execuções, `execution_key`, origem (`manual`, `scheduled`, `webhook`, `xml_feed` ou `api`), fonte, duração, contadores padronizados, totais agregados, agrupamento por origem/status e timeline compacta.
- Os contadores por execução cobrem total, inseridos, atualizados, ignorados, desconhecidos, sem alteração, produtos, variações, tabelas, erros e alertas, reaproveitando resumo de BigShop, XML/feed e `import_jobs`.
- Erros vindos de `payload.issues`, `import_jobs.errors` e erro geral do evento agora incluem ação para abrir produto ou revisar regra, sem expor dados sensíveis.
- `/app/sincronizacao` ganhou filtro por origem, timeline compacta, comparação entre duas execuções, resumo com contadores padronizados, origem/duração por item e ações nos erros.
- `scripts/validate-production.ps1` passa a validar o contrato de `GET /api/v1/integrations/sync-history` sem depender de a loja demo ter eventos.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest`, suíte focada de integrações/importações/BigShop/analytics/SaaS/recomendação (`32 tests`, `375 assertions`), PHPUnit completo (`124 tests`, `1368 assertions`), `php vendor/bin/pint --dirty --test` e `npm --prefix frontend run build` com o aviso conhecido de bundle acima de 500 kB.
- `git diff --check`, sintaxe de `scripts/validate-production.ps1` e varredura de segredos passaram; os achados do scan amplo foram apenas avisos de fim de linha e nenhum segredo real foi encontrado nos arquivos versionados alterados.
- Validação visual local rodou em `http://127.0.0.1:5177/app/sincronizacao`, com backend local em `8002`, Chrome headless/CDP e resposta sintética autorizada apenas no browser para cobrir timeline/comparação/ações sem alterar o banco local. Desktop e mobile passaram sem erros de console e sem overflow horizontal; as capturas ficaram em `.tmp/sprint143-sincronizacao-*.png` e não devem ser versionadas.
- Commit `9f1cfc6` enviado para `main`; o run `26653769731` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/sincronizacao`, `GET /api/v1/integrations/sync-history` com `API sync history OK`, páginas públicas, SaaS, portal, widget JS/CSS, APIs, CORS, login demo, go-live readiness, integrações e taxonomia. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 144 Erros por produto com ações de correção

- Relida a documentação obrigatória antes da sprint, incluindo `docs/credentials.local.md` somente em modo mascarado por envolver produção/deploy e validações autenticadas. Não houve novo acesso ao portal Sizebay nem uso de credenciais.
- Usado o benchmark já registrado de Settings/Sync e erros por produto da Sizebay como referência para contexto, causa, ação recomendada, detalhes de tamanhos e exportação.
- `GET /api/v1/integrations/sync-history` agora retorna `issue_summary` agregado e `issue_groups` por execução, agrupando erros por causa raiz, criticidade, status de resolução, mensagens de amostra e produtos afetados.
- Os erros por produto passam a incluir `uid`, causa raiz, rótulo, ação recomendada, contexto de produto/variação/SKU/tamanhos/categoria/marca/URL e resolução atual, sempre sem expor segredos ou credenciais.
- Criados `GET /api/v1/integrations/sync-issues/export` para exportação CSV filtrada por execução e `POST /api/v1/integrations/sync-issues/actions` para ignorar com motivo, solicitar reprocessamento ou marcar revisão, registrando auditoria e atualizando o payload do evento.
- `/app/sincronizacao` ganhou painel de correção de erros, grupos por causa raiz, botões para vincular tabela, revisar categoria/regra, reprocessar e ignorar com motivo, além de exportação por execução.
- `/app/produtos` passa a aceitar o query param `busca` para abrir links de correção já com o produto/SKU filtrado.
- `scripts/validate-production.ps1` passa a validar o endpoint de exportação de erros com cabeçalhos `execution_key` e `root_cause`.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest` (`10 tests`, `183 assertions`), suíte focada de integrações/importações/BigShop/produtos/categorias/marcas/modelagens/analytics (`31 tests`, `529 assertions`), PHPUnit completo (`124 tests`, `1388 assertions`), `php vendor/bin/pint --dirty --test` e `npm --prefix frontend run build` com o aviso conhecido de bundle acima de 500 kB.
- Validação visual local rodou em `http://127.0.0.1:5177/app/sincronizacao`, com backend local em `8002`, Chrome headless/CDP e resposta sintética autorizada apenas no browser para cobrir grupos, contexto, ações e exportação sem alterar o banco local. Desktop e mobile passaram sem erros de console e sem overflow horizontal; as capturas ficaram em `.tmp/sprint144-sync-errors-*.png` e não devem ser versionadas.
- `git diff --check`, `git diff --cached --check` e varredura de segredos passaram; `package.json` da raiz permaneceu não versionado e `.tmp` não foi stageado.
- Commit `d988e85` enviado para `main`; o run `26655128955` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/sincronizacao`, `GET /api/v1/integrations/sync-history`, `GET /api/v1/integrations/sync-issues/export` com `API sync issues export OK`, páginas públicas, SaaS, portal, widget JS/CSS, APIs, CORS, login demo, go-live readiness, integrações e taxonomia. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 145 Simulação de importação e impacto das regras

- Relida a documentação obrigatória antes da sprint, incluindo `docs/credentials.local.md` somente em modo mascarado por envolver produção/deploy e integrações. Não houve novo acesso ao portal Sizebay nem uso de credenciais.
- Usado o benchmark já registrado de Settings/Importation Rules e Sync da Sizebay como referência para simulação antes de salvar, condições, ações e impacto no catálogo.
- Criado `ImportRuleImpactService` para simular regras de importação em modo somente leitura, comparando regra atual e regra proposta contra amostra real do catálogo da empresa ativa ou amostra técnica quando ainda não há produtos.
- Criado `POST /api/v1/integrations/{platform}/import-rules/simulate`, protegido por permissão de integrações, sem gravar `Product`, `IntegrationEvent`, `PlatformConnection`, segredo ou payload sensível.
- A simulação retorna total de amostra, produtos afetados, percentual, impacto por regra, uso de fallback, obrigatórios ausentes, valores alterados, antes/depois por produto e avisos de conflito ou regra ampla demais.
- Regras conflitantes por campo de origem são sinalizadas; conflito crítico bloqueia salvamento no frontend até o lojista ajustar e simular novamente.
- `/app/regras-de-importacao` ganhou botão de simular impacto, painel de impacto no catálogo, avisos, tabela por regra e lista de produtos alterados com antes/depois. O feedback global deixa de tratar a simulação como salvamento.
- `scripts/validate-production.ps1` passa a validar `POST /api/v1/integrations/custom/import-rules/simulate` com amostra, impacto e linhas retornadas.
- Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest` (`11 tests`, `195 assertions`), suíte focada de integrações/importações/BigShop/produtos/categorias/marcas/modelagens/analytics (`32 tests`, `541 assertions`), PHPUnit completo (`125 tests`, `1400 assertions`), `php vendor/bin/pint --dirty --test` e `npm --prefix frontend run build` com o aviso conhecido de bundle acima de 500 kB.
- Validação visual local rodou em `http://127.0.0.1:5177/app/regras-de-importacao`, com backend local em `8002`, Chrome headless/CDP e respostas sintéticas autorizadas apenas no browser para cobrir impacto, bloqueios, antes/depois e estado de salvamento bloqueado sem alterar o banco local. Desktop e mobile passaram sem erros de console e sem overflow horizontal; as capturas ficaram em `.tmp/sprint145-import-rules-*.png` e não devem ser versionadas.
- `git diff --check`, `git diff --cached --check`, sintaxe de `scripts/validate-production.ps1` e varredura de segredos passaram; `package.json` da raiz permaneceu não versionado e `.tmp` não foi stageado.
- Commit `2e35db3` enviado para `main`; o run `26656219719` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/regras-de-importacao`, `POST /api/v1/integrations/custom/import-rules/simulate` com `API import rule simulation OK`, páginas públicas, SaaS, portal, widget JS/CSS, APIs, CORS, login demo, go-live readiness, integrações, sincronização e taxonomia. Resultado final: `PRODUCTION VALIDATION OK`.
## 2026-05-29 - Sprint 146 Galeria de botões e personalização mais polida

- Relida a documentação obrigatória antes da sprint, incluindo `docs/credentials.local.md` somente em modo mascarado por envolver produção/deploy e validações autenticadas. Não houve novo acesso ao portal Sizebay nem uso de credenciais; a galeria pública de botões foi observada novamente apenas em leitura para ajustar a referência visual.
- A galeria principal de `/app/widget` foi reorganizada para refletir melhor a leitura pública da Sizebay: 10 modelos principais mais compactos, com 2 estilos legados preservados em seção recolhível de compatibilidade para instalações antigas.
- Os rótulos/descrições dos estilos principais foram refinados para ficar mais próximos do benchmark observado e a lista principal passou a trabalhar como grade 2x5 no desktop, mantendo a prévia, publicação, desfazer e rascunho que já existiam.
- A compatibilidade legada passou a viver fora do fluxo principal, evitando poluir a seleção do lojista sem quebrar valores antigos salvos em produção.
- Validação local já executada antes do push: `npm --prefix frontend run build` e `git diff --check` passaram; a revisão funcional da tela `/app/widget` confirmou a separação da galeria principal e da área legada.
- Commit `19bb566` enviado para `main`; o run `26659696245` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/widget`, widget JS/CSS, `GET /api/v1/widget-install`, `POST /api/v1/widget-install/placement-preview`, páginas públicas, SaaS, portal, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.
## 2026-05-29 - Sprint 147 Editor completo do modal do Provador

- Relida a documentação obrigatória antes da sprint, incluindo `docs/credentials.local.md` em modo mascarado por envolver produção/deploy e integrações. Não houve novo acesso ao portal Sizebay nem alteração de credenciais; a referência foi usada apenas em leitura para o benchmark de customização do modal.
- Criado o editor dedicado do modal do provador em `/app/widget`, separando claramente a personalização do botão da personalização da experiência completa.
- O contrato de tema agora inclui `theme.presentation_mode` e `theme.modal.*`, com logo, textos, etapas, tabela, cores, bordas, tipografia e estilo da tabela, além de normalização segura para dados antigos ou incompletos.
- `PATCH /api/v1/widget-install` passou a aceitar e persistir a customização do modal em rascunho e publicação, e o widget público consome o novo contrato para desenhar o modal e a tabela de medidas.
- O fluxo de publicação exige contraste mínimo no texto e no destaque do modal antes de salvar em produção.
- A tela `/app/widget` ganhou prévia desktop/mobile do modal completo, alerta de contraste e visual de tabela integrado ao editor, mantendo salvar rascunho, publicar e desfazer.
- Validações locais passaram com `php artisan test` usando o PHP `C:\php\php.exe` com `sqlite3`, `php vendor/bin/pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos. A revisão visual headless abriu `/app/widget` com login demo, confirmou o bloco `Modal do provador` e a prévia aberta em desktop.
- Commit `fe82320` enviado para `main`; o run `26663180067` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/widget`, widget JS/CSS, APIs públicas, APIs protegidas, CORS, login demo, go-live readiness, integrações, sincronização, taxonomia e `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 148 Relatórios de uso do widget

- Relida a documentação obrigatória antes da sprint. `docs/credentials.local.md` ainda não foi reaberto nesta etapa porque o ciclo ainda estava em implementação/validação local, sem entrar em deploy ou produção.
- Usado o benchmark já registrado de `Reports / Usage Data` da Sizebay como referência para KPIs, segmentação por dispositivo, funil e filtros por período.
- Criado `POST /api/v1/public/widget-events`, com eventos idempotentes por `client_event_id` para `button_impression`, `virtual_try_on_open`, `measurement_table_open`, `recommendation_generated`, `size_selected` e `feedback_submitted`.
- Criado `GET /api/v1/analytics/widget-usage`, com filtros por período, produto, tabela, marca, categoria, plataforma e dispositivo, retornando resumo, funil, evolução diária, distribuição por device e opções de filtros para o portal.
- `/app/analytics` ganhou a seção `Uso do widget`, com cards de KPI, filtros operacionais, funil, distribuição por dispositivo e evolução diária, preservando a área já existente de qualidade da recomendação.
- O widget público passou a emitir eventos de uso de forma tolerante a falhas, com chaves determinísticas por visita/ação para evitar duplicidade em re-render e reabertura do mesmo fluxo.
- O script `scripts/validate-production.ps1` foi ampliado para checar também `GET /api/v1/analytics/widget-usage`.
- Validações locais passaram com `C:\php\php.exe -l` nos PHP alterados, `C:\php\php.exe artisan test --filter=WidgetAssetTest`, `C:\php\php.exe artisan test --filter=AnalyticsApiTest`, `C:\php\php.exe artisan test`, `C:\php\php.exe vendor\bin\pint --dirty --test` e `npm --prefix frontend run build`.
- `git diff --check` e a varredura de segredos passaram; os únicos avisos observados no terminal foram de fim de linha (`LF`/`CRLF`) em arquivos frontend/PowerShell, sem whitespace inválido nem segredo real nos arquivos versionados alterados.
- A validação visual local rodou em `http://127.0.0.1:5177/app/analytics`, com backend local em `8002`, Playwright headless e usuário demo. O banco local precisou receber a migration pendente `2026_05_29_120000_create_widget_usage_events_table` para a tela carregar o novo relatório; em seguida foram gerados eventos demo locais para revisar o funil e os cards com dados reais. Desktop e mobile passaram sem erros de console/página; as capturas ficaram em `.tmp/sprint148-analytics-desktop.png` e `.tmp/sprint148-analytics-mobile.png` e não devem ser versionadas.
- Commit `14116a3` enviado para `main`; o run `26664926905` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/analytics`, `API widget usage analytics OK`, widget JS/CSS, páginas públicas, SaaS, portal, APIs, CORS, login demo, go-live readiness, integrações, sincronização e taxonomia. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-29 - Sprint 149 Ranking de produtos e relatório de recomendações

- Relida a documentação obrigatória antes da sprint. `docs/credentials.local.md` ainda não foi reaberto nesta etapa porque o ciclo permanece em implementação/validação local, sem deploy ou produção.
- Usado o benchmark já registrado de `Reports / Recommendations` e `Reports / Usage Data` da Sizebay como referência para ranking por produto, recomendações emitidas, drill-down e exportação operacional.
- Criado `RecommendationAnalyticsRequest` e `RecommendationAnalyticsService` para consolidar ranking de produtos, relatório paginado de recomendações, filtros compartilhados e exportação CSV sem expor dados pessoais desnecessários.
- `GET /api/v1/analytics/recommendations` agora retorna `product_ranking` com impressões, abertura do provador, consulta de tabela, recomendações, aplicação de tamanho, erros, devoluções/trocas, taxa de uso, taxa de seleção e flags de atenção, além do `recommendation_report` paginado com produto, SKU, tabela usada, tamanho recomendado, confiança, origem, plataforma, dispositivo e sinais comerciais.
- Criado `GET /api/v1/analytics/recommendations/export` para exportar CSV do ranking ou das recomendações emitidas usando os mesmos filtros e drill-down por produto/tabela.
- `/app/analytics` ganhou as seções `Ranking de produtos` e `Recomendações emitidas`, com botões de drill-down, exportação CSV, paginação e restauração explícita do heading `Uso do widget` para preservar a hierarquia visual já entregue na Sprint 148.
- `scripts/validate-production.ps1` foi ampliado para validar o endpoint autenticado de recomendações e a exportação CSV na produção.
- Validações locais passaram com `C:\php\php.exe -l` nos PHP alterados, `C:\php\php.exe artisan test --filter=AnalyticsApiTest`, `C:\php\php.exe artisan test`, `C:\php\php.exe vendor\bin\pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos, com único falso positivo conhecido do login demo do próprio smoke de produção.
- A validação visual local rodou em `http://127.0.0.1:5177/app/analytics`, com backend local em `8002`, Playwright headless e usuário demo. Desktop e mobile passaram sem erros de console/página; o relatório exibiu `Uso do widget`, `Ranking de produtos` e `Recomendações emitidas`. As capturas ficaram em `.tmp/sprint149-analytics-desktop.png` e `.tmp/sprint149-analytics-mobile.png` e não devem ser versionadas.
- Commit `ce6ddbb` enviado para `main`; o run `26666285868` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A primeira execução local de `scripts/validate-production.ps1` encontrou apenas um problema do próprio validador: o PowerShell entregou `Content-Type` do CSV como array no endpoint de exportação de recomendações. O endpoint em produção respondeu corretamente; o script foi normalizado com `Get-HeaderValue` para aceitar header simples ou múltiplo sem falso negativo.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente após a normalização do header, incluindo `/app/analytics`, `API recommendation analytics OK`, `API recommendation export OK`, widget JS/CSS, páginas públicas, SaaS, portal, APIs, CORS, login demo, go-live readiness, integrações, sincronização e taxonomia. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 150 Pedidos no portal da empresa

- Relida a documentação obrigatória antes da sprint. `docs/credentials.local.md` permaneceu fechada nesta etapa porque o trabalho ainda estava em implementação e validação local, sem entrar em produção.
- Usado o benchmark já registrado de `Orders` e `Reports / Orders Overview` da Sizebay como referência para status, data, quantidade, valor, uso do assistente e fallback quando o rastreamento ainda não está configurado.
- Criadas as tabelas `merchant_orders` e `merchant_order_items` para registrar pedidos do lojista com dados mínimos: referência, origem, status, data, receita, itens, tamanho comprado e vínculo com uso do Provador.
- Criado `MerchantOrderService` com filtros por período/status/origem/uso do Provador/busca, visão geral operacional, listagem paginada, modelo CSV e importação com prévia antes do commit.
- A importação CSV cruza `order_reference_hash` com `recommendation_learning_events` para marcar pedidos assistidos e preencher recomendação/tamanho/receita assistida sem expor dados pessoais do consumidor.
- Criados `GET /api/v1/orders/overview`, `GET /api/v1/orders`, `GET /api/v1/orders/template` e `POST /api/v1/orders/import`, protegidos pela mesma permissão de analytics já usada na área de resultados.
- O `DatabaseSeeder` agora gera pedidos demo recentes para a loja de teste e `scripts/validate-production.ps1` foi ampliado para validar `/app/pedidos`, `API orders overview OK` e `API orders list OK`.
- A tela `/app/pedidos` foi conectada ao backend já esboçado no frontend, entregando filtros operacionais, cards de conversão assistida, blocos de status/origem, produtos com mais pedidos, fallback CSV e tabela detalhada de pedidos.
- Validações locais passaram com `php -l`, `C:\php\php.exe artisan test --filter=MerchantOrdersApiTest`, `C:\php\php.exe artisan test` completo (`128 tests`, `1489 assertions`), `C:\php\php.exe vendor\bin\pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos.
- A validação visual local rodou em `http://127.0.0.1:5177/app/pedidos`, com backend local em `8002`, Playwright headless e usuário demo. Foi necessário incluir `api/v1/orders*` no allowlist de CORS local e limpar cache do Laravel para a rota carregar via `5177`; depois disso desktop e mobile passaram sem erros de console/página. As capturas ficaram em `.tmp/sprint150-orders-desktop.png` e `.tmp/sprint150-orders-mobile.png` e não devem ser versionadas.
- Commit `1707593` enviado para `main`; o run `26671679040` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/pedidos`, `API orders overview OK`, `API orders list OK`, widget JS/CSS, páginas públicas, SaaS, portal, APIs, CORS, login demo, go-live readiness, integrações, sincronização e taxonomia. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 151 Devoluções e trocas com mapeamento de motivos

- Releitura obrigatória concluída antes da sprint, seguindo `docs/README.md` e `docs/sprint_governance.md`. Os 30 documentos obrigatórios foram relidos; `docs/credentials.local.md` permaneceu fechado nesta etapa porque o trabalho ficou em implementação e validação local, sem produção/deploy.
- Usado o benchmark já registrado de `Returns` e `Reports / Returns` da Sizebay como referência para upload CSV, método de processamento, motivo normalizado e reaproveitamento dos sinais comerciais.
- Criadas as tabelas `merchant_returns` e `merchant_return_items` para registrar protocolo, pedido, status, motivo, produto, SKU, tamanhos comprado/ideal/devolvido/trocado, valor devolvido e vínculo com uso do Provador por item.
- Criado `MerchantReturnService` com visão geral operacional, listagem paginada, modelo CSV/XLSX, importação CSV/XLSX/JSON, sugestão automática de mapeamento de colunas, validação por linha/coluna e reaproveitamento de pedidos/recomendações já gravados.
- A importação cruza `order_reference_hash`, SKU, produto e tamanho com `merchant_orders`, `merchant_order_items` e `recommendation_learning_events` para marcar devoluções assistidas e gerar sinais idempotentes de `return` ou `exchange` em `recommendation_learning_events`, alimentando analytics e IA sem expor pedido em claro.
- Criados `GET /api/v1/returns/overview`, `GET /api/v1/returns`, `GET /api/v1/returns/template` e `POST /api/v1/returns/import`, protegidos pela mesma permissão de analytics já usada em relatórios, pedidos e auditoria.
- O portal ganhou `/app/devolucoes`, com filtros por período/status/motivo/origem/uso do Provador, cards de impacto, motivos normalizados, produtos mais impactados, assistente de mapeamento e tabela operacional por ocorrência. O menu, a ajuda contextual e o manual rápido passaram a apontar para a nova área.
- `DatabaseSeeder` agora gera devoluções/trocas demo ligadas aos pedidos recentes e `scripts/validate-production.ps1` foi ampliado para validar `/app/devolucoes`, `API returns overview OK`, `API returns list OK`, `API returns template OK` e `API returns preview OK`.
- Validações locais passaram com `php -l`, suíte focada `MerchantReturnsApiTest|MerchantOrdersApiTest|AnalyticsApiTest|RecommendationApiTest`, PHPUnit completo (`132 tests`, `1508 assertions`), `C:\php\php.exe vendor\bin\pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos.
- A varredura de segredos não encontrou segredo real nos arquivos alterados; os únicos achados foram strings legítimas do próprio validador em `scripts/validate-production.ps1` que verificam campos `access_token` e `secret`, sem credencial em claro. `package.json` da raiz continuou fora do stage e `.tmp` permaneceu local-only.
- A validação visual local rodou em `http://127.0.0.1:5177/app/devolucoes`, com backend local em `8002`, Playwright headless e usuário demo. Desktop e mobile passaram sem erros de console/página e sem overflow horizontal; as capturas ficaram em `.tmp/sprint151-returns-desktop.png` e `.tmp/sprint151-returns-mobile.png` e não devem ser versionadas.
- Commit `b2f71a7` enviado para `main`; o run `26672385027` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/devolucoes`, `API returns overview OK`, `API returns list OK`, `API returns template OK`, `API returns preview OK`, widget JS/CSS, páginas públicas, SaaS, portal, APIs, CORS, login demo, go-live readiness, integrações, sincronização e taxonomia. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 152 Aprendizado com pedidos, devoluções e feedback

- Relida a documentação obrigatória antes da sprint, incluindo `docs/README.md`, `docs/sprint_governance.md`, `docs/analytics_admin.md` e `docs/data_learning_lgpd_outliers.md`.
- Usado o benchmark já registrado de `Reports`, `Orders`, `Returns` e rastreamento da Sizebay como referência para separar recomendação aplicada de aprendizado, transformar sinais reais em fila operacional e manter revisão humana obrigatória.
- Criado `LearningPipelineService` para resumir sinais prontos para aprendizado, fila de revisão, candidatas estáveis para IA, padrões por produto/tabela/categoria/marca/modelagem e status de retenção/anonimização sem precisar abrir um BI paralelo.
- `GET /api/v1/analytics/recommendations` agora inclui `learning_pipeline`, com resumo de recomendações aplicadas versus sinais prontos, guardrails de revisão/LGPD, padrões de aprendizado, candidatas estáveis para IA e sugestões explicadas de ajuste por tabela.
- `MeasurementTableInsightService` passou a devolver `suggested_adjustment` com direção, foco de medidas/modelagem, headline e explicação, e `/app/assistente` reaproveita esse contexto para mostrar por que a IA sugere revisar uma tabela antes de qualquer publicação.
- `/app/analytics` ganhou a seção `Aprendizado com dados reais`, com cards operacionais, blocos de base consistente para IA, fila de revisão manual, padrões por produto/tabela/marca/categoria/modelagem e cartão de retenção/anonimização.
- A rotina `pv:privacy-anonymize` ganhou janelas separadas para dados do widget, comentários de feedback, perfis e payloads de aprendizado, mantendo hash de pedido e permitindo auditoria sem reter contexto sensível além da janela necessária.
- Validações locais passaram com `php -l`, suíte focada `AnalyticsApiTest|AiMeasurementAssistantTest|HardeningApiTest`, PHPUnit completo (`132 tests`, `1515 assertions`), `C:\php\php.exe vendor\bin\pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos.
- A varredura de segredos não encontrou segredo real nos arquivos alterados; os únicos achados foram strings legítimas de comandos, testes e do próprio `scripts/validate-production.ps1`. `package.json` da raiz continuou fora do stage e `.tmp` permaneceu local-only.
- A validação visual local rodou em `http://127.0.0.1:5177/app/analytics`, com backend local em `8002`, Playwright headless e usuário demo. Desktop e mobile passaram sem erros de console/página e sem overflow horizontal; as capturas ficaram em `.tmp/sprint152-analytics-desktop.png` e `.tmp/sprint152-analytics-mobile.png` e não devem ser versionadas.
- Commit `f419109` enviado para `main`; o run `26672977847` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/analytics`, `API recommendation analytics OK`, `API recommendation export OK`, widget JS/CSS, páginas públicas, SaaS, portal, APIs, CORS, login demo, go-live readiness, integrações, sincronização, taxonomia, pedidos e devoluções. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 153 Assistente IA para criação e revisão de tabelas

- Relida a documentação obrigatória antes da sprint, incluindo `docs/README.md`, `docs/sprint_governance.md`, `docs/ai_assistant.md` e `docs/portal_ui_guidelines.md`.
- Usado o benchmark já registrado de `Measurement Guide`, `Modelings`, `Products` e relatórios da Sizebay como referência para revisão guiada, comparação antes de aplicar e explicação simples para o lojista.
- `POST /api/v1/ai/measurement-table-suggestions` agora aceita categoria, marca, base da tabela, sistema de tamanho, ranges, tabela para comparação e modo de explicação simples.
- `MeasurementTableSuggestionService` passou a devolver `review_context` com dados usados, confiança por bloco, nível de risco, riscos explicados, plano de ação, explicação simples para o lojista e comparação entre tabela atual e tabela sugerida.
- O assistente agora sugere tabela inicial já com `measurement_target`, `size_system` e `range_mode`, mantendo publicação sempre em rascunho e revisão humana obrigatória.
- `MeasurementTableInsightService` passou a considerar também categoria e marca ao reutilizar sinais reais compatíveis para enriquecer o contexto de revisão.
- `/app/assistente` ganhou coleta de categoria/marca, seletor de tabela para comparação, modo `Explicar para o lojista`, resumo de confiança, riscos, comparação com a tabela atual, plano recomendado e edição final do rascunho antes de salvar.
- Validações locais passaram com `php -l`, suíte focada `AiMeasurementAssistantTest|AnalyticsApiTest`, PHPUnit completo (`132 tests`, `1530 assertions`), `C:\php\php.exe vendor\bin\pint --dirty --test` e `npm --prefix frontend run build`.
- `git diff --check` e a varredura de segredos ainda serão repetidos no fechamento final da sprint, depois da atualização documental pós-deploy.
- `git diff --check` passou com apenas avisos conhecidos de `LF`/`CRLF` sem whitespace inválido, e a varredura de segredos encontrou somente referências legítimas do próprio produto, documentação e validadores, sem credencial real versionada.
- Commit `52463bc` enviado para `main`; o run `26674078434` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/assistente`, `/app/widget`, `/app/analytics`, pedidos, devoluções, taxonomia, integrações, sincronização, widget JS/CSS, páginas públicas, SaaS, portal, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.
- A validação visual local rodou em `http://127.0.0.1:5177/app/assistente`, com backend local em `8002`, navegador headless e usuário demo. Desktop e mobile passaram com a revisão guiada, a explicação simples e a comparação com a tabela atual; as capturas ficaram em `.tmp/sprint153-assistente-*.png` e não devem ser versionadas.

## 2026-05-30 - Sprint 154 Publicação e checklist conectado a dados reais

- Relida a documentação obrigatória antes da sprint, seguindo `docs/README.md` e a lista completa de documentos mandatórios.
- Usado o benchmark já registrado de Dashboard, Settings/Service e Settings/Data Sources da Sizebay como referência para maturidade operacional, cobertura, instalação validada e sinais de prontidão.
- `GET /api/v1/go-live/readiness` foi ampliado para devolver resumo operacional mais rico, `connected_data` com catálogo/widget/sincronização e `report` pronto para compartilhar com o lojista.
- `GoLiveReadinessController` passou a calcular cobertura real de catálogo, publicação do widget, estado de sincronização, bloqueios, avisos, recomendações e links diretos para resolução.
- `/app/go-live` foi reconstruída como checklist conectado, com cards de situação, blocos de dados conectados, relatório para o lojista, agrupamento por área e botão de revalidação.
- `frontend/src/services/api.ts` e `frontend/.env.example` passaram a usar `8002` como fallback/local default do backend, alinhando a documentação e a validação visual do portal.
- `backend/config/cors.php` passou a incluir `api/v1/go-live*`, corrigindo o carregamento do painel local em `5177` durante a validação headless.
- Validações locais passaram com `C:\\php\\php.exe -l` nos PHP alterados, suíte focada `GoLiveReadinessApiTest|WidgetInstallApiTest|IntegrationsApiTest`, PHPUnit completo (`132 tests`, `1571 assertions`), `C:\\php\\php.exe vendor\\bin\\pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos.
- A varredura de segredos não encontrou credencial real versionada; os únicos achados foram strings legítimas de fixtures de teste (`sk_test_checkout`, `sk_test_sync`, `pk_test_checkout`).
- A validação visual local rodou em `http://127.0.0.1:5177/app/go-live`, com backend local em `8002`, navegador headless e usuário demo. Desktop e mobile passaram com resumo, dados conectados, relatório e checklist agrupado; as capturas ficaram em `.tmp/sprint154-go-live-*.png` e não devem ser versionadas.
- Commit `024f665` enviado para `main`; o run `26675093553` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/go-live`, `/app/widget`, `/app/analytics`, pedidos, devoluções, usuários, taxonomia, integrações, sincronização, widget JS/CSS, páginas públicas, SaaS, portal, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 155 Usuários, permissões e contexto de empresa

- Relida a documentação obrigatória antes da sprint, incluindo `docs/README.md`, `docs/sprint_governance.md`, `docs/user_access_permissions.md` e `docs/portal_ui_guidelines.md`.
- Usado o benchmark já registrado de portal cliente, comportamento de conta e navegação do admin SaaS dentro do portal da empresa como referência para convites, permissões visíveis e trilha de contexto.
- Criada a migration `2026_05_30_051500_add_invitation_tracking_to_merchant_user_table` para rastrear `invitation_status`, `invited_at` e `accepted_at` por empresa.
- `UserAccessController` passou a devolver status de convite no payload dos usuários, aceitar `send_invite`, registrar auditoria detalhada para criação/edição/convite e manter antes/depois do acesso da empresa.
- `AuthController` passou a marcar convite pendente como aceito no primeiro login da empresa e registrar `users.invite_accepted`.
- `AuditLogController` passou a aceitar filtros por usuário, empresa, módulo, categoria e limite, preparando a rastreabilidade operacional dos acessos.
- `/app/usuarios`, `/app/usuarios/novo`, `/saas/usuarios`, `/saas/usuarios-empresas` e seus formulários passaram a esconder ações sensíveis sem permissão de edição, mostrar status de convite e permitir reenviar convite operacional.
- `App.vue` reforçou a faixa visual quando um admin SaaS está dentro do portal da empresa, exibindo o lojista e o código da empresa ativa.
- Validações locais passaram com `php -l`, suíte focada `UserAccessApiTest|AuthTest|AnalyticsApiTest`, PHPUnit completo (`133 tests`, `1583 assertions`), `C:\\php\\php.exe vendor\\bin\\pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos.
- A validação visual local rodou em `http://127.0.0.1:5177/app/usuarios`, com backend local em `8002`, Playwright headless e três contextos: lojista dono, usuário restrito sem `users.edit` e admin SaaS dentro do portal. As capturas ficaram em `.tmp/sprint155-users-*.png` e não devem ser versionadas.
- Commit `4c42900` enviado para `main`; o run `26675615573` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/usuarios`, `/saas/usuarios`, `/saas/usuarios-empresas`, `/app/widget`, `/app/analytics`, pedidos, devoluções, taxonomia, integrações, sincronização, widget JS/CSS, páginas públicas, SaaS, portal, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 156 Cobranca, plano e autonomia do cliente

- Relida a documentação obrigatória antes da sprint, incluindo `docs/README.md`, `docs/sprint_governance.md`, `docs/current_platform_state.md`, `docs/roadmap_sprints.md`, `docs/portal_ui_guidelines.md` e `docs/sizebay_benchmark.md`.
- Usado o benchmark já registrado de `Billing/Charges` e autonomia do portal cliente da Sizebay como referência para separar visibilidade da cobrança no portal da empresa do controle crítico que continua no Admin.
- `GET /api/v1/billing/subscription` foi ampliado para devolver `company`, `plan`, `subscription`, `payment_links`, `commercial_requests` e `actions`, combinando assinatura recorrente, último checkout, status comercial e histórico de troca BigShop da empresa ativa.
- Criado `POST /api/v1/billing/payment-links/resolve`, que libera a abertura de links financeiros somente após validar o contexto da empresa ativa e registrar auditoria com ator, empresa, origem do link e host, sem devolver a URL bruta na API principal do portal.
- `IntegrationChangeRequestController` deixou de expor o `payment_link` bruto para o portal da empresa e passou a devolver apenas disponibilidade, host e payload seguro de acesso; `/app/integracoes` agora reutiliza o mesmo fluxo auditado do billing para abrir o link comercial da troca BigShop.
- O portal ganhou `/app/plano-e-cobranca`, com resumo do plano atual, plataforma, benefício BigShop, status comercial, próximo vencimento, governança financeira, links financeiros auditados e histórico de solicitações comerciais em linguagem operacional.
- `App.vue`, `router/index.ts` e `DashboardView.vue` passaram a incluir a nova área no menu/ajuda contextual e no atalho do painel. `backend/config/cors.php` também passou a incluir `api/v1/billing*`, corrigindo a revisão local do portal em `5177`.
- Validações locais passaram com `php -l`, suíte focada `BillingSubscriptionApiTest|IntegrationChangeRequestApiTest`, PHPUnit completo (`136 tests`, `1602 assertions`), `C:\\php\\php.exe vendor\\bin\\pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos. O único falso positivo inicial foi o fixture `APP_USR-test-token` em teste automatizado; a repetição da varredura com exceção explícita para o valor de teste confirmou `SECRET_SCAN_CHANGED_FILES_OK`.
- A validação visual local rodou em `http://127.0.0.1:5177/app/plano-e-cobranca`, com backend local em `8002`, Playwright headless e uma empresa local controlada com assinatura mensal, benefício BigShop e solicitação comercial pendente. A tela exibiu o destaque do benefício, `2` links financeiros, `1` solicitação comercial e abriu o resumo de cobrança em `http://localhost/checkout/sucesso?ref=billing-local-ref`; as capturas ficaram em `.tmp/sprint156-billing-*.png` e não devem ser versionadas.
- O primeiro push da sprint saiu no commit `37242e1`, mas o run `26676274917` falhou na validação do backend porque `BillingSubscriptionApiTest` fixava `http://localhost` para o link auditado enquanto o CI usa `APP_FRONTEND_URL=http://127.0.0.1:5173`.
- O ajuste de CI foi publicado no commit `5c35b9b`, trocando a expectativa fixa pela URL derivada de `config('app.frontend_url', config('app.url'))`.
- O run `26676347155` do GitHub Actions finalizou com sucesso para o commit `5c35b9b`, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/app/plano-e-cobranca`, `/app/widget`, `/app/analytics`, pedidos, devoluções, usuários, taxonomia, integrações, sincronização, widget JS/CSS, páginas públicas, SaaS, portal, APIs, CORS, login demo e go-live readiness. Resultado final: `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 157 Auditoria, termos e segurança operacional

- Relida a documentação obrigatória antes da sprint, incluindo `docs/README.md`, `docs/sprint_governance.md`, `docs/current_platform_state.md`, `docs/roadmap_sprints.md`, `docs/user_access_permissions.md`, `docs/security_compliance.md`, `docs/analytics_admin.md` e os demais documentos mandatórios. `docs/credentials.local.md` permaneceu fechada nesta etapa porque o trabalho ainda estava em implementação e validação local.
- Usado o benchmark já registrado de histórico operacional, aceites legais e rastreabilidade da Sizebay como referência para expor ações críticas por empresa sem vazar dado pessoal desnecessário.
- Criada a migration `2026_05_30_120000_create_legal_acceptances_table` e o modelo `LegalAcceptance` para centralizar aceite legal por empresa, usuário, IP mascarável, hash de IP, documento, versão e origem da ação.
- Criado `LegalAcceptanceRecorder`, reutilizado no checkout público e na troca comercial BigShop para registrar aceites centralizados com origem, versões e metadados sanitizados.
- `PublicCheckoutController` passou a registrar `legal.checkout_terms_accepted` com ator, contexto, versões e vínculo com `CheckoutSession`; `IntegrationChangeRequestController` também passou a centralizar o aceite de termos da troca BigShop.
- `WidgetInstallController`, `IntegrationController`, `MeasurementTableController`, `ImportController` e `ProductController` passaram a auditar ações críticas com `before`, `after`, ator, empresa e contexto, cobrindo publicação/rascunho do widget, troca de integração com rotação de segredo, alteração/importação de tabela, commit de importação e vínculo em lote de tabela.
- Criado `SaasAuditController` com `GET /api/v1/saas/audit-logs` e `GET /api/v1/saas/audit-logs/export`, devolvendo resumo, filtros, logs críticos, aceites centralizados e exportação CSV por empresa.
- O SaaS ganhou `/saas/auditoria`, com filtros por empresa/categoria/módulo/evento/documento/período, cards operacionais, tabela `Ações críticas`, tabela `Aceites de termos` e botão de exportação CSV.
- `scripts/validate-production.ps1` foi ampliado para validar a página `/saas/auditoria` e, quando o usuário autenticado tiver a permissão, também validar `GET /api/v1/saas/audit-logs?limit=5`.
- Validações locais passaram com `C:\php\php.exe -l` nos PHP alterados, suíte focada `PublicCheckoutFlowTest|IntegrationChangeRequestApiTest|SaasAuditApiTest|WidgetInstallApiTest|IntegrationsApiTest`, PHPUnit completo (`138 tests`, `1619 assertions`), `C:\php\php.exe vendor\bin\pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos.
- A varredura de segredos não encontrou credencial real versionada nos arquivos alterados. `package.json` da raiz continuou fora do stage e `.tmp` permaneceu local-only.
- A validação visual local rodou em `http://127.0.0.1:5177/saas/auditoria`, com backend local em `8002`, navegador headless e login admin SaaS. O primeiro ciclo revelou uma quebra real de ambiente local: faltava aplicar a migration `legal_acceptances` no SQLite usado pelo backend de `8002`. Após `C:\php\php.exe artisan migrate --force`, desktop e mobile passaram com a tela autenticada, sem erro visível e com as seções `Ações críticas` e `Aceites de termos`. As capturas ficaram em `.tmp/sprint157-saas-audit-desktop.png` e `.tmp/sprint157-saas-audit-mobile.png` e não devem ser versionadas.
- Commit `12b98c5` enviado para `main`; o run `26677090414` do GitHub Actions finalizou com sucesso, incluindo validação backend, build frontend, deploy remoto, deploy da raiz pública, master admin e smoke público.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, incluindo `/saas/auditoria`, páginas SaaS, páginas do portal, widget JS/CSS, APIs, CORS, login demo, go-live readiness, integrações, sincronização, taxonomia, pedidos, devoluções, analytics e `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 158 Base de conhecimento e suporte contextual

- Relida a documentação obrigatória antes da sprint, retomando `docs/README.md`, `docs/current_platform_state.md`, `docs/roadmap_sprints.md`, `docs/portal_ui_guidelines.md` e o benchmark já consolidado da Sizebay para ajuda, suporte e manuais.
- Usado o benchmark já registrado de suporte, documentação pública e manuais operacionais da Sizebay como referência para reduzir dúvida dentro da própria tela, sem jogar o lojista direto para atendimento humano.
- A ajuda criada na Sprint 128 em `/app/ajuda` foi promovida a uma base de conhecimento real, com fonte única de artigos em `frontend/src/content/helpCenter.ts`, cobrindo telas críticas do portal e guias por plataforma.
- Foram adicionados artigos para painel, produtos, tabelas, modelagens, categorias, marcas, taxonomia, importações, regras, instalação do provador, integrações, sincronização, relatórios, pedidos, devoluções, assistente IA, publicação, cobrança, usuários e plataformas como BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart, XML/feed, API e personalizada.
- `/app/ajuda` agora tem busca interna, agrupamento por assunto, artigos relacionados, resumo de contexto atual da empresa e CTA `Suporte com contexto`.
- `App.vue` deixou de manter listas duplicadas de ajuda e passou a usar a mesma base compartilhada para a barra contextual, o link de manual e o suporte.
- O CTA de suporte agora monta o link do WhatsApp com assunto, rota, empresa, código, lojista, plataforma e usuário, reduzindo a necessidade de o cliente explicar o contexto manualmente.
- O menu do portal ganhou entrada explícita para `/app/ajuda`, mantendo a área acessível fora da barra contextual.
- Validações locais passaram com `npm --prefix frontend run build`, `git diff --check` e varredura de segredos.
- A varredura de segredos não encontrou credencial real versionada nos arquivos alterados. `.tmp` permaneceu local-only.
- A validação visual local rodou em `http://127.0.0.1:5177/app/ajuda`, com backend local em `8002`, navegador headless e usuário demo da empresa. Desktop e mobile passaram com busca, troca de artigo, botão `Suporte com contexto` e CTA relacionado; a barra de ajuda contextual em `http://127.0.0.1:5177/app/produtos` também apontou corretamente para o artigo de produtos. As capturas ficaram em `.tmp/sprint158-help-desktop.png` e `.tmp/sprint158-help-mobile.png` e não devem ser versionadas.
- O fechamento publicado da sprint saiu no commit `b30ce84`; o run `26677519329` do workflow `Deploy Production` terminou verde.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, cobrindo páginas públicas, páginas SaaS, portal autenticado, widget JS/CSS, APIs operacionais, CORS, login demo, readiness e retornando `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 159 Polimento final Sizebay-plus do portal

- Relida a documentação obrigatória antes da sprint, incluindo `docs/README.md`, `docs/current_platform_state.md`, `docs/development_guidelines.md`, `docs/portal_ui_guidelines.md`, `docs/sprint_governance.md` e o trecho final do roadmap com as Sprints 159 e 160.
- Feito benchmark local rápido das telas críticas do portal autenticado (`/app`, `/app/produtos`, `/app/integracoes`, `/app/sincronizacao`, `/app/analytics` e `/app/usuarios`) para localizar ruído visual, estados rasos e pontos de permissão pouco explícitos.
- Criado o componente compartilhado `frontend/src/components/OperationalStateCard.vue` para padronizar carregamento, vazio, erro, sucesso e modo leitura com linguagem curta, ação opcional e comportamento responsivo.
- `App.vue` passou a usar o novo estado compartilhado para o carregamento do contexto da empresa, evitando mensagem solta e alinhando a experiência entre telas.
- `/app/produtos` foi polida com modo leitura explícito, erro orientado, estado vazio com próximo passo, ocultação de ações sensíveis para acessos sem edição e vínculo em lote exibido só para quem realmente pode alterar o catálogo.
- `/app/usuarios` passou a distinguir melhor modo leitura, carregamento, erro e vazio; ações de convite, edição e ativação continuam escondidas para quem não pode editar.
- `/app/integracoes` ganhou estado padrão para carregamento/erro/vazio e aviso claro quando o usuário só pode consultar a configuração sem salvar conexão, token, feed ou validação.
- `/app/sincronizacao` ganhou estados orientados para comparação vazia, histórico filtrado sem resultados, detalhe sem execução selecionada e rodada sem erro por produto.
- Criado `docs/portal_visual_checklist.md` e referenciado em `docs/portal_ui_guidelines.md` como checklist rápido de clareza, estados, permissão e responsividade para futuras telas.
- Validações locais passaram com `npm --prefix frontend run build` e revisão visual headless em `http://127.0.0.1:5177/app/produtos`, `/app/integracoes`, `/app/sincronizacao` e `/app/usuarios`, incluindo cenário com filtro vazio em produtos e checagem mobile sem rolagem horizontal em `.tmp/sprint159-visual/`.
- O fechamento publicado da sprint saiu no commit `44d3513`; o run `26677917313` do workflow `Deploy Production` terminou verde.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, cobrindo páginas públicas, SaaS, portal autenticado, widget, APIs operacionais, CORS, login demo e retornando `PRODUCTION VALIDATION OK`.

## 2026-05-30 - Sprint 160 Migração Sizebay e importação assistida de clientes

- Relida a documentação obrigatória antes da sprint, incluindo `docs/README.md`, `docs/current_platform_state.md`, `docs/imports_data_quality.md`, `docs/data_learning_lgpd_outliers.md`, `docs/bigshop_integration.md`, `docs/portal_ui_guidelines.md`, `docs/sprint_governance.md` e o trecho final do roadmap. `docs/credentials.local.md` permaneceu fechada nesta etapa porque o ciclo ainda estava em implementação e validação local.
- Usado o benchmark já consolidado da Sizebay para `Measurement Guide/Table Measurements`, `Products`, `Brands`, `Categories`, `Modelings`, `Importation Rules`, `Settings/Data Sources`, `Settings/Sync` e `Reports`, comparando a estrutura esperada com o catálogo local e com o comparador BigShop já existente.
- Criado `SizebayMigrationService`, com suporte a pacote de migração em `JSON`, `CSV`, `XLSX` e `ZIP`, leitura por seções (`measurement_tables`, `products`, `brands`, `categories`, `fit_profiles`, `import_rules` e `reports`) e prévia unificada antes de qualquer gravação.
- A prévia da migração agora retorna resumo por seção, linhas válidas/inválidas, cobertura contra catálogo atual ou BigShop, fila de revisão, avisos do pacote e snapshot agregado de relatórios/import rules sem expor segredos, cookies, sessão nem dado pessoal identificável.
- O commit do lote gera `batch_id`, aplica apenas vínculos confiáveis, cria sugestões revisáveis de taxonomia quando necessário, preserva auditoria no `import_jobs.metadata` e grava snapshot suficiente para desfazer o lote com rollback de produtos, variantes, tabelas, marcas, categorias, modelagens e sugestões criadas.
- Criado `POST /api/v1/imports/{importJob}/rollback`, protegido por permissão de importações, com escopo por empresa e auditoria explícita de desfazer lote.
- `PreviewImportRequest` e `ImportService` foram ampliados para aceitar `type=sizebay_migration`, seções para arquivo isolado, `content_base64` para binários, comparação opcional com BigShop e delegação transparente para o novo fluxo.
- `/app/importacoes` foi reconstruída para suportar migração assistida com amostra JSON, upload binário/texto, seleção de seção, comparação BigShop, cobertura, fila de revisão, histórico detalhado e botão de desfazer lote.
- A UI local da nova importação foi validada em `http://127.0.0.1:5177/app/importacoes`, com backend em `8002`, navegador headless e login demo técnico via API/localStorage. Desktop e mobile passaram com a prévia da migração, bloco de cobertura, fila de revisão e sem rolagem horizontal; as capturas ficaram em `.tmp/sprint160-visual/imports-desktop.png` e `.tmp/sprint160-visual/imports-mobile.png` e não devem ser versionadas.
- Validações locais passaram com `C:\\php\\php.exe -l` nos PHP alterados, `C:\\php\\php.exe artisan test --filter=ImportsApiTest`, PHPUnit completo (`140 tests`, `1646 assertions`), `C:\\php\\php.exe vendor\\bin\\pint --dirty --test`, `npm --prefix frontend run build`, `git diff --check` e varredura de segredos. Os únicos achados da varredura foram termos legítimos do próprio código de bloqueio (`cookie`, `token`, `access_token_encrypted`) e o fixture de login demo já usado pelos testes, sem credencial real versionada.
- O fechamento publicado da sprint saiu no commit `4cd0d2f`; o run `26678665133` do workflow `Deploy Production` terminou verde.
- A validação pós-deploy com `scripts/validate-production.ps1` passou integralmente, cobrindo páginas públicas, SaaS, portal autenticado, widget, analytics, pedidos, devoluções, taxonomia, integrações, sincronização, importação assistida e retornando `PRODUCTION VALIDATION OK`.
