# Analytics, SaaS Admin e Auditoria

Atualizado em: 2026-05-23

## Objetivo

Dar visibilidade operacional sem transformar a primeira versao em BI complexo.

## Status Sprint 10

Implementado:

- tela protegida `/app/analytics`;
- tela protegida `/saas` para usuarios `admin` ou `support`;
- rota protegida `GET /api/v1/analytics/recommendations`;
- rota protegida `GET /api/v1/audit-logs`;
- rotas protegidas `GET /api/v1/saas/overview` e `GET /api/v1/saas/merchants`;
- tabela `audit_logs`;
- logs de auditoria para tabelas de medidas, widget e integracoes.

## Analytics do lojista

Resumo entregue por `/analytics/recommendations`:

- recomendacoes totais;
- recomendacoes hoje;
- recomendacoes em 7 dias;
- confianca media;
- total de feedbacks;
- taxa de feedback positivo;
- produtos sem tabela de medidas;
- alertas do widget e integracoes.
- perfis de comprador ativos/reconhecidos;
- qualidade media de perfil;
- eventos de aprendizado por status;
- outliers bloqueados;
- sinais comerciais de carrinho, compra, devolucao e troca.

Tambem retorna series diarias, distribuicao por tamanho, produtos com recomendacao, produtos sem tabela, status de aprendizado, sinais comerciais e outliers recentes para revisao.

Status Sprint 36: `/app/analytics` mostra os novos cards de perfis, aprendizado e outliers. Sinais `blocked_outlier` sao armazenados para investigacao e nao entram direto em refinamento automatico.

## SaaS Admin

Rotas admin exigem `users.role` em:

- `admin`;
- `support`.

Sem esse papel, a API retorna `403`.

Resumo entregue:

- lojistas;
- lojas/empresas;
- produtos;
- widgets ativos;
- integracoes configuradas;
- recomendacoes em 7 dias;
- usos de IA em 7 dias;
- falhas recentes de importacao/integracao.

## Auditoria

Tabela `audit_logs` guarda:

- `merchant_id`;
- `user_id`;
- evento;
- categoria;
- severidade;
- recurso auditado;
- hash de IP/user-agent;
- metadata sem tokens, secrets ou senhas.

Eventos iniciais:

- `measurement_table.created`;
- `measurement_table.updated`;
- `measurement_table.deleted`;
- `widget_install.updated`;
- `integration.updated`.
- `auth.login`;
- `auth.logout`.

## Pendencias

- Criar usuario admin real em producao quando for operar suporte.
- Adicionar filtros por periodo quando houver maior volume.
