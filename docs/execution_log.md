# Execution Log

## 2026-05-23 - Documentacao inicial e deploy

- Estudados projetos de referencia: BigShop HelpDesk, Marca Hora, BigShop360, Provador Virtual v1, BigShop front/back.
- Definido stack oficial Laravel + Vue + MySQL.
- Definida publicacao inicial em `/provadorvirtual_v2/` para preservar v1.
- Criada documentacao base em `docs/`.
- Criado `.gitignore` com `docs/credentials.local.md` ignorado.
- Criado workflow `.github/workflows/deploy.yml`.
- Identificados secrets faltantes para deploy SSH: `SSH_PRIVATE_KEY` ou `SSH_PRIVATE_KEY_B64`; opcional `SSH_PASSPHRASE`; recomendado `PRODUCTION_ENV`.
- Inicializado Git local em `main`, conectado ao remoto `git@github.com:helberfmelo/provadorvirtualv2.git` e publicado commit inicial `2dedd37`.
- Workflow `Deploy Production` disparou no GitHub Actions, mas o job nao iniciou por bloqueio de billing/spending limit da conta GitHub.
- Cadastrados no GitHub Actions: `SSH_PRIVATE_KEY`, `SSH_PRIVATE_KEY_B64` e `PRODUCTION_ENV`.
- Validado acesso SSH local ao HostGator/opents62 com a chave cadastrada; `/home1/opents62/public_html` existe e `/home1/opents62/public_html/provadorvirtual_v2` ainda nao existe.
- Reexecutado manualmente o workflow apos cadastrar os secrets; o job continuou bloqueado por billing/spending limit antes de iniciar qualquer etapa.
- Repositorio alterado para publico pelo usuario; workflow reexecutado e finalizado com sucesso.

## 2026-05-23 - Sprint 1 Fundacao Laravel/Vue

- Criado `backend/` com Laravel 12, Sanctum, rotas API versionadas e fallback SPA.
- Criado `frontend/` com Vue 3, TypeScript, Vite, Pinia, Vue Router, Axios e Font Awesome.
- Criadas migrations iniciais para users, merchants, companies, products, variants, measurement tables, widget installs, recommendation logs e feedbacks.
- Criado seed demo com lojista, loja, produto ficticio, cinco variacoes, tabela de medidas e instalacao de widget.
- Criada pagina `/produto-teste` com produto ficticio, seletor de tamanho, tabela de medidas e recomendacao inicial client-side.
- Criados endpoints `/api/v1/health`, `/api/v1/auth/login`, `/api/v1/me` e `/api/v1/demo/product-test`.
- Validacoes locais: `php artisan migrate:fresh --seed`, `php artisan test`, `php artisan route:list --path=api/v1`, `npm run build` e smoke integrado em `php artisan serve`.
- Primeiro Actions da Sprint 1 falhou na validacao backend porque `APP_BASE_PATH=/` no `.env.example` fazia o Laravel procurar `//bootstrap/app.php`; variavel removida do backend e mantida apenas como `VITE_APP_BASE_PATH` no build frontend.
- Segundo Actions da Sprint 1 passou por validacao/build/deploy remoto, mas falhou no smoke publico com HTTP 404 porque publicou em `/home1/opents62/public_html/provadorvirtual_v2`.
- Confirmado via SSH que `provadorvirtual_v1` esta em `/home1/opents62/provadorvirtual.online/provadorvirtual_v1`; workflow ajustado para publicar v2 em `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`.
- Deploy no docroot correto passou no Actions, mas teste manual mostrou que `/api/v1/health` estava retornando o `index.html` do Vue. Ajustado `.htaccess` para enviar `api`, `up` e `sanctum` ao front controller Laravel preservando o path, e smoke publico passou a validar conteudo JSON da API.
- Como o rewrite interno com `PATH_INFO` continuou caindo no fallback SPA no HostGator, a compatibilidade inicial passou a usar redirect 307 para endpoints Laravel limpos (`api`, `sanctum`, `up`) apontarem para a entrada publica funcional.
- Redirect 307 precisa usar URL-path absoluta (`/provadorvirtual_v2/public/...`) no HostGator; destino relativo virou caminho fisico no header `Location`.
- Run `26326675713` do GitHub Actions finalizou com sucesso para o commit `97ce033`; smokes validaram frontend e JSON real da API.

## 2026-05-23 - Sprint 2 Produtos, Variacoes e Tabelas

