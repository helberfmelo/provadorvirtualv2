# Roadmap e Sprints

Atualizado em: 2026-05-29

Este roadmap busca um produto enxuto, robusto e comercialmente usavel. Não e MVP mínimo; e uma primeira versao consistente.

## Sprint 0 - Documentação e Preparacao

Objetivo: criar fonte de verdade, deploy inicial e regras de trabalho.

Entregas:

- docs iniciais;
- `.gitignore`;
- `.github/workflows/deploy.yml`;
- lista de secrets faltantes;
- backlog inicial.

## Sprint 1 - Fundação Laravel/Vue

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
- página `/produto-teste` inicial.

## Sprint 2 - Produtos, Variações e Tabelas

Objetivo: lojista conseguir cadastrar produto e tabela de medidas.

Entregas:

- CRUD produtos;
- CRUD variações;
- CRUD tabelas;
- templates de medidas;
- vinculo produto/tabela;
- validações e testes.

Status: concluído e publicado em produção no run `26326950616`.

## Sprint 3 - Motor de Recomendação

Objetivo: recomendação real sem depender de IA externa.

Entregas:

- service de recomendação;
- normalizacao de medidas;
- scoring por tamanho;
- confiança e explicacao;
- logs;
- endpoints públicos;
- testes de casos comuns e extremos.

Status: concluído e publicado em produção no run `26327119754`.

## Sprint 4 - Widget Universal v1

Objetivo: snippet funcionar em qualquer página.

Entregas:

- bundle JS/CSS;
- config-check;
- modal/drawer responsivo;
- fluxo de medidas;
- recomendação e feedback;
- página `/produto-teste` usando widget real;
- guia custom/Shopify/WooCommerce/Nuvemshop.

Status: concluído e publicado em produção no run `26331199145`.

## Sprint 5 - Painel do Lojista

Objetivo: experiencia operacional completa para configurar a loja.

Entregas:

- dashboard;
- produtos/tabelas/integrações;
- tela de instalação;
- onboarding guiado;
- estados vazios uteis;
- ajustes mobile.

Status: concluído e publicado em produção no run `26331485173`.

## Sprint 6 - Importacao e Templates Assistidos

Objetivo: reduzir trabalho manual do lojista.

Entregas:

- importacao CSV/XML;
- parser de feed Google Shopping quando possível;
- assistente para criar tabela a partir de modelo;
- preview e validação antes de importar;
- jobs e logs.

Status: concluído e publicado em produção no run `26331691701`.

## Sprint 7 - Integração BigShop Base

Objetivo: conectar BigShop por API e sincronizar dados reais.

Entregas:

- cadastro de conexão;
- token criptografado;
- probe remoto;
- sync produtos/grades;
- mapeamento tabela de medidas quando disponível;
- relatório de lacunas por loja.

Status: concluído e publicado em produção no run `26331844564`.

## Sprint 8 - BigShop Um Clique

Objetivo: definir e implementar primeiro caminho nativo.

Entregas:

- especificacao de handshake com BigShop;
- endpoint de ativação;
- snippet/tema automático ou instrucao interna;
- teste em loja controlada;
- documentação para time BigShop.

Status: concluído e publicado em produção no run `26332055677`.

## Sprint 9 - IA para OCR e Tabelas

Objetivo: acelerar criação de tabelas sem comprometer confiança.

Entregas:

- provider IA configuravel;
- OCR de imagem/texto;
- sugestão de tabela;
- revisão obrigatória pelo lojista;
- logs de custo/uso;
- guardrails.

Status: concluído e publicado em produção no run `26332326042`. OCR de imagem real depende de `OPENAI_API_KEY` ou `GEMINI_API_KEY` e ativação do provider externo.

## Sprint 10 - Analytics e SaaS Admin

Objetivo: dar visibilidade de uso, qualidade e operação.

Entregas:

- dashboard de recomendações;
- taxa de feedback positivo;
- produtos sem tabela;
- erros de widget;
- painel SaaS para lojistas;
- audit logs.

Status: concluído e publicado em produção no run `26332544138`.

## Sprint 11 - Hardening, LGPD e Observabilidade

Objetivo: preparar release público com segurança.

Entregas:

- politicas de privacidade/termos;
- CORS por domínio;
- rate limit;
- mascaramento de logs;
- retenção;
- incident runbook;
- smoke e rollback testados.

Status: concluído e publicado em produção no run `26332960822`.

## Sprint 12 - Go-live Assistido

Objetivo: publicar v2 com segurança e decidir cutover.

Entregas:

- deploy production verde;
- produto teste em produção;
- loja BigShop piloto;
- validação de widget externo;
- checklist de cutover;
- plano para raiz `provadorvirtual.online`.

Status: concluído e publicado em produção no run `26333226813`. Go-live assistido permanece na subpasta `/provadorvirtual_v2/`; cutover para a raiz depende de aceite comercial e piloto BigShop.

## Roadmap de Evolucao - Sprints 13 a 22

Documento detalhado: `docs/intelligent_sizing_roadmap.md`.

Resumo:

- Sprint 13: catálogo inteligente de medidas, importando e normalizando a base do v1.
- Sprint 14: wizard de tabelas para lojista com modelo pronto, IA, OCR e validação.
- Sprint 15: widget inteligente e gamificado com precisao progressiva.
- Sprint 16: perfis anônimos/conhecidos de consumidor e consentimento.
- Sprint 17: benchmark e base por marca, com Zak como primeira referência controlada.
- Sprint 18: pacotes de integração por plataforma, priorizando BigShop um clique.
- Sprint 19: IA externa em produção com Gemini/OpenAI, custo e guardrails.
- Sprint 20: pipeline de aprendizado e outliers.
- Sprint 21: recomendação contextual e comercial.
- Sprint 22: preparacao comercial Sizebay-like e piloto.

Status: Sprint 13 a 22 continuam como trilha evolutiva inteligente.

## Sprints 23 a 26 - Evolucao Comercial Executada

### Sprint 23 - SaaS admin, empresas e identidade

Objetivo: permitir operação interna de empresas sem checkout público e preparar acesso por código.

Entregas:

- `cpf` no usuário;
- endereço completo em `merchant_companies`;
- `access_code` no formato `aaaa + id com 4 digitos`;
- comando `php artisan pv:create-master-admin`;
- endpoints SaaS para listar/criar/editar empresas;
- endpoint público para resolver empresa por código ou CNPJ;
- CEP primeiro no formulário SaaS com ViaCEP no frontend.

Status: implementado e testado.

### Sprint 24 - Loja teste realista e widget Sizebay-like

Objetivo: simular uma loja real com produtos e botões do Provador Virtual dentro da página de produto.

Entregas:

- loja demo `Provador Virtual Loja Teste`;
- 4 produtos demo: 2 femininos e 2 masculinos;
- 4 tabelas de medidas por tipo de produto;
- storefront pública em `/produto-teste`;
- página de produto por slug;
- widget com botões `Descubra seu tamanho` e `Tabela de Medidas`;
- modal de tabela de medidas;
- assinatura `desenvolvido por provadorvirtual.online`;
- reuso local de medidas anteriores pelo navegador.

Status: implementado e testado.

### Sprint 25 - Personalizador visual do widget

Objetivo: lojista ajustar o visual do widget/tabela e ver o resultado antes de publicar.

Entregas:

- tema ampliado: cores, fundo, texto, fonte, tamanho, peso e raio;
- validação backend dos novos campos;
- visualizador em tempo real em `/app/widget`;
- snippet continua independente por plataforma.

Status: implementado e testado.

### Sprint 26 - Landing e checkout Pagar.me transparente

Objetivo: abrir contratacao pública com checkout transparente e provisionamento inicial.

Entregas:

- landing pública clean com CTAs;
- rota `/checkout`;
- checkout com CEP primeiro e ViaCEP;
- tokenizacao de cartão no navegador via chave pública Pagar.me;
- pedido direto na Pagar.me pelo backend;
- Pix, boleto e cartão;
- tabelas `checkout_sessions` e `payment_events`;
- webhook `POST /api/v1/webhooks/pagarme`;
- liberacao da empresa quando pagamento aprovado;
- tela `/checkout/sucesso`.

Status: implementado e testado. Produção depende de `PAGARME_SECRET_KEY`, `PAGARME_PUBLIC_KEY` e `PAGARME_WEBHOOK_SECRET` em `PRODUCTION_ENV`.

## Sprints 27 a 30 - Nova trilha comercial e operacional

### Sprint 27 - Site público raiz e checkout anual único

Objetivo: substituir a landing v1 na raiz pelo site público v2 e fechar a regra comercial atual.

Entregas:

- landing v2 com estrutura inspirada no v1, sem promessa de gratuidade;
- cores v2 no lugar do lilas legado;
- publicação da build pública em `https://provadorvirtual.online/`;
- preservacao da aplicacao em `/provadorvirtual_v2/` para backend, widget e rollback;
- checkout com um plano anual único;
- select de plataforma com BigShop como primeira opção;
- preço padrão `R$ 189,90/mes` no anual;
- preço BigShop `R$ 129,90/mes` no anual;
- cartão em até 10x sem juros e Pix a vista com 5% de desconto;
- boleto removido;
- plataforma salva na empresa e no widget;
- testes de preço por plataforma e bloqueio de boleto.

Status: implementado, publicado e validado em produção no run `26336554760`.

### Sprint 28 - Monitor de pagamentos e e-mails transacionais

Objetivo: reduzir dependencia exclusiva do webhook e criar operação de comunicacao transacional.

Entregas:

- comando Artisan para sincronizar pagamentos pendentes com a Pagar.me;
- agendamento Laravel do monitor de pagamentos a cada 5 minutos;
- documentação de cron cPanel com log;
- configuração SaaS de credenciais SMTP, com senha criptografada e sem retorno em claro na API;
- CRUD SaaS de e-mails transacionais com listagem, novo, editar e ativar/desativar;
- templates iniciais: cadastro realizado, pagamento confirmado, aguardando pagamento com Pix, erro no pagamento, recuperacao de senha e renovacao de plano;
- testes para API de e-mails e comando de sincronização de pagamentos.

Status: implementado, publicado e validado em produção no run `26336899986`.

### Sprint 29 - Login contextual e acesso de empresa

Objetivo: permitir acesso por e-mail ou CPF e selecionar empresa por código/CNPJ quando for portal do lojista.

Entregas:

- login por e-mail ou CPF no SaaS;
- login do portal da empresa exigindo código da loja ou CNPJ;
- reuso seguro de usuário com mesmo e-mail/CPF em mais de uma empresa;
- ajuste de checkout/cadastro para vincular usuário existente quando aplicável;
- mensagens claras quando o usuário não pertence a empresa informada.
- contexto de lojista/empresa gravado no token de acesso;
- painel passa a enviar e exibir o campo `Código da loja ou CNPJ`.

Status: implementado, publicado e validado em produção no run `26337254520`.

### Sprint 30 - Usuários e permissões por módulo

Objetivo: permitir que SaaS e lojista gerenciem usuários com permissões granulares.

Entregas:

- CRUD de usuários no portal SaaS;
- CRUD de usuários no portal da empresa;
- listagem, novo, editar e ativar/desativar em todos os CRUDs;
- permissões por módulo/menu com visualizar e editar;
- ao marcar editar, visualizar fica automaticamente ativo;
- enforcement inicial no backend para módulos criticos.

Status: implementado, publicado e validado em produção no run `26337792120`.

## Sprints 31 a 37 - Refinamento operacional e escala

### Sprint 31 - Automações de e-mail e ciclo financeiro

Objetivo: transformar os templates em disparos transacionais reais.

Entregas:

- service de envio usando as credenciais SMTP salvas;
- disparo de cadastro realizado, pagamento confirmado e erro/pendência de pagamento;
- reenvio de Pix pendente com controle de frequência;
- links de checkout de renovacao;
- histórico de envios por empresa e template.

Status: implementado, publicado e validado em produção no run `26338061259`.

### Sprint 32 - Oferta BigShop travada, site público e mobile

Objetivo: impedir uso indevido do desconto BigShop e refinar a experiencia comercial pública.

Entregas:

- bloquear painel de integrações para mostrar apenas BigShop quando a empresa contratou BigShop;
- bloquear atualização do widget para plataformas diferentes da BigShop nesses contratos;
- separar planos/precos públicos em duas colunas com CTA próprio;
- abrir `Falar com especialista` no WhatsApp oficial;
- trocar favicon para icone PV laranja/branco;
- configurar tags OG/Twitter para compartilhamento no WhatsApp;
- trocar imagens da loja teste por fotos mais fieis aos produtos;
- revisar responsividade com menu mobile em drawer;
- melhorar footer público com copyright e crédito OTS;
- incluir CTA para quem ainda não tem loja online criar loja na BigShop.

Status: implementado, publicado e validado em produção no run `26338411089`.

### Sprint 33 - Login multiempresa e permissões refinadas

Objetivo: completar a experiencia de usuários que participam de mais de uma empresa.

Entregas:

- seletor de empresa após login quando houver multiplas empresas;
- escopo por empresa em todas as telas do portal;
- enforcement completo das permissões de visualizar/editar;
- auditoria por usuário/empresa/módulo.
- alternancia de empresa no topo do painel sem logout;
- testes cobrindo login multiempresa, troca de contexto, escopo de dados e negacao auditada.

Status: implementado, publicado e validado em produção no run `26338888072`.

### Sprint 34 - Guias de integração por plataforma

Objetivo: deixar a implantacao default para o maximo de plataformas.

Entregas:

- guias e snippets para Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart e custom;
- checklist visual por plataforma no portal;
- validação de domínio/snippet instalado;
- matriz de dados suportados por plataforma.

Status: implementado, publicado e validado em produção no run `26339199751`.

### Sprint 35 - BigShop um clique em produção

Objetivo: preparar o ajuste final do lado BigShop e ativar o fluxo nativo.

Entregas:

- contrato final de payload BigShop;
- teste com loja piloto real;
- ajustes no código da BigShop para instalar widget e mapear produto/tabela;
- monitoramento de ativações BigShop.

Status: implementado, publicado e validado em produção no run `26339426665`. O contrato, o snippet de instalação e o monitoramento estão prontos no SaaS; teste com loja piloto real segue pendente de `BIGSHOP_ACTIVATION_SECRET`, `store_id` e token `x-api` oficiais.

### Sprint 36 - Inteligencia de perfis e aprendizado

Objetivo: evoluir a recomendação sem comprometer LGPD e qualidade estatistica.

Entregas previstas:

- perfis anônimos e conhecidos com consentimento;
- edição fluida de medidas salvas no widget;
- sinais de compra/devolucao/feedback;
- deteccao de outliers antes de alimentar modelos;
- dashboards de qualidade da recomendação.

Status: implementado, publicado e validado em produção no run `26339824157`. A Sprint 36 criou perfis anônimos com token local, consentimento e esquecimento; eventos de aprendizado para recomendação, feedback e sinais comerciais; `outlier_score`/`learning_status`; e paineis de qualidade no analytics. O run anterior `26339739429` falhou por nome automático de foreign key acima do limite do MySQL e foi corrigido com migration idempotente e identificadores curtos.

### Sprint 37 - Piloto comercial e qualidade final

Objetivo: preparar venda assistida e piloto com clientes reais.

Entregas previstas:

- teste real de checkout/Pagar.me em produção;
- teste ponta a ponta em loja BigShop;
- performance do widget em páginas de produto reais;
- revisão responsiva/acessibilidade;
- pacote comercial e checklist de onboarding.

Status: implementado, publicado e validado em produção no run `26340033238`. A Sprint 37 ampliou go-live/readiness com Pagar.me, transação real, cron, performance do widget, acessibilidade/mobile e pacote de piloto; também criou `docs/commercial_pilot_package.md` e ampliou `scripts/validate-production.ps1`. Testes reais Pagar.me/BigShop seguem pendentes das credenciais oficiais.

### Sprint 38 - UX corretiva: navegação por contexto

Objetivo: corrigir a mistura entre portal SaaS e portal da empresa, criando uma base visual mais clara antes de refatorar os CRUDs.

Entregas previstas:

- separar shell/menu do SaaS e do portal da empresa;
- remover menus de lojista da navegação do SaaS;
- usar menu lateral em areas autenticadas, com drawer no mobile;
- manter menu público separado de operação interna;
- atualizar documentação de rotas e URLs.

Status: implementado, publicado e validado em produção no run `26342322716`.

### Sprint 39 - SaaS list-first e subpaginas

Objetivo: transformar o portal SaaS em telas operacionais de listagem, com formulários em páginas proprias.

Entregas previstas:

- `/saas` apenas como visão geral;
- `/saas/empresas` como listagem de empresas ocupando a tela;
- `/saas/empresas/nova` e `/saas/empresas/:id/editar` como formulários separados;
- `/saas/emails` como area de e-mail transacional separada;
- formulários de credenciais SMTP e templates fora da mesma tela da visão geral;
- manter ações de criar, editar e ativar/desativar nas listagens.

Status: implementado, publicado e validado em produção no run `26342542196`.

### Sprint 40 - Portal da empresa list-first e subpaginas

Objetivo: revisar as telas do lojista para evitar páginas emboladas e padronizar CRUDs.

Entregas previstas:

- listagens de produtos, tabelas, usuários e importacoes ocupando a tela;
- páginas separadas para novo/editar quando o CRUD exigir muitos campos;
- revisão de widget, integrações, assistente, analytics e go-live para reduzir secoes misturadas;
- submenus claros por operação: catálogo, configuração, inteligencia, publicação e acessos.

Status: implementado, publicado e validado em produção no run `26342724625`. Produtos, tabelas de medidas e usuários foram separados em listagens e formulários próprios.

### Sprint 41 - Revisão visual, responsiva e QA de telas

Objetivo: validar tela por tela a experiencia final em desktop e mobile.

Entregas previstas:

- reduzir desalinhamentos, overflow e tabelas espremidas;
- revisar hierarquia visual de cards, formulários, botões e tabelas;
- garantir drawer mobile para SaaS e portal da empresa;
- ampliar checklist de validação visual/rotas;
- publicar e validar produção após cada ajuste.

Status: implementado. A Sprint 41 consolidou as diretrizes em `docs/portal_ui_guidelines.md`, ajustou alinhamento de tabelas/ações/cabecalhos, ampliou o validador de produção para as novas rotas SaaS e empresa e reforçou o smoke do GitHub Actions.

### Sprint 42 - Ajustes pos-inspeção visual

Objetivo: corrigir defaults de formulários que ainda davam sensacao de prototipo ou podiam induzir erro no cadastro.

Entregas:

- formulário `Nova empresa` sem nome pre-preenchido como loja teste;
- plataforma padrão de cadastro interno ajustada para BigShop;
- formulário `Novo produto` sem categoria/tabela incompatibilizadas por padrão;
- nova validação visual dos formulários em produção.

Status: implementado, publicado e validado em produção no run `26343135605`.

### Sprint 43 - Cerebro inteligente do v1 no v2

Objetivo: transformar a base padrão do v1 no catálogo inteligente inicial do Provador Virtual v2.

Entregas previstas:

- importar `default_measurement_tables_data.json` do v1 para `backend/database/data`;
- normalizar gênero, tipo de produto, altura, peso, idade e campos de medidas para templates do v2;
- trocar os templates fixos por modelos inteligentes consultados pela API `/measurement-templates`;
- melhorar a tela de nova tabela com seletor de modelo inteligente filtrado por produto/gênero;
- reforcar no site público e no portal da empresa que a IA acelera tabelas, usa base brasileira e exige revisão humana;
- limpar a documentação local de URLs para manter somente login master SaaS e login do portal da empresa.

