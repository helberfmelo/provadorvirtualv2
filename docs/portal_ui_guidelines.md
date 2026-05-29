# Diretrizes de UX dos Portais

Atualizado em: 2026-05-29

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

## Navegação e ajuda operacional

A navegação autenticada deve priorizar jornadas, não uma lista solta de telas:

- Empresa: agrupar em Operação, Catálogo, Provador, Resultados e Conta;
- SaaS: agrupar em SaaS e Operação;
- cada grupo deve manter rótulos curtos, em PT-BR e próximos da linguagem do lojista;
- telas críticas do portal da empresa devem ter ajuda contextual curta, com link para manual, próximo passo e suporte;
- a ajuda deve orientar sem ocupar a área principal nem repetir textos longos que já aparecem na tela;
- admin/support visualizando o portal da empresa deve enxergar claramente esse contexto e ter retorno explícito ao SaaS.

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
- conferir se cabeçalho, rodapé, favicon e tags OG usam os assets oficiais em `frontend/public/images/brand/`;
- conferir se inputs, selects, textareas e botões usam as classes globais (`admin-form`, `form`, `inline-form`, `btn`, `icon-link` ou equivalentes) para evitar controles sem CSS.
- conferir que tooltips customizados não usam `title` nativo quando houver risco de tooltip do navegador sair da tela ou aparecer na barra inferior;
- conferir que ações de sucesso, erro e orientação operacional aparecem em modal, não como mensagem solta no topo da página;
- conferir que modais de feedback tenham fechamento manual visível com `x` no canto superior direito, além de qualquer fechamento automático;
- conferir que a página inteira não cria rolagem horizontal; conteúdos largos devem rolar apenas dentro de containers próprios como `.table-wrap` ou `.guide-snippet`;
- conferir que novas rotas críticas do portal da empresa tenham entrada no manual rápido e ajuda contextual quando fizer sentido.
