# Governança de Sprints

Atualizado em: 2026-05-23  
Status: leitura obrigatória antes de qualquer sprint.

## 1. Documentos obrigatórios

Antes de iniciar qualquer sprint, reler a lista em `docs/README.md`.

Quando a sprint tocar produção, banco, SMTP, deploy, IA ou integrações, reler também `docs/credentials.local.md`.

## 2. Fonte de verdade

Os documentos em `docs/` são a fonte oficial de produto, arquitetura, deploy e operação. Se uma decisao mudar o produto, atualizar a documentação na mesma sprint.

## 3. Ciclo obrigatório

Toda sprint deve seguir:

1. reler documentos obrigatórios;
2. conferir estado local do workspace;
3. revisar backlog e escolher sprint ativa;
4. implementar backend, frontend, banco, docs e scripts necessários;
5. criar migrations e seeders quando houver mudanca estrutural;
6. executar validações locais possiveis;
7. testar como usuário real os fluxos afetados;
8. corrigir problemas encontrados;
9. atualizar documentação;
10. fazer commit;
11. fazer push;
12. acompanhar GitHub Actions até concluir;
13. conferir se o deploy remoto terminou com sucesso;
14. validar produção quando a sprint incluir deploy;
15. registrar pendências, incidentes e próxima sprint.

Não iniciar a sprint seguinte enquanto a sprint atual não tiver commit, push e GitHub Actions/deploy conferidos. Se a verificação remota falhar, a próxima sprint passa a ser a correção numerada dessa falha.

## 4. Commit e push

Ao final de sprint concluida:

- sempre fazer commit;
- sempre fazer push;
- sempre acompanhar Actions até status final;
- sempre conferir se o deploy remoto passou depois do push;
- se push, Actions ou deploy falhar, corrigir imediatamente em nova sprint numerada ou registrar bloqueio operacional explícito.

## 5. Criterio de pronto

Uma sprint so esta pronta quando:

- código implementado;
- migrations/seeders necessários criados;
- testes/builds/smokes executados quando possível;
- documentação atualizada;
- commit e push realizados;
- Actions/deploy remoto verificado após o push;
- pendências registradas.

## 6. Regras permanentes

- Nunca commitar credenciais.
- Nunca alterar produção sem plano de rollback.
- Nunca ocultar falha de integração com fallback falso.
- Não expor termos internos em telas públicas.
- Manter código em ingles e UI em PT-BR.
- Preservar v1 até decisao explicita de cutover.