- Criados endpoints protegidos por Sanctum para produtos, variacoes, tabelas de medidas e templates.
- Criados Form Requests, Resources e controllers com escopo por lojista autenticado.
- Dashboard passou a consumir `/merchant/overview` com contadores reais.
- Criadas telas Vue `/app/produtos` e `/app/tabelas-de-medidas` para CRUD operacional da loja demo.
- Criados testes `ProductsApiTest` e `MeasurementTablesApiTest`.
- Validacoes locais: `php artisan route:list --path=api/v1`, `php artisan test`, `vendor/bin/pint` e `npm run build`.
- Run `26326950616` do GitHub Actions finalizou com sucesso para o commit `3b37c90`.
- Validado em producao: `/app/produtos`, `/app/tabelas-de-medidas`, login demo, `/api/v1/products` e `/api/v1/measurement-tables`.

## 2026-05-23 - Sprint 3 Motor de Recomendacao

- Criado `RecommendationEngine` deterministico com normalizacao de medidas, scoring por faixa, confianca, notas de caimento e alertas.
- Criados endpoints publicos `/api/v1/public/recommendations/config-check`, `/api/v1/public/recommendations` e `/api/v1/public/recommendations/{id}/feedback`.
- Recomendacoes criam `recommendation_sessions` e `recommendation_logs` com hash de IP/user-agent, sem coletar nome, email ou telefone.
- Pagina `/produto-teste` passou a chamar a API real e registrar feedback.
- Smoke do GitHub Actions passou a postar uma recomendacao real e validar `recommended_size = M`.
- Validacoes locais: `php artisan test`, `vendor/bin/pint`, `npm run build`, YAML do workflow e smoke integrado em `php artisan serve`.
- Run `26327119754` do GitHub Actions finalizou com sucesso para o commit `1c31542`.
- Validado em producao: `/produto-teste` e POST em `/api/v1/public/recommendations`, retornando `recommended_size = M`.

## 2026-05-23 - Sprint 4 Widget Universal v1

- Criados assets publicos `backend/public/widget/v1/provador-virtual.js` e `backend/public/widget/v1/provador-virtual.css`.
- Widget le atributos padrao e aliases legados, executa `config-check`, renderiza botao, abre modal responsivo, chama recomendacao e registra feedback.
- Pagina `/produto-teste` passou a carregar o mesmo snippet publico usado por lojas externas.
- Smoke do GitHub Actions passou a validar entrega do JS/CSS do widget.
- Validacoes locais: `php artisan test`, `npm run build`, YAML do workflow e asset test `WidgetAssetTest`.
- Run `26331199145` do GitHub Actions finalizou com sucesso para o commit `06144cf`.
- Validado em producao: JS/CSS do widget e `/produto-teste`.

## 2026-05-23 - Sprint 5 Painel do Lojista

- Criados endpoints protegidos `/api/v1/widget-install`, `/api/v1/integrations` e `/api/v1/integrations/{platform}`.
- Widget install passa a retornar URLs publicas, tema, dominios, produto de exemplo e snippet pronto para copiar.
- Catalogo de integracoes inicial inclui BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray e personalizada.
- Tokens e webhook secrets de plataformas sao persistidos criptografados e nunca retornam em claro.
- Dashboard do lojista passou a destacar produtos, tabelas, widget e integracoes.
- Criadas telas Vue `/app/widget` e `/app/integracoes` com estados operacionais e controles responsivos.
- Criados testes `WidgetInstallApiTest` e `IntegrationsApiTest`.
- Validacoes locais: `php artisan test`, `vendor/bin/pint` e `npm run build`.
- Run `26331424403` do GitHub Actions publicou a Sprint 5, mas validacao manual mostrou `500` em endpoint protegido quando o cliente perdia `Authorization` no redirect limpo `/api -> /public/api`.
- Ajustado build de producao para o painel usar `/provadorvirtual_v2/public/api/v1` direto e configurado Laravel para retornar `401` JSON em APIs sem token.
- Run `26331485173` do GitHub Actions finalizou com sucesso para o commit `2b9d4e9`.
- Validado em producao: `/app/widget`, `/app/integracoes`, `/public/api/v1/widget-install`, `/public/api/v1/integrations` e resposta `401` controlada sem token.

## 2026-05-23 - Sprint 6 Importacao e Templates Assistidos