Status: implementado, publicado e validado em produção no run `26343538804`.

### Sprint 44 - CRUD SaaS de usuários das empresas

Objetivo: separar usuários internos do SaaS dos usuários das empresas clientes.

Entregas previstas:

- criar APIs SaaS especificas para listar, criar, editar e ativar/desativar usuários de empresas clientes;
- incluir selecao de empresa por código/nome/CNPJ e vinculo correto com lojista;
- criar listagem em tela cheia e formulários dedicados em `/saas/usuarios-empresas`;
- manter permissões por módulo do portal da empresa com regra editar seleciona visualizar;
- atualizar menu, permissão e documentação para não misturar usuários SaaS com usuários de clientes.

Status: implementado, publicado e validado em produção no run `26343868801`.

### Sprint 45 - Feedback global de salvamento

Objetivo: garantir que qualquer salvamento nos portais SaaS e empresa tenha retorno claro para o usuário.

Entregas:

- modal pequeno central para estado `Salvando`;
- modal de sucesso com fechamento automático em 4 segundos;
- modal de erro persistente com botão `Fechar`;
- tratamento amigável para erros `422`, permissão, registro ausente e falha de conexão;
- interceptador global para `POST`, `PATCH`, `PUT` e `DELETE` autenticados dos portais;
- exclusoes para login/logout, checkout público, preview/importacao assistida e ações que não são salvamento.

Status: implementado, publicado e validado em produção no run `26344601240`.

### Sprint 46 - Recarregamento ao trocar empresa

Objetivo: garantir que o portal da empresa recarregue os dados ao alternar a empresa ativa.

Entregas:

- `RouterView` operacional passa a usar chave baseada em rota e `activeCompany.id`;
- telas do portal da empresa desmontam e montam novamente ao trocar empresa;
- chamadas `onMounted()` de painel, produtos, tabelas, widget, integrações, analytics, usuários e go-live passam a buscar os dados do novo contexto;
- fluxo vindo do SaaS para o portal da empresa preserva a separacao de menus.

Status: implementado, publicado e validado em produção no run `26344923662`.

### Sprint 47 - Integrações BigShop e XML

Objetivo: aprofundar a estratégia Sizebay-like de integrações por plataforma e permitir sincronização por XML/feed no painel.

Entregas:

- adicionar `feed_url` e `feed_format` nas conexões de plataforma;
- criar sincronização XML por URL em `POST /api/v1/integrations/{platform}/sync-xml`;
- mapear XML Google Merchant com produto pai por `g:item_group_id`, variação por `g:id`, tamanho, cor, gênero, disponibilidade, imagem e link;
- adicionar tooltips nos campos da tela de integrações e botão de sincronização XML;
- validar feed e API da Luna Moda Festa sem registrar segredos na documentação;
- documentar matriz por plataforma, pesquisa Sizebay, BigShop e roadmap de conectores.

Status: implementado, testado localmente e enviado ao GitHub no commit `6fd8f46`.

### Sprint 48 - Revisão de textos e idioma dos portais

Objetivo: corrigir textos sem acentos, til e cedilha nos portais SaaS/empresa e registrar a regra como obrigatória nas próximas sprints.

Entregas:

- revisar strings visíveis do frontend autenticado e público;
- revisar mensagens de API exibidas nos portais;
- preservar aliases técnicos de APIs/importações sem acento quando fazem parte do contrato de dados;
- atualizar diretrizes obrigatórias de desenvolvimento e UX para exigir PT-BR correto;
- reforçar que controles de formulário devem usar estilo global do portal.

Status: implementado e testado localmente com `npm run build`, `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=UserAccessApiTest` e `git diff --check`.

### Sprint 49 - Padronização visual dos controles

Objetivo: garantir que selects, inputs, textareas, checkboxes e botões dos portais SaaS e empresa usem estilos consistentes.

Entregas:

- ampliar o estilo global de `.form`, `.admin-form`, `.inline-form` e áreas equivalentes para textareas e selects;
- padronizar estados de foco e desabilitado dos controles;
- corrigir checkboxes herdando largura/altura de input comum;
- estilizar textarea de Pix copia e cola fora de formulário administrativo;
- manter botões operacionais dentro dos padrões `.btn`, `.icon-link`, `.row-actions`, `payment-tabs`, `size-picker` e previews do widget.

Status: implementado e testado localmente com `npm run build` e `git diff --check`.

### Sprint 50 - Correção do CI pós-acentuação e regra de conferência

Objetivo: corrigir os testes que falharam no GitHub Actions após a revisão de textos e tornar obrigatória a conferência de Actions/deploy depois de cada push.

Entregas:

- atualizar expectativas de testes para mensagens em PT-BR com acentos;
- documentar que push não encerra sprint sem conferir GitHub Actions;
- documentar comandos de conferência remota no runbook de deploy;
- registrar falhas dos runs `26346764503` e `26346828756` como incidente operacional corrigido.

Status: implementado, enviado ao GitHub no commit `c2826a5` e validado no run `26347139903`, com deploy remoto e smoke público concluídos com sucesso.

### Sprint 51 - Roadmap e governança do ciclo de integrações

Objetivo: registrar o novo ciclo de correções e reforçar a regra operacional de releitura, commit, push e verificação remota antes de avançar sprint.

Entregas:

- atualizar a fonte de verdade para exigir releitura dos documentos obrigatórios antes de cada sprint;
- reforçar que nenhuma sprint termina sem commit, push e GitHub Actions/deploy verificado;
- registrar as próximas sprints corretivas de integrações, cron e instalação do widget;
- manter a sequência numérica após a Sprint 50.

Status: implementado e testado localmente com `git diff --check`.

### Sprint 52 - UX da tela de integrações

Objetivo: corrigir tooltip fora da tela, remover rolagem horizontal indevida, simplificar ações de sincronização/teste e trocar mensagens soltas por modais consistentes.

Entregas previstas:

- ajustar tooltips para ficarem contidos no viewport em desktop e mobile;
- eliminar overflow horizontal da tela `/app/integracoes` e revisar grids que estouram a largura;
- reorganizar botões de ação da integração para reduzir ambiguidade entre salvar, sincronizar XML, testar conexão e sincronizar API;
- garantir que botões sem credencial/URL exibam orientação clara em modal;
- após sincronizar XML, mostrar modal orientando acessar `/app/produtos` para visualizar os produtos sincronizados.

Status: implementado no commit `24520a3` e publicado com sucesso no run `26348028309`. A tela de integrações passou a usar tooltips customizados sem `title` nativo, feedbacks por modal, ações separadas por configuração, XML/feed e API BigShop, e CSS defensivo contra overflow horizontal.

### Sprint 53 - Sincronização automática de integrações

Objetivo: sincronizar automaticamente integrações por XML/feed e preparar a mesma base para outros conectores necessários.

Entregas previstas:

- criar comando Artisan para sincronizar integrações ativas com `feed_url`;
- agendar no Laravel scheduler pelo menos 4 execuções diárias;
- registrar eventos de sucesso/falha em `integration_events`;
- documentar o cron completo para cPanel e comando manual de validação;
- atualizar go-live/operacional com a rotina de integração.

Status: implementado no commit `684ba67` e publicado com sucesso no run `26348238406`. O comando `pv:integrations-sync-feeds` roda pelo scheduler às `00:00`, `06:00`, `12:00` e `18:00` em `America/Sao_Paulo`.

### Sprint 54 - Guia detalhado de instalação do widget

Objetivo: deixar claro no portal e na documentação onde o script/container do widget deve ser instalado na loja ou site.

Entregas previstas:

- criar seção detalhada em `/app/widget` e/ou `/app/integracoes` explicando o ponto de instalação na página de produto;
- documentar que o container deve ficar no local visual dos botões do Provador Virtual, perto do seletor de tamanho/grade e antes ou perto do botão comprar;
- explicar atualização de produto, variação e SKU em troca de grade;
- reforçar a orientação específica da BigShop para `produto.vue` da model3 plano pro em sprint futura no repositório BigShop correto;
- atualizar docs de widget e integrações por plataforma.

Status: implementado no commit `7b06d4d` e publicado com sucesso no run `26348462160`. A Sprint 54 também expôs `window.ProvadorVirtual.reload(...)` para troca dinâmica de variação/SKU sem reload da página.

### Sprint 55 - Feedbacks operacionais por modal

Objetivo: remover os feedbacks de sucesso inline que ainda restavam em telas operacionais e manter retorno de ação pelo modal central.

Entregas:

- trocar avisos `success-message` restantes por `showFeedback`;
- remover a classe visual de sucesso inline não utilizada;
- revisar textos visíveis encontrados na varredura para PT-BR com acentos;
- manter mensagens estáticas informativas somente quando forem contexto permanente da tela;
- validar build e busca por padrões antigos.

Status: implementado no commit `01d0461` e publicado com sucesso no run `26348653353`, com deploy remoto e smoke público concluídos.

### Sprint 56 - Registro do deploy verificado

Objetivo: registrar na fonte de verdade que a Sprint 55 teve push, GitHub Actions e deploy remoto conferidos antes de encerrar o ciclo.

Entregas:

- atualizar estado atual, roadmap e log de execução com o run `26348653353`;
- reforçar no índice obrigatório que nenhuma sprint começa sem releitura e nenhuma sprint termina sem deploy verificado;
- validar a documentação com `git diff --check`.

Status: implementado no commit `b90cf10` e publicado com sucesso no run `26348767486`, com deploy remoto e smoke público concluídos.

### Sprint 57 - Atualização dos actions do deploy

Objetivo: remover o alerta de depreciação futura do runtime Node 20 nos actions oficiais do GitHub Actions, mantendo a esteira de deploy compatível com a próxima mudança do GitHub.

Entregas:

- verificar as versões atuais oficiais de `actions/checkout` e `actions/setup-node`;
- atualizar o workflow de deploy para `actions/checkout@v6` e `actions/setup-node@v6`;
- registrar a correção preventiva na documentação de estado e execução;
- validar o YAML e acompanhar o deploy remoto até o status final.

Status: implementado no commit `7f4a142` e publicado com sucesso no run `26348869694`, com deploy remoto e smoke público concluídos.

### Sprint 58 - Widget BigShop model3 pro

Objetivo: estudar as cópias locais do front model3 pro, backend BigShop e painel BigShop, instalar o widget universal no ponto correto da página de produto e documentar a configuração por loja.

Entregas:

- permitir que o widget do Provador Virtual resolva loja BigShop por `data-platform="bigshop"` e `data-store-id` externo da BigShop;
- emitir evento público `provadorvirtual:config` para o front decidir se deve esconder ou manter a tabela de medidas nativa;
- aplicar na cópia local do `pro_store/produto.vue` o loader dinâmico do widget logo após seleção de cor/tamanho;
- manter fallback da tabela BigShop quando o produto não tiver tabela no Provador Virtual;
- documentar app `Provador Virtual` em `Configurações > Apps adicionais`, SQL do catálogo `bbs.apps` e campos por loja;
- validar backend/widget e registrar que as cópias locais BigShop foram usadas para estudo/aplicação controlada, sem acionar scripts de publicação próprios da BigShop.

Status: implementado no commit `98c13a7` e publicado com sucesso no run `26349330161`, com deploy remoto e smoke público concluídos.

### Sprint 59 - Fechamento manual dos modais

Objetivo: permitir que o usuário feche manualmente qualquer modal central de feedback sem precisar aguardar o fechamento automático.

Entregas:

- adicionar botão `x` no canto superior direito do `SaveFeedbackModal`;
- manter o fechamento automático de mensagens de sucesso e informação;
- evitar que um modal de sucesso reabra automaticamente quando o usuário fechou manualmente o estado de salvamento da mesma operação;
- registrar a regra em `docs/portal_ui_guidelines.md`.

Status: implementado no commit `a53613a` e publicado com sucesso no run `26368265436`, com deploy remoto, deploy da raiz pública, master admin, smoke público, assets públicos do widget e endpoint de recomendação conferidos após o deploy.

### Sprint 60 - Catálogo global do app BigShop

Objetivo: garantir que o app `Provador Virtual` apareça no select `Configurações > Apps adicionais > Tipo` do painel BigShop.

Entregas:

- identificar que o select é alimentado pelo endpoint BigShop `/get_apps`, baseado na tabela global `apps`;
- ajustar a cópia local de `sistema/context/get_apps.php` para criar `app_code='provador_virtual'` com `INSERT ... WHERE NOT EXISTS` antes de retornar a lista;
- deixar o painel BigShop priorizando `Provador Virtual` no topo quando a API retornar esse app;
- documentar que, se o ambiente local apontar para outra cópia do backend, o SQL precisa ser aplicado no banco usado por esse backend.

Status: painel BigShop implementado no commit `4c2c92b3e` e enviado para `hotfix/couto-integration-support`; documentação do Provador preparada para commit e verificação remota.

### Sprint 61 - Preservar tabela no sync XML

Objetivo: impedir que a sincronização XML/feed remova o vínculo manual de tabela de medidas quando o catálogo da plataforma não informa o nome da tabela.

Entregas:

- preservar `measurement_table_id` existente em produtos já sincronizados quando o XML não trouxer `measurement_table`;
- manter o vínculo automático por nome quando o XML/CSV trouxer tabela explicitamente;
- cobrir o comportamento no teste de integração XML;
- documentar a depuração do widget BigShop e o retorno `measurement_table_missing`.

Status: implementado no commit `2074f03` e publicado com sucesso no run `26352328525`, com deploy remoto e smoke público concluídos.

### Sprint 62 - Depuração BigShop e seleção real de tabela

Objetivo: remover hardcoding remanescente no editor local de Apps adicionais da BigShop, impedir que o portal mostre tabela fantasma em produto sem vínculo real e registrar os próximos bloqueios da Luna Moda Festa.

Entregas:

- ajustar a cópia local `D:\Projetos\bigbangshop2.0\src\pages\configurations\additionalAppsEdit.vue` para depender apenas de `bbs.apps` na opção do app, labels, descrição e `json_fields`;
- remover fallback local, ID fixo e textos fixos do Provador Virtual no painel BigShop;
- limpar `deleted_at` e `last_full` antes do save do app ativo para evitar regravar soft delete antigo vindo do editor genérico;
- corrigir o portal do Provador Virtual para não selecionar automaticamente a primeira tabela quando `products.measurement_table_id` está `NULL`;
- confirmar que a URL pública do script do widget responde `200`;
- confirmar que o `config-check` da Luna retorna `403` quando enviado com `Origin: https://www.lunamodafesta.com.br`, indicando domínio não liberado, e `measurement_table_missing` sem `Origin`, indicando ausência de tabela vinculada depois que o domínio for liberado;
- manter as alterações do repositório BigShop somente locais, sem commit e sem push, conforme orientação do usuário.

Status: implementado no commit `3f242ac` e publicado com sucesso no run `26353363931`, com deploy remoto e smoke público concluídos.

### Sprint 63 - Resolver widget BigShop pela integração

Objetivo: fazer o endpoint público do widget encontrar lojas BigShop configuradas na tela de Integrações, mesmo quando `merchant_companies.platform` ainda não está marcado como `bigshop`.

Entregas:

- adicionar fallback por `platform_connections.platform='bigshop'` e `external_store_id` no middleware de origem do widget;
- adicionar o mesmo fallback na resolução de produto do endpoint público de recomendação;
- cobrir `config-check` BigShop por conexão de plataforma nos testes de recomendação;
- cobrir CORS/origem BigShop por conexão de plataforma nos testes de hardening;
- documentar as duas fontes aceitas para resolução pública BigShop.

Status: implementado no commit `a575777` e publicado com sucesso no run `26353804637`, com deploy remoto, smoke público e `config-check` da Luna Moda Festa retornando `configured=true`.

### Sprint 64 - Corrigir preflight CORS do widget

Objetivo: eliminar o `load_error` restante na Luna Moda Festa causado por redirect no preflight CORS do navegador.

Entregas:

- confirmar no console e no HAR que o `OPTIONS` para `/provadorvirtual_v2/api/v1/public/recommendations/config-check` recebe `307` e falha com `net::ERR_INVALID_REDIRECT`;
- alterar o widget público para calcular a base padrão da API como `/provadorvirtual_v2/public/api/v1` quando o script estiver em subpasta;
- manter `data-api-base-url` como override explícito para instalações especiais;
- adicionar `window.ProvadorVirtual.diagnostics()` para depuração controlada;
- emitir detalhes de falha no evento `provadorvirtual:config`, incluindo `api_base`, `request_url`, nome/mensagem do erro, status HTTP e trecho de resposta quando houver;
- documentar o diagnóstico e o motivo técnico do redirect em preflight.

Status: implementado no commit `445e7bb` e publicado com sucesso no run `26354288938`, com deploy remoto, smoke público, `OPTIONS` direto retornando `204` e `config-check` da Luna Moda Festa retornando `configured=true` sem redirect.

### Sprint 65 - Validação visual do piloto Luna Moda Festa

Objetivo: registrar a confirmação real em loja BigShop model3 pro após a correção do preflight CORS do widget.

Entregas:

- confirmar em produção que a página `https://www.lunamodafesta.com.br/716076-vestido-longo-luna-2553-fucsia` renderiza os botões do Provador Virtual;
- registrar que a integração da Luna Moda Festa está funcional via XML/feed para o produto `716076`, loja BigShop `53`, variação `46125939` e SKU/ref `2553`;
- registrar que o botão `PV Descubra seu tamanho` e o botão `cm Tabela de Medidas` aparecem no ponto esperado da página de produto, abaixo da seleção de tamanho;
- reforçar que o fluxo validado depende de: app BigShop ativo, domínios liberados, feed sincronizado, produto com `measurement_table_id` vinculado e widget usando `/provadorvirtual_v2/public/api/v1` sem redirect;
- atualizar documentação de BigShop/model3 pro e log de execução.

Status: implementado no commit `9895b34` e publicado com sucesso no run `26354617302`, com deploy remoto e smoke público concluídos.

### Sprint 66 - Widget v2 gamificado com lógica do v1

Objetivo: testar minuciosamente o widget v1, entender suas etapas e migrar a experiência gamificada para o widget universal v2 sem abandonar a identidade visual e os contratos atuais do v2.

Entregas:

- testar `https://provadorvirtual.online/provadorvirtual_v1/demo.php`, links principais e todas as etapas do drawer do v1;
- documentar o fluxo v1: altura/peso/idade, gênero/formato corporal, medidas detalhadas, barra de precisão, confete em 100%, recomendação e feedback;
- refatorar o widget v2 para drawer em etapas, mantendo os botões públicos `PV Descubra seu tamanho` e `cm Tabela de Medidas`;
- derivar medidas detalhadas da tabela configurada do produto;
- manter recomendação disponível ao longo da jornada, como no v1;
- exibir feedback final completo e visível no resultado;
- persistir `shopper_profile.raw_widget_data` em `recommendation_logs.raw_widget_payload` para auditoria, aprendizado e melhoria do fluxo;
- incluir `raw_widget_payload` na anonimização LGPD;
- validar desktop, mobile, console, rede, recomendação, feedback e tabela de medidas.

Status: implementado no commit `f52b228`; o primeiro deploy remoto (`26356327237`) falhou apenas no smoke público por validar o marcador antigo `data-pv-submit`. Corrigido no commit `f1d2dbf`, publicado com sucesso no run `26356510237`, com deploy remoto, deploy da raiz pública, master admin e smoke público concluídos.

### Sprint 67 - Corrigir avanço sequencial do widget

