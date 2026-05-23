# Roadmap e Sprints

Atualizado em: 2026-05-23

Este roadmap busca um produto enxuto, robusto e comercialmente usavel. Nao e MVP minimo; e uma primeira versao consistente.

## Sprint 0 - Documentacao e Preparacao

Objetivo: criar fonte de verdade, deploy inicial e regras de trabalho.

Entregas:

- docs iniciais;
- `.gitignore`;
- `.github/workflows/deploy.yml`;
- lista de secrets faltantes;
- backlog inicial.

## Sprint 1 - Fundacao Laravel/Vue

Objetivo: app rodando local e deployavel.

Entregas:

- scaffold `backend/` Laravel;
- scaffold `frontend/` Vue;
- `.env.example`;
- health endpoints;
- layout base;
- auth base;
- migrations iniciais;
- seed demo;
- pagina `/produto-teste` inicial.

## Sprint 2 - Produtos, Variacoes e Tabelas

Objetivo: lojista conseguir cadastrar produto e tabela de medidas.

Entregas:

- CRUD produtos;
- CRUD variacoes;
- CRUD tabelas;
- templates de medidas;
- vinculo produto/tabela;
- validacoes e testes.

Status: concluido e publicado em producao no run `26326950616`.

## Sprint 3 - Motor de Recomendacao

Objetivo: recomendacao real sem depender de IA externa.

Entregas:

- service de recomendacao;
- normalizacao de medidas;
- scoring por tamanho;
- confianca e explicacao;
- logs;
- endpoints publicos;
- testes de casos comuns e extremos.

Status: concluido e publicado em producao no run `26327119754`.

## Sprint 4 - Widget Universal v1

Objetivo: snippet funcionar em qualquer pagina.

Entregas:

- bundle JS/CSS;
- config-check;
- modal/drawer responsivo;
- fluxo de medidas;
- recomendacao e feedback;
- pagina `/produto-teste` usando widget real;
- guia custom/Shopify/WooCommerce/Nuvemshop.

Status: concluido e publicado em producao no run `26331199145`.

## Sprint 5 - Painel do Lojista

Objetivo: experiencia operacional completa para configurar a loja.

Entregas:

- dashboard;
- produtos/tabelas/integracoes;
- tela de instalacao;
- onboarding guiado;
- estados vazios uteis;
- ajustes mobile.

Status: concluido e publicado em producao no run `26331485173`.

## Sprint 6 - Importacao e Templates Assistidos

Objetivo: reduzir trabalho manual do lojista.

Entregas:

- importacao CSV/XML;
- parser de feed Google Shopping quando possivel;
- assistente para criar tabela a partir de modelo;
- preview e validacao antes de importar;
- jobs e logs.

Status: concluido e publicado em producao no run `26331691701`.

## Sprint 7 - Integracao BigShop Base

Objetivo: conectar BigShop por API e sincronizar dados reais.

Entregas:

- cadastro de conexao;
- token criptografado;
- probe remoto;
- sync produtos/grades;
- mapeamento tabela de medidas quando disponivel;
- relatorio de lacunas por loja.

Status: concluido e publicado em producao no run `26331844564`.

## Sprint 8 - BigShop Um Clique

Objetivo: definir e implementar primeiro caminho nativo.

Entregas:

- especificacao de handshake com BigShop;
- endpoint de ativacao;
- snippet/tema automatico ou instrucao interna;
- teste em loja controlada;
- documentacao para time BigShop.

Status: concluido e publicado em producao no run `26332055677`.

## Sprint 9 - IA para OCR e Tabelas

Objetivo: acelerar criacao de tabelas sem comprometer confianca.

Entregas:

- provider IA configuravel;
- OCR de imagem/texto;
- sugestao de tabela;
- revisao obrigatoria pelo lojista;
- logs de custo/uso;
- guardrails.

Status: concluido e publicado em producao no run `26332326042`. OCR de imagem real depende de `OPENAI_API_KEY` ou `GEMINI_API_KEY` e ativacao do provider externo.

## Sprint 10 - Analytics e SaaS Admin

Objetivo: dar visibilidade de uso, qualidade e operacao.

Entregas:

- dashboard de recomendacoes;
- taxa de feedback positivo;
- produtos sem tabela;
- erros de widget;
- painel SaaS para lojistas;
- audit logs.

Status: concluido e publicado em producao no run `26332544138`.

## Sprint 11 - Hardening, LGPD e Observabilidade

Objetivo: preparar release publico com seguranca.

Entregas:

- politicas de privacidade/termos;
- CORS por dominio;
- rate limit;
- mascaramento de logs;
- retencao;
- incident runbook;
- smoke e rollback testados.

Status: concluido e publicado em producao no run `26332960822`.