- Criadas migrations `import_jobs` e `integration_events`.
- Criado service de importacao com preview e commit sincronizados.
- Criados endpoints protegidos `/api/v1/imports`, `/api/v1/imports/preview` e `/api/v1/imports/{importJob}`.
- CSV de produtos cria/atualiza produtos e variacoes por SKU/ID externo.
- CSV de tabelas cria/atualiza tabelas e substitui linhas da tabela importada.
- Parser inicial de Google Shopping XML cria preview/commit de produtos quando o feed informa campos basicos.
- Criada tela Vue `/app/importacoes` com amostras, leitura de arquivo, preview, commit e historico.
- Criado documento `docs/imports_data_quality.md`.
- Criados testes `ImportsApiTest`.
- Validacoes locais: `php artisan test`, `vendor/bin/pint`, `npm run build` e smoke autenticado em `/api/v1/imports/preview`.
- Run `26331691701` do GitHub Actions finalizou com sucesso para o commit `2c9279b`.
- Validado em producao: `/app/importacoes`, `GET /public/api/v1/imports` e `POST /public/api/v1/imports/preview`.

## 2026-05-23 - Sprint 7 Integracao BigShop Base

- Criado `BigShopClient` para chamadas `GET /v3/getEndPoints` e `GET /v3/products`.
- Criado `BigShopSyncService` para probe, sync de produtos, grades e tabelas de medidas estruturadas.
- Criados endpoints protegidos `/api/v1/integrations/bigshop/probe` e `/api/v1/integrations/bigshop/sync`.
- Sync cria/atualiza `products`, `product_variants`, `measurement_tables` e registra `integration_events`.
- Tela `/app/integracoes` passou a mostrar acoes de testar e sincronizar para BigShop.
- Criados testes `BigShopIntegrationTest` com `Http::fake`.
- Validacoes locais: `php artisan test`, `vendor/bin/pint`, `npm run build` e smoke de erro controlado `422` sem conexao BigShop.
- Run `26331844564` do GitHub Actions finalizou com sucesso para o commit `2254a07`.
- Validado em producao: `/app/integracoes` e `POST /public/api/v1/integrations/bigshop/probe` retornando `422` controlado sem credencial real.

## 2026-05-23 - Sprint 8 BigShop Um Clique

- Criada configuracao `BIGSHOP_ACTIVATION_SECRET` para controlar a ativacao nativa por HMAC.
- Criado endpoint publico assinado `POST /api/v1/public/bigshop/activate`.
- Endpoint valida timestamp, assinatura `sha256=<hmac>` e payload minimo da loja BigShop.
- Ativacao cria/atualiza usuario, lojista, empresa, conexao BigShop e instalacao do widget.
- Tokens recebidos sao salvos criptografados e nunca retornam em claro.
- Resposta retorna `dashboard_url`, `widget_url`, `widget_public_key` e status operacional.
- Criados testes `BigShopActivationTest`.
- Validacoes locais: `php artisan test`, `vendor/bin/pint`, `npm run build`, `git diff --check` e smoke local retornando `503` quando o secret nao esta configurado.
- Run `26332055677` do GitHub Actions finalizou com sucesso para o commit `729e1c3`.
- Validado em producao: `POST /public/api/v1/public/bigshop/activate` retorna `503` controlado enquanto `BIGSHOP_ACTIVATION_SECRET` nao esta configurado.

## 2026-05-23 - Sprint 9 IA para OCR e Tabelas

- Criada tabela `ai_usage_logs` para registrar uso, provider, modelo, tokens estimados, custo estimado e resumo sem conteudo bruto.
- Criados endpoints protegidos `/api/v1/ai/status` e `/api/v1/ai/measurement-table-suggestions`.
- Criado parser local para texto/CSV de tabela de medidas.
- Imagens ficam preparadas no front; enquanto provider externo nao estiver ativo, a API retorna `needs_provider` em vez de simular OCR.
- Sugestoes sempre retornam `review_required=true` e `status=draft`.
- Criada tela Vue `/app/assistente` para colar texto/CSV, enviar imagem, revisar medidas e criar rascunho.
- Criado documento `docs/ai_assistant.md`.
- Criados testes `AiMeasurementAssistantTest`.
- Validacoes locais: `php artisan test`, `vendor/bin/pint`, `npm run build`, `php artisan route:list --path=api/v1/ai`, `git diff --check` e smoke autenticado local com 2 linhas sugeridas.
- Run `26332326042` do GitHub Actions finalizou com sucesso para o commit `b7a88d1`.
- Validado em producao: `/app/assistente`, `GET /public/api/v1/ai/status` e `POST /public/api/v1/ai/measurement-table-suggestions` com 2 linhas sugeridas.