Objetivo: impedir que o widget v2 pule etapas ou exiba 100% antes do usuário passar por `Medidas`, `Corpo` e `Detalhes`.

Entregas:

- limitar a precisão exibida por etapa, mesmo quando houver perfil completo salvo no navegador;
- alterar o rodapé para avançar entre etapas e chamar recomendação somente na etapa 3;
- remover atalho de recomendação da etapa 2;
- disparar confete somente quando a precisão real chegar a 100%;
- corrigir perda de clique no rodapé quando o usuário preenche inputs numéricos e clica direto para avançar;
- validar por teste visual temporário o caso de perfil completo salvo e o caso de recomendação básica sem confete;
- atualizar documentação do contrato sequencial do widget.

Status: implementado no commit `4284a24` e publicado com sucesso no run `26357843460`, com deploy remoto, deploy da raiz pública, master admin, smoke público e validação pós-deploy nas páginas do produto teste e da Luna Moda Festa.

### Sprint 68 - Recomendações progressivas do widget

Objetivo: aproximar novamente o widget v2 da dinâmica prática do v1, mantendo recomendação útil desde altura + peso sem permitir 100% ou confete antes da etapa completa.

Entregas:

- recomendar automaticamente somente quando altura e peso estiverem preenchidos; altura isolada ou peso isolado não recomenda tamanho;
- manter o botão `Aumentar precisão` no corpo das etapas e o tamanho recomendado no rodapé fixo quando a API já retornou recomendação;
- exibir banners de dica e recomendação parcial em cada etapa para incentivar o consumidor a continuar preenchendo;
- permitir clicar nos passos 1, 2, 3 e 4 para avançar ou voltar, respeitando os bloqueios: etapa 2 exige altura/peso, etapa 3 exige gênero/formato corporal e etapa 4 exige todas as medidas detalhadas;
- trocar as silhuetas exibidas conforme o gênero escolhido, com conjuntos feminino e masculino;
- persistir medidas e progresso por tabela de medidas no `localStorage`, permitindo reuso em outros produtos com a mesma tabela e atualização quando o consumidor altera dados;
- enviar snapshots silenciosos ao fechar o widget quando já houver recomendação e o consumidor tiver alterado dados;
- disparar confete apenas ao chegar ao resultado depois de preencher todas as medidas detalhadas, com configuração `confetti_enabled` no tema do widget e padrão ativado;
- manter créditos, ano, privacidade e termos no rodapé do drawer;
- validar o fluxo com teste local de widget, suíte backend completa e build frontend.

Status: implementado no commit `790d875` e publicado com sucesso no run `26366746266`, com deploy remoto, deploy da raiz pública, master admin, smoke público, verificação dos assets públicos e teste Playwright pós-deploy no produto teste.

### Sprint 69 - Hierarquia visual e silhuetas do widget

Objetivo: ajustar a hierarquia visual do drawer do widget v2 para ficar mais próximo do v1, sem alterar o fluxo progressivo aprovado.

Entregas:

- deixar o cabeçalho do drawer com fundo baseado nas cores configuradas no portal da empresa;
- destacar os botões de ação `Aumentar precisão` e `Finalizar e ver resultado` no mesmo padrão visual do CTA principal;
- tornar o botão fixo do rodapé mais discreto enquanto o consumidor ainda está nas etapas 1, 2 ou 3, mantendo destaque forte somente no resultado com 100% de precisão;
- copiar para o v2 as silhuetas do widget v1 e renderizá-las como máscara CSS, permitindo que recebam a tonalidade configurada pela loja;
- cobrir a existência dos novos assets no teste do widget;
- validar o fluxo visual com Playwright mockado: cabeçalho tematizado, CTA progressivo, rodapé discreto antes do resultado, silhueta via asset do v1, confete e feedback final.

Status: implementado no commit `a53613a` e publicado com sucesso no run `26368265436`, com deploy remoto, deploy da raiz pública, master admin, smoke público, verificação dos assets públicos de silhueta e endpoint público de recomendação.

### Sprint 70 - Produto teste sem tamanho padrão e identidade visual

Objetivo: refinar as páginas de produto teste e aplicar a identidade visual oficial do Provador Virtual em todas as páginas públicas e autenticadas.

Entregas:

- remover a seleção automática de tamanho nas páginas `/produto-teste/:slug`;
- manter preço inicial visível sem escolher tamanho e trocar o texto de estoque para orientar o usuário a selecionar um tamanho;
- separar o link `Voltar para loja teste` do nome da loja em um badge próprio, evitando o texto embolado;
- copiar logo, ícone e favicon oficiais para `frontend/public/images/brand/`;
- usar o logo oficial no cabeçalho global e no rodapé público;
- configurar favicon PNG, apple touch icon, `theme-color` e tags OG/Twitter com imagem oficial da marca;
- validar que a loja teste, o app e os portais continuam sem rolagem horizontal e sem tamanho pré-selecionado.

Status: implementado no commit `d5d4e69` e publicado com sucesso no run `26370389245`, com deploy remoto, deploy da raiz pública, master admin, smoke público, `validate-production.ps1`, assets de marca públicos e Playwright pós-deploy no produto teste.

### Sprint 71 - Atualizar assets oficiais da marca

Objetivo: substituir logo, ícone e favicon pelos novos arquivos oficiais enviados pelo usuário.

Entregas:

- substituir `frontend/public/images/brand/icon_provador_virtual.png`;
- substituir `frontend/public/images/brand/logo_provador_virtual.png`;
- substituir `frontend/public/images/brand/favicon_provador_virtual.png`;
- atualizar dimensões OG do logo para `3054x261`;
- validar build, assets públicos e smoke de produção após deploy.

Status: implementado no commit `d17d412` e publicado com sucesso no run `26370907476`, com deploy remoto, deploy da raiz pública, master admin, smoke público, assets de marca públicos, tags OG atualizadas e `validate-production.ps1`.

### Sprint 72 - Alinhar degrade do cabeçalho do widget

Objetivo: deixar o cabeçalho do drawer do widget com o mesmo degradê visual usado nos botões principais e na barra de precisão.

Entregas:

- alterar o cabeçalho `.pv-drawer-header` para usar o gradiente `secondary -> warm`, igual aos CTAs e à barra de precisão;
- fazer `--pv-warm` derivar de `--pv-accent`, garantindo que a cor de destaque configurada pela loja seja refletida no cabeçalho, botões e precisão;
- preservar o fluxo, textos e hierarquia do widget, sem alterar JavaScript nem contratos públicos;
- validar o asset público do widget, builds do frontend/backend e checagem de diff.

Status: implementado no commit `4204bf1` e publicado com sucesso no run `26371467799`, com deploy remoto, deploy da raiz pública, master admin, smoke público, `validate-production.ps1` e verificação do CSS público com cache bust confirmando o novo gradiente do cabeçalho.

### Sprint 73 - Aviso discreto de salvamento local no widget

Objetivo: tornar a comunicação sobre salvamento de medidas no navegador mais curta, discreta e posicionada no fim do corpo do widget.

Entregas:

- remover o texto com checkbox `Salvar minhas medidas neste navegador para próximas recomendações` do passo de medidas;
- adicionar aviso único no fim do corpo rolável do drawer: ao usar o Provador Virtual, o usuário concorda em salvar seus dados neste navegador;
- aplicar fonte menor e peso normal ao aviso, sem bold;
- cobrir a nova frase e classe CSS no teste de asset do widget;
- validar sintaxe do widget, teste de asset, builds do frontend/backend e checagem de diff.

Status: implementado no commit `415e68f` e publicado com sucesso no run `26372104049`, com deploy remoto, deploy da raiz pública, master admin, smoke público, `validate-production.ps1` e verificação dos assets públicos confirmando a nova frase discreta e a remoção do texto antigo.

### Sprint 74 - Refinar microtexto de salvamento local

Objetivo: ajustar a hierarquia visual do aviso de salvamento local para que ele fique ainda mais discreto e alinhado à linha de precisão do widget.

Entregas:

- deixar o aviso `Ao usar o Provador Virtual...` em itálico;
- usar no aviso a mesma escala de fonte da linha `Nível de precisão da IA:`;
- reduzir a margem superior do aviso para ele não parecer um bloco principal do formulário;
- proteger o estilo itálico no teste de asset do widget;
- validar teste de asset, builds do frontend/backend e checagem de diff.

Status: implementado no commit `0c83622` e publicado com sucesso no run `26372649754`, com deploy remoto, deploy da raiz pública, master admin, smoke público, `validate-production.ps1` e verificação do CSS público confirmando `font-size: 0.88em` e `font-style: italic`.

### Sprint 75 - Refinar widget mobile e silhuetas

Objetivo: deixar a experiência mobile do widget mais compacta e corrigir a renderização das silhuetas herdadas do v1.

Entregas:

- aumentar logo e ícone de menu no cabeçalho mobile da loja teste;
- manter os quatro passos do widget em uma única linha no mobile;
- escurecer a fonte dos passos com fundo claro para melhorar legibilidade;
- trocar a renderização das silhuetas de máscara CSS para imagens PNG reais;
- compactar a etapa de corpo no mobile para reduzir rolagem e mostrar os cards mais cedo;
- validar o widget em viewports mobile com Playwright.

Status: implementado no commit `2a92a0b` e publicado com sucesso no run `26377480787`, com deploy remoto, smoke público e verificação dos assets públicos do widget.

### Sprint 76 - Remover escala de nota do widget

Objetivo: simplificar o feedback final do widget removendo a avaliação redundante de nota de 1 a 5.

Entregas:

- manter a pergunta `Essa recomendação ajudou?` com os botões `Sim, ajudou` e `Não ajudou`;
- remover a seção `Nota da recomendação` e os botões de 1 a 5;
- deixar o widget enviar feedback sem `rating`, preservando a API compatível com registros antigos;
- remover estilos e testes ligados à escala de nota;
- atualizar documentação do contrato atual do widget.

Status: implementado no commit `6c835c8` e publicado com sucesso no run `26378458765`, com deploy remoto, smoke público e verificação dos assets públicos do widget.

### Sprint 77 - Posicionar aviso de salvamento na etapa inicial

Objetivo: manter a frase de consentimento operacional visível somente quando o consumidor informa as medidas básicas, deixando as demais etapas mais limpas.

Entregas:

- exibir `Ao usar o Provador Virtual, você concorda em salvar seus dados neste navegador.` somente na etapa 1;
- manter o aviso no fim do corpo rolável do widget, abaixo do conteúdo da etapa inicial;
- deixar o aviso em itálico, centralizado e com fonte menor;
- cobrir a regra de renderização e o novo tamanho no `WidgetAssetTest`;
- validar sintaxe do widget, teste de asset, build do frontend e checagem de diff.

Status: implementado no commit `2a5c055` e publicado com sucesso no run `26378864592`, com deploy remoto, smoke público, verificação dos assets públicos e Playwright mobile em produção.

### Sprint 78 - Handoff do tamanho recomendado e demo mobile

Objetivo: deixar a loja teste mobile autoexplicativa e fazer o tamanho recomendado voltar para a página do produto sem fricção.

Entregas:

- tornar o tamanho recomendado acionável no banner parcial, no rodapé fixo e no resultado do widget;
- fechar o drawer ao aceitar a recomendação e emitir `provadorvirtual:size-selected` para a loja aplicar a variação correspondente;
- bloquear clique fantasma de touch para impedir que o drawer reabra imediatamente no mobile;
- deixar `/produto-teste` e `/produto-teste/:slug` explícitos como demonstração sem venda real, com instruções para clicar no widget;
- impedir que o clique manual nos tamanhos fictícios pareça uma compra real, mantendo seleção apenas quando o widget aplicar a recomendação;
- trocar as silhuetas do drawer para carregamento imediato e validar imagens reais no mobile;
- revisar header, menu drawer, vitrine, página de produto, widget e footer em viewport mobile.

Status: implementado no commit `003c996` e publicado com sucesso no run `26381419082`, com deploy remoto, smoke público, verificação de assets do widget e Playwright mobile em produção.

### Sprint 79 - Copy comercial e benefícios mobile

Objetivo: ajustar a mensagem comercial do plano único e refinar a leitura mobile dos cards de benefícios da landing.

Entregas:

- remover menção a BigShop do headline principal da seção de planos;
- reposicionar a mensagem para o valor do produto: provador moderno com IA para vender mais na loja online;
- corrigir a responsividade específica de `.landing-benefits.metric-grid`, que mantinha 3 colunas no mobile por especificidade;
- transformar os cards de benefícios em linhas compactas com ícone destacado, título e texto legíveis no celular;
- validar build do frontend, checagem de diff e Playwright mobile da landing.

Status: implementado no commit `83ac2da` e publicado com sucesso no run `26381750743`, com deploy remoto, smoke público e Playwright mobile em produção validando headline, cards e ausência de overflow horizontal.

### Sprint 80 - Linguagem do provador e ajuda do widget

Objetivo: trocar a palavra widget por provador nas superficies públicas e explicar o que é o widget dentro da seção técnica do painel.

Entregas:

- substituir textos públicos de site, loja teste, produto teste, termos e privacidade para usar `provador` quando o usuário final vê a experiência;
- manter a seção técnica `/app/widget` como `Widget`, mas adicionar ícone `i` de informação ao título da seção;
- explicar que o widget é o provador que aparece na página de produto da loja, abre a recomendação por IA, mostra tabela de medidas e ajuda o cliente a escolher tamanho sem sair da compra;
- renomear o título principal da seção para `Instalação do provador` e ajustar microcópias de carregamento, salvamento, preview e checklist;
- validar em mobile que as páginas públicas não exibem mais a palavra `widget`, não têm overflow horizontal e que o painel mostra o ícone e a explicação.

Status: implementado no commit `feb76e2` e publicado com sucesso no run `26382678616`, com build local, checagem de diff, Playwright mobile local e smoke Playwright em produção.

### Sprint 81 - UX mobile premium do portal

Objetivo: corrigir sobreposição no header mobile, reduzir redundância nos textos de topo e deixar a navegação do sistema mais clara no celular.

Entregas:

- corrigir a especificidade que mantinha `Portal da empresa` visível no header mobile autenticado;
- deixar o header mobile do portal com marca e menu, movendo usuário e saída para dentro do drawer;
- adicionar botão de fechar dentro dos drawers público e autenticado, escondendo o toggle do header enquanto o drawer está aberto;
- trocar o menu `Widget` do portal para `Provador`, mantendo a explicação técnica com ícone `i` dentro da seção `/app/widget`;
- compactar tipografia, espaçamento e botões de topo do sistema em mobile;
- revisar textos redundantes de topo em produtos, tabelas, usuários, provador, go-live, SaaS e e-mails;
- corrigir acentos visíveis em textos como `Fundação`, `vão`, `instalações`, `Últimos` e `tráfego`.

Status: implementado no commit `b82316b` e publicado com sucesso no run `26383644699`, com build local, checagem de diff, auditoria Playwright mobile local em 36 checks e smoke Playwright mobile em produção.

### Sprint 82 - Checkout transparente Mercado Pago

Objetivo: ativar Mercado Pago como operadora funcional do checkout transparente e deixar a escolha da operadora controlável pelo painel SaaS.

Entregas:

- criar camada `CheckoutPaymentManager` para alternar entre Mercado Pago e Pagar.me;
- implementar Mercado Pago via API de pagamentos (`/v1/payments`) com Pix, cartão tokenizado pelo MercadoPago.js/CardForm, webhook e sincronização pendente;
- adicionar `/saas/checkout` e `/api/v1/saas/checkout-settings` para selecionar `mercado_pago` ou `pagarme`;
- manter Pagar.me preservada como alternativa, mas marcada como pendente até as informações faltantes chegarem;
- documentar variáveis `MERCADO_PAGO_*` e registrar que os valores reais herdados do NoAzul ficam apenas em `docs/credentials.local.md`, `.env` remoto/local ou secret seguro;
- cobrir checkout Mercado Pago, webhook, seleção SaaS e sync de pagamentos em testes.

Status: implementado no commit `e9ab2f9` e publicado com sucesso no run `26384825165`, com testes locais completos, build frontend, deploy remoto/raiz, smoke público e smoke pós-deploy do endpoint `/api/v1/public/checkout/config` retornando Mercado Pago como operadora ativa.

### Sprint 83 - Checkout cartão primeiro e parcelas

Objetivo: deixar o checkout público mais natural para contratação imediata por cartão, preservando Pix como alternativa com desconto.

Entregas:

- abrir o checkout com cartão selecionado quando disponível;
- mostrar Pix como segunda aba com tag pequena `5% off`;
- limitar o parcelamento a até 10x sem juros;
- mostrar no select o valor de cada parcela para cada quantidade escolhida;
- exigir escolha explícita das parcelas antes de exibir o total do cartão;
- destacar o valor da parcela e deixar o total anual menos proeminente, exceto em 1x;
- ocultar selects técnicos do Mercado Pago que o comprador não precisa escolher.

Status: implementado no commit `7eadd35` e publicado com sucesso no run `26386034325`, com build frontend, suíte backend completa, Pint, checagem de diff, auditoria mobile mockada e smoke pós-deploy confirmando `max_installments=10`.

### Sprint 84 - Cópia comercial de pagamento

Objetivo: alinhar todos os textos atuais que explicam a condição de pagamento para cartão em até 10x sem juros ou Pix com 5% de desconto.

Entregas:

- atualizar a landing pública na seção de planos e nos cards de preço;
- atualizar defaults de e-mails transacionais e migrar templates padrão existentes sem sobrescrever personalizações;
- revisar spec, backlog, arquitetura e pacote comercial para remover a regra antiga de parcelamento da orientação atual.

Status: implementado no commit `fe2ab48` e publicado com sucesso no run `26386407174`, com build/testes locais, checagem de diff, deploy remoto, smoke público e verificação da cópia de pagamento em produção.

### Sprint 85 - Checkout mobile Mercado Pago

Objetivo: corrigir os campos seguros do Mercado Pago que ficaram grandes demais no checkout mobile, preservando a tokenização segura por iframe.

Entregas:

- travar altura, min-height e max-height dos invólucros `.mp-secure-field` em 44px;
- conter o `iframe` interno do MercadoPago.js em 22px para impedir expansão por estilo inline no Android;
- manter a experiência de toque confortável, sem overflow horizontal e com cartão como aba inicial;
- validar em Playwright mobile com SDK mockado inserindo iframes propositalmente gigantes.

Status: implementado no commit `84ca5e6` e publicado com sucesso no run `26386718075`, com build local, checagem de diff, Playwright mobile mockado e smoke Playwright mobile em produção confirmando campos Mercado Pago em 44px.

### Sprint 86 - Governança e roadmap comercial de planos

Objetivo: registrar como obrigatória a regra de título de commit por sprint e planejar o pacote comercial de planos, recorrência, aceite legal, cookies e boleto antes das alterações funcionais.

Entregas:

- tornar explícito que todo commit de sprint deve iniciar com `Sprint <numero> - `;
- registrar o roadmap completo das sprints 87 a 91;
- confirmar que a implementação seguirá commit, push e conferência de Actions/deploy antes de avançar de uma sprint para a próxima.

Validação:

- `git diff --check`;
- commit e push com prefixo obrigatório;
- conferência do workflow remoto.

### Sprint 87 - Planos mensal/anual e nova matriz de preços

Objetivo: atualizar todos os preços do site, checkout e sistema para suportar mensal e anual por plataforma, preservando o destaque correto do valor mensal.

Entregas:

- plano mensal para qualquer plataforma: `R$ 489,80/mês`;
- plano mensal para cliente BigShop: `R$ 389,80/mês`;
- plano anual para qualquer plataforma: destaque de `R$ 449,80/mês`, com total anual e percentual de economia;
- plano anual para cliente BigShop: destaque de `R$ 349,90/mês`, com total anual e percentual de economia;
- API pública de checkout retornando planos mensal/anual, preços por plataforma, total anual, economia percentual e meios de pagamento permitidos;
- landing pública e checkout exibindo mensal e anual sem manter valores antigos;
- testes cobrindo preços, totais, economia e seleção por plataforma/ciclo.

Validação:

- testes backend do checkout;
- build frontend;
- `git diff --check`;
- commit, push e Actions/deploy.

Status: implementado na Sprint 87 com catálogo único de preços no backend, landing/checkout atualizados, testes de checkout cobrindo os novos valores e build frontend aprovado.

### Sprint 88 - Termos, privacidade, aceite e aviso de cookies

Objetivo: reforçar a camada legal operacional e salvar prova técnica do aceite no checkout.

Entregas:

- páginas `/termos` e `/privacidade` completas, em PT-BR, com escopo do SaaS, limites de responsabilidade, LGPD, dados do lojista, dados do consumidor, IA, integrações, pagamentos, recorrência, boleto e cookies;
- box de aceite dos termos já marcado no checkout, com link para termos e privacidade;
- persistência de aceite com IP, user-agent, usuário, e-mail, empresa, data/hora, versão de termos/privacidade e contexto do checkout;
- aviso discreto no rodapé da tela sobre cookies/localStorage necessários e operacionais, com botão `OK` e gravação em cookie/localStorage para não reaparecer;
- testes de validação do aceite e armazenamento dos metadados.

Validação:

- testes backend focados em checkout/legal;
- build frontend;
- inspeção visual mobile/desktop das páginas legais e modal de cookies;
- commit, push e Actions/deploy.

Status: implementado na Sprint 88 com páginas legais versionadas, aceite obrigatório no checkout, registro em `checkout_acceptances`, metadados legais na sessão de checkout e aviso de cookies/localStorage persistido no navegador.

### Sprint 89 - Recorrência de cartão e cancelamento de renovação

Objetivo: implementar renovação automática para pagamento mensal no cartão e disponibilizar cancelamento discreto da renovação no painel sem cancelar cobranças ou parcelas já em andamento.

Entregas:

- criação de assinatura Mercado Pago por `/preapproval` para plano mensal no cartão, com status autorizado e recorrência mensal;
- registro local da assinatura, status, provedor, ID remoto, ciclo, próxima cobrança, aceite de recorrência e histórico de eventos;
- webhook/sincronização para eventos de assinatura e pagamentos autorizados;
- opção discreta no portal da empresa para desabilitar a renovação automática;
- chamada à operadora para cancelar/pausar a assinatura remota, preservando pagamentos já capturados ou parcelas existentes;
- para anual, registrar a renovação automática somente quando tecnicamente suportada sem duplicar cobrança inicial; se a operadora não permitir de forma segura com parcelamento anual, manter a renovação anual como pendência operacional documentada.

Validação:

- testes backend com `Http::fake` para criar, consultar e cancelar assinatura;
- testes de permissão da rota do portal;
- build frontend;
- commit, push e Actions/deploy.

Status: implementado na Sprint 89 para plano mensal no cartão via Mercado Pago `/preapproval`, com registro local em `billing_subscriptions`, consulta/cancelamento no portal e cancelamento remoto por `PUT /preapproval/{id}` com `status=canceled`. A renovação anual permanece pendente por segurança operacional até validação sem dupla cobrança ou conflito com parcelamento.

### Sprint 90 - Boleto habilitável pelo SaaS

Objetivo: oferecer boleto no checkout somente quando o SaaS habilitar esse meio de pagamento.

Entregas:

- configuração em `/saas/checkout` para habilitar/desabilitar boleto;
- API pública de checkout retornando boleto apenas quando habilitado e suportado pela operadora ativa;
- checkout com aba de boleto, instrução de pagamento diferido e vencimento;
- integração Mercado Pago para boleto, salvando URL de instruções, linha digitável/código de barras quando retornados e status aguardando pagamento;
- tela de sucesso exibindo instruções de boleto;
- testes garantindo boleto oculto por padrão e disponível quando habilitado.

Validação:

- testes backend de configuração e checkout;
- build frontend;
- `git diff --check`;
- commit, push e Actions/deploy.

Status: implementado na Sprint 90 com toggle `Habilitar boleto` em `/saas/checkout`, método `boleto` oculto por padrão na API pública, criação de pagamento Mercado Pago com `payment_method_id=bolbradesco` quando habilitado e tela de sucesso com link/linha digitável.

### Sprint 91 - QA final do pacote comercial

Objetivo: validar o conjunto novo de planos, aceite, recorrência, cancelamento de renovação e boleto em local e produção.

Entregas:

- revisão final de documentação técnica, comercial, LGPD e runbooks;
- validação local do checkout nos ciclos mensal/anual, plataformas padrão/BigShop e meios cartão/Pix/boleto;
- validação de telas públicas, portal da empresa e SaaS em mobile;
- execução do script de produção após deploy;
- registro das pendências externas reais, como teste financeiro real e eventuais limitações da operadora.

Validação:

- suíte backend completa quando viável;
- build frontend;
- `scripts/validate-production.ps1` após deploy;
- commit, push e Actions/deploy.

Status: implementado na Sprint 91 com validação local completa (`php artisan test` com 79 testes e 635 assertions, `npm run build`) e validação de produção em `https://provadorvirtual.online` por `scripts/validate-production.ps1`, cobrindo páginas públicas, `/checkout`, `/termos`, `/privacidade`, rotas SaaS/app, widget, health, ops, recomendação, esquecimento LGPD, CORS, login demo e go-live readiness. O script retornou `PRODUCTION VALIDATION OK` e go-live `ready_with_warnings` antes e depois do deploy do commit `61e8fac`, publicado com sucesso no run `26413377677`; as pendências restantes são externas/operacionais: transação real Mercado Pago de baixo valor com webhook/cron, validação de renovação anual sem dupla cobrança, credenciais oficiais BigShop e finalização Pagar.me quando as informações chegarem.

### Sprint 92 - Modo modal central do provador

Objetivo: permitir que o lojista escolha se o fluxo do provador abre no drawer lateral atual ou em um modal central semelhante ao padrão Sizebay, mantendo a dinâmica e as funcionalidades sem mudança de regra.

Entregas:

- adicionar a opção visual `drawer` ou `modal` na personalização do widget em `/app/widget`;
- salvar a preferência no tema da instalação do widget, editável a qualquer momento pelo portal da empresa;
- manter `drawer` como padrão para instalações existentes;
- fazer o widget público abrir o mesmo fluxo de recomendação em modal central grande no desktop quando configurado;
- fazer o modal ocupar a tela toda no mobile;
- preservar botões, etapas, recomendação parcial, resultado, feedback, tabela de medidas, eventos e dados salvos.

Validação:

- testes backend de configuração do widget;
- teste de asset do widget;
- build frontend;
- `git diff --check`;
- commit, push e Actions/deploy.

Status: implementado na Sprint 92 no commit `3436cc5`, publicado com sucesso no run `26413966332`. Validações locais passaram com `php artisan test --filter=Widget`, `php artisan test`, `npm run build`, `vendor/bin/pint --dirty` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1` e assets públicos confirmando o modo modal.

### Sprint 93 - Previa de confetes no portal do widget

Objetivo: permitir que a empresa habilite ou desabilite a animacao de confetes nas configuracoes do widget e veja uma previa real ao ativar a opcao no portal.

Entregas:

- manter `theme.confetti_enabled` como configuracao editavel em `/app/widget`;
- ajustar a copia da opcao para deixar claro que se trata da animacao de confetes exibida no resultado completo;
- disparar, no portal, a mesma animacao usada na loja quando a empresa marca a opcao;
- preservar a regra do widget publico: confete so aparece para o comprador quando a precisao chega a 100% e a configuracao esta ativa;
- reutilizar a classe, quantidade de pecas, cores, duracao e keyframes do widget publico para garantir paridade visual.

Validação:

- build frontend;
- testes backend focados no widget;
- `git diff --check`;
- commit, push e Actions/deploy.

Status: implementado na Sprint 93 no commit `7093036`, publicado com sucesso no run `26414392783`. Validações locais passaram com `npm run build`, `php artisan test --filter=Widget` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`; os assets publicados confirmaram `portal-confetti-preview`, o label `Animação de confetes`, `.pv-confetti-layer` e `@keyframes pv-confetti-fall`.

### Sprint 94 - Limpeza do topo da loja teste

Objetivo: reduzir redundância textual no topo de `/produto-teste`, deixando a marca no header e usando o bloco principal para orientar a ação da demonstração.

Entregas:

- remover a repetição `Provador Virtual` do eyebrow e do título principal da vitrine teste;
- substituir o H1 por uma chamada focada em testar a recomendação de tamanho;
- manter a informação de vitrine fictícia em badges discretos;
- ocultar o CTA público `Teste o provador` quando o usuário já está em `/produto-teste` ou em uma página de produto da loja teste;
- preservar o fluxo da loja teste, cards de produto, widget público e páginas de produto.

Validação:

- build frontend;
- teste backend focado no payload demo;
- checagem de diff;
- verificação de produção pós-deploy;
- commit, push e Actions/deploy.

Status: implementado na Sprint 94 no commit `c0985fd`, publicado com sucesso no run `26414805731`. Validações locais passaram com `npm run build`, `php artisan test --filter=DemoProductTest`, `git diff --check` e conferência do build sem a frase antiga `Loja teste do Provador Virtual`. Validação de produção passou com `scripts/validate-production.ps1`; os assets publicados confirmaram a nova headline, ausência do texto antigo, CTA público oculto na rota da loja teste e CSS `.shop-heading-meta`.

### Sprint 95 - Checkout enxuto, pedidos SaaS e primeiro acesso

Objetivo: reduzir fricção no checkout público, registrar todas as tentativas de contratação e levar os dados completos da empresa para o primeiro acesso do portal.

Entregas:

- reorganizar os inputs do checkout com larguras proporcionais ao conteúdo esperado;
- manter no checkout apenas plataforma, CNPJ, dados de acesso, pagamento e aceite legal;
- deixar empresa, razão social, domínio e endereço para preenchimento no primeiro acesso ao portal da empresa;
- manter parcelas de cartão visíveis e claras mesmo antes de a operadora popular o select;
- gravar a sessão local antes da chamada à operadora para preservar tentativas recusadas e motivo da falha;
- adicionar `/saas/pedidos` com todos os pedidos e tentativas, incluindo falhas;
- adicionar detalhe do pedido com dados completos, aceite, IDs da operadora, assinatura, payloads e metadados;
- adicionar formulário de dados da empresa no dashboard quando o perfil ainda estiver incompleto.

Validação:

- `php artisan test --filter=PublicCheckoutFlowTest`;
- `php artisan test --filter=SaasCheckoutOrdersApiTest`;
- `php artisan test --filter=MerchantCompanyProfileApiTest`;
- `php artisan test`;
- `npm run build`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy.

Status: implementado na Sprint 95 no commit `1c029ae`, publicado com sucesso no run `26415840565`. Validações locais passaram com 85 testes backend e 678 assertions, além do build frontend. Validação de produção passou com `scripts/validate-production.ps1`, agora cobrindo também `/saas/checkout` e `/saas/pedidos`.

### Sprint 96 - Widget instalação por plataforma e visual organizado

Objetivo: melhorar a disposição da tela `/app/widget` e fazer o código de instalação mudar conforme a plataforma escolhida pela empresa.

Entregas:

- reorganizar a personalização em blocos visuais de instalação, domínios e aparência;
- manter preview, snippet e guia de instalação em painéis laterais mais legíveis;
- expor pela API `platform_guide` e `platform_guides` com snippet, passos, ponto de instalação, dados suportados e exemplo de reload;
- personalizar snippet e instruções para BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart e custom;
- permitir que a troca de plataforma no portal atualize imediatamente código, guia e matriz de dados;
- ampliar `scripts/validate-production.ps1` para cobrir `/app/widget`.

Validação:

- `php artisan test --filter=WidgetInstallApiTest`;
- `npm run build`;
- `php -l backend/app/Http/Resources/WidgetInstallResource.php`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 96 no commit `f44d281`, publicado com sucesso no run `26416798463`. Validações locais passaram com 85 testes backend e 690 assertions, build frontend, Pint e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, agora cobrindo também `/app/widget`.

### Sprint 97 - Ajuste vertical da configuração do widget

Objetivo: corrigir a leitura visual da tela `/app/widget` depois da reorganização anterior, priorizando campos empilhados e controles com largura/altura previsíveis.

Entregas:

- colocar plataforma, chave pública e status do widget um abaixo do outro;
- manter selects e inputs da configuração do widget com altura consistente;
- adicionar tooltip explicando por que a empresa deve informar domínios liberados;
- listar cores uma abaixo da outra, com campo hexadecimal legível;
- preservar preview, snippet e guias por plataforma.

Validação:

- `npm run build`;
- `php artisan test --filter=WidgetInstallApiTest`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 97 no commit `c188d4e`, publicado com sucesso no run `26418672266`. Validações locais passaram com build frontend, `WidgetInstallApiTest`, suíte backend completa com 85 testes e 690 assertions e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/widget`.

### Sprint 98 - Checkout contato em duas linhas e Pix mensal sem tag

Objetivo: ajustar a leitura do bloco `Acesso e pagamento` no checkout público e remover promessa visual de desconto Pix no plano mensal.

Entregas:

- colocar Nome e CPF na primeira linha dos dados de acesso;
- colocar E-mail e Telefone na segunda linha;
- preservar CPF e telefone com largura mais compacta que nome/e-mail;
- esconder a tag `5% off` da aba Pix quando o cliente selecionar plano mensal;
- manter a tag e o resumo de desconto Pix apenas quando o ciclo anual tiver desconto real.

Validação:

- `npm run build`;
- `php artisan test --filter=PublicCheckoutFlowTest`;
- `php artisan test`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 98 no commit `1e0af18`, publicado com sucesso no run `26419066028`. Validações locais passaram com build frontend, `PublicCheckoutFlowTest`, suíte backend completa com 85 testes e 690 assertions e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/checkout`.

### Sprint 99 - Retorno para plataforma e URLs limpas

Objetivo: quando um usuário autenticado entrar no site público, oferecer retorno claro ao SaaS ou ao Portal da Empresa e impedir que telas de frontend mantenham `/provadorvirtual_v2` na barra de endereço.

Entregas:

- exibir no cabeçalho público um botão `Voltar ao SaaS` para usuários `admin/support` com permissão SaaS;
- exibir no cabeçalho público um botão `Voltar ao portal` para usuários autenticados de empresa;
- manter o botão de saída no cabeçalho público para sessões autenticadas;
- redirecionar rotas antigas de frontend em `/provadorvirtual_v2` para as rotas canônicas na raiz do domínio;
- preservar `/provadorvirtual_v2/public/api`, `/provadorvirtual_v2/widget` e `/provadorvirtual_v2/up` como caminhos técnicos de API/widget/health;
- reforçar o smoke de deploy e a validação de produção para confirmar o destino limpo das URLs antigas.

Validação:

- `npm run build`;
- `php artisan test`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy, incluindo redirects legados para a raiz.

Status: implementado na Sprint 99 no commit `360ed12`, publicado com sucesso no run `26419953084`. Validações locais passaram com `npm run build`, `php artisan test` com 85 testes e 690 assertions e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo redirects de `/provadorvirtual_v2/`, `/provadorvirtual_v2/login` e `/provadorvirtual_v2/app/produtos/novo` para as URLs limpas da raiz.

### Sprint 100 - Conclusão e erros do checkout

Objetivo: impedir que erros técnicos da operadora apareçam para o cliente e garantir telas corretas de conclusão para Pix, boleto e cartão.

Entregas:

- usar chave de idempotência UUID no Mercado Pago e salvar essa chave nos metadados da sessão;
- traduzir erros opacos da operadora para mensagens amigáveis por meio de pagamento, mantendo código técnico para suporte;
- registrar em pedidos SaaS a mensagem amigável, a mensagem técnica original, o código do erro, operadora e meio de pagamento;
- exibir modal de erro no checkout com mensagem amigável e código de referência, incluindo atalho discreto para tentar Pix quando aplicável;
- desmontar o CardForm do Mercado Pago ao trocar para Pix/boleto ou alterar plano, evitando tokenização de cartão em pagamentos Pix;
- mostrar em `/checkout/sucesso` Pix com QR Code, copia e cola e botão de copiar; boleto com abrir, baixar e copiar código de barras; cartão aprovado com tela de sucesso;
- exibir sessão falhada em `/checkout/sucesso` com mensagem amigável e código do erro quando o cliente voltar por referência.

Validação:

- `npm run build`;
- `php artisan test --filter=PublicCheckoutFlowTest`;
- `php artisan test --filter=SaasCheckoutOrdersApiTest`;
- `php artisan test`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 100 no commit `c0415bd`, publicado com sucesso no run `26421412473`. Validações locais passaram com `npm run build`, `PublicCheckoutFlowTest` com 16 testes e 90 assertions, `php artisan test` com 86 testes e 700 assertions, `vendor/bin/pint --dirty` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/checkout`, `/checkout/sucesso` por pacote público, `/saas/pedidos`, APIs, widget e redirects legados.

### Sprint 101 - Corrige vencimento Pix Mercado Pago

Objetivo: corrigir a causa real da falha Pix em produção e preservar diagnóstico técnico útil para suporte sem expor erro de operadora ao cliente.

Entregas:

- identificar que o Mercado Pago recusava `date_of_expiration` por formato inválido no Pix/boleto;
- formatar vencimento de Pix e boleto como `yyyy-MM-ddTHH:mm:ss.000-03:00`, com timezone `America/Sao_Paulo`;
- manter mensagens técnicas de data como erro privado em `metadata.failure.technical_message`;
- preservar o código de rastreio UUID da operadora quando vier em `cause.data`;
- manter a tela pública com mensagem amigável para erros técnicos;
- cobrir Pix e boleto em testes para garantir o formato aceito pelo Mercado Pago.

Validação:

- testes controlados na API Mercado Pago confirmando que Pix mínimo, Pix no mesmo valor anual e Pix com novo formato de vencimento geram QR Code;
- cancelamento conferido para os pagamentos diagnósticos criados;
- `npm run build`;
- `php artisan test --filter=PublicCheckoutFlowTest`;
- `php artisan test`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 101 no commit `17fe291`, publicado com sucesso no run `26422281931`. Validações locais passaram com `PublicCheckoutFlowTest` com 17 testes e 94 assertions, `npm run build`, `php artisan test` com 87 testes e 704 assertions, `vendor/bin/pint --dirty` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/checkout`, `/saas/pedidos`, APIs, widget e redirects legados.

### Sprint 102 - Ajusta resumo da conclusão de pagamento

Objetivo: deixar a tela `/checkout/sucesso` mais clara para o cliente depois que o Pix ou outro pagamento é iniciado.

Entregas:

- trocar o rótulo `Código da empresa` por `Pedido`;
- trocar `Status da empresa` por `Status do pagamento`;
- traduzir status técnicos como `pending`, `pending_payment`, `approved`, `rejected` e `checkout_created`;
- remover a operadora do resumo público da conclusão;
- trocar `Meio` por `Forma de pagamento`;
- traduzir formas de pagamento para `Pix`, `Boleto` e `Cartão de crédito`;
- separar visualmente os botões `Acessar painel` e `Voltar ao site`.

