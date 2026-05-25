# Estado Atual da Plataforma

Atualizado em: 2026-05-25

## Estado do workspace

- `D:\Projetos\provadorvirtual_v2` estava vazio no inicio desta documentação.
- Git local foi inicializado em `main` e conectado ao remoto `git@github.com:helberfmelo/provadorvirtualv2.git`.
- Foram criados documentos iniciais, `.gitignore` e workflow de deploy.
- Sprint 1 scaffoldou `backend/` Laravel 12 e `frontend/` Vue 3/Vite.
- Sprint 2 publicou CRUD de produtos, variações, tabelas de medidas e templates operacionais no painel e na API.
- Sprint 3 criou motor determinístico, endpoints públicos de recomendação/config-check/feedback e conectou `/produto-teste` ao backend real.
- Sprint 4 criou widget público em `/widget/v1/provador-virtual.js` e `/widget/v1/provador-virtual.css`, com modal, config-check, recomendação e feedback.
- Sprint 5 criou configuração operacional do widget no painel, catálogo de integrações e persistência de conexões por plataforma.
- Sprint 6 criou importacao com preview/commit para CSV de produtos, CSV de tabelas e Google XML inicial.
- Sprint 7 criou conector BigShop base com probe, sync de produtos/grades/tabelas e eventos de integração.
- Sprint 8 criou ativação BigShop um clique por endpoint público assinado com HMAC.
- Sprint 9 criou assistente de tabelas com parser local, logs de uso de IA e revisão obrigatória.
- Sprint 10 criou analytics do lojista, SaaS admin básico e trilha `audit_logs`.
- Sprint 11 criou páginas legais, CORS por domínio do widget, rate limits, status operacional e rotinas de privacidade.
- Sprint 12 criou checklist de go-live, endpoint/tela de prontidão, script de validação de produção e plano de cutover.
- Sprint 23 criou cadastro interno de empresas no SaaS, endereço completo, CPF de usuário, código de acesso `aaaa + id`, busca pública por código/CNPJ e comando para master admin.
- Sprint 24 criou loja teste realista com 4 produtos, 4 tabelas demo e widget com os botões `Descubra seu tamanho` e `Tabela de Medidas`.
- Sprint 25 criou personalizador visual do widget/tabela com preview em tempo real no painel do lojista.
- Sprint 26 criou landing pública, checkout transparente Pagar.me, sessões/eventos de pagamento e webhook de ativação.
- Sprint 27 ajustou a landing para estrutura inspirada no v1, publicou build prevista para a raiz e simplificou o checkout para plano anual único sem boleto.
- Sprint 28 criou monitor de pagamentos pendentes, agendamento de cron/scheduler e configuração SaaS de SMTP/templates transacionais; publicado em produção no run `26336899986`.
- Sprint 29 preparou login por e-mail/CPF, acesso do portal por código/CNPJ e contexto de empresa no token; publicado em produção no run `26337254520`.
- Sprint 30 criou CRUD de usuários no SaaS e no portal da empresa, permissões por módulo/menu, status global/por empresa e ações de editar/ativar/desativar; publicado em produção no run `26337792120`.
- Sprint 31 criou automações de e-mail transacional, histórico de envios e comando/scheduler para pendências financeiras; publicado em produção no run `26338061259`.
- Sprint 32 implementou refinamento da oferta pública, trava de integração BigShop, favicon/OG, footer, imagens da loja teste e menu mobile em drawer; publicado em produção no run `26338411089`.
- Sprint 33 completou login multiempresa, seletor de empresa no painel, escopo por empresa nas APIs do portal, enforcement de permissões por rota e auditoria com empresa/módulo/ação; publicado em produção no run `26338888072`.
- Sprint 34 criou guias de integração por plataforma, snippets, checklist visual, matriz de dados suportados e validação protegida de instalação por URL pública; publicado em produção no run `26339199751`.
- Sprint 35 preparou o contrato BigShop um clique com snippet/contract na resposta da ativação e monitor protegido de ativações no painel; publicado em produção no run `26339426665`.
- Sprint 36 criou perfis anônimos com consentimento, token local, esquecimento, eventos de aprendizado, sinais comerciais, outlier score e analytics de qualidade; publicado em produção no run `26339824157`.
- Sprint 37 ampliou o pacote de piloto/go-live com checks de Pagar.me, transação real, cron, performance do widget, acessibilidade/mobile, comandos de automação e onboarding comercial; publicado em produção no run `26340033238`.
- Sprint 38 separou a navegação do SaaS e do portal da empresa, com menu lateral autenticado e drawer no mobile; publicado em produção no run `26342322716`.
- Sprint 39 separou os CRUDs do SaaS em listagens e formulários próprios; publicado em produção no run `26342542196`.
- Sprint 40 separou os CRUDs principais do portal da empresa em listagens e formulários próprios; publicado em produção no run `26342724625`.
- Sprint 41 registrou as diretrizes de UX dos portais, refinou tabelas/ações/cabecalhos e ampliou a validação de rotas.
- Sprint 42 limpou defaults confusos nos formulários de nova empresa e novo produto após inspeção visual autenticada.
- Sprint 43 importou o catálogo padrão do v1, criou templates inteligentes por gênero/produto/altura/peso/idade/formato corporal e reforçou IA/base brasileira no site e portal.
- Sprint 44 separou usuários internos do SaaS dos usuários das empresas clientes, com CRUD próprio em `/saas/usuarios-empresas`.
- Sprint 45 criou feedback global de salvamento nos portais, com modal de carregamento, sucesso temporário e erro persistente com mensagem amigável.
- Sprint 46 corrigiu o recarregamento das telas do portal da empresa quando o usuário alterna a empresa ativa.
- Sprint 47 aprofundou integrações por plataforma, adicionou XML/feed por URL, tooltips nos campos de integração, pesquisa Sizebay e roadmap de conectores.
- Sprint 48 revisou textos em PT-BR com acentos/cedilha/til e registrou a regra nas diretrizes obrigatórias dos portais.
- Sprint 49 padronizou estados e estilos globais de inputs, selects, textareas, checkboxes e foco/disabled nos portais.
- Sprint 50 corrigiu os testes que ainda esperavam mensagens sem acento e reforçou a regra obrigatória de conferir GitHub Actions/deploy após cada push.
- Sprint 51 iniciou o ciclo corretivo de integrações, registrando roadmap e reforçando a governança de releitura obrigatória, commit, push e Actions/deploy antes de avançar sprint.
- Sprint 52 corrigiu a UX da tela de integrações: tooltips customizados contidos na tela, mensagens de ações por modal, botões separados por finalidade e proteção contra rolagem horizontal indevida.
- Sprint 53 criou o comando agendável `pv:integrations-sync-feeds`, registrou syncs XML/feed em `integration_events` e configurou o scheduler para 4 execuções diárias.
- Sprint 54 detalhou no portal e nas docs onde instalar o widget na página de produto e adicionou recarregamento público do widget para troca dinâmica de variação/SKU.
- Sprint 55 removeu os feedbacks de sucesso inline restantes nas telas operacionais e padronizou essas ações no modal central.
- Sprint 56 registra a conferência remota da Sprint 55 e reforça o ciclo obrigatório de não avançar sprint sem deploy verificado.
- Sprint 57 atualiza os actions oficiais do workflow para `actions/checkout@v6` e `actions/setup-node@v6`, removendo o risco da depreciação do runtime Node 20 dos actions.
- Sprint 58 prepara a instalação nativa BigShop model3 pro: widget resolve loja por `platform=bigshop` + `external_store_id`, emite evento de configuração e a cópia local do `produto.vue` passa a carregar o widget sem IDs internos fixos.
- Sprint 59 adiciona fechamento manual com `x` no canto superior direito do modal central de feedback, preservando o fechamento automático para sucessos e avisos.
- Sprint 62 corrige o formulário de produto para mostrar tabela somente quando `measurement_table_id` existe, registra o ajuste local do editor BigShop sem hardcoding e confirma os bloqueios atuais da Luna: domínio do widget não liberado e produto `716076` sem tabela vinculada.
- Sprint 63 corrige a resolução pública BigShop para usar `platform_connections.external_store_id` como fallback; a Luna Moda Festa passou a retornar `configured=true` no `config-check` com os domínios públicos.
- Sprint 64 corrige a base padrão da API usada pelo widget em produção para evitar redirect no preflight CORS, publica a correção no run `26354288938` e confirma `config-check` da Luna Moda Festa com `configured=true`.
- Sprint 65 registra a confirmação visual do piloto Luna Moda Festa em produção: os botões `PV Descubra seu tamanho` e `cm Tabela de Medidas` aparecem na página BigShop model3 pro do produto `716076`; documentação publicada no run `26354617302`.
- Sprint 66 migra a lógica gamificada do widget v1 para o widget público v2: drawer em etapas, barra de precisão, formato corporal, medidas detalhadas, confete em 100%, feedback final visível e persistência do payload bruto da jornada para aprendizado/LGPD.
- Sprint 67 corrige o fluxo sequencial do widget v2: dados salvos no navegador não podem antecipar 100% na etapa 1, o rodapé só envia recomendação na etapa 3, e o confete só dispara quando a precisão real chega a 100%.
- Sprint 68 refina a paridade com o v1: altura + peso já geram recomendação parcial, o rodapé fixo mostra o tamanho recomendado, as etapas continuam bloqueadas por pré-requisitos, silhuetas variam por gênero, dados são salvos por tabela de medidas e o confete pode ser desligado por configuração do widget.
- Sprint 69 ajusta a hierarquia visual do widget: cabeçalho do drawer tematizado, CTAs de avanço mais fortes, rodapé discreto até o resultado 100%, feedback com escala explicada e silhuetas herdadas do v1 coloridas pelo tema da loja.