## 2026-05-23 - Sprint 10 Analytics e SaaS Admin

- Criada tabela `audit_logs`.
- Criado `AuditLogger` com hash de IP/user-agent e filtro de tokens/secrets/senhas em metadata.
- Criados endpoints protegidos `/api/v1/analytics/recommendations` e `/api/v1/audit-logs`.
- Criados endpoints admin `/api/v1/saas/overview` e `/api/v1/saas/merchants`, restritos a `admin` ou `support`.
- Analytics retorna recomendacoes, feedback positivo, produtos sem tabela, alertas, series diarias, tamanhos e produtos.
- Criadas telas Vue `/app/analytics` e `/saas`.
- Auditoria inicial cobre tabelas de medidas, widget e integracoes.
- Criado documento `docs/analytics_admin.md`.
- Criados testes `AnalyticsApiTest` e `SaasAdminApiTest`.
- Validacoes locais: `php artisan test`, `vendor/bin/pint`, `npm run build`, `php artisan route:list --path=api/v1/analytics`, `php artisan route:list --path=api/v1/saas`, `git diff --check` e smoke local com analytics 200 e SaaS 403 para lojista comum.
- Run `26332544138` do GitHub Actions finalizou com sucesso para o commit `4f93032`.
- Validado em producao: `/app/analytics`, `/saas`, `GET /public/api/v1/analytics/recommendations` e `GET /public/api/v1/saas/overview` retornando `403` para lojista comum.

## 2026-05-23 - Sprint 11 Hardening, LGPD e Observabilidade

- Criadas paginas publicas `/privacidade` e `/termos`.
- Criado endpoint publico `GET /api/v1/ops/status`.
- Adicionados rate limits em login, recomendacoes publicas, feedback, ativacao BigShop e status operacional.
- Criado middleware de origem do widget, validando `Origin` contra `widget_installs.allowed_domains`.
- Configuracao CORS global passou a atender apenas origens locais de desenvolvimento; o widget usa validacao dinamica por dominio.
- `AuditLogger` passou a mascarar metadata sensivel de forma recursiva e auth passou a registrar login/logout.
- Criados comandos `pv:privacy-anonymize` e `pv:privacy-prune`.
- Criado documento `docs/hardening_lgpd_observability.md`.
- Validacoes locais: `php artisan test --filter=HardeningApiTest`, `vendor/bin/pint`, `php artisan test`, `npm run build` e `git diff --check`.
- Run `26332960822` do GitHub Actions finalizou com sucesso para o commit `ac1025f`.
- Validado em producao: `/privacidade`, `/termos`, `GET /public/api/v1/ops/status`, bloqueio de origem nao cadastrada com `403` e origem `https://provadorvirtual.online` com CORS correto.
- Rollback readiness validado pelo backup criado no deploy: `provadorvirtual-v2-backup-20260523-094207-ac1025f2a2469b9876d93764652ce87acd0e7174.tar.gz`.

## 2026-05-23 - Sprint 12 Go-live Assistido

- Criado endpoint protegido `GET /api/v1/go-live/readiness`.
- Criada tela `/app/go-live` com checklist de prontidao, URLs de producao e credenciais pendentes.
- Criado script `scripts/validate-production.ps1` para smoke publico/autenticado em producao.
- Criado `tools/widget-external-smoke.html` para validar widget de producao servido por HTTP local.
- Criado documento `docs/go_live_cutover.md`.
- Validacoes locais: `php artisan test --filter=GoLiveReadinessApiTest`, `vendor/bin/pint`, `php artisan test`, `npm run build`, `php artisan route:list --path=api/v1/go-live` e `git diff --check`.
- Run `26333179219` do GitHub Actions finalizou com sucesso para o commit `f96bc4f`.
- Ajustado `scripts/validate-production.ps1` para normalizar header CORS retornado como array no PowerShell.
- Run `26333226813` do GitHub Actions finalizou com sucesso para o commit `e657a75`.
- Validado em producao: `/app/go-live`, `scripts/validate-production.ps1` com `PRODUCTION VALIDATION OK` e backup `provadorvirtual-v2-backup-20260523-095421-e657a75c92163ab29eae19a8cec5b5d5d1b6cd5c.tar.gz`.

## Pendencias abertas