Validação:

- `npm run build`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 102 no commit `84c383a`, publicado com sucesso no run `26423505273`. Validação local passou com `npm run build` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/checkout`, `/saas/pedidos`, APIs, widget e redirects legados.

### Sprint 103 - Ajusta copy e economia dos planos

Objetivo: refinar a comunicação dos planos na landing pública, destacando a economia anual sem explicar cálculo comercial para o cliente.

Entregas:

- remover da seção de planos a frase `sempre com o valor mensal em destaque`;
- adicionar tag `Economize 8,2%` no card anual de qualquer plataforma;
- adicionar tag `Economize 10,2%` no card anual de cliente BigShop;
- simplificar o texto auxiliar anual dos cards, removendo a explicação do comparativo com mensal;
- trocar o título da faixa BigShop para `Ainda não tem uma loja online ou quer mudar para uma plataforma mais inteligente?`.

Validação:

- `npm run build`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 103 no commit `0fb2dfe`, publicado com sucesso no run `26424134815`. Validação local passou com `npm run build` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/`, `/checkout`, `/app/widget`, APIs, widget e redirects legados.

### Sprint 104 - Enxuga textos e tooltips do provador

Objetivo: reduzir redundância e espaçamento visual na primeira etapa do provador, corrigindo também textos de tooltip que apareciam com entidades HTML escapadas.

Entregas:

- trocar a introdução da etapa `Suas medidas` para uma frase curta sobre altura, peso e idade opcional;
- remover o aviso redundante que repetia que altura e peso liberam a recomendação inicial;
- simplificar as mensagens de carregamento e liberação da recomendação inicial;
- reduzir espaçamentos e entrelinhas dos blocos informativos do widget;
- corrigir os tooltips de medidas para exibir acentuação correta, como `cabeça`, `chão`, `recomendações` e `peça`.

Validação:

- `node --check backend/public/widget/v1/provador-virtual.js`;
- `npm run build`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 104 no commit `9256077`, publicado com sucesso no run `26424515050`. Validações locais passaram com `node --check backend/public/widget/v1/provador-virtual.js`, `npm run build` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/produto-teste`, widget JS/CSS, APIs e redirects legados.

### Sprint 105 - Mantem aviso unico nas medidas

Objetivo: deixar a primeira etapa do provador com apenas um texto instrutivo antes dos campos.

Entregas:

- remover a frase `Comece com altura e peso. A idade é opcional.`;
- manter apenas `Preencha altura e peso para ver o tamanho inicial.` como aviso antes dos campos de medidas.

Validação:

- `node --check backend/public/widget/v1/provador-virtual.js`;
- `npm run build`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 105 no commit `8a04ed6`, publicado com sucesso no run `26425163585`. Validações locais passaram com `node --check backend/public/widget/v1/provador-virtual.js`, `npm run build` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`; o JS publicado contém `Preencha altura e peso para ver o tamanho inicial.` e não contém mais `Comece com altura e peso`.

### Sprint 106 - Botões personalizados do widget

Objetivo: permitir que a empresa escolha o visual dos botões públicos do provador, ajuste cores de fundo/texto e veja a prévia antes de salvar.

Entregas:

- adicionar `theme.button_style`, `theme.button_background` e `theme.button_text` ao contrato do widget;
- criar estilos públicos `gradient`, `clean`, `outline` e `soft` para os botões `Descubra seu tamanho` e `Tabela de Medidas`;
- aplicar animações de brilho, elevação, sublinhado e preenchimento respeitando redução de movimento do navegador;
- criar em `/app/widget` uma lista vertical de estilos e um box de cores dos botões com prévia em tempo real;
- atualizar testes e documentação do widget.

Validação:

- `node --check backend/public/widget/v1/provador-virtual.js`;
- `php artisan test --filter=Widget`;
- `npm run build`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 106 no commit `68b647a`, publicado com sucesso no run `26600519176`. Validações locais passaram com `node --check backend/public/widget/v1/provador-virtual.js`, `vendor/bin/phpunit --filter Widget`, `vendor/bin/phpunit`, `npm run build`, `vendor/bin/pint --dirty` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/widget`, widget JS/CSS, APIs públicas, SaaS, portal e redirects legados.

### Sprint 107 - Benchmark Zak Sizebay e cadastro BigShop real

Objetivo: cadastrar a Zak como cliente BigShop local/producao, estudar em profundidade o portal do cliente Sizebay da Zak e transformar os achados em plano pratico para igualar a operacao do Provador Virtual.

Entregas:

- cadastrar a Zak localmente e em producao com loja BigShop `124`, dominio `zak.com.br`, feed `https://www.zak.com.br/feed.xml` e token salvo criptografado;
- registrar apenas dados nao sensiveis em documentacao versionada, mantendo tokens/senhas fora do Git;
- estudar o portal Sizebay da Zak em modo somente leitura, incluindo dashboard, produtos, tabelas, modelagens, marcas, categorias, fontes de dados, sync, regras, customizacao, relatorios, pedidos e devolucoes;
- estudar a documentacao Sizebay de script, XML/API de produtos, order tracking e devolucoes, alem da galeria publica de botoes;
- documentar `docs/sizebay_zak_hyper_benchmark.md` com mapa do portal, comparacao de dados, plano seguro de importacao Zak e backlog priorizado;
- ajustar o cliente BigShop para usar `Store-Id` e aceitar retorno paginado/envelopado de produtos.

Validação:

- `vendor/bin/phpunit --filter=BigShopIntegrationTest`;
- `vendor/bin/phpunit`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- validação de produção após deploy.

Status: implementado na Sprint 107 no commit `931d09e`, publicado com sucesso no run `26602780031`. Validações locais passaram com `php -l backend/app/Services/Integrations/BigShopClient.php`, `vendor/bin/phpunit --filter BigShopIntegrationTest`, `vendor/bin/phpunit`, `vendor/bin/pint --dirty` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo páginas públicas, SaaS, portal, widget, APIs, CORS, login demo e go-live readiness.

### Sprint 108 - Botões da galeria Sizebay correta

Objetivo: corrigir a Sprint 106 para alinhar a personalização do widget aos 10 modelos da galeria pública correta da Sizebay, mantendo identidade própria, cores configuráveis e compatibilidade com estilos antigos.

Entregas:

- estudar a galeria correta `https://sizebay-buttons-gallery.vercel.app/` e mapear seus 10 padrões visuais sem copiar assets;
- substituir a seleção do portal por 10 modelos próprios em lista vertical: texto com ícones, ícone lateral, bloco escuro, sublinhado, pílulas, linha central, editorial, pontilhado, bloco claro e selo novo;
- atualizar a prévia do portal para refletir layout, cor de fundo, cor do texto, hover e animações de cada modelo;
- atualizar o widget público para renderizar os 10 estilos com `theme.button_style`, `theme.button_background` e `theme.button_text`;
- manter `gradient`, `clean`, `outline` e `soft` aceitos no backend/widget para compatibilidade com instalações já salvas;
- atualizar testes e documentação registrando que a correção da galeria ficou na Sprint 108.

Validação:

- `php -l` nos arquivos PHP alterados;
- `node --check backend/public/widget/v1/provador-virtual.js`;
- `vendor/bin/phpunit --filter Widget`;
- `vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 108 no commit `482631e`, publicado com sucesso no run `26603841134`. Validações locais passaram com `php -l`, `node --check backend/public/widget/v1/provador-virtual.js`, `vendor/bin/phpunit --filter Widget`, `vendor/bin/phpunit`, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, `git diff --check` e renderização Puppeteer dos 10 modelos. Validação de produção passou com `scripts/validate-production.ps1`, incluindo páginas públicas, SaaS, portal, `/app/widget`, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 109 - Dry-run BigShop Zak com grades

Objetivo: criar uma prévia segura da importação BigShop antes de alimentar a Zak, lendo produtos e `product_grids` com paginação, cruzando por produto e expondo erros por produto sem gravar produtos, variações ou tabelas.

Entregas:

- paginar chamadas BigShop de `products` e `product_grids` com `Store-Id`;
- criar serviço de dry-run que cruza `product_grids` por `produtoid`;
- extrair tamanho de `caracteristicas`, incluindo lista de atributos e texto como `Tamanho: M`;
- retornar contadores de produtos, grades, tamanhos, erros e alertas, além de amostra de produtos;
- registrar evento `dry_run_import` em `integration_events` sem persistir catálogo;
- adicionar botão `Prévia segura` e painel limpo de resultado em `/app/integracoes`;
- cobrir o fluxo com teste de feature garantindo que o dry-run não importa dados.

Validação:

- `php -l` nos arquivos PHP alterados;
- `vendor/bin/phpunit --filter BigShopIntegrationTest`;
- `vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 109 no commit `6aaf8f4`, publicado com sucesso no run `26604636247`. Validações locais passaram com `php -l`, `vendor/bin/phpunit --filter BigShopIntegrationTest`, `vendor/bin/phpunit`, `npm --prefix frontend run build`, `vendor/bin/pint --dirty` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo páginas públicas, SaaS, portal, `/app/integracoes`, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 110 - Tela de sincronização e erros por produto

Objetivo: criar uma tela limpa de sincronização, no padrão operacional observado na Sizebay, para revisar histórico, status, contadores e erros por produto antes de novas importações.

Entregas:

- criar endpoint protegido `GET /api/v1/integrations/sync-history`;
- consolidar eventos `dry_run_import`, `sync_products` e `xml_feed_sync`;
- anexar erros de `integration_events.payload.issues`, erros gerais do evento e erros de `import_jobs`;
- normalizar contadores de produtos, variações, tabelas, erros e alertas;
- criar rota `/app/sincronizacao`;
- adicionar menu `Sincronização` no portal da empresa;
- construir tela list-first com filtros por status/tipo, detalhe da execução, amostra de produtos e erros por produto;
- ampliar validação de produção para cobrir `/app/integracoes` e `/app/sincronizacao`.

Validação:

- `php -l` nos arquivos PHP alterados;
- `vendor/bin/phpunit --filter IntegrationsApiTest`;
- `vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 110 no commit `efe87b8`, publicado com sucesso no run `26605323289`. Validações locais passaram com `php -l`, `vendor/bin/phpunit --filter IntegrationsApiTest`, `vendor/bin/phpunit`, `npm --prefix frontend run build`, `vendor/bin/pint --dirty` e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/integracoes`, `/app/sincronizacao`, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 111 - Regras visuais de importação

Objetivo: permitir que a empresa configure visualmente como campos de categoria, marca, gênero, faixa etária, status e modelagem devem ser interpretados antes de rodar importações reais.

Entregas:

- adicionar `platform_connections.import_rules` como JSON versionado por conexão;
- criar `ImportRuleMapper` para normalizar categoria, marca, gênero, faixa etária, status e modelagem;
- aplicar as regras no dry-run BigShop, no sync BigShop e no sync XML/feed;
- incluir campos mapeados e alertas de regra obrigatória na prévia BigShop;
- criar rota `/app/regras-de-importacao` com lista vertical de regras, editor visual, normalizações e prévia;
- adicionar menu `Regras` no portal da empresa;
- ampliar validação de produção para cobrir `/app/regras-de-importacao`.

Validação:

- `php -l` nos arquivos PHP alterados;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter BigShopIntegrationTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 111 no commit `5d938ba`, publicado com sucesso no run `26606288957`. Validações locais passaram com `php -l`, PHPUnit com `pdo_sqlite/sqlite3` carregados por `-d`, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/regras-de-importacao`, `/app/integracoes`, `/app/sincronizacao`, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 112 - Tabelas flexíveis de medidas

Objetivo: evoluir as tabelas para suportar medida do corpo, medida da peça, sistema de tamanho, ranges explícitos e medidas compostas, preservando compatibilidade com o motor atual de recomendação.

Entregas:

- adicionar `measurement_target`, `size_system` e `range_mode` em `measurement_tables`;
- adicionar `measurements` e `composite_measurements` JSON em `measurement_table_rows`;
- manter colunas legadas de busto/cintura/quadril/altura/peso/comprimento/ombro como base do motor atual;
- sincronizar linhas com payload flexível e medida composta `fit_balance`;
- expor campos flexíveis nos resources e no `config-check` do widget;
- atualizar `/app/tabelas-de-medidas/nova` e edição com base da tabela, sistema de tamanho, modo de range e coluna de medida composta;
- atualizar a listagem de tabelas com base e sistema.

Validação:

- `php -l` nos arquivos PHP alterados;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter MeasurementTablesApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 112 no commit `2872cc7`, publicado com sucesso no run `26606965068`. Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter MeasurementTablesApiTest`, PHPUnit completo com 90 testes e 777 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/tabelas-de-medidas`, `/app/tabelas-de-medidas/nova`, `/app/regras-de-importacao`, `/app/integracoes`, `/app/sincronizacao`, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 113 - Cadastro de modelagens

Objetivo: criar um cadastro próprio de modelagens para que o lojista governe caimentos usados por produtos, tabelas e regras de importação, no padrão limpo observado no portal do cliente Sizebay.

Entregas:

- criar tabela `fit_profiles` com nome, código, tipo de produto, gênero, intensidade, elasticidade, status e metadados;
- popular modelagens padrão por merchant existente: Slim, Regular, Ampla, Solta e Conforto;
- criar API protegida `/api/v1/fit-profiles` com listagem, criação, edição, exclusão segura e contadores de uso;
- bloquear remoção de modelagem em uso por produtos ou tabelas;
- ao alterar o código de uma modelagem, atualizar produtos e tabelas vinculados para preservar o relacionamento;
- criar tela `/app/modelagens` com lista vertical, edição limpa, status e uso;
- adicionar menu `Modelagens` no portal da empresa;
- usar o cadastro nos formulários de produto e tabela de medidas;
- exibir modelagem nas listagens de produtos e tabelas;
- ampliar validação de produção para cobrir `/app/modelagens`.

Validação:

- `php -l` nos arquivos PHP alterados;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter FitProfilesApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter ProductsApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter MeasurementTablesApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 113 no commit `85f7cec`, publicado com sucesso no run `26607795341`. Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter FitProfilesApiTest`, testes focados de produtos/tabelas, PHPUnit completo com 91 testes e 806 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/modelagens`, `/app/tabelas-de-medidas`, `/app/produtos`, `/app/regras-de-importacao`, `/app/integracoes`, `/app/sincronizacao`, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 114 - Publicação e preview do widget

Objetivo: ampliar a personalização do widget com preview desktop/mobile, rascunho separado da configuração publicada, publicar/desfazer e manutenção da galeria completa de botões.

Entregas:

- adicionar campos de rascunho em `widget_installs`: `draft_platform`, `draft_allowed_domains`, `draft_theme` e `draft_is_active`;
- adicionar `published_at` e expor estado publicado/rascunho no recurso de widget;
- manter compatibilidade da API: chamadas antigas continuam publicando direto;
- adicionar `mode=draft`, `mode=publish` e `mode=discard` no `PATCH /api/v1/widget-install`;
- preservar o tema publicado para o widget público até o lojista clicar em publicar;
- permitir desfazer rascunho sem alterar a loja;
- adicionar estado visual `Publicado`, `Rascunho salvo` e `Alterações locais` em `/app/widget`;
- trocar o botão principal para `Salvar rascunho` e adicionar `Publicar` e `Desfazer`;
- ampliar o visualizador com alternância Desktop/Mobile;
- manter os 10 modelos de botões da galeria Sprint 108 no fluxo de personalização.

Validação:

- `php -l` nos arquivos PHP alterados;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter WidgetInstallApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter WidgetAssetTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 114 no commit `a6e1ff1`, publicado com sucesso no run `26608432348`. Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter WidgetInstallApiTest`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter WidgetAssetTest`, PHPUnit completo com 91 testes e 825 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/widget`, páginas públicas, SaaS, portal, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 115 - Analytics de uso e base de IA

Objetivo: usar pedidos, devoluções, trocas e feedback do provador para alimentar a base de aprendizado e melhorar sugestões de tabela sem ajuste automático e sem expor referências de pedido.

Entregas:

- ampliar o endpoint público de sinal comercial para aceitar tamanho comprado, devolvido, tamanho de troca, motivo de devolução, status, quantidade, valor, plataforma de origem e data do evento;
- manter `order_reference` somente como hash em `recommendation_learning_events.payload`;
- calibrar pesos de aprendizado: feedback positivo, carrinho, compra, devolução/troca e outliers críticos;
- criar `MeasurementTableInsightService` para agrupar sinais por tabela de medidas, calcular compras, devoluções/trocas, feedbacks, taxa de retorno, prioridade e ação sugerida;
- expor `measurement_table_insights` e novos KPIs comerciais em `/api/v1/analytics/recommendations`;
- mostrar no portal `/app/analytics` uma lista limpa de sugestões de tabela baseadas em pedidos, devoluções e feedback;
- alimentar o Assistente de IA com contexto de aprendizado compatível com tipo, gênero e modelagem da tabela sugerida;
- mostrar no `/app/assistente` os insights usados e avisos de revisão para o lojista antes de criar o rascunho.

Validação:

- `php -l` nos arquivos PHP alterados;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter RecommendationApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter AnalyticsApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter AiMeasurementAssistantTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- varredura de segredos;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 115 no commit `8277337`, publicado com sucesso no run `26609097848`. Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter RecommendationApiTest`, `--filter AnalyticsApiTest`, `--filter AiMeasurementAssistantTest`, PHPUnit completo com 92 testes e 850 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/analytics`, `/app/assistente`, `/app/widget`, páginas públicas, SaaS, portal, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 116 - Vínculo em lote de tabelas nos produtos

Objetivo: deixar a listagem de produtos mais parecida com a operação limpa observada na Sizebay, permitindo filtrar, selecionar vários produtos e vincular uma tabela sem abrir item por item.

Entregas:

- criar endpoint protegido `PATCH /api/v1/products/bulk-measurement-table`;
- validar escopo do merchant/empresa ativa antes de atualizar produtos e tabela;
- manter o vínculo canônico em `products.measurement_table_id`;
- carregar tabelas de medidas na listagem de produtos;
- adicionar barra compacta e sticky acima do cabeçalho da tabela com busca, filtros, select de tabela, botão `Vincular`, `Todos`, `Limpar` e contador de seleção;
- adicionar coluna de checkbox na listagem;
- habilitar select de vínculo apenas quando houver produto selecionado;
- atualizar docs explicando as formas de vínculo atuais: formulário do produto, importação/sync e vínculo em lote.

Validação:

- `php -l` nos arquivos PHP alterados;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter ProductsApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- varredura de segredos;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 116 no commit `e802ad6`, publicado com sucesso no run `26609619782`. Validações locais passaram com `php -l`, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter ProductsApiTest`, PHPUnit completo com 93 testes e 863 assertions, `npm --prefix frontend run build`, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/produtos`, `/app/produtos/novo`, páginas públicas, SaaS, portal, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 117 - Navegação contextual do logo

Objetivo: ajustar o destino do logo do Provador Virtual conforme o contexto de navegação, evitando que usuários do SaaS ou do portal da empresa saiam para o site ao clicar na marca.

Entregas:

- logo dentro do SaaS aponta para `/saas`;
- logo dentro do portal da empresa aponta para `/app`;
- logo no login e nas páginas públicas aponta para `/`;
- quando o usuário já está na home do site, clique no logo rola para o topo;
- logo do cabeçalho mobile público segue o mesmo comportamento.