## Referências confirmadas

### BigShop HelpDesk

- Laravel 11 + Sanctum no backend.
- Vue 3 + TypeScript + Pinia + Vue Router no frontend.
- Deploy por GitHub Actions via SSH.
- Padrão visual: `#0f172a`, `#ff4d5e`, `#ff7a1a`, `#111827`, Manrope.
- Governança forte: documentos obrigatórios antes de sprint, commit/push após sprint, Actions acompanhado.

### Marca Hora

- Laravel com deploy FTP + SSH em HostGator/opents62.
- Uso de `SSH_USERNAME` nos secrets.
- Compatibilidade de MySQL compartilhado exige `DB_COLLATION=utf8mb4_unicode_ci`.
- Caminho de referência no servidor: `/home1/opents62/public_html/...`.

### BigShop360

- Contrato de integração BigShop já analisado.
- API BigShop V3 observada em `https://api.bigshop.com.br`.
- Headers observados: `x-api` e `store-id`.
- Rotas públicas observadas: produtos, busca, marcas, categorias, grades, clientes e vendas.
- Lacunas importantes: carrinho, frete, checkout, webhooks e contrato formal de erro.

### Provador Virtual v1

- PHP puro, MySQL e JS vanilla.
- Widget com `data-merchant-id`, `data-sku-grade` e compatibilidade com atributos antigos.
- Endpoints de recomendação e feedback.
- Tabelas de medidas, produtos, empresas, logs e feedbacks.
- Gemini usado para OCR/IA em partes do produto.
- Página de teste BigShop existente.

