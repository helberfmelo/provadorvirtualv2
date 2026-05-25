# Product Backlog

Atualizado em: 2026-05-23

## Agora

- `DONE` Inicializar repositório Git ou clonar `helberfmelo/provadorvirtualv2` nesta pasta.
- `DONE` Cadastrar `SSH_PRIVATE_KEY`, `SSH_PRIVATE_KEY_B64` e `PRODUCTION_ENV` no GitHub Actions.
- `DONE` Regularizar billing/spending limit do GitHub Actions tornando o repositório público.
- `DONE` Confirmar path remoto final do v2: `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`.
- `DONE` Scaffold Laravel/Vue.
- `DONE` Criar `.env.example` backend/frontend.
- `DONE` Criar migrations iniciais.
- `DONE` Criar página `/produto-teste`.

## Produto

- `DONE` Cadastro/login de lojista via checkout público e criação interna SaaS.
- `DONE` Cadastro de empresa/loja sem checkout pelo SaaS admin.
- `DONE` CRUD de produtos.
- `DONE` CRUD de variações.
- `DONE` CRUD de tabelas de medidas.
- `DONE` Templates por tipo de peça/gênero.
- `DONE` Motor de recomendação.
- `DONE` Widget universal.
- `DONE` Feedback de recomendação.
- `DONE` Widget com botões `Descubra seu tamanho` e `Tabela de Medidas`.
- `DONE` Widget reusa medidas salvas localmente com aviso ao comprador.
- `DONE` Tela de instalação do widget.
- `DONE` Guia claro de onde instalar container/script do widget na página de produto.
- `DONE` Catálogo inicial de integrações.
- `DONE` Importacao CSV de produtos.
- `DONE` Importacao CSV de tabelas.
- `DONE` Parser inicial Google XML.
- `DONE` Sincronização automática de XML/feed por cron 4 vezes ao dia.
- `DONE` Analytics.
- `DONE` SaaS admin básico.
- `DONE` Código de acesso da empresa no formato `aaaa + id`.
- `DONE` Busca de empresa por código ou CNPJ.
- `DONE` Personalizador visual do widget com preview.

## Integrações

- `DONE` Cadastro manual de conexão por plataforma.
- `DONE` Contrato BigShop com desconto exibe e permite apenas integração BigShop no painel.
- `DONE` BigShop probe.
- `DONE` BigShop sync produtos/grades.
- `DONE` BigShop sync tabela de medidas quando houver payload estruturado.
- `DONE` BigShop um clique por endpoint assinado.
- `DONE` Contrato final BigShop um clique com `install_snippet` e `integration_contract`.
- `DONE` Monitoramento de ativações BigShop no portal.
- `DONE` Preparar instalação nativa BigShop model3 pro com configuração por loja e fallback da tabela de medidas nativa.
- `TODO` Cadastrar `BIGSHOP_ACTIVATION_SECRET` para teste real.
- `DONE` Base do guia/snippet custom no painel.
- `DONE` Guias e snippets para Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart e custom.
- `DONE` Checklist visual por plataforma no portal.
- `DONE` Validação de domínio/snippet instalado por URL pública.
- `DONE` Matriz de dados suportados por plataforma.
- `DONE` Tabelas `import_jobs` e `integration_events`.
- `TODO` Webhooks quando houver contrato.

## IA

- `DONE` Decidir provider inicial.
- `DONE` Localizar `GEMINI_API_KEY` no v1 e documentar somente em `docs/credentials.local.md`.
- `TODO` Cadastrar `GEMINI_API_KEY` em produção quando aprovar provider externo.
- `DONE` Extracao de tabela por texto/CSV.
- `TODO` OCR real de imagem com provider externo.
- `DONE` Sugestão de tabela com revisão.
- `TODO` Análise de feedback.
- `DONE` Importar catálogo padrão do v1 para templates inteligentes.
- `TODO` Wizard IA/OCR completo para lojista.
- `TODO` Prompt registry e limite de custo por lojista.

## Inteligencia de tamanho

- `DONE` Criar perfis anônimos de consumidor.
- `DONE` Criar perfis conhecidos/editaveis no fluxo do widget por token local.
- `DONE` Persistir medidas anteriores com consentimento.
- `DONE` Reusar medidas no widget com aviso claro.
- `DONE` Implementar formato corporal e barra de precisao no widget v2.
- `TODO` Criar score de qualidade de tabela.
- `DONE` Criar deteccao de outliers.
- `DONE` Criar pipeline inicial de aprendizado com compra/devolucao/feedback.
- `TODO` Criar base inicial Zak como benchmark operacional revisado.

## Operação