## Sprint 12 - Go-live Assistido

Objetivo: publicar v2 com seguranca e decidir cutover.

Entregas:

- deploy production verde;
- produto teste em producao;
- loja BigShop piloto;
- validacao de widget externo;
- checklist de cutover;
- plano para raiz `provadorvirtual.online`.

Status: concluido e publicado em producao no run `26333226813`. Go-live assistido permanece na subpasta `/provadorvirtual_v2/`; cutover para a raiz depende de aceite comercial e piloto BigShop.

## Roadmap de Evolucao - Sprints 13 a 22

Documento detalhado: `docs/intelligent_sizing_roadmap.md`.

Resumo:

- Sprint 13: catalogo inteligente de medidas, importando e normalizando a base do v1.
- Sprint 14: wizard de tabelas para lojista com modelo pronto, IA, OCR e validacao.
- Sprint 15: widget inteligente e gamificado com precisao progressiva.
- Sprint 16: perfis anonimos/conhecidos de consumidor e consentimento.
- Sprint 17: benchmark e base por marca, com Zak como primeira referencia controlada.
- Sprint 18: pacotes de integracao por plataforma, priorizando BigShop um clique.
- Sprint 19: IA externa em producao com Gemini/OpenAI, custo e guardrails.
- Sprint 20: pipeline de aprendizado e outliers.
- Sprint 21: recomendacao contextual e comercial.
- Sprint 22: preparacao comercial Sizebay-like e piloto.

Status: Sprint 13 a 22 continuam como trilha evolutiva inteligente.

## Sprints 23 a 26 - Evolucao Comercial Executada

### Sprint 23 - SaaS admin, empresas e identidade

Objetivo: permitir operacao interna de empresas sem checkout publico e preparar acesso por codigo.

Entregas:

- `cpf` no usuario;
- endereco completo em `merchant_companies`;
- `access_code` no formato `aaaa + id com 4 digitos`;
- comando `php artisan pv:create-master-admin`;
- endpoints SaaS para listar/criar/editar empresas;
- endpoint publico para resolver empresa por codigo ou CNPJ;
- CEP primeiro no formulario SaaS com ViaCEP no frontend.

Status: implementado e testado.

### Sprint 24 - Loja teste realista e widget Sizebay-like

Objetivo: simular uma loja real com produtos e botoes do Provador Virtual dentro da pagina de produto.

Entregas:

- loja demo `Provador Virtual Loja Teste`;
- 4 produtos demo: 2 femininos e 2 masculinos;
- 4 tabelas de medidas por tipo de produto;
- storefront publica em `/produto-teste`;
- pagina de produto por slug;
- widget com botoes `Descubra seu tamanho` e `Tabela de Medidas`;
- modal de tabela de medidas;
- assinatura `desenvolvido por provadorvirtual.online`;
- reuso local de medidas anteriores pelo navegador.

Status: implementado e testado.

### Sprint 25 - Personalizador visual do widget

Objetivo: lojista ajustar o visual do widget/tabela e ver o resultado antes de publicar.

Entregas:

- tema ampliado: cores, fundo, texto, fonte, tamanho, peso e raio;
- validacao backend dos novos campos;
- visualizador em tempo real em `/app/widget`;
- snippet continua independente por plataforma.

Status: implementado e testado.

### Sprint 26 - Landing e checkout Pagar.me transparente

Objetivo: abrir contratacao publica com checkout transparente e provisionamento inicial.

Entregas:

- landing publica clean com CTAs;
- rota `/checkout`;
- checkout com CEP primeiro e ViaCEP;
- tokenizacao de cartao no navegador via chave publica Pagar.me;
- pedido direto na Pagar.me pelo backend;
- Pix, boleto e cartao;
- tabelas `checkout_sessions` e `payment_events`;
- webhook `POST /api/v1/webhooks/pagarme`;
- liberacao da empresa quando pagamento aprovado;
- tela `/checkout/sucesso`.

Status: implementado e testado. Producao depende de `PAGARME_SECRET_KEY`, `PAGARME_PUBLIC_KEY` e `PAGARME_WEBHOOK_SECRET` em `PRODUCTION_ENV`.

## Sprints 27 a 30 - Nova trilha comercial e operacional

### Sprint 27 - Site publico raiz e checkout anual unico

Objetivo: substituir a landing v1 na raiz pelo site publico v2 e fechar a regra comercial atual.

Entregas:

