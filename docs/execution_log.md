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
- Cartão em até 12x; Pix a vista com 5% de desconto.
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