Validação:

- `npm --prefix frontend run build`;
- varredura de segredos;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 117 no commit `98c24b8`, publicado com sucesso no run `26609952186`. Validação local passou com `npm --prefix frontend run build`, varredura de segredos e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo login, SaaS, portal da empresa, páginas públicas, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 118 - Personalização visual dos botões com ícones

Objetivo: completar a personalização visual do provador com 12 modelos de botão, catálogo de ícones de medidas e preview mais limpo em modal, alinhando a experiência com a referência da galeria pública da Sizebay.

Entregas:

- tela `/app/widget` passa a ter uma coluna única para Instalação e Visual;
- card Visualizador saiu da coluna lateral e abre em modal por botão `Visualizar`;
- cards de `Código` e `Onde instalar` ficam no final da página;
- catálogo de botões foi ampliado de 10 para 12 opções;
- opções de botão aparecem em grade 3x4 no desktop;
- box abaixo da grade permite escolher cores de fundo/texto e ícones dos botões;
- `PV` e `cm` foram substituídos por ícones configuráveis no preview e no widget público;
- catálogo de ícones inclui cabide, régua, fita métrica, esquadro, camiseta, corpo, tabela e etiqueta;
- checkbox `Animar ícone do cabide` aparece apenas quando o cabide é o ícone do botão `Descubra seu tamanho`;
- animação do cabide usa movimento pendular controlado pelo tema;
- API aceita `button_primary_icon`, `button_secondary_icon` e `button_icon_animation`;
- widget público `/widget/v1/provador-virtual.js` e CSS renderizam os ícones escolhidos e os modelos `gallery_11_icon_chips` e `gallery_12_dual_cards`;
- defaults, seeders, checkout, criação SaaS e ativação BigShop passam a usar cabide/régua como padrão.

Validação:

- `npm --prefix frontend run build`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter "WidgetInstallApiTest|WidgetAssetTest"`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`;
- `vendor/bin/pint --dirty`;
- varredura de segredos;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 118 no commit `4c66327`, publicado com sucesso no run `26610700834`. Validações locais passaram com build frontend, testes focados do widget, PHPUnit completo com 93 testes e 884 assertions, `vendor/bin/pint --dirty`, varredura de segredos e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/widget`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 119 - Integrações em uma coluna com seções

Objetivo: melhorar a tela `/app/integracoes`, removendo o layout com coluna lateral e deixando o fluxo em uma coluna única com seções claras para plataforma, conexão, validação, instalação, dados, snippet e ações.

Entregas:

- substituir a grade lateral de plataformas por uma pilha vertical de seções;
- criar seção `Plataforma` com resumo da integração selecionada, modo de instalação e status;
- manter seletor de plataformas apenas quando houver mais de uma opção disponível e o contrato não estiver travado em BigShop;
- separar credenciais e catálogo na seção `Conexão`;
- separar URL de validação, botão de validação, checklist e resultado na seção `Validação da instalação`;
- agrupar passo a passo, local de instalação e snippet de reload na seção `Instalação no produto`;
- manter `Dados suportados`, `Snippet`, `Ações`, resultado de sincronização, prévia BigShop e ativações como seções independentes;
- ajustar CSS responsivo para a nova estrutura de uma coluna.

Validação:

- `npm --prefix frontend run build`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest`;
- varredura de segredos;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 119 no commit `c366754`, publicado com sucesso no run `26611218335`. Validações locais passaram com build frontend, `IntegrationsApiTest` com 7 testes e 84 assertions, varredura de segredos e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/integracoes`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 120 - Refinamento visual das integrações

Objetivo: corrigir os vazios visuais observados na tela `/app/integracoes` após a reorganização em seções, mantendo o layout limpo e confiável mesmo quando a API não envia todos os metadados da plataforma.

Entregas:

- adicionar fallback de nome, resumo e ícone para a plataforma selecionada;
- corrigir o CSS do resumo para o ícone não herdar regras genéricas de texto;
- adicionar passos padrão quando o guia da plataforma não trouxer passo a passo;
- ocultar `Dados suportados` quando a matriz estiver vazia;
- ocultar `Snippet` quando não houver snippet de integração disponível;
- manter o fluxo em uma coluna única, com seções visuais consistentes e sem cards vazios.

Validação:

- `npm --prefix frontend run build`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --configuration phpunit.xml --filter IntegrationsApiTest`;
- varredura de segredos;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 120 no commit `c1ebf36`, publicado com sucesso no run `26611893093`. Validações locais passaram com build frontend, `IntegrationsApiTest` com 7 testes e 84 assertions, varredura de credenciais e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/integracoes`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 121 - Status e instruções adaptativas de integrações

Objetivo: corrigir a integração Zak/BigShop aparecendo como `Rascunho` mesmo com dados mínimos salvos e fazer a seção `Plataforma` adaptar instruções por plataforma, seguindo o padrão de separação observado na Sizebay entre serviço na PDP, catálogo por XML/API e tracking.

Entregas:

- API passa a devolver status efetivo da integração quando a conexão antiga ainda estiver gravada como `draft`, mas possuir dados mínimos;
- `PATCH /api/v1/integrations/{platform}` não permite que uma conexão com store/feed/token volte a parecer rascunho por envio acidental de status `draft`;
- migração normaliza conexões antigas `draft` com dados suficientes para `configured`, incluindo a Zak/BigShop;
- seção `Plataforma` passa a mostrar conexão exigida, fluxo de catálogo, instalação na página de produto e tracking/aprendizado conforme BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart ou custom;
- label visual de `draft` muda para `Pendente`, evitando leitura de rascunho em integração operacional;
- frontend exibe próximo passo contextual por status e plataforma.

Validação:

- `php -l` nos arquivos PHP alterados;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --filter IntegrationsApiTest`;
- `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`;
- `npm --prefix frontend run build`;
- `vendor/bin/pint --dirty`;
- varredura de credenciais;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 121 no commit `dbbe6b8`, publicado com sucesso no run `26615382578`. Validações locais passaram com `php -l`, `IntegrationsApiTest` com 8 testes e 91 assertions, PHPUnit completo com 94 testes e 891 assertions, build frontend, `vendor/bin/pint --dirty`, varredura de credenciais e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/integracoes`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 122 - Empresa ativa e plataforma da loja

Objetivo: deixar claro onde a plataforma da loja é informada e corrigir a perda da empresa ativa quando o admin do SaaS acessa o portal da empresa e atualiza a página.

Entregas:

- store de autenticação passa a persistir a empresa ativa em `pv_active_company_id`;
- `/me` é carregado antes de renderizar as telas internas do portal/SaaS, evitando montagem de `/app/integracoes` sem contexto de empresa;
- seletor de empresa do portal fica desabilitado enquanto o contexto carrega e mostra placeholder controlado quando ainda não há empresa ativa;
- `/app/integracoes` passa a exibir um bloco `Plataforma da loja`, explicando que a plataforma vem do cadastro da empresa no SaaS, do checkout ou do primeiro acesso do painel;
- contratos BigShop ficam travados como BigShop no portal da empresa;
- empresas não BigShop podem trocar a plataforma operacional diretamente em `/app/integracoes`, sem poder ativar BigShop por conta própria;
- novo endpoint protegido `PATCH /api/v1/merchant/company-platform`;
- testes de API cobrem atualização de plataforma não BigShop, bloqueio de contrato BigShop e bloqueio de autoativação BigShop.

Validação:

- `npm --prefix frontend run build`;
- `php -l` nos arquivos PHP alterados;
- `vendor/bin/pint --dirty`;
- varredura de credenciais;
- `git diff --check`;
- GitHub Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Observação: `php artisan test --filter=MerchantCompanyProfileApiTest` não rodou neste ambiente local porque o PHP disponível está sem driver SQLite (`could not find driver` para `sqlite :memory:`). O workflow remoto validou backend e deploy com sucesso.

Status: implementado na Sprint 122 no commit `de6a1ef`, publicado com sucesso no run `26616086732`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/integracoes`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 123 - Troca protegida de integração BigShop

Objetivo: corrigir a Zak para carregar a integração BigShop no portal quando o admin do SaaS acessa a empresa, separar plataforma operacional de benefício comercial BigShop e criar o fluxo protegido de solicitação de troca para lojas BigShop com desconto.

Entregas:

- `ActiveTenant` passa a resolver o lojista pelo escopo `merchant:<id>` do token para admin/support, sem exigir vínculo pivot do admin com o lojista;
- `merchant_companies.bigshop_discount_active` separa o benefício comercial BigShop da plataforma operacional `platform=bigshop`;
- empresas sem benefício BigShop podem trocar a plataforma operacional diretamente em `/app/integracoes`, inclusive para BigShop sem desconto;
- empresas BigShop com benefício ativo veem o botão `Mudar integração`, aceitam termos em modal e geram `integration_change_requests`;
- SaaS lista solicitações pendentes na visão geral e na edição da empresa, com status, link de pagamento, observações e aplicação da troca quando concluída;
- criada página pública `/termos/troca-bigshop`;
- documentação registra o uso opcional de Google Tag Manager inspirado na documentação pública da Sizebay, com container na PDP e tag HTML disparada apenas em página de produto.

Validação:

- `npm --prefix frontend run build`;
- `php -l` nos arquivos PHP alterados;
- `vendor/bin/pint --dirty`;
- `php -d extension_dir=... -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/phpunit`;
- `git diff --check`;
- GitHub Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 123 nos commits `9e16705`, `c5b90e6` e `49c94e4`, publicado com sucesso no run `26617845717`. Validações locais passaram com PHPUnit completo (102 testes, 927 assertions), build frontend, `php -l`, `vendor/bin/pint --dirty`, varredura de credenciais e `git diff --check`. Validação de produção passou com `scripts/validate-production.ps1`, incluindo `/app/integracoes`, páginas públicas, SaaS, portal da empresa, widget JS/CSS, APIs, CORS, login demo e go-live readiness.

### Sprint 127 - Roadmap Sizebay para evoluir o Provador Virtual

Objetivo: transformar o comparativo local Sizebay x Provador Virtual em um roadmap de implementação, usando todos os itens comparados e mantendo a regra de benchmark real antes de cada sprint.

Regra obrigatória para todas as sprints deste roadmap:

- antes de codar, reler a documentação obrigatória do projeto listada em `docs/README.md`;
- acessar o portal MySizebay da Zak em modo somente leitura nas telas relacionadas à sprint;
- não alterar nada na Sizebay, não publicar, não salvar, não enviar mensagens e não acionar suporte;
- registrar internamente, sem credenciais ou tokens, o que a Sizebay faz bem e o que o Provador fará melhor;
- implementar no Provador Virtual uma versão igual ou superior em clareza, organização, usabilidade e capacidade operacional;
- validar localmente com testes proporcionais ao risco, build frontend quando houver UI, varredura de segredos e `git diff --check`;
- commitar com título `Sprint <numero> - <titulo>`, fazer push, acompanhar GitHub Actions/deploy até o status final e rodar `scripts/validate-production.ps1`;
- seguir para a próxima sprint do roadmap sem parar, exceto se o usuário pedir pausa, mudança de prioridade ou encerramento.

Entregas:

- roadmap criado a partir de todos os grupos do comparativo: navegação, painel, produtos, tabelas, modelagens, marcas, categorias, taxonomias, integrações, sincronização, regras, widget, relatórios, pedidos, devoluções, IA, publicação, usuários, cobrança, auditoria e suporte;
- sprints futuras numeradas a partir da Sprint 128;
- cada sprint contém telas Sizebay a consultar, itens do comparativo cobertos, entregas esperadas e critérios de aceite.

Validação:

- recaptura read-only da Sizebay executada em 2026-05-29;
- varredura de segredos;
- `git diff --check`;
- commit, push e Actions/deploy;
- `scripts/validate-production.ps1` após deploy.

Status: implementado na Sprint 127 no commit `a66236cb`, publicado com sucesso no run `26623161467`. Validação local documental passou com varredura de segredos e `git diff --check`; o GitHub Actions finalizou com deploy e smoke público com sucesso. A validação local pós-deploy com `scripts/validate-production.ps1` foi tentada, mas esta máquina não conseguiu abrir conexão TCP 443 para `provadorvirtual.online`/`108.179.241.241`, então o bloqueio ficou registrado como conectividade local.

### Sprint 128 - Navegação limpa e ajuda contextual

Benchmark Sizebay antes de codar:

- abrir `/dashboard`, menu lateral, cabeçalho e área de suporte/chat;
- observar como a Sizebay reduz ruído visual, organiza o menu e deixa ajuda acessível.

Itens do comparativo cobertos:

- clareza visual;
- menu do portal;
- cabeçalho e identidade;
- linguagem da interface;
- ajuda e suporte.

Entregas:

- reorganizar a navegação do portal da empresa por jornadas: Operação, Catálogo, Provador, Resultados e Conta;
- reduzir termos técnicos visíveis e trocar textos americanizados por linguagem do lojista brasileiro;
- criar ajuda contextual discreta por tela, com links para manual, suporte e próximos passos;
- revisar cabeçalho, estados ativos e atalhos para manter a tela mais limpa do que a Sizebay;
- garantir que admin SaaS continue entendendo claramente quando está no portal da empresa.

Critérios de aceite:

- menus continuam cobrindo todas as funcionalidades atuais;
- a navegação fica mais curta visualmente sem perder acesso;
- cada tela crítica tem ajuda contextual, mas sem blocos longos de texto;
- layout responsivo sem sobreposição.

Status: implementado na Sprint 128 no commit `001275b`, publicado com sucesso no run `26625998268`. O portal da empresa passou a ter navegação agrupada por Operação, Catálogo, Provador, Resultados e Conta, com sidebar escura, estado ativo mais claro, rótulos em linguagem de lojista, ajuda contextual por tela e manual rápido em `/app/ajuda`. O benchmark read-only da Sizebay confirmou o valor de menu curto, tenant visível, tela limpa e suporte acessível; o Provador Virtual aplicou esses aprendizados com próximos passos e manual sem blocos longos. Validação local passou com build frontend, preview em `5174`, varredura de segredos e `git diff --check`; o GitHub Actions finalizou com deploy e smoke público com sucesso. A validação local pós-deploy com `scripts/validate-production.ps1` foi tentada, mas esta máquina novamente não conseguiu abrir conexão HTTPS para produção, então o bloqueio ficou registrado como conectividade local.

### Sprint 129 - Painel de cobertura e prontidão operacional

Benchmark Sizebay antes de codar:

- abrir `/dashboard` e `/products`;
- observar os indicadores de produtos cobertos, ativos, pendentes e o caminho visual para resolver pendências.

Itens do comparativo cobertos:

- painel inicial;
- cobertura do catálogo;
- pendências operacionais;
- prontidão para publicar.

Entregas:

- criar no Painel um placar de cobertura com produtos totais, cobertos, ativos, pendentes, sem tabela, sem modelagem, sem categoria, com erro de sincronização e com instalação não validada;
- adicionar uma lista compacta de próximas ações priorizadas;
- conectar o painel com Sincronização, Produtos, Tabelas, Modelagens, Regras e Publicação;
- mostrar evolução de cobertura por período quando houver dados suficientes;
- criar endpoint agregado para evitar cálculo espalhado no frontend.

Critérios de aceite:

- lojista entende em até uma tela o que falta para publicar;
- todos os números levam para a lista filtrada correspondente;
- resultado funciona para loja vazia, loja em implantação e loja com grande catálogo.

Status: implementado na Sprint 129 no commit `d1a71ad`, publicado com sucesso no run `26627213077`. O Painel recebeu um placar operacional de cobertura com produtos totais, cobertos, ativos, pendentes, inativos, sem tabela, sem modelagem, sem categoria, com erro de sincronização e com instalação pendente. O agregado vem de `GET /api/v1/merchant/overview`, evitando cálculo espalhado no frontend, e os números levam a filtros acionáveis em `/app/produtos` ou ao checklist de publicação. A lista de próximas ações prioriza pendências e a evolução de cobertura aparece quando houver histórico suficiente. Validações locais passaram com build frontend, PHP lint, Pint, testes `MerchantOverviewApiTest|ProductsApiTest`, visual em `5174`, varredura de segredos, `git diff --check`; o GitHub Actions finalizou com deploy e smoke público com sucesso, e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

### Sprint 130 - Produtos com status, colunas e filtros superiores

Benchmark Sizebay antes de codar:

- abrir `/products`;
- observar abas, colunas Product, Category, Chart, Sizes, Brand, Age group, Modeling e filtros.

Itens do comparativo cobertos:

- listagem de produtos;
- colunas da lista;
- filtros;
- produto com categoria, marca, faixa etária, modelagem, tamanhos, tabela e status.

Entregas:

- evoluir a listagem de produtos com colunas de categoria, marca, faixa etária, gênero, modelagem, tamanhos, tabela vinculada e prontidão;
- criar filtros compactos por status, tabela, categoria, marca, gênero, faixa etária, modelagem, origem do dado e erro;
- manter a barra de seleção em massa já planejada/implementada, com seleção de tabela e ações rápidas;
- adicionar contadores por aba: todos, prontos, pendentes, sem tabela, com erro e desativados;
- garantir que a tabela continue densa, rápida e legível.

Critérios de aceite:

- a lista permite encontrar produtos problemáticos mais rápido que na Sizebay;
- filtros não quebram em mobile;
- seleção em massa continua funcionando;
- backend pagina e filtra sem carregar catálogo inteiro no cliente.

Status: implementado na Sprint 130 no commit `84ea4be`, publicado com sucesso no run `26629170275`. A listagem de Produtos passou a usar paginação e filtros no backend, com abas acionáveis para todos, prontos, pendentes, sem tabela, com erro e desativados; a tabela ganhou colunas de categoria, marca, gênero, faixa etária, modelagem, tamanhos, tabela, prontidão, origem e status. O benchmark read-only da Sizebay confirmou a utilidade de abas com contadores, busca, limpar filtros, seleção por checkbox e colunas Product, Category, Chart, Sizes, Brand, Age group e Modeling; o Provador Virtual evoluiu esse padrão com filtros superiores mais completos e prontidão operacional explícita. Validações locais passaram com API paginada, filtros server-side, seleção em massa preservada, build frontend, testes `ProductsApiTest|MerchantOverviewApiTest`, Pint, varredura de segredos, `git diff --check` e inspeção visual desktop/mobile em `5175`; o GitHub Actions finalizou com deploy e smoke público com sucesso, e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

### Sprint 131 - Detalhe do produto, origem dos dados e ativação por produto

Benchmark Sizebay antes de codar:

- abrir `/products/form/new` e detalhes de produto existentes, sem salvar;
- observar blocos de integração, imagem, ativação do provador e tamanhos.

Itens do comparativo cobertos:

- detalhe do produto;
- ativar/desativar provador por produto;
- origem dos dados;
- separação entre dado importado e ajuste manual.

Entregas:

- criar/expandir a tela de detalhe do produto com abas ou seções: resumo, dados importados, tabela/modelagem, tamanhos, mídia, diagnóstico e histórico;
- mostrar origem de cada dado: API, XML/feed, BigShop, regra, IA ou ajuste manual;
- permitir ativar/desativar Provador Virtual e Tabela de Medidas por produto, com auditoria;
- permitir ajustes manuais controlados sem perder a informação importada original;
- exibir diagnóstico acionável do produto.

Critérios de aceite:

- produto com erro mostra causa e ação recomendada;
- alterações manuais não sobrescrevem silenciosamente dados importados;
- ativação por produto reflete no widget/API pública.