- Repositorio esta publico para manter a cota do GitHub Actions disponivel.
- `GEMINI_API_KEY` foi encontrada no v1 e copiada para `docs/credentials.local.md`; ainda falta cadastrar/ativar em producao quando aprovado.
- Opcional: cadastrar `OPENAI_API_KEY` como provider alternativo.
- Cadastrar `BIGSHOP_ACTIVATION_SECRET` em `PRODUCTION_ENV`.
- Receber loja piloto BigShop, `store_id`, token `x-api` e webhook secret, se existir.

## 2026-05-23 - Roadmap inteligente pos Sprint 12

- Estudado `D:\Projetos\provadorvirtual_v1` para migrar conceitos de catalogo padrao, Gemini, OCR, modelo por IA, wizard de tabela e widget gamificado.
- Encontrada `GEMINI_API_KEY` no `.env` do v1; valor documentado apenas em `docs/credentials.local.md`, ignorado pelo Git.
- Estudada documentacao publica Sizebay sobre script, API, produto, XML, OnPage, order tracking, devolucoes, Size Tailor, Shopify e categorias.
- Capturado fluxo publico Zak/Sizebay em camisa e calca: tenant `1235`, botoes `DESCUBRA SEU TAMANHO`/`TABELA DE MEDIDAS`, cookies `SIZEBAY_SESSION_ID_V4` e recomendacoes por perfis de teste.
- Criados documentos `docs/v1_intelligence_migration.md`, `docs/sizebay_benchmark.md`, `docs/data_learning_lgpd_outliers.md` e `docs/intelligent_sizing_roadmap.md`.
- Atualizados docs obrigatorios, backlog, arquitetura, widget e IA.

## 2026-05-23 - Sprint 27 Raiz e checkout anual

- Criada landing publica v2 com estrutura inspirada no v1 e cores do v2.
- Checkout publico passou para plano anual unico, sem boleto, com BigShop como primeira plataforma.
- Preco padrao: `R$ 189,90/mes`; preco BigShop: `R$ 129,90/mes`.
- Cartao em ate 12x; Pix a vista com 5% de desconto.
- Workflow passou a publicar build estatica na raiz e manter backend/app em `/provadorvirtual_v2/`.
- Validacoes locais: `php artisan test`, `npm run build`, `npx vite build --outDir dist-root` e `git diff --check`.
- Run `26336510709` publicou app e raiz, mas falhou no passo `Ensure master admin` por `ssh: connect ... Connection refused` logo apos os deploys remotos.
- Workflow ajustado para retry no passo de master admin e diagnostico SSH tolerante a indisponibilidade transitoria.
- Run `26336554760` finalizou com sucesso apos retry SSH; `scripts/validate-production.ps1` validou raiz, v2, APIs, CORS, recomendacao e go-live.

## 2026-05-23 - Sprint 28 Monitor de pagamentos e e-mails

- Criadas tabelas `email_settings` e `transactional_emails`.
- Criado painel SaaS para configurar SMTP com senha criptografada e templates transacionais.
- Criados templates iniciais: cadastro realizado, pagamento confirmado, aguardando pagamento, erro no pagamento, recuperacao de senha e renovacao de plano.
- Criado comando `php artisan pv:payments-sync --limit=50` para consultar pedidos pendentes na Pagar.me e ativar empresas pagas.
- Scheduler configurado para rodar monitor de pagamentos a cada 5 minutos, anonimizacao diaria e limpeza operacional semanal.
- Documentado cron cPanel com log em `docs/deploy_runbook.md`.
- Validacoes locais focadas: `php artisan test --filter=SaasEmailApiTest`, `php artisan test --filter=PaymentSyncCommandTest` e `npm run build`.
- Validacoes locais completas: `php artisan test`, `npm run build`, build raiz com `VITE_APP_BASE_PATH=/`, `git diff --check` e `vendor/bin/pint --dirty`.
- Run `26336899986` do GitHub Actions finalizou com sucesso para o commit `ce65f82`.
- Validado em producao: raiz, paginas publicas, API health/ops/demo/recommendation, CORS, login demo e go-live com `scripts/validate-production.ps1`.

## 2026-05-23 - Sprint 29 Login contextual e multiempresa

