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

- `DONE` Cadastro/login de lojista via checkout publico e criacao interna SaaS.
- `DONE` Cadastro de empresa/loja sem checkout pelo SaaS admin.
- `DONE` CRUD de produtos.
- `DONE` CRUD de variacoes.
- `DONE` CRUD de tabelas de medidas.
- `DONE` Templates por tipo de peca/genero.
- `DONE` Motor de recomendacao.
- `DONE` Widget universal.
- `DONE` Feedback de recomendacao.
- `DONE` Widget com botoes `Descubra seu tamanho` e `Tabela de Medidas`.
- `DONE` Widget reusa medidas salvas localmente com aviso ao comprador.
- `DONE` Tela de instalacao do widget.
- `DONE` Catalogo inicial de integracoes.
- `DONE` Importacao CSV de produtos.
- `DONE` Importacao CSV de tabelas.
- `DONE` Parser inicial Google XML.
- `DONE` Analytics.
- `DONE` SaaS admin basico.
- `DONE` Codigo de acesso da empresa no formato `aaaa + id`.
- `DONE` Busca de empresa por codigo ou CNPJ.
- `DONE` Personalizador visual do widget com preview.

## Integracoes

- `DONE` Cadastro manual de conexao por plataforma.
- `DONE` Contrato BigShop com desconto exibe e permite apenas integracao BigShop no painel.
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
- `DONE` Localizar `GEMINI_API_KEY` no v1 e documentar somente em `docs/credentials.local.md`.
- `TODO` Cadastrar `GEMINI_API_KEY` em producao quando aprovar provider externo.
- `DONE` Extracao de tabela por texto/CSV.
- `TODO` OCR real de imagem com provider externo.
- `DONE` Sugestao de tabela com revisao.
- `TODO` Analise de feedback.
- `TODO` Importar catalogo padrao do v1 para templates inteligentes.
- `TODO` Wizard IA/OCR completo para lojista.
- `TODO` Prompt registry e limite de custo por lojista.

## Inteligencia de tamanho

- `TODO` Criar perfis anonimos de consumidor.
- `TODO` Criar perfis conhecidos/editaveis.
- `TODO` Persistir medidas anteriores com consentimento.
- `TODO` Reusar medidas no widget com aviso claro.
- `TODO` Implementar formato corporal e barra de precisao no widget v2.
- `TODO` Criar score de qualidade de tabela.
- `TODO` Criar deteccao de outliers.
- `TODO` Criar pipeline de aprendizado com compra/devolucao/feedback.
- `TODO` Criar base inicial Zak como benchmark operacional revisado.

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
- `DONE` Landing publica limpa com CTA para checkout, teste e contato.
- `DONE` Checkout transparente Pagar.me; regra atual usa Pix e cartao, sem boleto.
- `DONE` Webhook Pagar.me e ativacao de empresa paga.
- `DONE` Landing publica v2 inspirada no v1 e preparada para rodar na raiz.
- `DONE` Checkout anual unico sem boleto, com desconto BigShop e Pix.
- `DONE` Site publico com CTA separado para plano padrao e plano BigShop, WhatsApp oficial, favicon PV e tags OG.
- `DONE` Menu mobile em drawer no site/app Vue.
- `DONE` Footer publico com credito OTS e CTA para criar loja na BigShop.
- `TODO` Configurar `PAGARME_CHECKOUT_SUCCESS_URL=https://provadorvirtual.online/checkout/sucesso` em producao junto com as chaves reais.
- `DONE` Monitorar pagamento pendente por cron alem do webhook.
- `DONE` CRUD de credenciais SMTP e e-mails transacionais.
- `DONE` Disparar automaticamente e-mails transacionais por evento.
- `DONE` Criar historico de envios transacionais.
- `DONE` Login do portal da empresa com codigo/CNPJ.
- `DONE` Login por e-mail ou CPF no SaaS e no portal da empresa.
- `DONE` Reuso de usuario por e-mail/CPF em mais de uma empresa.
- `DONE` CRUD de usuarios e permissoes no SaaS e no portal da empresa.

## Pagamentos

- `DONE` Checkout transparente Pagar.me com tokenizacao de cartao no navegador.
- `DONE` Persistencia de `checkout_sessions` e `payment_events`.
- `DONE` Regra comercial atual: plano anual unico, cartao ate 12x, Pix com 5% de desconto, sem boleto.
- `DONE` Preco padrao `R$ 189,90/mes` e preco BigShop `R$ 129,90/mes`.
- `TODO` Cadastrar `PAGARME_SECRET_KEY`, `PAGARME_PUBLIC_KEY` e `PAGARME_WEBHOOK_SECRET` em producao.
- `TODO` Cadastrar URLs Pagar.me de sucesso/cancelamento apontando para a raiz do dominio.

## Benchmark e mercado

- `DONE` Estudar documentacao publica Sizebay.
- `DONE` Capturar fluxo Zak com Sizebay em camisa e calca.
- `DONE` Identificar tenant Zak Sizebay `1235` e contrato tecnico observado.
- `TODO` Capturar outras lojas com Sizebay de forma controlada.
- `TODO` Documentar matriz de plataformas: Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart, Custom e BigShop.