### BigShop front/back

- Front da loja BigShop tem página de produto Vue/Quasar em `front/stores/pro_store/produto.vue`.
- Produto possui `tabela_de_medidas`, grades/tamanhos, SKU e identificadores de grade.
- Front API V3 usa `get_contents_api3.php` e `auxiliar_functions_v3.php`.
- Back/API contém funções para produto, grade, tabela de medidas e busca.

## Decisões já tomadas

- Preservar v1 durante desenvolvimento.
- Publicar v2 inicialmente em `/provadorvirtual_v2/`.
- Usar Laravel/Vue em estrutura separada `backend/` e `frontend/`.
- Usar OpenAPI/documentação para endpoints públicos e integráveis quando forem criados.
- Widget deve ser isolado e resiliente a CSS/JS do e-commerce.

## Bloqueios e informações faltantes

- GitHub Actions voltou a executar após o repositório ser alterado para público.
- Path remoto confirmado pelo v1: `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`.
- Acesso SSH local ao HostGator/opents62 foi validado; `https://provadorvirtual.online/provadorvirtual_v1/` responde a partir de `/home1/opents62/provadorvirtual.online/provadorvirtual_v1`.
- Sprint 1 publicada em produção pelo GitHub Actions no run `26326675713`.
- Sprint 2 publicada em produção pelo GitHub Actions no run `26326950616`.
- Sprint 3 publicada em produção pelo GitHub Actions no run `26327119754`.
- Sprint 4 publicada em produção pelo GitHub Actions no run `26331199145`.
- Sprint 5 publicada em produção pelo GitHub Actions no run `26331485173`.
- Sprint 6 publicada em produção pelo GitHub Actions no run `26331691701`.
- Sprint 7 publicada em produção pelo GitHub Actions no run `26331844564`.
- Sprint 8 publicada em produção pelo GitHub Actions no run `26332055677`.
- Sprint 9 publicada em produção pelo GitHub Actions no run `26332326042`.
- Sprint 10 publicada em produção pelo GitHub Actions no run `26332544138`.
- Sprint 11 publicada em produção pelo GitHub Actions no run `26332960822`.
- Sprint 12 publicada em produção pelo GitHub Actions no run `26333226813`.
- Sprint 33 publicada em produção pelo GitHub Actions no run `26338888072`.
- Sprint 34 publicada em produção pelo GitHub Actions no run `26339199751`.
- Sprint 35 publicada em produção pelo GitHub Actions no run `26339426665`.
- Sprint 36 publicada em produção pelo GitHub Actions no run `26339824157`; o run `26339739429` falhou por limite de tamanho de nome de foreign key MySQL e foi corrigido no commit `5d5b5dc`.
- Sprint 37 publicada em produção pelo GitHub Actions no run `26340033238`.
- Sprint 38 publicada em produção pelo GitHub Actions no run `26342322716`.
- Sprint 39 publicada em produção pelo GitHub Actions no run `26342542196`.
- Sprint 40 publicada em produção pelo GitHub Actions no run `26342724625`.
- Sprint 41 publicada em produção pelo GitHub Actions no run `26342904562`.
- Sprint 42 publicada em produção pelo GitHub Actions no run `26343135605`.
- Sprint 43 publicada em produção pelo GitHub Actions no run `26343538804`.
- Sprint 44 publicada em produção pelo GitHub Actions no run `26343868801`.
- Sprint 45 publicada em produção pelo GitHub Actions no run `26344601240`.
- Sprint 46 publicada em produção pelo GitHub Actions no run `26344923662`.
- Sprint 47 enviada ao GitHub no commit `6fd8f46`; validação local passou com `php artisan test --filter=IntegrationsApiTest` e `npm run build`.
- Sprint 48 enviada ao GitHub no commit `59ced6f`; validação local passou com `npm run build`, `php artisan test --filter=IntegrationsApiTest`, `php artisan test --filter=UserAccessApiTest` e `git diff --check`.
- Runs `26346764503` e `26346828756` falharam porque testes ainda esperavam mensagens sem acentos após a Sprint 48.
- Sprint 50 enviada ao GitHub no commit `c2826a5`; o run `26347139903` finalizou com sucesso, incluindo deploy remoto e smoke público.
- Sprint 52 enviada ao GitHub no commit `24520a3`; o run `26348028309` finalizou com sucesso, incluindo deploy remoto e smoke público.
- Sprint 53 enviada ao GitHub no commit `684ba67`; o run `26348238406` finalizou com sucesso, incluindo deploy remoto e smoke público.
- Sprint 54 enviada ao GitHub no commit `7b06d4d`; o run `26348462160` finalizou com sucesso, incluindo deploy remoto e smoke público.
- Sprint 55 enviada ao GitHub no commit `01d0461`; o run `26348653353` finalizou com sucesso, incluindo deploy remoto e smoke público.
- Sprint 56 enviada ao GitHub no commit `b90cf10`; o run `26348767486` finalizou com sucesso, incluindo deploy remoto e smoke público.
- Sprint 57 enviada ao GitHub no commit `7f4a142`; o run `26348869694` finalizou com sucesso, incluindo deploy remoto e smoke público.
- Sprint 58 enviada ao GitHub no commit `98c13a7`; o run `26349330161` finalizou com sucesso, incluindo deploy remoto e smoke público.
- Sprint 62 enviada ao GitHub no commit `3f242ac`; o run `26353363931` finalizou com sucesso, incluindo deploy remoto e smoke público.
- Sprint 63 enviada ao GitHub no commit `a575777`; o run `26353804637` finalizou com sucesso, incluindo deploy remoto, smoke público e validação do `config-check` da Luna Moda Festa.
- Sprint 68 enviada ao GitHub no commit `790d875`; o run `26366746266` finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin, smoke público e validação Playwright pós-deploy do widget no produto teste.
- Sprint 69 enviada ao GitHub no commit `a53613a`; o run `26368265436` finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin, smoke público, verificação dos assets públicos de silhueta e endpoint público de recomendação.
- Sprint 70 enviada ao GitHub no commit `d5d4e69`; o run `26370389245` finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin, smoke público, assets oficiais de marca e validação Playwright pós-deploy do produto teste sem tamanho padrão.
- Sprint 71 enviada ao GitHub no commit `d17d412`; o run `26370907476` finalizou com sucesso, substituindo as três imagens oficiais de marca por novas versões: ícone `312x312`, favicon `312x312` e logo `3054x261`.
- Sprint 72 enviada ao GitHub no commit `4204bf1`; o run `26371467799` finalizou com sucesso, alinhando o cabeçalho do widget ao mesmo gradiente dos botões e da barra de precisão.
- Sprint 73 enviada ao GitHub no commit `415e68f`; o run `26372104049` finalizou com sucesso, trocando o checkbox de salvamento de medidas por um aviso discreto no fim do corpo do widget.
- Sprint 74 enviada ao GitHub no commit `0c83622`; o run `26372649754` finalizou com sucesso, refinando o aviso de salvamento local para itálico e mesma escala da linha de precisão.
- Sprint 75 enviada ao GitHub no commit `2a92a0b`; o run `26377480787` finalizou com sucesso, refinando o widget mobile, aumentando logo/menu e corrigindo as silhuetas para renderizar como imagens PNG reais.
- Sprint 76 enviada ao GitHub no commit `6c835c8`; o run `26378458765` finalizou com sucesso, removendo a escala redundante de nota de 1 a 5 do feedback final do widget.
- Sprint 77 enviada ao GitHub no commit `2a5c055`; o run `26378864592` finalizou com sucesso, posicionando o aviso de salvamento local somente na primeira etapa do widget, no fim do corpo rolável, em itálico e com fonte menor.
- Sprint 78 enviada ao GitHub no commit `003c996`; o run `26381419082` finalizou com sucesso, ajustando a loja teste mobile e o handoff do widget: tocar no tamanho recomendado fecha o drawer, emite `provadorvirtual:size-selected` e a página `/produto-teste/:slug` marca o tamanho recomendado.
- Sprint 79 enviada ao GitHub no commit `83ac2da`; o run `26381750743` finalizou com sucesso, reforçando na landing pública o valor do provador com IA para vender mais na loja online e melhorando os cards de benefícios no mobile.
- Sprint 80 enviada ao GitHub no commit `feb76e2`; o run `26382678616` finalizou com sucesso, trocando a cópia pública de `widget` para `provador` e adicionando explicação com ícone `i` na seção técnica `/app/widget`.
- Sprint 81 enviada ao GitHub no commit `b82316b`; o run `26383644699` finalizou com sucesso, corrigindo sobreposição do header mobile, refinando drawers e compactando a UI mobile do portal.
- API limpa em produção usa redirect 307 para `/provadorvirtual_v2/public/api/...` no HostGator; `curl -L` e navegadores recebem JSON real.
- Painel autenticado em produção usa `/provadorvirtual_v2/public/api/v1` direto para evitar perda de `Authorization` em clientes que não preservam header durante redirect.
- A raiz `https://provadorvirtual.online/` passa a ser o site público comercial; `/provadorvirtual_v2/` permanece como app/backend e rollback.
- Falta chave de IA externa (`OPENAI_API_KEY` ou `GEMINI_API_KEY`) para OCR real de imagem.
- Falta credencial BigShop real para loja de teste.
- Falta cadastrar `BIGSHOP_ACTIVATION_SECRET` em `PRODUCTION_ENV` para habilitar ativação um clique real.
- Falta cadastrar as chaves Pagar.me em `PRODUCTION_ENV`, com URLs de retorno na raiz, configurar cron no cPanel e validar uma transação real de baixo valor.
- Sprint 37 deixa essas pendências visíveis em `/app/go-live`; teste real de Pagar.me e BigShop continua bloqueado até receber/cadastrar as credenciais oficiais.

