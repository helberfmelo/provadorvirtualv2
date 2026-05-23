# Incident Runbook

Atualizado em: 2026-05-23

## Classificacao

P1:

- site/app fora;
- widget fora em producao;
- recomendacao retornando tamanho incorreto de forma sistemica;
- vazamento ou suspeita de credencial.

P2:

- deploy falhou;
- BigShop sync falhou;
- SMTP falhou;
- pagina de produto teste fora;
- lentidao relevante.

P3:

- erro visual;
- guia incompleto;
- item isolado de produto/tabela.

## Primeiros passos

1. Identificar URL/ambiente.
2. Verificar GitHub Actions.
3. Testar `/up`, `/api/v1/health`, `/produto-teste`.
4. Testar `/api/v1/ops/status`.
5. Consultar logs Laravel.
6. Confirmar se houve deploy recente.
7. Registrar em `docs/execution_log.md`.

## Widget nao aparece

Verificar:

- script carregou;
- `data-merchant-id`, `data-store-id`, `data-sku`/`data-variant-id`;
- config-check retornou configurado;
- CORS;
- console do navegador;
- produto tem tabela vinculada.

## Recomendacao falhou

Verificar:

- tabela de medidas valida;
- variacao correta;
- medidas recebidas;
- logs de scoring;
- status do lojista;
- rate limit.

## Suspeita de dado pessoal antigo

1. Rodar `php artisan pv:privacy-anonymize --dry-run`.
2. Validar contagens retornadas.
3. Rodar `php artisan pv:privacy-anonymize`.
4. Para logs operacionais antigos, rodar `php artisan pv:privacy-prune --dry-run` e depois sem `--dry-run`.
5. Registrar a acao em `docs/execution_log.md`.

## Deploy falhou

Verificar:

- `SSH_PRIVATE_KEY`/`SSH_PRIVATE_KEY_B64`;
- `SSH_HOST`, `SSH_PORT`, `SSH_USERNAME`;
- path remoto;
- `.env` remoto ou `PRODUCTION_ENV`;
- permissao de `storage` e `bootstrap/cache`;
- compatibilidade PHP.

## Banco falhou

Verificar:

- host `localhost` em producao;
- banco/usuario/senha;
- `DB_COLLATION=utf8mb4_unicode_ci`;
- migration destrutiva;
- backup recente.

## Rollback

1. Parar alteracoes.
2. Identificar backup anterior.
3. Preservar `.env` e `storage`.
4. Restaurar artefato.
5. Rodar `optimize:clear`.
6. Validar smokes.
7. Registrar causa e prevencao.
