# Diretrizes de UX dos Portais

Atualizado em: 2026-05-23

Este documento registra a correção solicitada para evitar telas emboladas no portal SaaS e no portal da empresa.

## Regra principal de CRUD

Todo CRUD novo ou existente deve seguir o padrão list-first:

- a rota base do CRUD mostra somente a listagem ocupando a tela;
- a listagem tem botão `Novo`, botão de editar e ação de ativar/desativar quando aplicável;
- cadastro abre em rota própria `/novo`;
- edição abre em rota própria `/:id/editar`;
- formulários longos não devem ficar ao lado da tabela principal;
- cards de resumo podem existir apenas em visões gerais, não dentro da listagem operacional do CRUD.

## Separação de portais

O portal SaaS e o portal da empresa não devem misturar menus:

- SaaS: visão geral, empresas, usuários SaaS, usuários das empresas e e-mails transacionais;
- Empresa: painel, catálogo, configuração do widget, integrações, importações, assistente, analytics, go-live e usuários da empresa;
- links entre contextos devem ser explícitos e secundários;
- no mobile, o menu autenticado deve abrir em drawer.

## Rotas SaaS revisadas

- `/saas`: visão geral operacional;
- `/saas/empresas`: listagem de empresas;
- `/saas/empresas/nova`: cadastro de empresa;
- `/saas/empresas/:id/editar`: edição de empresa;
- `/saas/usuarios`: listagem de usuários SaaS;
- `/saas/usuarios/novo`: cadastro de usuário SaaS;
- `/saas/usuarios/:id/editar`: edição de usuário SaaS;
- `/saas/usuarios-empresas`: listagem de usuários das empresas clientes;
- `/saas/usuarios-empresas/novo`: cadastro de usuário de empresa cliente;
- `/saas/usuarios-empresas/:id/editar`: edição de usuário de empresa cliente;
- `/saas/emails`: listagem de e-mails transacionais e histórico;
- `/saas/emails/configuracoes`: credenciais SMTP;
- `/saas/emails/novo`: cadastro de template;
- `/saas/emails/:id/editar`: edição de template.

## Rotas da empresa revisadas

- `/app/produtos`: listagem de produtos;
- `/app/produtos/novo`: cadastro de produto;
- `/app/produtos/:id/editar`: edição de produto;
- `/app/tabelas-de-medidas`: listagem de tabelas;
- `/app/tabelas-de-medidas/nova`: cadastro de tabela;
- `/app/tabelas-de-medidas/:id/editar`: edição de tabela;
- `/app/usuarios`: listagem de usuários da empresa;
- `/app/usuarios/novo`: cadastro de usuário da empresa;
- `/app/usuarios/:id/editar`: edição de usuário da empresa.

## Checklist antes de finalizar sprint

- `npm run build`;
- `git diff --check`;
- validar rotas públicas e autenticadas pelo `scripts/validate-production.ps1` após deploy;
- confirmar que nenhuma rota de listagem principal voltou a exibir formulário longo na mesma tela;
- revisar textos visíveis em PT-BR com acentos, til e cedilha corretos antes de commitar;
- conferir se inputs, selects, textareas e botões usam as classes globais (`admin-form`, `form`, `inline-form`, `btn`, `icon-link` ou equivalentes) para evitar controles sem CSS.
- conferir que tooltips customizados não usam `title` nativo quando houver risco de tooltip do navegador sair da tela ou aparecer na barra inferior;
- conferir que ações de sucesso, erro e orientação operacional aparecem em modal, não como mensagem solta no topo da página;
- conferir que a página inteira não cria rolagem horizontal; conteúdos largos devem rolar apenas dentro de containers próprios como `.table-wrap` ou `.guide-snippet`.
