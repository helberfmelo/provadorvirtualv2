# Hardening, LGPD e Observabilidade

Atualizado em: 2026-05-23

## Objetivo

Preparar o Provador Virtual para uso publico com controles basicos de seguranca,
privacidade e diagnostico operacional.

## Status Sprint 11

Implementado:

- paginas publicas `/privacidade` e `/termos`;
- rota publica `GET /api/v1/ops/status`;
- rate limit nas rotas publicas de login, widget e ativacao BigShop;
- CORS dinamico por dominio autorizado em `widget_installs.allowed_domains`;
- mascaramento recursivo de campos sensiveis em `audit_logs.metadata`;
- auditoria de login e logout;
- comandos de anonimizacao e limpeza de logs;
- smoke de deploy incluindo status operacional.

## CORS do widget

Rotas protegidas por origem:

- `POST /api/v1/public/recommendations/config-check`
- `POST /api/v1/public/recommendations`
- `POST /api/v1/public/recommendations/{id}/feedback`

Regras:

- requisicao sem `Origin` e permitida para smokes e chamadas server-to-server;
- requisicao com `Origin` precisa corresponder a dominio ativo do lojista;
- subdominios sao aceitos quando o dominio raiz estiver liberado;
- `OPTIONS` responde preflight para o widget, mas o `POST` real continua validando a origem por lojista.

## Rate limits

Limites iniciais:

- `POST /api/v1/auth/login`: 10 requisicoes por minuto;
- rotas publicas de recomendacao: 60 requisicoes por minuto;
- feedback do widget: 120 requisicoes por minuto;
- ativacao BigShop: 20 requisicoes por minuto;
- status operacional: 60 requisicoes por minuto.

## Retencao e anonimizacao

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
`integration_events` antigos. Analytics de recomendacao permanece preservado.

## Variaveis

```env
PRIVACY_WIDGET_DATA_RETENTION_DAYS=30
OPERATIONAL_LOG_RETENTION_DAYS=180
CORS_ALLOWED_ORIGINS=http://127.0.0.1:5173,http://localhost:5173
LOG_DAILY_DAYS=14
```

`CORS_ALLOWED_ORIGINS` atende o painel em desenvolvimento local. O widget publico
usa a validacao dinamica por dominio liberado na loja.

## Status operacional

`GET /api/v1/ops/status` retorna:

- `status`;
- check de banco;
- check de escrita em storage;
- driver de fila;
- ambiente;
- timestamp.

A rota nao expoe credenciais, paths internos ou detalhes de erro.

## Pendencias

- Criar rotina agendada no servidor para executar comandos de privacidade.
- Definir politica comercial final de retencao nos contratos.
- Cadastrar usuario `admin` real para operacao SaaS.
