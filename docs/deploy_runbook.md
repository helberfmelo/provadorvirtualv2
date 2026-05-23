# Deploy e Operacao

Atualizado em: 2026-05-23

## Estrategia

Deploy oficial por GitHub Actions via SSH, seguindo o padrao BigShop HelpDesk/Marca Hora.

Publicacao inicial:

- URL: `https://provadorvirtual.online/provadorvirtual_v2`
- Base path: `/provadorvirtual_v2/`
- Pasta remota esperada: `/home1/opents62/public_html/provadorvirtual_v2`
- Backup remoto: `/home1/opents62/deploy_backups/provadorvirtual_v2`

Motivo: preservar `https://provadorvirtual.online/provadorvirtual_v1/` ate o cutover.

## Banco de producao

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

## SMTP de producao

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.provadorvirtual.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@provadorvirtual.online
MAIL_FROM_NAME="Provador Virtual"
```

Senha fica apenas em `docs/credentials.local.md`, `.env` remoto ou secret seguro.

## Secrets ja informados pelo usuario como cadastrados

- `FTP_PASSWORD`
- `FTP_SERVER`
- `FTP_USERNAME`
- `SSH_HOST`
- `SSH_PORT`
- `SSH_USERNAME`

## Secrets que ainda faltam para deploy SSH

Obrigatorio cadastrar um destes:

- `SSH_PRIVATE_KEY`
- ou `SSH_PRIVATE_KEY_B64`

Opcional:

- `SSH_PASSPHRASE`, se a chave tiver senha.

Recomendado para primeiro deploy sem criar `.env` manualmente no servidor:

- `PRODUCTION_ENV`, multiline com o conteudo completo do `.env` de producao.

Alternativa ao `PRODUCTION_ENV`: criar manualmente o arquivo `.env` em `/home1/opents62/public_html/provadorvirtual_v2` antes do primeiro deploy. O workflow preserva `.env` remoto quando o secret nao existir.

## Secrets futuros conforme sprint

IA:

- `OPENAI_API_KEY` se usarmos OpenAI;
- ou `GEMINI_API_KEY` se mantivermos Gemini para OCR/legado.

BigShop:

- credenciais reais devem ficar no banco criptografado por loja. Para testes automatizados, podemos precisar de `BIGSHOP_TEST_STORE_ID` e `BIGSHOP_TEST_API_TOKEN`.

Pagamentos:

- `PAGARME_SECRET_KEY`, `PAGARME_PUBLIC_KEY`, `PAGARME_WEBHOOK_SECRET`; ou credenciais Mercado Pago, se essa escolha for retomada.

## Workflow criado

Arquivo:

- `.github/workflows/deploy.yml`

Comportamento:

- se `backend/` e `frontend/` ainda nao existirem, o workflow encerra em sucesso informando que o app ainda nao foi scaffoldado;
- instala dependencias backend/frontend;
- valida backend em SQLite no CI;
- builda frontend com base path de producao;
- copia `frontend/dist` para `backend/public`;
- empacota Laravel sem `.env`;
- autentica por SSH;
- faz backup do release anterior;
- extrai novo release;
- aplica `PRODUCTION_ENV` se cadastrado;
- roda `optimize:clear`, `migrate --force`, `ProductionSeeder` se existir, `storage:link` e caches;
- faz smoke publico.

## `.env` de producao minimo

```env
APP_NAME="Provador Virtual"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://provadorvirtual.online/provadorvirtual_v2
APP_BASE_PATH=/provadorvirtual_v2/
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
```

## Primeira publicacao

1. Confirmar path remoto.
2. Cadastrar `SSH_PRIVATE_KEY` ou `SSH_PRIVATE_KEY_B64`.
3. Criar banco e usuario no cPanel.
4. Criar `.env` remoto ou cadastrar `PRODUCTION_ENV`.
5. Fazer push para `main`.
6. Acompanhar Actions.
7. Validar:
   - `/provadorvirtual_v2/`;
   - `/provadorvirtual_v2/login`;
   - `/provadorvirtual_v2/produto-teste`;
   - `/provadorvirtual_v2/up`.

## Rollback

O workflow gera backup em:

- `/home1/opents62/deploy_backups/provadorvirtual_v2`

Rollback manual:

1. escolher backup anterior;
2. fazer backup do estado atual;
3. extrair backup escolhido em `/home1/opents62/public_html/provadorvirtual_v2`;
4. preservar `.env` e `storage`;
5. rodar `php artisan optimize:clear`;
6. validar smokes.

## Incidentes

Registrar em `docs/execution_log.md` e, quando houver causa operacional, atualizar `docs/incident_runbook.md`.