- landing v2 com estrutura inspirada no v1, sem promessa de gratuidade;
- cores v2 no lugar do lilas legado;
- publicacao da build publica em `https://provadorvirtual.online/`;
- preservacao da aplicacao em `/provadorvirtual_v2/` para backend, widget e rollback;
- checkout com um plano anual unico;
- select de plataforma com BigShop como primeira opcao;
- preco padrao `R$ 189,90/mes` no anual;
- preco BigShop `R$ 129,90/mes` no anual;
- cartao em ate 12x e Pix a vista com 5% de desconto;
- boleto removido;
- plataforma salva na empresa e no widget;
- testes de preco por plataforma e bloqueio de boleto.

Status: implementado, publicado e validado em producao no run `26336554760`.

### Sprint 28 - Monitor de pagamentos e e-mails transacionais

Objetivo: reduzir dependencia exclusiva do webhook e criar operacao de comunicacao transacional.

Entregas:

- comando Artisan para sincronizar pagamentos pendentes com a Pagar.me;
- agendamento Laravel do monitor de pagamentos a cada 5 minutos;
- documentacao de cron cPanel com log;
- configuracao SaaS de credenciais SMTP, com senha criptografada e sem retorno em claro na API;
- CRUD SaaS de e-mails transacionais com listagem, novo, editar e ativar/desativar;
- templates iniciais: cadastro realizado, pagamento confirmado, aguardando pagamento com Pix, erro no pagamento, recuperacao de senha e renovacao de plano;
- testes para API de e-mails e comando de sincronizacao de pagamentos.

Status: implementado, publicado e validado em producao no run `26336899986`.

### Sprint 29 - Login contextual e acesso de empresa

Objetivo: permitir acesso por e-mail ou CPF e selecionar empresa por codigo/CNPJ quando for portal do lojista.

Entregas:

- login por e-mail ou CPF no SaaS;
- login do portal da empresa exigindo codigo da loja ou CNPJ;
- reuso seguro de usuario com mesmo e-mail/CPF em mais de uma empresa;
- ajuste de checkout/cadastro para vincular usuario existente quando aplicavel;
- mensagens claras quando o usuario nao pertence a empresa informada.
- contexto de lojista/empresa gravado no token de acesso;
- painel passa a enviar e exibir o campo `Codigo da loja ou CNPJ`.

Status: implementado, publicado e validado em producao no run `26337254520`.

### Sprint 30 - Usuarios e permissoes por modulo

Objetivo: permitir que SaaS e lojista gerenciem usuarios com permissoes granulares.

Entregas:

- CRUD de usuarios no portal SaaS;
- CRUD de usuarios no portal da empresa;
- listagem, novo, editar e ativar/desativar em todos os CRUDs;
- permissoes por modulo/menu com visualizar e editar;
- ao marcar editar, visualizar fica automaticamente ativo;
- enforcement inicial no backend para modulos criticos.

Status: implementado, publicado e validado em producao no run `26337792120`.

## Sprints 31 a 37 - Refinamento operacional e escala

### Sprint 31 - Automacoes de e-mail e ciclo financeiro

Objetivo: transformar os templates em disparos transacionais reais.

Entregas:

- service de envio usando as credenciais SMTP salvas;
- disparo de cadastro realizado, pagamento confirmado e erro/pendencia de pagamento;
- reenvio de Pix pendente com controle de frequencia;
- links de checkout de renovacao;
- historico de envios por empresa e template.

Status: implementado, publicado e validado em producao no run `26338061259`.

### Sprint 32 - Oferta BigShop travada, site publico e mobile

Objetivo: impedir uso indevido do desconto BigShop e refinar a experiencia comercial publica.

Entregas:

- bloquear painel de integracoes para mostrar apenas BigShop quando a empresa contratou BigShop;
- bloquear atualizacao do widget para plataformas diferentes da BigShop nesses contratos;
- separar planos/precos publicos em duas colunas com CTA proprio;
- abrir `Falar com especialista` no WhatsApp oficial;
- trocar favicon para icone PV laranja/branco;
- configurar tags OG/Twitter para compartilhamento no WhatsApp;
- trocar imagens da loja teste por fotos mais fieis aos produtos;
- revisar responsividade com menu mobile em drawer;
- melhorar footer publico com copyright e credito OTS;
- incluir CTA para quem ainda nao tem loja online criar loja na BigShop.

Status: implementado, publicado e validado em producao no run `26338411089`.

### Sprint 33 - Login multiempresa e permissoes refinadas

Objetivo: completar a experiencia de usuarios que participam de mais de uma empresa.

Entregas:

- seletor de empresa apos login quando houver multiplas empresas;
- escopo por empresa em todas as telas do portal;
- enforcement completo das permissoes de visualizar/editar;
- auditoria por usuario/empresa/modulo.
- alternancia de empresa no topo do painel sem logout;
- testes cobrindo login multiempresa, troca de contexto, escopo de dados e negacao auditada.

Status: implementado, publicado e validado em producao no run `26338888072`.

