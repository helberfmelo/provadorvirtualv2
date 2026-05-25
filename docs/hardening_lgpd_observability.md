# Hardening, LGPD e Observabilidade

Atualizado em: 2026-05-25

## Objetivo

Preparar o Provador Virtual para uso público com controles basicos de segurança,
privacidade e diagnostico operacional.

## Status Sprint 11

Implementado:

- páginas públicas `/privacidade` e `/termos`;
- rota pública `GET /api/v1/ops/status`;
- rate limit nas rotas públicas de login, widget e ativação BigShop;
- CORS dinamico por domínio autorizado em `widget_installs.allowed_domains`;
- mascaramento recursivo de campos sensíveis em `audit_logs.metadata`;
- auditoria de login e logout;
- comandos de anonimização e limpeza de logs;
- smoke de deploy incluindo status operacional.

## Status Sprint 88

Implementado:

- `/termos` e `/privacidade` versionados em `2026-05-25`, com escopo do SaaS, checkout, recorrência, pagamentos, integrações, IA, cookies/localStorage e LGPD;
- checkout público exige aceite legal e grava prova técnica em `checkout_acceptances`;
- prova técnica inclui sessão de checkout, usuário, empresa, e-mail, documento, versões legais, data/hora, IP, user-agent e metadados do plano/meio de pagamento;
- aviso inferior de cookies/localStorage é exibido no site e sistema até o usuário clicar em `OK`;
- aceite do aviso é salvo em cookie e localStorage para reduzir repetição da mensagem.

## CORS do widget

Rotas protegidas por origem:

- `POST /api/v1/public/recommendations/config-check`
- `POST /api/v1/public/recommendations`
- `POST /api/v1/public/recommendations/{id}/feedback`

Regras:

- requisicao sem `Origin` e permitida para smokes e chamadas server-to-server;
- requisicao com `Origin` precisa corresponder a domínio ativo do lojista;
- subdominios são aceitos quando o domínio raiz estiver liberado;
- `OPTIONS` responde preflight para o widget, mas o `POST` real continua validando a origem por lojista.

## Rate limits

Limites iniciais:

- `POST /api/v1/auth/login`: 10 requisições por minuto;
- rotas públicas de recomendação: 60 requisições por minuto;
- feedback do widget: 120 requisições por minuto;
- ativação BigShop: 20 requisições por minuto;
- status operacional: 60 requisições por minuto.

## Retenção e anonimização

Comando para anonimizar dados antigos do widget:

```powershell
php artisan pv:privacy-anonymize --days=30
php artisan pv:privacy-anonymize --days=30 --dry-run
```

O comando remove dados corporais/identificadores tecnicos antigos de:

- `recommendation_sessions.shopper_profile`, `ip_hash`, `user_agent_hash`;
- `recommendation_logs.input_measurements`, `score_breakdown`;
- `recommendation_feedbacks.comment`.

Comando para limpar logs operacionais antigos:

```powershell
php artisan pv:privacy-prune --days=180
php artisan pv:privacy-prune --days=180 --dry-run
```

O comando remove `audit_logs` e `ai_usage_logs` antigos e aplica soft delete em
`integration_events` antigos. Analytics de recomendação permanece preservado.

## Variáveis

```env
PRIVACY_WIDGET_DATA_RETENTION_DAYS=30
OPERATIONAL_LOG_RETENTION_DAYS=180
CORS_ALLOWED_ORIGINS=http://127.0.0.1:5173,http://localhost:5173
LOG_DAILY_DAYS=14
```

`CORS_ALLOWED_ORIGINS` atende o painel em desenvolvimento local. O widget público
usa a validação dinâmica por domínio liberado na loja.

## Status operacional

`GET /api/v1/ops/status` retorna:

- `status`;
- check de banco;
- check de escrita em storage;
- driver de fila;
- ambiente;
- timestamp.

A rota não expoe credenciais, paths internos ou detalhes de erro.

## Pendências

- Criar rotina agendada no servidor para executar comandos de privacidade.
- Definir política comercial final de retenção nos contratos.
- Cadastrar usuário `admin` real para operação SaaS.
