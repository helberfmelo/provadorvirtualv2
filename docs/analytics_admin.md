# Analytics, SaaS Admin e Auditoria

Atualizado em: 2026-05-29

## Objetivo

Dar visibilidade operacional sem transformar a primeira versao em BI complexo.

## Status Sprint 10

Implementado:

- tela protegida `/app/analytics`;
- tela protegida `/saas` para usuários `admin` ou `support`;
- rota protegida `GET /api/v1/analytics/recommendations`;
- rota protegida `GET /api/v1/audit-logs`;
- rotas protegidas `GET /api/v1/saas/overview` e `GET /api/v1/saas/merchants`;
- tabela `audit_logs`;
- logs de auditoria para tabelas de medidas, widget e integrações.

## Analytics do lojista

Resumo entregue por `/analytics/recommendations`:

- recomendações totais;
- recomendações hoje;
- recomendações em 7 dias;
- confiança média;
- total de feedbacks;
- taxa de feedback positivo;
- produtos sem tabela de medidas;
- alertas do widget e integrações.
- perfis de comprador ativos/reconhecidos;
- qualidade média de perfil;
- eventos de aprendizado por status;
- outliers bloqueados;
- sinais comerciais de carrinho, compra, devolucao e troca.
- KPIs de compra/devolucao/troca, taxa de retorno e tabelas com revisão sugerida.

Também retorna series diarias, distribuicao por tamanho, produtos com recomendação, produtos sem tabela, status de aprendizado, sinais comerciais, insights por tabela de medidas e outliers recentes para revisão.

Status Sprint 36: `/app/analytics` mostra os novos cards de perfis, aprendizado e outliers. Sinais `blocked_outlier` são armazenados para investigacao e não entram direto em refinamento automático.

Status Sprint 115: `/app/analytics` passa a mostrar sugestões por tabela de medidas com base em pedidos, devoluções, trocas e feedback. A referência de pedido é guardada apenas como hash, e os insights indicam revisão humana em vez de alterar tabelas automaticamente.

Status Sprint 148: `/app/analytics` também passa a mostrar o relatório `Uso do widget`, alimentado por `GET /api/v1/analytics/widget-usage`.

Resumo entregue por `/analytics/widget-usage`:

- impressões dos botões do provador;
- aberturas do provador;
- consultas à tabela de medidas;
- recomendações geradas;
- tamanhos aplicados;
- feedbacks enviados;
- conversões associadas quando existir compra ligada à recomendação;
- taxa de uso, taxa de consulta de tabela, taxa de aplicação do tamanho e taxa de conversão;
- funil do widget;
- distribuição por desktop, mobile e tablet;
- evolução diária do uso.

Filtros entregues:

- período (`today`, `7d`, `30d`, `90d`, `custom`);
- produto;
- tabela de medidas;
- marca;
- categoria;
- plataforma;
- dispositivo.

Regra operacional Sprint 148: o widget envia `button_impression`, `virtual_try_on_open`, `measurement_table_open`, `recommendation_generated`, `size_selected` e `feedback_submitted` para `POST /api/v1/public/widget-events`. A contagem é idempotente por `client_event_id` dentro do merchant para evitar duplicidade em re-render, reload ou repetição de chamada.

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
- integrações configuradas;
- recomendações em 7 dias;
- usos de IA em 7 dias;
- falhas recentes de importacao/integração.

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

## Pendências

- Criar usuário admin real em produção quando for operar suporte.
- Adicionar filtros por periodo quando houver maior volume.