Status: implementado na Sprint 131 no commit `1c0fc58`, publicado com sucesso no run `26630698467`. O benchmark read-only da Sizebay confirmou um formulário único com integração, imagem, dados principais, ativação do Virtual Fitting Room, tamanhos da loja e dados do Size & Fit Advisor. O Provador Virtual evoluiu esse padrão com detalhe em abas, origem por campo, snapshot importado, ajustes manuais preservados, ativação individual de Provador Virtual/Tabela de Medidas com auditoria, diagnóstico acionável e API pública respeitando os bloqueios por produto. Validações locais passaram com build frontend, testes `ProductsApiTest|RecommendationApiTest`, Pint, PHP lint, varredura de segredos, `git diff --check` e inspeção visual desktop/mobile em `5175`; o GitHub Actions finalizou com deploy e smoke público com sucesso, e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

### Sprint 132 - Vínculo de tabelas melhor que Sizebay

Benchmark Sizebay antes de codar:

- abrir `/products` e `/table-measurements`;
- observar como a coluna de tabela aparece no produto e como o vínculo fica visível.

Itens do comparativo cobertos:

- vínculo de tabela ao produto;
- visualização da tabela vinculada;
- ação em massa de vincular;
- clareza de produtos sem tabela.

Entregas:

- combinar o melhor dos dois mundos: coluna clara como a Sizebay e ação em massa mais rápida que a Sizebay;
- criar prévia antes de vincular tabela em massa, mostrando produtos afetados e conflitos;
- permitir desfazer vínculo em massa com auditoria;
- adicionar recomendações de tabela por categoria, marca, modelagem e tamanhos;
- expor produtos sem tabela como fila operacional.

Critérios de aceite:

- seleção de produtos e vínculo em massa são rápidos e seguros;
- vínculo aparece imediatamente na linha do produto;
- há confirmação quando a ação substituir vínculo existente.

Status: implementado na Sprint 132 no commit `ea5b06b`, publicado com sucesso no run `26632065139`. O benchmark read-only da Sizebay confirmou coluna `Chart`, seleção por checkbox e clareza de produtos sem tabela; o Provador Virtual evoluiu esse padrão com fila operacional de sem tabela, prévia em massa com conflitos e recomendações, confirmação explícita para substituir vínculos, desfazer por `batch_id`, histórico por produto e auditoria. Validações locais passaram com build frontend, `ProductsApiTest`, Pint, PHP lint, varredura de segredos, `git diff --check` e inspeção visual desktop/mobile em `5175`; `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK` após repetição por reset TCP transitório na primeira tentativa.

### Sprint 133 - Tabelas com importar, exportar e observações

Benchmark Sizebay antes de codar:

- abrir `/table-measurements` e `/modelings`;
- observar botões Export, Import, Create e filtros.

Itens do comparativo cobertos:

- lista de tabelas;
- importação manual;
- exportação de catálogo;
- observações;
- filtros de tabelas.

Entregas:

- adicionar importação e exportação CSV/XLSX para tabelas de medidas;
- criar modelos de planilha para medidas do corpo, medidas da peça e sistema de tamanho;
- padronizar observações por tabela, tamanho e medida;
- adicionar filtros por tipo de tabela, status, categoria, modelagem e uso;
- criar validação visual antes de importar.

Critérios de aceite:

- lojista consegue baixar, editar e reenviar uma tabela;
- erros de importação apontam linha, coluna, campo e correção sugerida;
- exportação respeita filtros aplicados.

Status: implementado na Sprint 133 no commit `3c2dda6`, publicado com sucesso no run `26633856533`. O benchmark read-only da Sizebay em `/table-measurements` confirmou botões Export, Import e Create, busca e filtros simples; `/modelings` não expôs uma tela própria útil nesta sessão, mas reforçou que modelagem precisa aparecer como filtro operacional. O Provador Virtual evoluiu esse fluxo com exportação CSV/XLSX respeitando filtros, modelos editáveis para corpo/peça/misto, importação com prévia visual antes de gravar, erros por linha/coluna/campo/sugestão, bloqueio de tamanhos duplicados, observações por tabela/tamanho/medida e auditoria `measurement_table.imported`. Validações locais passaram com PHPUnit completo (`108 tests`, `1052 assertions`), `MeasurementTablesApiTest`, Pint, build frontend, varredura de segredos, `git diff --check` e inspeção visual desktop/mobile em `5175`; o GitHub Actions finalizou com deploy e smoke público com sucesso, e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

### Sprint 134 - Editor avançado de medidas e variações

Benchmark Sizebay antes de codar:

- abrir `/table-measurements/form/new` e `/modelings/form/new`;
- observar medidas do corpo, medidas da peça, sistema de tamanhos, faixas, medidas compostas, variação personalizada e desativação do provador.

Itens do comparativo cobertos:

- medidas do corpo;
- medidas da peça;
- sistema de tamanhos;
- faixas de medida;
- medidas compostas;
- variação personalizada;
- desativar provador por tabela.

Entregas:

- revisar o editor de tabelas para deixar os tipos de medida mais claros e guiados;
- criar blocos específicos para medida do corpo, medida da peça, sistema de tamanho, faixas e medidas compostas;
- adicionar variação personalizada com exemplos e validação;
- permitir desativar o provador por tabela, mantendo apenas tabela de medidas quando necessário;
- criar prévia da tabela como o consumidor verá no widget.

Critérios de aceite:

- o lojista entende quando usar cada tipo de medida;
- medidas compostas e faixas validam unidade, mínimo, máximo e consistência;
- desativar provador por tabela afeta corretamente o produto vinculado.

Status: implementado na Sprint 134 no commit `d816f41`, publicado com sucesso no run `26635156508`. O benchmark read-only da Sizebay em `/table-measurements/form/new` e `/modelings/form/new` confirmou formulario longo de criacao com campos principais, medicao do corpo/peca, sistema de tamanho, ranges, medida composta, variacao personalizada e controle para desativar o provador. O Provador Virtual evoluiu esse fluxo com editor em blocos guiados, validacao de unidade/minimo/maximo, variacoes customizadas, preview publico do widget e modo por tabela para manter apenas Tabela de Medidas quando o provador estiver desativado. Validações locais passaram com PHPUnit completo (`109 tests`, `1063 assertions`), testes focados de tabelas/recomendacao, Pint, PHP lint, build frontend, varredura de segredos, `git diff --check` e inspecao visual desktop/mobile em `5175`; o GitHub Actions finalizou com deploy e smoke publico com sucesso, e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

### Sprint 135 - Modelagens com diagnóstico e correção guiada

Benchmark Sizebay antes de codar:

- abrir `/modelings`, `/modelings/form/new` e tela de sincronização com erros de modelagem;
- observar como a Sizebay aponta modelagem ausente.

Itens do comparativo cobertos:

- modelagens;
- erros de modelagem;
- diagnóstico por produto;
- sugestão de correção.

Entregas:

- transformar modelagens em entidade central para diagnóstico, regras e IA;
- mostrar produtos sem modelagem ou com modelagem incompatível;
- criar ação rápida para criar modelagem a partir de produtos afetados;
- sugerir modelagem por categoria, marca, gênero, faixa etária e histórico;
- registrar impacto da modelagem nas recomendações.

Critérios de aceite:

- erro de modelagem nunca aparece sozinho: sempre tem sugestão de correção;
- modelagem criada pode ser aplicada em massa;
- alterações ficam auditadas.

Status: implementado na Sprint 135 no commit `9a69f27`, publicado com sucesso no run `26636901205`. O benchmark read-only da Sizebay em `/modelings`, `/modelings/form/new`, `/settings/sync` e `/settings/sync/importation-rules` mostrou que a tela de modelagens reaproveita Measurement Table, enquanto a tela de sincronização aponta erros `[API] 500: "Modeling not found"` por produto com categoria, marca, gênero, faixa etária, tamanhos e ação de expansão. O Provador Virtual evoluiu esse fluxo com diagnóstico dedicado de modelagens, grupos de correção com sugestão sempre presente, criação e aplicação em massa de modelagem, auditoria por lote, histórico no produto, metadados para regras/IA e contexto de modelagem registrado no config-check/recomendação pública. Validações locais passaram com PHPUnit completo (`111 tests`, `1097 assertions`), testes focados de modelagens/recomendação, Pint, PHP lint, build frontend, varredura de segredos, `git diff --check` e inspeção visual desktop/mobile em `5177`; o GitHub Actions finalizou com deploy e smoke público com sucesso, e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

### Sprint 136 - Marcas locais e marcas normalizadas

Benchmark Sizebay antes de codar:

- abrir `/brands` e `/sizebay-brands`;
- observar Name, Associated brand, importação, exportação e criação.

Itens do comparativo cobertos:

- marcas do lojista;
- marca normalizada;
- mapeamento local para marca global;
- importar e exportar cadastros.

Entregas:

- criar tela de Marcas no portal da empresa;
- mapear marca importada para marca normalizada do Provador Virtual;
- permitir criar, editar, importar, exportar e mesclar marcas;
- sugerir normalização por nome parecido, domínio, feed e histórico;
- usar marca normalizada nas regras, IA, relatórios e filtros.

Critérios de aceite:

- produtos importados agrupam marcas duplicadas corretamente;
- lojista pode revisar sugestões antes de aplicar;
- regras e filtros usam marca normalizada sem perder o nome original.

Status: implementado na Sprint 136 no commit `e5c3cc2`, publicado com sucesso no run `26638565143`. O benchmark read-only da Sizebay em `/brands` e `/sizebay-brands` mostrou uma gestão simples de marca local com Associated brand, importação/exportação e cadastro de marca global; o Provador Virtual evoluiu esse padrão com tela dedicada `/app/marcas`, descoberta automática das marcas vindas dos produtos, sugestões revisáveis de normalização, criação/edição/importação/exportação/mescla, preservação da marca original e aplicação da marca normalizada em metadados para regras, IA, relatórios, recomendação pública e filtros de produtos. Validações locais passaram com PHPUnit completo (`114 tests`, `1149 assertions`), testes focados de marcas/produtos/recomendação/importações/integrações, Pint, PHP lint, build frontend, varredura de segredos, `git diff --check` e inspeção visual desktop/mobile em `5177`; o GitHub Actions finalizou com deploy e smoke público com sucesso, e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK` incluindo `/app/marcas` e `GET /api/v1/brands`.

### Sprint 137 - Categorias locais e taxonomia do Provador

Benchmark Sizebay antes de codar:

- abrir `/categories` e `/sizebay-categories`;
- observar categoria local, tipo, subcategorias e traduções.

Itens do comparativo cobertos:

- categorias do lojista;
- categoria normalizada;
- taxonomia global;
- traduções de categorias;
- tipo da categoria.

Entregas:

- criar tela de Categorias no portal da empresa;
- mapear categoria importada para taxonomia normalizada do Provador;
- criar árvore inicial de categorias, subcategorias, tipo, gênero e faixa etária;
- permitir importação/exportação de categorias;
- preparar campo de tradução como base futura, sem poluir a UX brasileira.

Critérios de aceite:

- produto importado pode ser filtrado por categoria original e normalizada;
- categorias sem mapeamento aparecem como pendência operacional;
- taxonomia alimenta regras, modelagens, IA e relatórios.

Status: implementado na Sprint 137 no commit `8c4d505`, publicado com sucesso no run `26640876246`. O benchmark read-only da Sizebay em `/categories` e `/sizebay-categories` mostrou a separação entre categorias locais, tipo de categoria, subcategorias e traduções da taxonomia global; o Provador Virtual evoluiu esse padrão com tela dedicada `/app/categorias`, descoberta automática das categorias vindas dos produtos, árvore inicial de taxonomia, sugestões revisáveis, edição de tipo/gênero/faixa etária/tradução, importação/exportação/mescla e aplicação em produtos preservando a categoria original. Validações locais passaram com PHPUnit completo (`117 tests`, `1201 assertions`), testes focados de categorias/produtos/recomendação/importações/integrações, Pint, PHP lint, build frontend, varredura de segredos, `git diff --check` e inspeção visual desktop/mobile em `5177`; o GitHub Actions finalizou com deploy e smoke público com sucesso, e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK` incluindo `/app/categorias`, redirect legado e `GET /api/v1/categories`.

### Sprint 138 - Taxonomia inteligente e base de aprendizado

Benchmark Sizebay antes de codar:

- revisar `/sizebay-categories`, `/sizebay-brands`, regras e relatórios;
- observar como taxonomias sustentam diagnóstico e recomendação.

Itens do comparativo cobertos:

- taxonomia normalizada;
- marca normalizada;
- categorias traduzíveis;
- base de IA;
- qualidade de recomendação.

Entregas:

- criar base interna de taxonomia com versionamento;
- alimentar IA e regras com categoria, marca, gênero, faixa etária, modelagem e sistema de tamanho;
- criar fila de revisão para mapeamentos sugeridos pela IA;
- medir confiança da sugestão e impacto nos produtos afetados;
- registrar aprendizados sem expor dados sensíveis.

Critérios de aceite:

- IA não aplica mapeamento crítico sem confirmação quando confiança for baixa;
- toda sugestão mostra motivo;
- mapeamentos aprovados melhoram próximas importações.

Status: implementado na Sprint 138 nos commits `9bf85d9` e `66d3391`, publicado com sucesso no run `26644028670`. O primeiro run `26643813668` falhou no deploy remoto porque o MySQL recusou o nome automático longo de um índice da migration; a correção encurtou os índices e tornou a migration tolerante ao estado parcial criado pela falha. O benchmark read-only da Sizebay em `/sizebay-categories`, `/sizebay-brands`, regras de importação e relatórios confirmou taxonomia global, marcas com estatísticas, regras condicionais e filtros por marca/categoria/gênero/faixa etária/dispositivo/período; o Provador Virtual evoluiu esse padrão com versionamento de taxonomia, fila `/app/taxonomia`, sugestões de categoria e marca com confiança, motivo, impacto, contexto de gênero/faixa etária/modelagem/sistema de tamanho, confirmação obrigatória para baixa confiança e eventos de aprendizado sem dados sensíveis. Validações locais passaram com `php -l`, `TaxonomyIntelligenceApiTest`, suíte focada de taxonomia/categorias/marcas/produtos/recomendação/importações/integrações/analytics, PHPUnit completo (`120 tests`, `1242 assertions`), Pint, build frontend, `git diff --check`, varredura de segredos e validação visual headless desktop/mobile em `5177` com backend em `8002`; `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`, incluindo `/app/taxonomia`, redirect legado e `GET /api/v1/taxonomy/intelligence`.

### Sprint 139 - Integrações por plataforma melhores que Sizebay

Benchmark Sizebay antes de codar:

- abrir Settings/Data Sources da captura autenticada e guias públicos da Sizebay;
- comparar instruções de plataforma, fonte de dados e instalação.

Itens do comparativo cobertos:

- escolha de plataforma;
- fontes de dados;
- XML/feed;
- API;
- instruções por plataforma;
- cadastro de plataforma no cliente.

Entregas:

- transformar `/app/integracoes` em experiência 100% adaptada por plataforma;
- criar guias específicos para BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart, XML/feed, API e personalizada;
- mostrar somente campos relevantes para a plataforma escolhida;
- separar catálogo, instalação do widget e rastreamento de pedidos/devoluções;
- criar matriz de dados suportados por plataforma.

Critérios de aceite:

- lojista não vê campo irrelevante para sua plataforma;
- cada plataforma tem passo a passo próprio;
- admin SaaS consegue ver o estado técnico e comercial da integração.

Status: implementado na Sprint 139 no commit `3ae241b`, publicado com sucesso no run `26647308642` e validado em produção com `scripts/validate-production.ps1`. `/app/integracoes` agora usa metadados por plataforma para mostrar somente campos relevantes, inclui plataformas dedicadas `xml_feed` e `api`, separa conexão, catálogo, instalação, dados suportados, snippet e ações, e o SaaS passa a expor `integration_state` com estado técnico/comercial sem revelar segredos. Validações locais passaram com `php -l`, suíte focada de integrações/SaaS/perfil/widget/checkout/troca de integração, PHPUnit completo (`121 tests`, `1268 assertions`), Pint, build frontend, `git diff --check`, varredura de segredos e validação visual headless em `/app/integracoes` com XML/feed e API; a validação de produção confirmou páginas públicas, SaaS, portal, widget, APIs, CORS, login demo, go-live readiness, `API integrations OK` e taxonomia.

### Sprint 140 - BigShop com governança comercial superior

Benchmark Sizebay antes de codar:

- revisar Data Sources e fluxo de plataforma da Sizebay;
- comparar com o modelo BigShop do Provador, que precisa ser melhor por ser diferencial próprio.

Itens do comparativo cobertos:

- integração BigShop;
- benefício BigShop;
- troca de integração;
- termos e governança;
- solicitação para SaaS.

Entregas:

- polir a experiência BigShop no portal para deixar explícito desconto, benefício e limitações de troca;
- melhorar o modal de troca protegida com resumo financeiro, termos, aceite e próximos passos;
- criar tela SaaS dedicada de solicitações de troca com filtros, status e histórico;
- registrar auditoria completa da solicitação, aceite e aplicação;
- adicionar mensagens transacionais para solicitação, pagamento pendente e troca concluída.

Critérios de aceite:

- loja BigShop entende por que está travada e como solicitar troca;
- SaaS consegue operar a solicitação sem acessar banco;
- nenhum dado sensível aparece no portal.

Status: implementada localmente na Sprint 140. O portal `/app/integracoes` agora explica o benefício BigShop, mostra acompanhamento da solicitação e abre modal protegido com resumo financeiro, termos, aceite e próximos passos. O SaaS ganhou `/saas/trocas-bigshop` para operar solicitações com filtros, status, histórico de auditoria, link de pagamento, observações internas e aplicação da troca sem acessar banco. A API registra auditoria de solicitação, aceite, atualização, pagamento, conclusão e aplicação; e-mails transacionais novos cobrem `troca_bigshop_solicitada`, `troca_bigshop_pagamento_pendente` e `troca_bigshop_concluida`. Validações locais passaram com `php -l`, `IntegrationChangeRequestApiTest`, suíte focada BigShop/integrações/SaaS/e-mails/checkout, PHPUnit completo (`121 tests`, `1285 assertions`), Pint e build frontend. O commit, deploy green e validação de produção serão registrados após publicação.

### Sprint 141 - API, webhook, GTM e validação de instalação

Benchmark Sizebay antes de codar:

- revisar Settings/Data Sources, documentação pública Sizebay API, XML/feed, Shopify e rastreamento;
- observar como a Sizebay separa catálogo, widget e eventos.

Itens do comparativo cobertos:

- API;
- webhook;
- validação de instalação;
- Google Tag Manager;
- código de instalação;
- segurança de credenciais.

Entregas:

- criar guias e exemplos de API por plataforma;
- permitir teste de webhook com logs recentes, mascaramento e rotação de segredo;
- criar guia GTM opcional para lojas sem app/tema simples;
- melhorar validação de instalação para mostrar script encontrado, container encontrado, produto, variação, SKU e botões renderizados;
- criar estado de diagnóstico por URL validada.

Critérios de aceite:

- validação informa exatamente o que falta na página de produto;
- segredo nunca aparece em texto puro após salvo;
- GTM é apresentado como alternativa, não como padrão quando há integração nativa.

Status: planejada.

### Sprint 142 - Posicionamento visual do botão na página de produto

Benchmark Sizebay antes de codar:

- revisar Settings/Service e configurações de posição antes/depois/dentro de seletor;
- observar uso de âncora, tag e seletor.

Itens do comparativo cobertos:

- local do botão na página;
- seletor/âncora CSS;
- pré-visualização da instalação;
- validação de container.

Entregas:

- criar configurador de posição do widget com opções antes, depois e dentro de um seletor;
- adicionar teste visual do seletor na URL da página de produto;
- sugerir seletores comuns por plataforma;
- validar se o container existe antes do script carregar;
- salvar configurações por plataforma/tema.

Critérios de aceite:

- lojista consegue testar posição antes de publicar;
- seletor inválido bloqueia publicação com mensagem clara;
- widget não duplica botões.

Status: planejada.

### Sprint 143 - Histórico de sincronização e contadores por execução

Benchmark Sizebay antes de codar:

- abrir Settings/Sync na captura autenticada;
- observar histórico, totais, inseridos, atualizados, desconhecidos e erros.

Itens do comparativo cobertos:

- histórico de sincronização;
- contadores por execução;
- status da importação;
- logs operacionais.

Entregas:

- padronizar histórico de sincronização por execução;
- exibir totais, inseridos, atualizados, ignorados, desconhecidos, com erro e sem alteração;
- permitir comparar duas execuções;
- criar timeline compacta de sincronizações;
- registrar origem: manual, agendada, BigShop, XML/feed, API ou webhook.

Critérios de aceite:

- cada execução tem resumo e detalhe;
- erros levam para produto ou regra relacionada;
- histórico continua performático com muitos eventos.

Status: planejada.

### Sprint 144 - Erros por produto com ações de correção

Benchmark Sizebay antes de codar:

- abrir Settings/Sync e erros por produto na captura autenticada;
- observar permalink, contexto e detalhes de tamanhos.

Itens do comparativo cobertos:

- erros por produto;
- detalhes de tamanho;
- ação de correção;
- diagnóstico de modelagem, categoria, marca e tabela.

Entregas:

- criar lista de erros por produto com severidade, causa e ação recomendada;
- adicionar botões: vincular tabela, criar modelagem, revisar categoria, revisar marca, ignorar com motivo e reprocessar;
- mostrar contexto do dado recebido: produto, variação, SKU, tamanhos, categoria, marca e URL;
- criar agrupamento por causa raiz;
- permitir exportar erros.

Critérios de aceite:

- nenhum erro crítico fica sem próxima ação;
- lojista consegue resolver erros em lote;
- resolução atualiza cobertura do painel.

Status: planejada.

### Sprint 145 - Simulação de importação e impacto das regras

Benchmark Sizebay antes de codar:

- revisar Settings/Importation Rules e Sync;
- observar lógica visual de condições e ações.

Itens do comparativo cobertos:

- simulação antes de importar;
- regras de importação;
- ações das regras;
- impacto de regras no catálogo.

Entregas:

- expandir simulação de importação para plataformas além da BigShop;
- mostrar antes/depois de cada regra aplicada;
- calcular produtos afetados por regra antes de salvar;
- permitir testar regra contra amostra real do catálogo;
- criar aviso para regras conflitantes ou muito amplas.

Critérios de aceite:

- lojista entende o impacto antes de publicar uma regra;
- regras conflitantes são bloqueadas ou sinalizadas;
- simulação não altera dados permanentes.

Status: planejada.

### Sprint 146 - Galeria de botões e personalização mais polida

Benchmark Sizebay antes de codar:

- abrir galeria pública de botões Sizebay e Settings/Buttons Customization;
- comparar modelos, animações, prévia, publicar e desfazer.

Itens do comparativo cobertos:

- personalização de botões;
- galeria de modelos;
- ícones dos botões;
- animação;
- cores;
- pré-visualização;
- publicar/desfazer.

Entregas:

- revisar os 12 modelos do Provador com acabamento visual mais refinado;
- melhorar estados de hover, foco, carregamento e desabilitado;
- adicionar prévia lado a lado com contexto de página de produto;
- reforçar publicar, desfazer e rascunho;
- validar animação do cabide, acessibilidade e `prefers-reduced-motion`.

Critérios de aceite:

- cada modelo parece pronto para produção;
- botão mantém legibilidade com cores extremas;
- publicação/desfazer é auditável.

Status: planejada.

### Sprint 147 - Editor completo do modal do Provador

Benchmark Sizebay antes de codar:

- abrir Settings/VFR Customization na captura autenticada;
- observar separação entre botões e experiência completa do provador.

Itens do comparativo cobertos:

- customização do provador;
- modal do provador;
- pré-visualização desktop/mobile;
- publicar/desfazer.

Entregas:

- criar editor dedicado para o modal do Provador Virtual;
- permitir personalizar cores, cantos, tipografia controlada, logo, textos, etapas e estilo da tabela;
- criar prévia desktop/mobile do modal completo;
- separar rascunho e versão publicada;
- validar contraste e acessibilidade.

Critérios de aceite:

- lojista diferencia claramente personalização do botão e do modal;
- mudanças só afetam produção após publicar;
- visual gerado não quebra o widget público.

Status: planejada.

### Sprint 148 - Relatórios de uso do widget

Benchmark Sizebay antes de codar:

- abrir Reports/Usage Data na captura autenticada;
- observar impressões, recomendações, consultas de tabela, taxa de uso, período e dispositivo.

Itens do comparativo cobertos:

- relatório de uso;
- uso por dispositivo;
- funil do widget;
- filtros por período.

Entregas:

- criar funil do widget: impressões, cliques, abertura do provador, recomendação gerada, consulta de tabela e conversão quando houver pedido;
- segmentar por computador, celular e tablet;
- criar filtros por período, produto, categoria, marca, tabela e plataforma;
- mostrar taxa de uso e evolução temporal;
- preparar eventos públicos necessários no widget.

Critérios de aceite:

- relatório prova uso real do widget;
- eventos são idempotentes e não duplicam contagem;
- filtros carregam rápido.

Status: planejada.

### Sprint 149 - Ranking de produtos e relatório de recomendações

Benchmark Sizebay antes de codar:

- abrir Reports/Recommendations e Usage Data na captura autenticada;
- observar ranking de produtos e recomendações emitidas.

Itens do comparativo cobertos:

- ranking de produtos;
- recomendações emitidas;
- produtos com maior uso;
- produtos com maior erro.

Entregas:

- criar ranking de produtos por impressões, cliques, recomendações, consultas, erros, devoluções e taxa de uso;
- criar relatório de recomendações emitidas com tamanho recomendado, tabela usada, confiança e origem;
- permitir drill-down para produto e tabela;
- destacar produtos de alto tráfego sem tabela ou com alto erro;
- exportar relatórios.

Critérios de aceite:

- lojista identifica onde o Provador gera mais valor;
- relatório ajuda priorizar correções;
- dados não expõem informação pessoal desnecessária.

Status: planejada.

### Sprint 150 - Pedidos no portal da empresa

Benchmark Sizebay antes de codar:

- abrir Orders e Reports/Orders Overview na captura autenticada;
- observar status, data, quantidade, preço e uso do assistente.

Itens do comparativo cobertos:

- pedidos;
- visão geral de pedidos;
- relação pedido x uso do assistente;
- rastreamento comercial.

Entregas:

- levar pedidos relevantes para o portal da empresa;
- mostrar status, data, itens, valor, produto, tamanho comprado e se houve uso do Provador;
- criar indicadores de conversão assistida;
- integrar pedidos por plataforma quando disponível;
- criar fallback de importação CSV quando não houver API.

Critérios de aceite:

- lojista consegue ver pedidos relacionados ao Provador;
- dados sensíveis são minimizados;
- pedido alimenta relatórios e IA.

Status: planejada.

### Sprint 151 - Devoluções e trocas com mapeamento de motivos

Benchmark Sizebay antes de codar:

- abrir Returns e Reports/Returns na captura autenticada;
- observar upload CSV e mapeamento de método/motivo.

Itens do comparativo cobertos:

- devoluções;
- upload de devoluções;
- motivo de troca/devolução;
- aprendizado com resultado real.

Entregas:

- criar importação de devoluções por CSV/XLSX e API quando disponível;
- mapear motivo, tamanho comprado, tamanho ideal, produto, pedido e status;
- criar assistente de mapeamento de colunas;
- mostrar devoluções relacionadas ao uso ou não uso do Provador;
- alimentar relatórios e IA.

Critérios de aceite:

- arquivo com erro aponta linha e coluna;
- motivo de devolução fica normalizado;
- dados alimentam indicadores sem expor informação pessoal desnecessária.

Status: planejada.

### Sprint 152 - Aprendizado com pedidos, devoluções e feedback

Benchmark Sizebay antes de codar:

- revisar Reports, Orders, Returns e documentação de rastreamento;
- observar como dados reais retroalimentam recomendação.

Itens do comparativo cobertos:

- aprendizado com dados reais;
- feedback do consumidor;
- pedidos/devoluções/feedback para IA;
- melhoria de recomendações.

Entregas:

- criar pipeline de aprendizado com pedidos, devoluções, trocas, feedback e uso do widget;
- detectar padrões por produto, tabela, categoria, marca e modelagem;
- criar sugestões de ajuste de tabela com explicação;
- separar aprendizado automático de recomendação aplicada;
- aplicar regras LGPD, retenção e anonimização.

Critérios de aceite:

- IA explica por que sugeriu ajuste;
- sugestão não altera tabela sem aprovação;
- dados sensíveis são minimizados e auditáveis.

Status: planejada.

### Sprint 153 - Assistente IA para criação e revisão de tabelas

Benchmark Sizebay antes de codar:

- revisar Measurement Guide, Modelings, Products e relatórios;
- observar como a Sizebay organiza dados para que o Provador faça melhor com IA.

Itens do comparativo cobertos:

- assistente para o lojista;
- sugestão de tabela;
- base de aprendizado;
- revisão humana.

Entregas:

- evoluir o Assistente IA para sugerir criação/revisão de tabelas por categoria, marca, modelagem e dados reais;
- criar fluxo de revisão guiada antes de aplicar;
- mostrar confiança, dados usados e riscos;
- gerar tabela inicial com medidas do corpo, peça, sistema de tamanho e faixas quando aplicável;
- criar modo "explicar para o lojista" com linguagem simples.

Critérios de aceite:

- assistente nunca aplica mudança crítica sem confirmação;
- sugestão inclui justificativa;
- lojista consegue comparar tabela atual e sugerida.

Status: planejada.

### Sprint 154 - Publicação e checklist conectado a dados reais

Benchmark Sizebay antes de codar:

- revisar Dashboard, Settings/Service, Settings/Data Sources e validações;
- observar como cobertura e configuração indicam maturidade operacional.

Itens do comparativo cobertos:

- publicação;
- prontidão;
- go-live;
- instalação validada;
- cobertura do catálogo.

Entregas:

- conectar tela de Publicação aos dados reais do Painel, Sincronização, Widget e Produtos;
- criar checklist com bloqueios, alertas e recomendações;
- diferenciar pronto, pronto com avisos e bloqueado;
- gerar relatório de publicação para o lojista;
- criar botão para revalidar tudo.

Critérios de aceite:

- publicação não é liberada quando item crítico está quebrado;
- avisos explicam impacto;
- checklist tem links diretos para resolver.

Status: planejada.

### Sprint 155 - Usuários, permissões e contexto de empresa

Benchmark Sizebay antes de codar:

- revisar portal cliente e comportamento de conta;
- comparar com admin SaaS acessando portal da empresa.

Itens do comparativo cobertos:

- usuários;
- seletor de empresa para admin;
- conta;
- permissões;
- contexto ativo.

Entregas:

- endurecer permissões por papel no portal da empresa;
- revisar seletor de empresa, persistência e troca de contexto;
- adicionar trilha visual quando admin SaaS estiver impersonando/acessando empresa;
- criar logs de ações sensíveis por usuário;
- revisar usuários da empresa com convites e status.

Critérios de aceite:

- refresh não perde empresa ativa;
- usuário sem permissão não vê ações sensíveis;
- ações críticas registram ator e contexto.

Status: planejada.

### Sprint 156 - Cobranca, plano e autonomia do cliente

Benchmark Sizebay antes de codar:

- abrir Billing/Charges na captura autenticada;
- observar autonomia de cobrança no portal cliente.

Itens do comparativo cobertos:

- cobrança;
- plano;
- checkout;
- diferença entre portal cliente e Admin.

Entregas:

- criar área de Plano e Cobrança no portal da empresa;
- mostrar plano atual, plataforma, benefício BigShop, status comercial e próximos vencimentos quando aplicável;
- permitir acessar faturas/links de pagamento gerados pelo SaaS;
- mostrar histórico de solicitações comerciais;
- manter ações financeiras críticas controladas pelo Admin.

Critérios de aceite:

- lojista entende plano e cobrança sem acessar Admin;
- BigShop com desconto fica claro;
- links de pagamento são seguros e auditados.

Status: planejada.

### Sprint 157 - Auditoria, termos e segurança operacional

Benchmark Sizebay antes de codar:

- revisar histórico de sincronização, conta, termos e operações sensíveis;
- comparar como eventos críticos ficam rastreáveis.

Itens do comparativo cobertos:

- termos e governança;
- auditoria;
- segurança de credenciais;
- logs de ações.

Entregas:

- criar trilha de auditoria para publicar widget, desfazer publicação, mudar integração, vincular tabela, alterar regra, alterar tabela, importar dados e aceitar termos;
- mascarar e rotacionar segredos onde aplicável;
- centralizar aceites de termos por empresa, usuário, IP e data;
- criar tela SaaS de auditoria por empresa;
- adicionar exportação de auditoria.

Critérios de aceite:

- ação crítica sempre tem ator, data, antes/depois e contexto;
- credencial sensível não aparece em texto puro;
- auditoria não expõe dados pessoais desnecessários.

Status: planejada.

### Sprint 158 - Base de conhecimento e suporte contextual

Benchmark Sizebay antes de codar:

- abrir Support, manuais e documentação pública Sizebay;
- observar como suporte e manual reduzem dúvidas do cliente.

Itens do comparativo cobertos:

- ajuda e suporte;
- documentação dentro da tela;
- manual/base de conhecimento;
- instruções por plataforma.

Entregas:

- criar base de conhecimento do Provador no portal;
- adicionar artigos por plataforma, widget, tabelas, modelagens, regras, sincronização, relatórios e cobrança;
- criar busca interna;
- ligar ajuda contextual de cada tela ao artigo correspondente;
- criar CTA de suporte com contexto da tela e empresa.

Critérios de aceite:

- cada tela crítica tem artigo relacionado;
- suporte recebe contexto sem o lojista precisar explicar tudo;
- base não contém credenciais nem dados sensíveis.

Status: planejada.

### Sprint 159 - Polimento final Sizebay-plus do portal

Benchmark Sizebay antes de codar:

- percorrer todas as telas Sizebay estudadas e as telas equivalentes do Provador;
- comparar clareza, densidade, navegação, estados vazios, textos, loading e responsividade.

Itens do comparativo cobertos:

- densidade de informação;
- operação por etapas;
- limpeza visual geral;
- linguagem;
- consistência entre telas.

Entregas:

- revisar todo o portal para reduzir ruído visual;
- padronizar estados vazios, carregamento, erro, sucesso e permissões;
- revisar textos para português claro e objetivo;
- validar responsividade e ausência de sobreposição;
- criar checklist visual Sizebay-plus para futuras telas.

Critérios de aceite:

- telas críticas parecem uma plataforma única, limpa e organizada;
- estados vazios orientam ação;
- nenhuma tela tem texto ou card desnecessário;
- build, testes, smoke e validação de produção passam.

Status: planejada.

### Sprint 160 - Migração Sizebay e importação assistida de clientes

Contexto:

- clientes que saem da Sizebay precisam trazer o máximo possível de configuração operacional para o Provador Virtual;
- a Zak é o piloto real para validar o fluxo, usando os dados já conhecidos da BigShop/loja `124` e os materiais que o cliente autorizar fornecer;
- qualquer acesso ao portal Sizebay continua somente leitura, sem salvar, publicar, alterar dados, contatar suporte ou registrar credenciais.

Benchmark Sizebay antes de codar:

- revisar Measurement Guide/Table Measurements, Products, Brands, Categories, Modelings, Settings/Data Sources, Settings/Sync, Importation Rules e Reports;
- confirmar quais exportações o cliente consegue baixar do portal Sizebay e quais dados precisam vir de arquivos fornecidos pelo cliente;
- comparar os dados exportados com o feed/API BigShop da Zak para medir cobertura, conflitos e campos ausentes antes de qualquer importação final.

Itens do comparativo cobertos:

- migração assistida de clientes Sizebay;
- tabelas de medidas;
- vínculos produto-tabela;
- modelagens, marcas e categorias;
- regras de importação;
- relatórios e histórico agregado;
- dados de aprendizado com minimização LGPD.

Entregas:

- criar fluxo de migração Sizebay com upload de pacote CSV/XLSX/JSON/ZIP e prévia antes de gravar;
- aceitar como fontes arquivos exportados pelo cliente, capturas estruturadas autorizadas e dados próprios da loja, como feed/API BigShop da Zak;
- criar parsers e mapeamentos para tabelas de medidas, linhas por tamanho, medidas corporais, medidas da peça, sistema de tamanho, unidade, ranges, observações e status;
- importar ou reconciliar produtos, variantes, SKUs, links públicos, imagens, grade/tamanho, categoria, marca, gênero, faixa etária, modelagem e vínculo com tabela;
- importar marcas, categorias, modelagens e regras de importação como sugestões revisáveis, aproveitando a taxonomia inteligente da Sprint 138;
- gerar dry-run com criados, atualizados, ignorados, conflitos, baixa confiança, campos ausentes e produtos afetados;
- criar fila de revisão para conflitos de tabela, categoria, marca, modelagem, sistema de tamanho e associação produto-tabela;
- permitir aplicar lote somente após confirmação, com `batch_id`, auditoria e rollback/desfazer do lote;
- registrar aprendizados aprovados para melhorar próximas importações, sem expor dados sensíveis;
- para relatórios da Sizebay, importar apenas agregados permitidos pelo cliente, como uso por produto/categoria/dispositivo/período e devoluções normalizadas, evitando PII e mantendo pedido como hash quando necessário;
- bloquear a importação de segredos, tokens, cookies, sessões, dados pessoais de consumidores, mensagens de suporte e qualquer dado sem base legal/autorização.

Critérios de aceite:

- nenhuma informação importada é aplicada sem prévia e confirmação;
- mapeamento crítico de baixa confiança nunca vincula tabela ou altera categoria/marca/modelagem sem revisão humana;
- cada sugestão mostra motivo, origem, confiança e impacto em produtos afetados;
- a Zak gera dry-run comparando Sizebay/exportações autorizadas com BigShop/feed e mostra cobertura de produtos, tabelas, tamanhos e conflitos;
- lote aplicado pode ser auditado e desfeito;
- segredos Sizebay, cookies e sessões não aparecem em arquivos versionados, logs, banco em claro ou documentação;
- dados agregados de relatório/devolução respeitam minimização LGPD e não expõem consumidor identificável;
- mapeamentos aprovados melhoram importações futuras e aparecem na fila de aprendizado/taxonomia.

Validações:

- testes PHP para parsers, dry-run, aplicação, rollback, auditoria e bloqueios de baixa confiança;
- testes de integração com os serviços existentes de produtos, tabelas, marcas, categorias, modelagens, importações, analytics e taxonomia;
- build frontend e validação visual local na porta `5177`, com backend em `8002`;
- `git diff --check`, Pint, varredura de segredos e validação de produção quando a sprint for implementada.

Status: planejada.