- Login passou a aceitar `login` por e-mail ou CPF, mantendo alias legado `email`.
- Portal da empresa passou a receber `company_access` por codigo da loja ou CNPJ.
- Usuarios multiempresa precisam informar empresa; usuarios de uma unica empresa seguem com fallback automatico para compatibilidade.
- Token Sanctum passa a carregar contexto `merchant:{id}` e `company:{id}`.
- APIs do portal usam `ActiveTenant` para resolver o lojista ativo pelo token.
- Checkout publico e cadastro interno SaaS reaproveitam usuario existente por e-mail/CPF, permitindo uma pessoa em varias empresas.
- Tela `/login` agora mostra campo `E-mail ou CPF` e `Codigo da loja ou CNPJ`.
- Validacoes locais focadas: `php artisan test --filter=AuthTest`, `php artisan test --filter=PublicCheckoutFlowTest` e `npm run build`.
- Validacoes locais completas: `php artisan test`, `npm run build`, build raiz com `VITE_APP_BASE_PATH=/`, `git diff --check` e `vendor/bin/pint --dirty`.
- Run `26337158927` aplicou backend/raiz, mas falhou no smoke por falso negativo de `curl | grep -q` com `pipefail` e exit `23`.
- Workflow ajustado no commit `08547b8` para validar respostas HTTP em variavel antes do `grep`, evitando SIGPIPE falso.
- Run `26337254520` finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 30 Usuarios e permissoes por modulo

- Criada migration para `users.status`, `users.permissions`, `merchant_user.status`, `merchant_user.permissions` e vinculo opcional com `merchant_company_id`.
- Criado catalogo canonico de modulos/permissoes para portal da empresa e portal SaaS.
- Criadas APIs protegidas `/api/v1/merchant/users` e `/api/v1/saas/users`.
- Login passa a bloquear usuario globalmente inativo e acesso de empresa desativado.
- Menu do frontend passa a respeitar permissoes de visualizacao.
- Criadas telas `/app/usuarios` e `/saas/usuarios` com listagem, novo, editar e ativar/desativar.
- CRUD de empresas no SaaS ganhou editar e ativar/desativar na listagem.
- Criado documento `docs/user_access_permissions.md`.
- Validacoes locais: `php artisan test --filter=UserAccessApiTest`, `php artisan test`, `npm run build`.
- Run `26337792120` do GitHub Actions finalizou com sucesso para o commit `87e9577`.
- Validado em producao: `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 31 Automacoes de e-mail e ciclo financeiro

- Criada tabela `transactional_email_sends` para historico de disparos.
- Criado `TransactionalEmailService` com renderizacao de templates, configuracao dinamica de mailer e controle de duplicidade.
- Checkout publico registra `cadastro_realizado` e eventos de status registram `aguardando_pagamento`, `pagamento_confirmado` ou `erro_pagamento`.
- Cadastro interno de empresa no SaaS envia `cadastro_realizado` quando ha owner.
- Criado comando `php artisan pv:emails-dispatch --limit=50` para reprocessar pendencias e reenviar Pix pendente apos 6 horas.
- Scheduler passou a rodar o dispatcher de e-mails a cada 10 minutos.
- SaaS ganhou historico de envios em `/api/v1/saas/transactional-email-sends` e listagem na tela `/saas`.
- Criado documento `docs/transactional_email_automation.md`.
- Validacoes locais focadas: `php artisan test --filter=TransactionalEmailDispatchTest`, `php artisan test --filter=SaasEmailApiTest`, `php artisan test --filter=PublicCheckoutFlowTest`, `php artisan test --filter=PaymentSyncCommandTest` e `npm run build`.
- Validacoes locais completas: `php artisan test`, `npm run build`, build raiz com `VITE_APP_BASE_PATH=/`, `git diff --check` e `vendor/bin/pint --dirty`.
- Run `26338061259` do GitHub Actions finalizou com sucesso para o commit `62e0830`.
- Validado em producao: `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 32 Oferta BigShop travada, site publico e mobile

- Criada sprint adicional para impedir que desconto BigShop seja usado com integracao de plataforma mais cara.
- Backend passou a filtrar catalogo de integracoes para BigShop quando a empresa ativa e BigShop.
- Backend passou a bloquear `PATCH /integrations/{platform}` e `PATCH /widget-install` para plataformas diferentes de BigShop nesses contratos.
- Painel do lojista passou a mostrar aviso de contrato BigShop e apenas a opcao BigShop no widget.
- Checkout publico passou a aceitar query `platform` para CTA direto do plano padrao ou BigShop.
- Landing ganhou duas colunas de preco com CTAs exclusivos, WhatsApp oficial, secao BigShop, footer refinado, favicon PV e metatags OG/Twitter.
- Loja teste passou a usar fotos externas que representam vestido, blusa, camiseta e calca jeans.
- Menu mobile do Vue passou a abrir em drawer com botao de barras.
- Validacoes locais focadas: `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=WidgetInstallApiTest` e `npm run build`.
- Validacoes locais completas: `php artisan test`, `npm run build`, build raiz com `VITE_APP_BASE_PATH=/`, `git diff --check` e `vendor/bin/pint --dirty`.
- Run `26338411089` do GitHub Actions finalizou com sucesso para o commit `116fcf6`.
- Primeira tentativa de validacao de producao teve timeout transitorio de conexao logo apos deploy; nova tentativa 15 segundos depois retornou `PRODUCTION VALIDATION OK`.
- Validacoes adicionais em producao: OG tags da raiz, `/favicon.svg`, imagens demo por API e precos `R$ 189,90`/`R$ 129,90`.

