# Roadmap e Sprints

Atualizado em: 2026-05-23

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
- cartão em até 12x e Pix a vista com 5% de desconto;
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

Status: implementado e validado localmente; aguardando commit, push e verificação remota da sprint.

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

Status: implementado localmente; aguardando commit, push e verificação remota da sprint.
