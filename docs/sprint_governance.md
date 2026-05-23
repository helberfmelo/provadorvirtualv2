# Governanca de Sprints

Atualizado em: 2026-05-23  
Status: leitura obrigatoria antes de qualquer sprint.

## 1. Documentos obrigatorios

Antes de iniciar qualquer sprint, reler a lista em `docs/README.md`.

Quando a sprint tocar producao, banco, SMTP, deploy, IA ou integracoes, reler tambem `docs/credentials.local.md`.

## 2. Fonte de verdade

Os documentos em `docs/` sao a fonte oficial de produto, arquitetura, deploy e operacao. Se uma decisao mudar o produto, atualizar a documentacao na mesma sprint.

## 3. Ciclo obrigatorio

Toda sprint deve seguir:

1. reler documentos obrigatorios;
2. conferir estado local do workspace;
3. revisar backlog e escolher sprint ativa;
4. implementar backend, frontend, banco, docs e scripts necessarios;
5. criar migrations e seeders quando houver mudanca estrutural;
6. executar validacoes locais possiveis;
7. testar como usuario real os fluxos afetados;
8. corrigir problemas encontrados;
9. atualizar documentacao;
10. fazer commit;
11. fazer push;
12. acompanhar GitHub Actions;
13. validar producao quando a sprint incluir deploy;
14. registrar pendencias, incidentes e proxima sprint.

## 4. Commit e push

Ao final de sprint concluida:

- sempre fazer commit;
- sempre fazer push;
- sempre acompanhar Actions;
- se push/action falhar, registrar como bloqueio operacional.

## 5. Criterio de pronto

Uma sprint so esta pronta quando:

- codigo implementado;
- migrations/seeders necessarios criados;
- testes/builds/smokes executados quando possivel;
- documentacao atualizada;
- commit e push realizados;
- Actions verificado;
- pendencias registradas.

## 6. Regras permanentes

- Nunca commitar credenciais.
- Nunca alterar producao sem plano de rollback.
- Nunca ocultar falha de integracao com fallback falso.
- Nao expor termos internos em telas publicas.
- Manter codigo em ingles e UI em PT-BR.
- Preservar v1 ate decisao explicita de cutover.
