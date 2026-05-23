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

## Estrutura esperada apos scaffold

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
- `/app/widget`
- `/app/integracoes`
- `/api/v1/health`
- `/api/v1/demo/product-test`
- `/api/v1/products`
- `/api/v1/measurement-tables`
- `/api/v1/measurement-templates`
- `/api/v1/widget-install`
- `/api/v1/integrations`
- `/api/v1/public/recommendations/config-check`
- `/api/v1/public/recommendations`
- `/api/v1/public/recommendations/{id}/feedback`
- `/widget/v1/provador-virtual.js`
- `/widget/v1/provador-virtual.css`

## Variaveis frontend

```env
VITE_APP_NAME=Provador Virtual
VITE_APP_BASE_PATH=/
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
VITE_WIDGET_BASE_URL=http://127.0.0.1:8000/widget/v1
```

## Variaveis backend importantes

```env
APP_NAME="Provador Virtual"
APP_ENV=local
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:5173

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

MAIL_MAILER=log
```

## Validacoes locais esperadas

```powershell
cd backend
php artisan route:list
php artisan test

cd ..\frontend
npm run build
```

## Dados demo obrigatorios

Seeder inicial deve criar:

- um lojista demo;
- uma empresa/loja demo;
- um produto de moda ficticio;
- tres ou mais variacoes;
- tabela de medidas completa;
- instalacao de widget demo;
- pagina `/produto-teste` funcional.

Status Sprint 1: dados demo criados por `DatabaseSeeder` e publicados em producao por `ProductionSeeder`.

Status Sprint 2: painel demo permite CRUD de produtos, variacoes e tabelas de medidas com os endpoints protegidos por Sanctum.

Status Sprint 3: `/produto-teste` chama a API publica de recomendacao e registra log/feedback anonimo.

Status Sprint 4: widget publico disponivel em `backend/public/widget/v1` e carregado na pagina `/produto-teste`.

Status Sprint 5: painel demo permite configurar widget e conexoes de plataforma com tokens criptografados.