## 2026-05-23 - Sprint 36 Perfis, aprendizado e outliers

- Criadas tabelas `shopper_profiles` e `recommendation_learning_events`.
- Sessao/log de recomendacao passaram a guardar vinculo de perfil, consentimento, snapshot, `outlier_score`, `learning_status` e `learning_reason`.
- Widget passou a salvar perfil anonimo com consentimento, token local, limpeza de perfil, genero, formato corporal, caimento e barra de precisao.
- Criados sinais publicos `feedback`, `add_to_cart`, `purchase`, `return` e `exchange` para aprendizado.
- Analytics passou a exibir perfis, qualidade media, sinais de aprendizado, sinais comerciais e outliers bloqueados.
- Validacoes locais: `vendor/bin/pint --dirty`, `php artisan test`, `npm run build`, filtros `RecommendationApiTest`, `AnalyticsApiTest` e `HardeningApiTest`.
- Run `26339739429` falhou no deploy remoto porque o MySQL recusou a foreign key automatica `recommendation_learning_events_recommendation_feedback_id_foreign` por exceder 64 caracteres.
- Commit `5d5b5dc` tornou a migration idempotente para recuperar a tentativa parcial e usou nomes curtos para foreign key/indices.
- Run `26339824157` finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 37 Pacote comercial e piloto assistido

- `GET /api/v1/go-live/readiness` passou a incluir checks de Pagar.me, transacao real, cron, performance do widget, acessibilidade/mobile e pacote de piloto.
- `/app/go-live` passou a exibir links comerciais, onboarding, comandos de automacao e pendencias reais.
- Criado `docs/commercial_pilot_package.md`.
- `scripts/validate-production.ps1` passou a validar `/checkout`, widget JS/CSS, perfil consentido, esquecimento de perfil, sinal de aprendizado e pacote de piloto.
- Validacoes locais: `vendor/bin/pint --dirty`, `php artisan test`, `npm run build` e `php artisan test --filter=GoLiveReadinessApiTest`.
- Run `26340033238` finalizou com sucesso e o validador ampliado retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 38 UX corretiva: navegacao por contexto

- Registradas as sprints 38 a 41 para corrigir arquitetura de informacao, CRUDs list-first e revisao visual/responsiva.
- Shell autenticado passou a separar portal SaaS e portal da empresa.
- SaaS deixou de exibir menus de lojista; portal da empresa deixou de misturar atalhos de SaaS no menu principal.
- Areas autenticadas passaram a usar menu lateral no desktop e drawer no mobile.
- Validacoes locais: `npm run build` e `git diff --check`.

## 2026-05-23 - Sprint 39 SaaS list-first e subpaginas

- `/saas` foi reduzida para visao geral com metricas, atalhos e tabelas resumidas.
- Empresas SaaS foram separadas em `/saas/empresas`, `/saas/empresas/nova` e `/saas/empresas/:id/editar`.
- Usuarios SaaS foram separados em `/saas/usuarios`, `/saas/usuarios/novo` e `/saas/usuarios/:id/editar`.
- E-mails foram separados em `/saas/emails`, `/saas/emails/configuracoes`, `/saas/emails/novo` e `/saas/emails/:id/editar`.
- O menu SaaS passou a listar visao geral, empresas, usuarios e e-mails, sem misturar modulos do portal da empresa.
- Validacoes locais: `npm run build`.

## 2026-05-23 - Sprint 40 Portal da empresa list-first e subpaginas

- Produtos foram separados em `/app/produtos`, `/app/produtos/novo` e `/app/produtos/:id/editar`.
- Tabelas de medidas foram separadas em `/app/tabelas-de-medidas`, `/app/tabelas-de-medidas/nova` e `/app/tabelas-de-medidas/:id/editar`.
- Usuarios da empresa foram separados em `/app/usuarios`, `/app/usuarios/novo` e `/app/usuarios/:id/editar`.
- As listagens agora ocupam a tela e as edicoes abrem em telas proprias.
- Validacoes locais: `npm run build`.

