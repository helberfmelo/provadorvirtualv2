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

## Pendencias abertas

- Regularizar billing/spending limit do GitHub Actions para repositorio privado.
- Definir chave de IA quando a sprint de OCR/assistencia for iniciada.
