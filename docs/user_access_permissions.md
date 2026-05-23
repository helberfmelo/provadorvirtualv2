# Usuarios e Permissoes

Atualizado em: 2026-05-23

## Objetivo

Permitir que o SaaS e cada empresa cliente gerenciem usuarios com acesso por modulo, mantendo o login simples por e-mail ou CPF e o contexto da empresa por codigo/CNPJ.

## Status Sprint 33

Implementado:

- CRUD de usuarios do portal da empresa em `/app/usuarios`;
- CRUD de usuarios internos do portal SaaS em `/saas/usuarios`;
- CRUD SaaS separado para usuarios das empresas clientes em `/saas/usuarios-empresas`;
- listagem, botao de novo, edicao e ativar/desativar nos CRUDs de usuarios;
- edicao e ativar/desativar tambem no CRUD de empresas do SaaS;
- permissao por modulo/menu com `visualizar` e `editar`;
- ao marcar `editar`, `visualizar` fica ativo automaticamente;
- `users.status` para ativacao/desativacao global pelo SaaS;
- `merchant_user.status` para ativacao/desativacao por empresa;
- `users.permissions` para permissoes SaaS;
- `merchant_user.permissions` para permissoes do portal da empresa;
- menu Vue passa a respeitar permissoes de visualizacao;
- backend bloqueia CRUD de usuarios quando o usuario nao tem permissao `users.edit`;
- middleware `portal.permission` protege as rotas dos modulos do portal da empresa e do SaaS;
- usuarios com mais de uma empresa recebem uma lista de empresas no login e escolhem o contexto;
- painel permite trocar a empresa ativa pelo seletor do topo sem logout;
- token Sanctum carrega `merchant:{id}` e `company:{id}`;
- queries de produtos, tabelas, widget, integracoes, importacoes, analytics, go-live e auditoria respeitam a empresa ativa;
- `audit_logs` registra `merchant_company_id`, `module` e `action` para eventos novos e negacoes de permissao.

## Modulos do portal da empresa

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

## Modulos do SaaS

- `saas_dashboard`
- `saas_companies`
- `saas_users`
- `saas_company_users`
- `saas_emails`
- `saas_audit`

## Regras

- `admin` e `support` podem acessar o SaaS conforme permissoes SaaS.
- `saas_users` gerencia somente equipe interna do SaaS; `saas_company_users` gerencia usuarios vinculados a empresas clientes.
- `owner` da empresa tem acesso total no portal da empresa.
- Usuarios `merchant` sem permissao `users.edit` podem visualizar a lista se tiverem `users.view`, mas nao podem criar, editar, ativar ou desativar usuarios.
- Usuario globalmente inativo nao consegue fazer login.
- Usuario desativado em uma empresa nao consegue entrar naquela empresa, mesmo que exista em outra.
- O sistema impede o usuario de desativar o proprio acesso no CRUD correspondente.
- Se o usuario estiver vinculado a varias empresas e tentar entrar sem codigo/CNPJ, a API responde `409` com `company_options`.
- Negacoes de acesso geram evento `permission.denied` com escopo, modulo e acao.

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

## Pendencias

- Registrar auditoria detalhada por alteracao de permissao.