- `DONE` Deploy v2 em subpasta.
- `DONE` Smoke público com frontend e API JSON.
- `DONE` Backup/rollback automatizado pelo workflow.
- `DONE` Registro de incidentes em `docs/execution_log.md`.
- `DONE` Páginas públicas de privacidade e termos.
- `DONE` CORS dinamico por domínio do widget.
- `DONE` Rate limit nas rotas públicas criticas.
- `DONE` Status operacional público.
- `DONE` Comandos de anonimização e limpeza de logs.
- `DONE` Checklist de go-live.
- `DONE` Tela de prontidão para go-live.
- `DONE` Script de validação de produção.
- `DONE` Plano de cutover para raiz do domínio.
- `DONE` Landing pública limpa com CTA para checkout, teste e contato.
- `DONE` Checkout transparente Pagar.me; regra atual usa Pix e cartão, sem boleto.
- `DONE` Webhook Pagar.me e ativação de empresa paga.
- `DONE` Landing pública v2 inspirada no v1 e preparada para rodar na raiz.
- `DONE` Checkout anual único sem boleto, com desconto BigShop e Pix.
- `DONE` Site público com CTA separado para plano padrão e plano BigShop, WhatsApp oficial, favicon PV e tags OG.
- `DONE` Menu mobile em drawer no site/app Vue.
- `DONE` Footer público com crédito OTS e CTA para criar loja na BigShop.
- `DONE` Implementar Mercado Pago como operadora ativa do checkout transparente.
- `DONE` Criar configuração SaaS para escolher entre Mercado Pago e Pagar.me.
- `DONE` Monitorar pagamento pendente por cron além do webhook.
- `DONE` CRUD de credenciais SMTP e e-mails transacionais.
- `DONE` Disparar automaticamente e-mails transacionais por evento.
- `DONE` Criar histórico de envios transacionais.
- `DONE` Login do portal da empresa com código/CNPJ.
- `DONE` Login por e-mail ou CPF no SaaS e no portal da empresa.
- `DONE` Reuso de usuário por e-mail/CPF em mais de uma empresa.
- `DONE` CRUD de usuários e permissões no SaaS e no portal da empresa.
- `DONE` CRUD SaaS separado para usuários das empresas clientes.
- `DONE` Pacote comercial/piloto assistido em `/app/go-live`.
- `DONE` Validação de produção cobre checkout, widget, perfil consentido, sinal de aprendizado e pacote de piloto.
- `DONE` Separar completamente a navegação do SaaS e do portal da empresa.
- `DONE` Refatorar CRUDs do SaaS para listagem em tela própria e formulário em tela própria.
- `DONE` Refatorar CRUDs do portal da empresa para listagem em tela própria e formulário em tela própria.
- `DONE` Revisar responsividade, alinhamento e padrão list-first dos portais autenticados.
- `DONE` Limpar defaults confusos nos formulários de nova empresa e novo produto.
- `DONE` Feedback global de salvamento, sucesso e erro nos portais SaaS e empresa.
- `DONE` Remover feedbacks de sucesso inline restantes e usar modal central nas ações operacionais.

## Pagamentos

- `DONE` Checkout transparente Pagar.me com tokenizacao de cartão no navegador.
- `DONE` Checkout transparente Mercado Pago com Pix, cartão tokenizado pelo MercadoPago.js, webhook e sincronização pendente.
- `DONE` Painel SaaS `/saas/checkout` para escolher `mercado_pago` ou `pagarme`.
- `DONE` Persistência de `checkout_sessions` e `payment_events`.
- `DONE` Atualizar regra comercial para plano mensal e anual por plataforma, com mensal em destaque no anual, total anual e economia percentual.
- `TODO` Implementar recorrência automática no cartão para plano mensal e, quando tecnicamente seguro, para renovação anual.
- `TODO` Criar opção discreta no portal da empresa para cancelar somente a renovação automática, sem cancelar pagamentos já capturados ou parcelas em andamento.
- `TODO` Salvar prova técnica de aceite dos termos no checkout.
- `TODO` Permitir boleto no checkout somente quando habilitado no painel SaaS.
- `DONE` Registrar variáveis Mercado Pago em `PRODUCTION_ENV` sem versionar valores reais.
- `TODO` Executar transação real Mercado Pago de baixo valor e confirmar webhook/cron.
- `TODO` Finalizar Pagar.me quando as informações pendentes chegarem.

## Benchmark e mercado

- `DONE` Estudar documentação pública Sizebay.
- `DONE` Capturar fluxo Zak com Sizebay em camisa e calca.
- `DONE` Identificar tenant Zak Sizebay `1235` e contrato técnico observado.
- `TODO` Capturar outras lojas com Sizebay de forma controlada.
- `DONE` Documentar matriz de plataformas: Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart, Custom e BigShop.