## 2026-05-23 - Sprint 41 Revisao visual, responsiva e QA de telas

- Registrado `docs/portal_ui_guidelines.md` como referencia obrigatoria para separar listagem, cadastro e edicao nos CRUDs.
- Ajustadas tabelas, acoes por linha, cabecalhos e largura minima de formularios/tabelas para reduzir desalinhamento e overflow espremido.
- Validador de producao passou a cobrir rotas novas do SaaS e do portal da empresa.
- Smoke do GitHub Actions passou a validar rotas autenticadas principais na raiz e na subpasta.

## 2026-05-23 - Sprint 42 Ajustes pos-inspecao visual

- Inspecao visual autenticada gerou screenshots de SaaS, portal da empresa, listagens, formularios e mobile.
- `Nova empresa` deixou de abrir preenchida com `Loja teste`.
- Cadastro interno de empresa agora inicia com BigShop como plataforma padrao.
- `Novo produto` deixou de abrir com categoria e tabela incompatibilizadas por padrao.
- Run `26343135605` do GitHub Actions finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 43 Cerebro inteligente do v1 no v2

- Reestudados `table_new.php`, `ajax_get_default_table.php`, `ajax_get_gender_and_types.php`, `ajax_ocr_table.php`, `includes/gemini-ai.php` e `default_measurement_tables_data.json` do v1.
- Confirmado que o v1 buscava primeiro modelos em `standard_models`, com medidas por genero/produto/altura/peso/idade/formato corporal, antes de sugerir via Gemini.
- Importado o JSON padrao do v1 para `backend/database/data/default_measurement_tables_data.json`.
- Criado `StandardMeasurementCatalog` para normalizar os modelos em templates do v2 consumidos por `/api/v1/measurement-templates`.
- Tela de nova/editar tabela passou a ter seletor de modelo inteligente filtrado por produto/genero.
- Site publico e assistente IA passaram a destacar base brasileira, IA assistiva, revisao humana e aprendizado seguro.
- `docs/credentials.local.md` foi limpo localmente para exibir somente as duas URLs de login pedidas.
- Run `26343538804` do GitHub Actions finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 44 CRUD SaaS de usuarios das empresas

- Criado modulo SaaS `saas_company_users` para separar usuarios internos de usuarios de empresas clientes.
- `/api/v1/saas/users` passou a listar usuarios internos `admin`/`support`.
- Criadas APIs `/api/v1/saas/company-users` para listar, criar, editar e ativar/desativar acessos de clientes.
- Criadas telas `/saas/usuarios-empresas`, `/saas/usuarios-empresas/novo` e `/saas/usuarios-empresas/:id/editar`.
- Formulario permite selecionar empresa por codigo/nome/CNPJ, definir perfil, status do acesso e permissoes do portal da empresa.
- `pv:create-master-admin` passou a garantir permissoes SaaS completas.
- Validacoes locais: `php artisan test --filter=UserAccessApiTest`, `npm run build`, `vendor/bin/pint --dirty` e `git diff --check`.
- Run `26343868801` do GitHub Actions finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 45 Feedback global de salvamento

- Criado modal global pequeno e central para salvamento, sucesso e erro.
- `api.ts` passou a interceptar mutacoes autenticadas dos portais SaaS/empresa.
- Sucesso fica visivel por 4 segundos e fecha automaticamente.
- Erros ficam abertos ate o usuario fechar e mostram motivo amigavel, inclusive `422` de validacao.
- Mantidas exclusoes para login/logout, checkout publico, previews e acoes que nao representam salvamento.
- Validacoes locais: `npm run build`, `php artisan test --filter=UserAccessApiTest` e `git diff --check`.
- Run `26344601240` do GitHub Actions finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## 2026-05-23 - Sprint 46 Recarregamento ao trocar empresa

- Corrigido o shell operacional para recriar a tela atual quando `activeCompany.id` muda.
- A troca pelo seletor de empresa no portal agora remonta a `RouterView` do portal da empresa.
- Com isso, painel e CRUDs executam novamente seus carregamentos de dados no novo contexto.
- Validacoes locais: `npm run build` e `git diff --check`.
- Run `26344923662` do GitHub Actions finalizou com sucesso e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.
