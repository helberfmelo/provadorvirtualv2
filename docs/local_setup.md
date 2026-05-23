# Setup Local

Atualizado em: 2026-05-23

## Pre-requisitos

- PHP 8.2+;
- Composer;
- Node 20+;
- npm;
- MySQL/MariaDB local;
- Git;
- extensoes PHP: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `xml`, `zip`.

## Banco local sugerido

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=provadorvirtual_v2
DB_USERNAME=root
DB_PASSWORD=carbonos
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

## Estrutura esperada após scaffold

```powershell
cd D:\Projetos\provadorvirtual_v2

# backend
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000

# frontend
cd ..\frontend
npm install
npm run dev
```

## URLs locais

- Backend: `http://127.0.0.1:8000`
- Frontend: `http://127.0.0.1:5173`
- API: `http://127.0.0.1:8000/api/v1`

## Acesso demo

- E-mail: `demo@provadorvirtual.online`
- Senha: `provador123`

## Rotas iniciais

- `/`
- `/login`
- `/produto-teste`
- `/app`
- `/app/produtos`
- `/app/tabelas-de-medidas`
- `/app/assistente`
- `/app/analytics`
- `/app/importacoes`
- `/app/widget`
- `/app/integracoes`
- `/api/v1/health`
- `/api/v1/demo/product-test`
- `/api/v1/products`
- `/api/v1/measurement-tables`
- `/api/v1/measurement-templates`
- `/api/v1/widget-install`
- `/api/v1/integrations`
- `/api/v1/integrations/bigshop/probe`
- `/api/v1/integrations/bigshop/sync`
- `/api/v1/imports`
- `/api/v1/imports/preview`
- `/api/v1/ai/status`
- `/api/v1/ai/measurement-table-suggestions`
- `/api/v1/analytics/recommendations`
- `/api/v1/audit-logs`
- `/api/v1/go-live/readiness`
- `/api/v1/saas/overview`
- `/api/v1/saas/merchants`
- `/api/v1/ops/status`
- `/api/v1/public/recommendations/config-check`
- `/api/v1/public/recommendations`
- `/api/v1/public/recommendations/{id}/feedback`
- `/api/v1/public/bigshop/activate`
- `/widget/v1/provador-virtual.js`
- `/widget/v1/provador-virtual.css`

## Variáveis frontend

```env
VITE_APP_NAME=Provador Virtual
VITE_APP_BASE_PATH=/
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_WIDGET_BASE_URL=http://127.0.0.1:8000/widget/v1
```

## Variáveis backend importantes

```env
APP_NAME="Provador Virtual"
APP_ENV=local
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:5173

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

MAIL_MAILER=log

AI_PROVIDER=local
AI_MODEL=local-table-parser-v1

PRIVACY_WIDGET_DATA_RETENTION_DAYS=30
OPERATIONAL_LOG_RETENTION_DAYS=180
CORS_ALLOWED_ORIGINS=http://127.0.0.1:5173,http://localhost:5173
```

## Validações locais esperadas

```powershell
cd backend
php artisan route:list
php artisan test

cd ..\frontend
npm run build
```

## Dados demo obrigatórios

Seeder inicial deve criar:

- um lojista demo;
- uma empresa/loja demo;
- um produto de moda fictício;
- tres ou mais variações;
- tabela de medidas completa;
- instalação de widget demo;
- página `/produto-teste` funcional.

Status Sprint 1: dados demo criados por `DatabaseSeeder` e publicados em produção por `ProductionSeeder`.

Status Sprint 2: painel demo permite CRUD de produtos, variações e tabelas de medidas com os endpoints protegidos por Sanctum.

Status Sprint 3: `/produto-teste` chama a API pública de recomendação e registra log/feedback anônimo.

Status Sprint 4: widget público disponível em `backend/public/widget/v1` e carregado na página `/produto-teste`.

Status Sprint 5: painel demo permite configurar widget e conexões de plataforma com tokens criptografados.

Em produção, o painel usa `VITE_API_BASE_URL=/provadorvirtual_v2/public/api/v1`. Localmente, manter `http://127.0.0.1:8000/api/v1`.

Status Sprint 6: painel demo permite analisar e importar CSV de produtos, CSV de tabelas e feed Google XML inicial.

Status Sprint 7: backend possui conector BigShop com probe/sync testados via `Http::fake`; tela de integrações mostra ações BigShop.

Status Sprint 8: ativação BigShop um clique disponível por endpoint assinado; sem `BIGSHOP_ACTIVATION_SECRET`, retorna `503`.

Status Sprint 9: painel demo possui `/app/assistente`; texto/CSV gera sugestão de tabela em rascunho e `ai_usage_logs` registra uso sem conteúdo bruto.

Status Sprint 10: painel demo possui `/app/analytics`; `/saas` exige usuário com papel `admin` ou `support`.

Status Sprint 11: páginas `/privacidade` e `/termos` disponíveis; rotas públicas do widget validam origem por domínio; comandos `pv:privacy-anonymize` e `pv:privacy-prune` disponíveis.

Status Sprint 12: painel possui `/app/go-live`; script `scripts/validate-production.ps1` valida produção; `tools/widget-external-smoke.html` testa snippet fora do app quando servido por HTTP local.