### Sprint 34 - Guias de integracao por plataforma

Objetivo: deixar a implantacao default para o maximo de plataformas.

Entregas:

- guias e snippets para Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart e custom;
- checklist visual por plataforma no portal;
- validacao de dominio/snippet instalado;
- matriz de dados suportados por plataforma.

Status: implementado, publicado e validado em producao no run `26339199751`.

### Sprint 35 - BigShop um clique em producao

Objetivo: preparar o ajuste final do lado BigShop e ativar o fluxo nativo.

Entregas:

- contrato final de payload BigShop;
- teste com loja piloto real;
- ajustes no codigo da BigShop para instalar widget e mapear produto/tabela;
- monitoramento de ativacoes BigShop.

Status: implementado, publicado e validado em producao no run `26339426665`. O contrato, o snippet de instalacao e o monitoramento estao prontos no SaaS; teste com loja piloto real segue pendente de `BIGSHOP_ACTIVATION_SECRET`, `store_id` e token `x-api` oficiais.

### Sprint 36 - Inteligencia de perfis e aprendizado

Objetivo: evoluir a recomendacao sem comprometer LGPD e qualidade estatistica.

Entregas previstas:

- perfis anonimos e conhecidos com consentimento;
- edicao fluida de medidas salvas no widget;
- sinais de compra/devolucao/feedback;
- deteccao de outliers antes de alimentar modelos;
- dashboards de qualidade da recomendacao.

Status: implementado, publicado e validado em producao no run `26339824157`. A Sprint 36 criou perfis anonimos com token local, consentimento e esquecimento; eventos de aprendizado para recomendacao, feedback e sinais comerciais; `outlier_score`/`learning_status`; e paineis de qualidade no analytics. O run anterior `26339739429` falhou por nome automatico de foreign key acima do limite do MySQL e foi corrigido com migration idempotente e identificadores curtos.

### Sprint 37 - Piloto comercial e qualidade final

Objetivo: preparar venda assistida e piloto com clientes reais.

Entregas previstas:

- teste real de checkout/Pagar.me em producao;
- teste ponta a ponta em loja BigShop;
- performance do widget em paginas de produto reais;
- revisao responsiva/acessibilidade;
- pacote comercial e checklist de onboarding.

Status: implementado, publicado e validado em producao no run `26340033238`. A Sprint 37 ampliou go-live/readiness com Pagar.me, transacao real, cron, performance do widget, acessibilidade/mobile e pacote de piloto; tambem criou `docs/commercial_pilot_package.md` e ampliou `scripts/validate-production.ps1`. Testes reais Pagar.me/BigShop seguem pendentes das credenciais oficiais.

### Sprint 38 - UX corretiva: navegacao por contexto

Objetivo: corrigir a mistura entre portal SaaS e portal da empresa, criando uma base visual mais clara antes de refatorar os CRUDs.

Entregas previstas:

- separar shell/menu do SaaS e do portal da empresa;
- remover menus de lojista da navegacao do SaaS;
- usar menu lateral em areas autenticadas, com drawer no mobile;
- manter menu publico separado de operacao interna;
- atualizar documentacao de rotas e URLs.

### Sprint 39 - SaaS list-first e subpaginas

Objetivo: transformar o portal SaaS em telas operacionais de listagem, com formularios em paginas proprias.

Entregas previstas:

- `/saas` apenas como visao geral;
- `/saas/empresas` como listagem de empresas ocupando a tela;
- `/saas/empresas/nova` e `/saas/empresas/:id/editar` como formularios separados;
- `/saas/emails` como area de e-mail transacional separada;
- formularios de credenciais SMTP e templates fora da mesma tela da visao geral;
- manter acoes de criar, editar e ativar/desativar nas listagens.

### Sprint 40 - Portal da empresa list-first e subpaginas

Objetivo: revisar as telas do lojista para evitar paginas emboladas e padronizar CRUDs.

Entregas previstas:

- listagens de produtos, tabelas, usuarios e importacoes ocupando a tela;
- paginas separadas para novo/editar quando o CRUD exigir muitos campos;
- revisao de widget, integracoes, assistente, analytics e go-live para reduzir secoes misturadas;
- submenus claros por operacao: catalogo, configuracao, inteligencia, publicacao e acessos.

### Sprint 41 - Revisao visual, responsiva e QA de telas

Objetivo: validar tela por tela a experiencia final em desktop e mobile.

Entregas previstas:

- reduzir desalinhamentos, overflow e tabelas espremidas;
- revisar hierarquia visual de cards, formularios, botoes e tabelas;
- garantir drawer mobile para SaaS e portal da empresa;
- ampliar checklist de validacao visual/rotas;
- publicar e validar producao apos cada ajuste.
