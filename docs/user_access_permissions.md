# Usuários e Permissões

Atualizado em: 2026-05-29

## Objetivo

Permitir que o SaaS e cada empresa cliente gerenciem usuários com acesso por módulo, mantendo o login simples por e-mail ou CPF e o contexto da empresa por código/CNPJ.

## Status Sprint 33

Implementado:

- CRUD de usuários do portal da empresa em `/app/usuarios`;
- CRUD de usuários internos do portal SaaS em `/saas/usuarios`;
- CRUD SaaS separado para usuários das empresas clientes em `/saas/usuarios-empresas`;
- listagem, botão de novo, edição e ativar/desativar nos CRUDs de usuários;
- edição e ativar/desativar também no CRUD de empresas do SaaS;
- permissão por módulo/menu com `visualizar` e `editar`;
- ao marcar `editar`, `visualizar` fica ativo automaticamente;
- `users.status` para ativação/desativacao global pelo SaaS;
- `merchant_user.status` para ativação/desativacao por empresa;
- `users.permissions` para permissões SaaS;
- `merchant_user.permissions` para permissões do portal da empresa;
- menu Vue passa a respeitar permissões de visualizacao;
- backend bloqueia CRUD de usuários quando o usuário não tem permissão `users.edit`;
- middleware `portal.permission` protege as rotas dos módulos do portal da empresa e do SaaS;
- usuários com mais de uma empresa recebem uma lista de empresas no login e escolhem o contexto;
- painel permite trocar a empresa ativa pelo seletor do topo sem logout;
- frontend persiste a última empresa ativa em `pv_active_company_id` e carrega `/me` antes de renderizar telas internas, mantendo o seletor correto após refresh;
- token Sanctum carrega `merchant:{id}` e `company:{id}`;
- queries de produtos, tabelas, widget, integrações, importacoes, analytics, go-live e auditoria respeitam a empresa ativa;
- `audit_logs` registra `merchant_company_id`, `module` e `action` para eventos novos e negacoes de permissão.

## Módulos do portal da empresa

- `dashboard`
- `products`
- `measurement_tables`
- `imports`
- `ai_assistant`
- `analytics`
- `widget`
- `integrations`
- `go_live`
- `users`

## Módulos do SaaS

- `saas_dashboard`
- `saas_companies`
- `saas_users`
- `saas_company_users`
- `saas_emails`
- `saas_audit`

## Regras

- `admin` e `support` podem acessar o SaaS conforme permissões SaaS.
- `saas_users` gerencia somente equipe interna do SaaS; `saas_company_users` gerencia usuários vinculados a empresas clientes.
- `owner` da empresa tem acesso total no portal da empresa.
- Usuários `merchant` sem permissão `users.edit` podem visualizar a lista se tiverem `users.view`, mas não podem criar, editar, ativar ou desativar usuários.
- Usuário globalmente inativo não consegue fazer login.
- Usuário desativado em uma empresa não consegue entrar naquela empresa, mesmo que exista em outra.
- O sistema impede o usuário de desativar o próprio acesso no CRUD correspondente.
- Se o usuário estiver vinculado a varias empresas e tentar entrar sem código/CNPJ, a API responde `409` com `company_options`.
- Negacoes de acesso geram evento `permission.denied` com escopo, módulo e ação.

## Endpoints

SaaS:

- `GET /api/v1/saas/users`
- `POST /api/v1/saas/users`
- `PATCH /api/v1/saas/users/{user}`
- `GET /api/v1/saas/company-users`
- `POST /api/v1/saas/company-users`
- `PATCH /api/v1/saas/company-users/{user}`

Portal da empresa:

- `GET /api/v1/merchant/users`
- `POST /api/v1/merchant/users`
- `PATCH /api/v1/merchant/users/{user}`
- `POST /api/v1/auth/select-company`

## Pendências

- Registrar auditoria detalhada por alteracao de permissão.
