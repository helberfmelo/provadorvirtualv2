# Deploy e Operação

Atualizado em: 2026-05-23

## Estrategia

Deploy oficial por GitHub Actions via SSH, seguindo o padrão BigShop HelpDesk/Marca Hora.

Publicação inicial:

- URL: `https://provadorvirtual.online/provadorvirtual_v2`
- Base path: `/provadorvirtual_v2/`
- Pasta remota: `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`
- Backup remoto: `/home1/opents62/deploy_backups/provadorvirtual_v2`

Motivo: preservar `https://provadorvirtual.online/provadorvirtual_v1/` até o cutover.

Publicação comercial atual depois da Sprint 27:

- Site público raiz: `https://provadorvirtual.online/`
- Build estática da raiz: `/home1/opents62/provadorvirtual.online`
- Backup da raiz: `/home1/opents62/deploy_backups/provadorvirtual_root`
- Backend/app operacional e rollback continuam em `/provadorvirtual_v2/`.
- A raiz aponta APIs e widget para `/provadorvirtual_v2/public/api/v1` e `/provadorvirtual_v2/widget/v1`.

## Banco de produção

Dados ficam registrados em `docs/credentials.local.md` e no `.env` remoto:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=opents62_provadorvirtual_v2
DB_USERNAME=opents62_provadorvirtual_v2
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

## SMTP de produção

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.provadorvirtual.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@provadorvirtual.online
MAIL_FROM_NAME="Provador Virtual"
```

Senha fica apenas em `docs/credentials.local.md`, `.env` remoto ou secret seguro.

## Secrets cadastrados no GitHub Actions

- `FTP_PASSWORD`
- `FTP_SERVER`
- `FTP_USERNAME`
- `SSH_HOST`
- `SSH_PORT`
- `SSH_PRIVATE_KEY`
- `SSH_PRIVATE_KEY_B64`
- `SSH_USERNAME`
- `PRODUCTION_ENV`

`SSH_PRIVATE_KEY` e `SSH_PRIVATE_KEY_B64` foram cadastrados usando a chave local HostGator/opents62 já usada no projeto Marca Hora. `PRODUCTION_ENV` foi cadastrado com o `.env` mínimo de produção e uma `APP_KEY` própria para este projeto.

Opcional:

- `SSH_PASSPHRASE`, se a chave tiver senha.

## Histórico de bloqueio do Actions

Em 2026-05-23, o workflow foi disparado no GitHub, mas o job não iniciou porque a conta/repositório estava bloqueado por billing/spending limit do GitHub Actions.

No mesmo dia, o repositório foi alterado para público e a reexecucao do workflow terminou com sucesso.

Se o repositório voltar a ser privado, confirmar em GitHub:

- Billing & plans sem pendência de pagamento;
- spending limit do GitHub Actions suficiente;
- Actions habilitado para o repositório privado.

## Secrets futuros conforme sprint

IA:

- `AI_PROVIDER=local` e `AI_MODEL=local-table-parser-v1` podem ficar no `.env` para operar o parser local.
- `OPENAI_API_KEY` se usarmos OpenAI;
- ou `GEMINI_API_KEY` se mantivermos Gemini para OCR/legado.

BigShop:

- credenciais reais devem ficar no banco criptografado por loja. Para testes automatizados, podemos precisar de `BIGSHOP_TEST_STORE_ID` e `BIGSHOP_TEST_API_TOKEN`.
- `BIGSHOP_ACTIVATION_SECRET` deve ser cadastrado em `PRODUCTION_ENV` antes de liberar o um clique real.

Pagamentos:

- `PAGARME_SECRET_KEY`, `PAGARME_PUBLIC_KEY`, `PAGARME_WEBHOOK_SECRET`, `PAGARME_ENV`, `PAGARME_BASE_URL`, `PAGARME_CHECKOUT_SUCCESS_URL` e `PAGARME_CHECKOUT_CANCEL_URL`.
- Para checkout público na raiz, usar `PAGARME_CHECKOUT_SUCCESS_URL=https://provadorvirtual.online/checkout/sucesso` e `PAGARME_CHECKOUT_CANCEL_URL=https://provadorvirtual.online/checkout`.

Hardening:

- `PRIVACY_WIDGET_DATA_RETENTION_DAYS=30`;
- `OPERATIONAL_LOG_RETENTION_DAYS=180`;
- `CORS_ALLOWED_ORIGINS=http://127.0.0.1:5173,http://localhost:5173` para desenvolvimento local. Em produção, o widget usa validação dinâmica por domínio.

## Workflow criado

Arquivo:

- `.github/workflows/deploy.yml`

Comportamento:

- se `backend/` e `frontend/` ainda não existirem, o workflow encerra em sucesso informando que o app ainda não foi scaffoldado;
- pública Laravel em subpasta HostGator usando `.htaccess` raiz para encaminhar requisições para `public/`;
- no HostGator atual, endpoints limpos `api`, `sanctum` e `up` usam redirect 307 para `/provadorvirtual_v2/public/...`, preservando metodo/corpo em chamadas de API;
- o frontend autenticado em produção deve usar `VITE_API_BASE_URL=/provadorvirtual_v2/public/api/v1` para evitar perda de `Authorization` em clientes que não preservam header durante redirect;
- instala dependencias backend/frontend;
- valida backend em SQLite no CI;
- builda frontend com base path de produção;
- copia `frontend/dist` para `backend/public`;
- empacota Laravel sem `.env`;
- autentica por SSH;
- faz backup do release anterior;
- extrai novo release;
- aplica `PRODUCTION_ENV` se cadastrado;
- roda `optimize:clear`, `migrate --force`, `ProductionSeeder` se existir, `storage:link` e caches;
- pública a landing/app frontend também na raiz do domínio, preservando `/provadorvirtual_v1/` e `/provadorvirtual_v2/`;
- faz smoke público.

