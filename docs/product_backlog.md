# Product Backlog

Atualizado em: 2026-05-23

## Agora

- `DONE` Inicializar repositorio Git ou clonar `helberfmelo/provadorvirtualv2` nesta pasta.
- `DONE` Cadastrar `SSH_PRIVATE_KEY`, `SSH_PRIVATE_KEY_B64` e `PRODUCTION_ENV` no GitHub Actions.
- `DONE` Regularizar billing/spending limit do GitHub Actions tornando o repositorio publico.
- `DONE` Confirmar path remoto final do v2: `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`.
- `DONE` Scaffold Laravel/Vue.
- `DONE` Criar `.env.example` backend/frontend.
- `DONE` Criar migrations iniciais.
- `DONE` Criar pagina `/produto-teste`.

## Produto

- `TODO` Cadastro/login de lojista.
- `TODO` Cadastro de empresa/loja.
- `DONE` CRUD de produtos.
- `DONE` CRUD de variacoes.
- `DONE` CRUD de tabelas de medidas.
- `DONE` Templates por tipo de peca/genero.
- `DONE` Motor de recomendacao.
- `DONE` Widget universal.
- `DONE` Feedback de recomendacao.
- `DONE` Tela de instalacao do widget.
- `DONE` Catalogo inicial de integracoes.
- `DONE` Importacao CSV de produtos.
- `DONE` Importacao CSV de tabelas.
- `DONE` Parser inicial Google XML.
- `TODO` Analytics.
- `TODO` SaaS admin.

## Integracoes

- `DONE` Cadastro manual de conexao por plataforma.
- `DONE` BigShop probe.
- `DONE` BigShop sync produtos/grades.
- `DONE` BigShop sync tabela de medidas quando houver payload estruturado.
- `TODO` BigShop um clique.
- `DONE` Base do guia/snippet custom no painel.
- `DONE` Tabelas `import_jobs` e `integration_events`.
- `TODO` Guias Shopify.
- `TODO` Guias WooCommerce.
- `TODO` Guias Nuvemshop.
- `TODO` Webhooks quando houver contrato.

## IA

- `TODO` Decidir provider inicial.
- `TODO` Cadastrar chave quando sprint pedir.
- `TODO` OCR de tabela.
- `TODO` Sugestao de tabela com revisao.
- `TODO` Analise de feedback.

## Operacao

- `DONE` Deploy v2 em subpasta.
- `DONE` Smoke publico com frontend e API JSON.
- `DONE` Backup/rollback automatizado pelo workflow.
- `DONE` Registro de incidentes em `docs/execution_log.md`.
- `TODO` Checklist de go-live.
