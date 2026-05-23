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
- `DONE` Analytics.
- `DONE` SaaS admin basico.

## Integracoes

- `DONE` Cadastro manual de conexao por plataforma.
- `DONE` BigShop probe.
- `DONE` BigShop sync produtos/grades.
- `DONE` BigShop sync tabela de medidas quando houver payload estruturado.
- `DONE` BigShop um clique por endpoint assinado.
- `TODO` Cadastrar `BIGSHOP_ACTIVATION_SECRET` para teste real.
- `DONE` Base do guia/snippet custom no painel.
- `DONE` Tabelas `import_jobs` e `integration_events`.
- `TODO` Guias Shopify.
- `TODO` Guias WooCommerce.
- `TODO` Guias Nuvemshop.
- `TODO` Webhooks quando houver contrato.

## IA

- `DONE` Decidir provider inicial.
- `TODO` Cadastrar chave externa para OCR real de imagem.
- `DONE` Extracao de tabela por texto/CSV.
- `TODO` OCR real de imagem com provider externo.
- `DONE` Sugestao de tabela com revisao.
- `TODO` Analise de feedback.

## Operacao

- `DONE` Deploy v2 em subpasta.
- `DONE` Smoke publico com frontend e API JSON.
- `DONE` Backup/rollback automatizado pelo workflow.
- `DONE` Registro de incidentes em `docs/execution_log.md`.
- `DONE` Paginas publicas de privacidade e termos.
- `DONE` CORS dinamico por dominio do widget.
- `DONE` Rate limit nas rotas publicas criticas.
- `DONE` Status operacional publico.
- `DONE` Comandos de anonimizacao e limpeza de logs.
- `DONE` Checklist de go-live.
- `DONE` Tela de prontidao para go-live.
- `DONE` Script de validacao de producao.
- `DONE` Plano de cutover para raiz do dominio.