## Superficie atual

- Painel protegido: `/app`, `/app/produtos`, `/app/tabelas-de-medidas`, `/app/assistente`, `/app/analytics`, `/app/widget`, `/app/integracoes`, `/app/usuarios`.
- Painel SaaS protegido por papel/permissão: `/saas`, `/saas/empresas`, `/saas/usuarios` e `/saas/emails`, com entrada administrativa em `/saas/login`.
- CRUDs do SaaS seguem padrão list-first: listagem ocupa a tela e novo/editar abre rota própria.
- Login do portal da empresa: `/login`, aceitando e-mail ou CPF, campo de código/CNPJ para empresa e seletor quando o usuário tem multiplas empresas.
- CRUDs principais do portal da empresa também seguem padrão list-first: produtos, tabelas e usuários possuem listagem em tela própria e rotas separadas para novo/editar.
- Diretriz obrigatória de telas: `docs/portal_ui_guidelines.md`.
- Checkout público: `/checkout` e `/checkout/sucesso`.
- Site público raiz: landing comercial do Provador Virtual, com plano único, CTA para loja teste/checkout e cards de benefícios otimizados para mobile. A cópia pública usa `provador` em vez de `widget` para evitar jargão técnico.
- APIs protegidas: produtos, variações, tabelas, templates, widget-install e integrações, com middleware de permissão por módulo e escopo da empresa ativa.
- Importacoes protegidas: preview, commit e histórico em `/api/v1/imports`.
- Assistente protegido: status e sugestoes em `/api/v1/ai/*`.
- Analytics/auditoria protegidos: `/api/v1/analytics/*`, `/api/v1/audit-logs` e `/api/v1/saas/*`.
- Go-live protegido: `/api/v1/go-live/readiness` e `/app/go-live`.
- Pacote comercial protegido: `/app/go-live` mostra links de venda, onboarding, automações e pendências reais.
- Observabilidade pública: `/api/v1/ops/status`.
- BigShop protegido: probe e sync em `/api/v1/integrations/bigshop/*`.
- Monitor BigShop protegido: `GET /api/v1/integrations/bigshop/activations`.
- Validação de instalação protegida: `POST /api/v1/integrations/{platform}/validate-install`.
- BigShop público assinado: ativação em `/api/v1/public/bigshop/activate`.
- APIs públicas: health, produto demo e recomendações do widget.
- APIs públicas inteligentes: `/api/v1/public/recommendations/{id}/signal` e `/api/v1/public/shopper-profiles/forget`.
- APIs públicas de checkout: `/api/v1/public/checkout/config`, `/api/v1/public/checkout`, `/api/v1/public/checkout/{reference}` e `/api/v1/webhooks/pagarme`.
- APIs públicas de empresa: `/api/v1/public/company-access`.
- APIs SaaS de e-mail: `/api/v1/saas/email-settings` e `/api/v1/saas/transactional-emails`.
- Histórico SaaS de e-mail: `/api/v1/saas/transactional-email-sends`.
- APIs de usuários/permissões: `/api/v1/merchant/users` e `/api/v1/saas/users`.
- Loja teste pública: `/produto-teste` e `/produto-teste/:slug`, usando a identidade oficial do Provador Virtual, deixando claro que os produtos são fictícios/não estão à venda e carregando produtos sem tamanho selecionado por padrão. Na página de produto, os tamanhos são ilustrativos; o fluxo principal é clicar no provador, aceitar a recomendação e ver o tamanho aplicado automaticamente.
- Seção protegida `/app/widget`: aparece no menu como `Provador`, mas mantém o termo técnico `Widget` no topo da seção com ícone `i` e explicação de que é o provador exibido na página de produto para abrir recomendação por IA, tabela de medidas e seleção de tamanho.
- Mobile do portal: header autenticado exibe somente marca e menu; o drawer concentra navegação, usuário, saída e fechamento explícito para evitar sobreposição e reduzir carga visual.
- Widget público: `/widget/v1/provador-virtual.js` e `/widget/v1/provador-virtual.css`.
- O widget público v2 mantém os botões `PV Descubra seu tamanho` e `cm Tabela de Medidas`, mas o fluxo de recomendação agora segue a ordem do v1: medidas básicas, gênero/formato corporal, medidas detalhadas, resultado e feedback. Mesmo com perfil salvo no navegador, a etapa 1 deve limitar a precisão aos campos básicos e nunca pular direto para o resultado; quando houver altura + peso, o rodapé já pode exibir recomendação parcial e incentivar o aumento de precisão. O cabeçalho do drawer usa o mesmo gradiente dos botões e da barra de precisão, respeitando a cor de destaque configurada no tema da loja. O aviso de salvamento local das medidas aparece somente na etapa 1, no final do corpo rolável do widget, sem checkbox, em itálico e com fonte menor. O feedback final não exibe escala de nota de 1 a 5; usa apenas botões `Sim, ajudou` e `Não ajudou`, tamanho escolhido e comentário opcional. O tamanho recomendado é acionável no banner parcial, no rodapé e no resultado; ao tocar, o widget fecha e emite `provadorvirtual:size-selected` para a loja marcar a variação correspondente quando houver integração.
- Assets oficiais de marca ficam em `frontend/public/images/brand/` e alimentam o cabeçalho, rodapé, favicon e tags OG/Twitter.

## Próxima ação recomendada

Aguardar credenciais oficiais de Pagar.me e BigShop para executar transação real, ativação BigShop assinada e piloto em loja real; enquanto isso, usar `/app/go-live` como roteiro de demo assistida.
