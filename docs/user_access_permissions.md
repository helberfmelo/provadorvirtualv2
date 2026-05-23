# Usuarios e Permissoes

Atualizado em: 2026-05-23

## Objetivo

Permitir que o SaaS e cada empresa cliente gerenciem usuarios com acesso por modulo, mantendo o login simples por e-mail ou CPF e o contexto da empresa por codigo/CNPJ.

## Status Sprint 30

Implementado:

- CRUD de usuarios do portal da empresa em `/app/usuarios`;
- CRUD de usuarios do portal SaaS em `/saas/usuarios`;
- listagem, botao de novo, edicao e ativar/desativar nos CRUDs de usuarios;
- edicao e ativar/desativar tambem no CRUD de empresas do SaaS;
- permissao por modulo/menu com `visualizar` e `editar`;
- ao marcar `editar`, `visualizar` fica ativo automaticamente;
- `users.status` para ativacao/desativacao global pelo SaaS;
- `merchant_user.status` para ativacao/desativacao por empresa;
- `users.permissions` para permissoes SaaS;
- `merchant_user.permissions` para permissoes do portal da empresa;
- menu Vue passa a respeitar permissoes de visualizacao;
- backend bloqueia CRUD de usuarios quando o usuario nao tem permissao `users.edit`.

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
- `saas_emails`
- `saas_audit`

## Regras

- `admin` e `support` podem acessar o SaaS conforme permissoes SaaS.
- `owner` da empresa tem acesso total no portal da empresa.
- Usuarios `merchant` sem permissao `users.edit` podem visualizar a lista se tiverem `users.view`, mas nao podem criar, editar, ativar ou desativar usuarios.
- Usuario globalmente inativo nao consegue fazer login.
- Usuario desativado em uma empresa nao consegue entrar naquela empresa, mesmo que exista em outra.
- O sistema impede o usuario de desativar o proprio acesso no CRUD correspondente.

## Endpoints

SaaS:

- `GET /api/v1/saas/users`
- `POST /api/v1/saas/users`
- `PATCH /api/v1/saas/users/{user}`

Portal da empresa:

- `GET /api/v1/merchant/users`
- `POST /api/v1/merchant/users`
- `PATCH /api/v1/merchant/users/{user}`

## Pendencias

- Completar enforcement de permissao nos demais modulos alem do CRUD de usuarios.
- Criar seletor pos-login para usuarios com muitas empresas.
- Registrar auditoria detalhada por alteracao de permissao.