## Conferência obrigatória pós-push

Depois de todo push para `main`, conferir o workflow remoto antes de encerrar a sprint:

```powershell
gh run list --branch main --limit 5
gh run watch <run-id> --exit-status
```

Critério obrigatório:

- se o run passar, registrar o run/commit no log da sprint quando relevante;
- se o run falhar, não considerar a sprint concluída;
- corrigir a falha em sprint numerada, fazer novo commit/push e acompanhar o novo run até sucesso;
- quando o workflow incluir deploy, também confirmar os smokes de produção executados pelo Actions ou rodar `scripts/validate-production.ps1`.

## Cron no cPanel

Cron principal recomendado: executar o scheduler do Laravel a cada minuto. Ele já dispara o monitor de pagamentos a cada 5 minutos, o dispatcher de e-mails transacionais a cada 10 minutos e as rotinas de privacidade nos horarios programados.

Tempo:

```cron
* * * * *
```

Comando com log:

```bash
cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan schedule:run >> /home1/opents62/provadorvirtual.online/provadorvirtual_v2/storage/logs/cron-schedule.log 2>&1
```

Se o cPanel não aceitar scheduler a cada minuto, cadastrar temporariamente o monitor direto a cada 5 minutos:

```cron
*/5 * * * *
```

```bash
cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan pv:payments-sync --limit=50 >> /home1/opents62/provadorvirtual.online/provadorvirtual_v2/storage/logs/cron-payments-sync.log 2>&1
```

Validação manual do monitor:

```bash
cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan pv:payments-sync --limit=10
```

Validação manual dos e-mails transacionais:

```bash
cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan pv:emails-dispatch --limit=10
```

Se `/usr/local/bin/php` não existir no HostGator, trocar pelo path exibido no cPanel ou por `php`.

## `.env` de produção mínimo

```env
APP_NAME="Provador Virtual"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://provadorvirtual.online/provadorvirtual_v2
FRONTEND_URL=https://provadorvirtual.online/provadorvirtual_v2

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=opents62_provadorvirtual_v2
DB_USERNAME=opents62_provadorvirtual_v2
DB_PASSWORD=<senha>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=mail.provadorvirtual.com
MAIL_PORT=587
MAIL_USERNAME=noreply@provadorvirtual.online
MAIL_PASSWORD=<senha>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@provadorvirtual.online
MAIL_FROM_NAME="Provador Virtual"

PRIVACY_WIDGET_DATA_RETENTION_DAYS=30
OPERATIONAL_LOG_RETENTION_DAYS=180
CORS_ALLOWED_ORIGINS=http://127.0.0.1:5173,http://localhost:5173

PAGARME_ENV=production
PAGARME_API_VERSION=v5
PAGARME_BASE_URL=https://api.pagar.me/core/v5
PAGARME_SECRET_KEY=<secret>
PAGARME_PUBLIC_KEY=<public>
PAGARME_WEBHOOK_SECRET=<webhook>
PAGARME_CHECKOUT_SUCCESS_URL=https://provadorvirtual.online/checkout/sucesso
PAGARME_CHECKOUT_CANCEL_URL=https://provadorvirtual.online/checkout
```

## Primeira publicação

1. Criar banco e usuário no cPanel, se ainda não existirem.
2. Fazer push para `main`.
3. Acompanhar Actions.
4. Validar:
   - `/`;
   - `/checkout`;
   - `/provadorvirtual_v2/`;
   - `/provadorvirtual_v2/login`;
   - `/provadorvirtual_v2/produto-teste`;
   - `/provadorvirtual_v2/api/v1/health` com `curl -L`;
   - `/provadorvirtual_v2/api/v1/ops/status` com `curl -L`;
   - `/provadorvirtual_v2/up`.

## Validação de produção

Rodar após deploy:

```powershell
.\scripts\validate-production.ps1
```

O script usa a URL pública, valida páginas, APIs, recomendação, CORS e o endpoint
protegido de go-live com o usuário demo.

## Rollback

O workflow gera backup em:

- `/home1/opents62/deploy_backups/provadorvirtual_v2`

Sprint 11 validou a criação de backup no run `26332960822` com o arquivo `provadorvirtual-v2-backup-20260523-094207-ac1025f2a2469b9876d93764652ce87acd0e7174.tar.gz`.

Sprint 12 validou a criação de backup no run `26333226813` com o arquivo `provadorvirtual-v2-backup-20260523-095421-e657a75c92163ab29eae19a8cec5b5d5d1b6cd5c.tar.gz`.

Rollback manual:

1. escolher backup anterior;
2. fazer backup do estado atual;
3. extrair backup escolhido em `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`;
4. preservar `.env` e `storage`;
5. rodar `php artisan optimize:clear`;
6. validar smokes.

## Incidentes

Registrar em `docs/execution_log.md` e, quando houver causa operacional, atualizar `docs/incident_runbook.md`.
