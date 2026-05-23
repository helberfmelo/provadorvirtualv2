# Diretrizes de UX dos Portais

Atualizado em: 2026-05-23

Este documento registra a correcao solicitada para evitar telas emboladas no portal SaaS e no portal da empresa.

## Regra principal de CRUD

Todo CRUD novo ou existente deve seguir o padrao list-first:

- a rota base do CRUD mostra somente a listagem ocupando a tela;
- a listagem tem botao `Novo`, botao de editar e acao de ativar/desativar quando aplicavel;
- cadastro abre em rota propria `/novo`;
- edicao abre em rota propria `/:id/editar`;
- formularios longos nao devem ficar ao lado da tabela principal;
- cards de resumo podem existir apenas em visoes gerais, nao dentro da listagem operacional do CRUD.

## Separacao de portais

O portal SaaS e o portal da empresa nao devem misturar menus:

- SaaS: visao geral, empresas, usuarios SaaS e e-mails transacionais;
- Empresa: painel, catalogo, configuracao do widget, integracoes, importacoes, assistente, analytics, go-live e usuarios da empresa;
- links entre contextos devem ser explicitos e secundarios;
- no mobile, o menu autenticado deve abrir em drawer.

## Rotas SaaS revisadas

- `/saas`: visao geral operacional;
- `/saas/empresas`: listagem de empresas;
- `/saas/empresas/nova`: cadastro de empresa;
- `/saas/empresas/:id/editar`: edicao de empresa;
- `/saas/usuarios`: listagem de usuarios SaaS;
- `/saas/usuarios/novo`: cadastro de usuario SaaS;
- `/saas/usuarios/:id/editar`: edicao de usuario SaaS;
- `/saas/emails`: listagem de e-mails transacionais e historico;
- `/saas/emails/configuracoes`: credenciais SMTP;
- `/saas/emails/novo`: cadastro de template;
- `/saas/emails/:id/editar`: edicao de template.

## Rotas da empresa revisadas

- `/app/produtos`: listagem de produtos;
- `/app/produtos/novo`: cadastro de produto;
- `/app/produtos/:id/editar`: edicao de produto;
- `/app/tabelas-de-medidas`: listagem de tabelas;
- `/app/tabelas-de-medidas/nova`: cadastro de tabela;
- `/app/tabelas-de-medidas/:id/editar`: edicao de tabela;
- `/app/usuarios`: listagem de usuarios da empresa;
- `/app/usuarios/novo`: cadastro de usuario da empresa;
- `/app/usuarios/:id/editar`: edicao de usuario da empresa.

## Checklist antes de finalizar sprint

- `npm run build`;
- `git diff --check`;
- validar rotas publicas e autenticadas pelo `scripts/validate-production.ps1` apos deploy;
- confirmar que nenhuma rota de listagem principal voltou a exibir formulario longo na mesma tela.
