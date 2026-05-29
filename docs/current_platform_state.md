# Estado Atual da Plataforma

Atualizado em: 2026-05-28

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
- Sprint 82 enviada ao GitHub no commit `e9ab2f9`; o run `26384825165` finalizou com sucesso, implementando checkout transparente Mercado Pago, seleção de operadora em `/saas/checkout`, Pagar.me preservada como alternativa pendente e chaves de produção do NoAzul registradas apenas em referência local ignorada pelo Git/secret seguro.
- Sprint 83 enviada ao GitHub no commit `7eadd35`; o run `26386034325` finalizou com sucesso, priorizando cartão no checkout público, limitando parcelas a 10x sem juros e deixando Pix como alternativa com tag `5% off`.
- Sprint 84 enviada ao GitHub no commit `fe2ab48`; o run `26386407174` finalizou com sucesso, atualizando a cópia pública e transacional para cartão em até 10x sem juros ou Pix à vista com 5% de desconto.
- Sprint 85 enviada ao GitHub no commit `84ca5e6`; o run `26386718075` finalizou com sucesso, corrigindo os campos seguros reais do Mercado Pago no checkout mobile para 44px sem overflow horizontal.
- Sprint 86 registra o roadmap comercial de planos mensal/anual, recorrência, aceite legal, cookies e boleto, e torna obrigatório iniciar cada título de commit com `Sprint <numero> - `.
- Sprint 87 atualiza a matriz de planos para mensal/anual por plataforma, com preços `489,80`, `389,80`, `449,80` e `349,90`, total anual e percentual de economia retornados pela API.
- Sprint 88 reforça `/termos` e `/privacidade`, exige aceite no checkout, salva prova técnica em `checkout_acceptances` com IP/data/hora/usuário/empresa/versões legais e adiciona aviso inferior de cookies/localStorage com persistência no navegador.
- Sprint 88 enviada ao GitHub no commit `ae0dc2b`; o run `26411780677` finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Sprint 89 implementa recorrência mensal no cartão via Mercado Pago `/preapproval`, salva assinaturas em `billing_subscriptions` e adiciona opção discreta no dashboard para cancelar somente a renovação futura.
- Sprint 89 enviada ao GitHub no commit `aec5520`; o run `26412440589` finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Sprint 90 adiciona boleto habilitável pelo SaaS: `/saas/checkout` controla a exibição, o checkout público mantém boleto oculto por padrão e Mercado Pago gera pagamento `bolbradesco` quando habilitado.
- Sprint 90 enviada ao GitHub no commit `6ddf1c5`; o run `26412934331` finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin e smoke público.
- Sprint 91 valida o pacote comercial completo: `php artisan test` passou com 79 testes/635 assertions, `npm run build` passou e `scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK` em produção, com go-live `ready_with_warnings` por pendências externas já conhecidas.
- Sprint 91 enviada ao GitHub no commit `61e8fac`; o run `26413377677` finalizou com sucesso, incluindo deploy remoto, deploy da raiz pública, master admin, smoke público e nova validação de produção pós-deploy.
- Sprint 92 adiciona opção visual do provador entre drawer lateral e modal central. A preferência fica em `theme.presentation_mode`, é alterável em `/app/widget` e não muda a dinâmica de recomendação.
- Sprint 92 enviada ao GitHub no commit `3436cc5`; o run `26413966332` finalizou com sucesso e a validação de produção confirmou os assets públicos do modo modal.
- Sprint 93 adiciona prévia real da animação de confetes no portal: ao marcar `Animação de confetes` em `/app/widget`, a empresa vê a mesma celebração usada na loja, mantendo `theme.confetti_enabled` como controle do disparo no resultado completo.
- Sprint 93 enviada ao GitHub no commit `7093036`; o run `26414392783` finalizou com sucesso e a validação de produção confirmou os assets publicados da prévia de confetes.
- Sprint 94 limpa a redundância do topo da loja teste: a marca fica apenas no header, o H1 passa a orientar o teste da recomendação e o CTA `Teste o provador` some quando a própria experiência já está aberta.
- Sprint 94 enviada ao GitHub no commit `c0985fd`; o run `26414805731` finalizou com sucesso e a validação de produção confirmou a nova cópia no topo da loja teste.
- Sprint 95 simplifica o checkout para coletar somente CNPJ da empresa, registra tentativas recusadas pela operadora com motivo, adiciona `/saas/pedidos` com detalhe completo e move dados cadastrais da empresa para o primeiro acesso ao portal.
- Sprint 95 enviada ao GitHub no commit `1c029ae`; o run `26415840565` finalizou com sucesso e a validação de produção confirmou `/saas/checkout`, `/saas/pedidos` e o pacote público/API principal.
- Sprint 96 reorganiza `/app/widget` em blocos de instalação, domínios e personalização, e faz preview, snippet, guia e matriz de dados mudarem conforme a plataforma selecionada.
- Sprint 96 enviada ao GitHub no commit `f44d281`; o run `26416798463` finalizou com sucesso e a validação de produção confirmou `/app/widget` junto do pacote público/API principal.
- Sprint 97 ajusta `/app/widget` para manter plataforma, chave pública, status e cores em leitura vertical, com tooltip de domínios liberados.
- Sprint 97 enviada ao GitHub no commit `c188d4e`; o run `26418672266` finalizou com sucesso e a validação de produção confirmou `/app/widget`.
- Sprint 98 ajusta o checkout público para organizar Nome/CPF e E-mail/Telefone em duas linhas e ocultar `5% off` do Pix mensal.
- Sprint 98 enviada ao GitHub no commit `1e0af18`; o run `26419066028` finalizou com sucesso e a validação de produção confirmou `/checkout`.
- Sprint 99 adiciona retorno no cabeçalho público para usuários autenticados voltarem ao SaaS ou ao Portal da Empresa e canonicaliza rotas antigas de frontend em `/provadorvirtual_v2`.
- Sprint 99 enviada ao GitHub no commit `360ed12`; o run `26419953084` finalizou com sucesso e a validação de produção confirmou páginas, APIs, widget e redirects legados para a raiz.
- Sprint 100 corrige a conclusão do checkout: Pix mostra QR Code/copia e cola, boleto mostra abrir/baixar/copiar código de barras, cartão aprovado mostra sucesso e falhas da operadora viram modal amigável com código técnico.
- Sprint 100 enviada ao GitHub no commit `c0415bd`; o run `26421412473` finalizou com sucesso e a validação de produção confirmou `/checkout`, `/saas/pedidos`, APIs, widget e redirects legados para URLs limpas.
- Sprint 101 corrige a causa real da falha Pix Mercado Pago: `date_of_expiration` passa a usar milissegundos e timezone `America/Sao_Paulo`, preservando a mensagem técnica e o UUID de rastreio apenas no SaaS.
- Sprint 101 enviada ao GitHub no commit `17fe291`; o run `26422281931` finalizou com sucesso e a validação de produção confirmou `/checkout`, `/saas/pedidos`, APIs, widget e redirects legados.
- Sprint 102 ajusta `/checkout/sucesso`: o resumo público usa `Pedido`, `Status do pagamento` e `Forma de pagamento`, remove a operadora, traduz status técnicos e separa os botões finais.
- Sprint 102 enviada ao GitHub no commit `84c383a`; o run `26423505273` finalizou com sucesso e a validação de produção confirmou `/checkout`, `/saas/pedidos`, APIs, widget e redirects legados.
- Sprint 103 ajusta a landing pública: a seção de planos remove a explicação interna sobre destaque mensal, mostra tags `Economize 8,2%` e `Economize 10,2%` nos cards anuais e atualiza a chamada BigShop para lojas novas ou migração para uma plataforma mais inteligente.
- Sprint 103 enviada ao GitHub no commit `0fb2dfe`; o run `26424134815` finalizou com sucesso e a validação de produção confirmou `/`, `/checkout`, `/app/widget`, APIs, widget e redirects legados.
- Sprint 104 enxuga a etapa inicial do provador público, remove texto redundante sobre altura/peso, compacta blocos informativos e corrige tooltips com acentuação exibida corretamente.
- Sprint 104 enviada ao GitHub no commit `9256077`; o run `26424515050` finalizou com sucesso e a validação de produção confirmou `/produto-teste`, widget JS/CSS, APIs e redirects legados.
- Sprint 105 remove a frase auxiliar da etapa `Suas medidas` e mantém apenas o aviso `Preencha altura e peso para ver o tamanho inicial.` antes dos campos.
- Sprint 105 enviada ao GitHub no commit `8a04ed6`; o run `26425163585` finalizou com sucesso e a validação de produção confirmou o JS do widget publicado com aviso único.
- Sprint 106 adiciona personalização de botões em `/app/widget`: estilos `Destaque com brilho`, `Minimal com ícones`, `Contorno leve` e `Pílulas suaves`, cores próprias de fundo/texto dos botões, lista vertical de seleção e prévia com as mesmas animações do widget público.
- Sprint 106 enviada ao GitHub no commit `68b647a`; o run `26600519176` finalizou com sucesso e a validação de produção confirmou `/app/widget`, widget JS/CSS, APIs públicas, SaaS, portal e redirects legados.
- Sprint 107 registra a Zak/loja BigShop `124` como cliente piloto real em local e producao, com token criptografado e sem segredos versionados; o benchmark completo do portal Sizebay da Zak fica em `docs/sizebay_zak_hyper_benchmark.md`.
- Sprint 107 enviada ao GitHub no commit `931d09e`; o run `26602780031` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, widget, APIs, CORS, login demo e go-live readiness.
- Sprint 108 corrige a personalização de botões com base na galeria correta `https://sizebay-buttons-gallery.vercel.app/`: `/app/widget` passa a listar 10 modelos próprios inspirados nos cards públicos da galeria, com cores de fundo/texto, prévia e animações equivalentes no widget.
- Sprint 108 enviada ao GitHub no commit `482631e`; o run `26603841134` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, `/app/widget`, widget JS/CSS, APIs, CORS, login demo e go-live readiness.
- Sprint 109 inicia a importação segura da Zak: BigShop agora tem dry-run paginado de `products` e `product_grids`, join por `produtoid`, extração de tamanho de `caracteristicas`, erros por produto e painel de prévia em `/app/integracoes`, sem gravar produtos/tabelas.
- Sprint 109 enviada ao GitHub no commit `6aaf8f4`; o run `26604636247` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, `/app/integracoes`, widget, APIs, CORS, login demo e go-live readiness.
- Sprint 110 cria `/app/sincronizacao`: histórico limpo de eventos de sync/dry-run/XML com filtros, contadores, amostra de produtos e erros por produto, usando `integration_events` e `import_jobs`.
- Sprint 110 enviada ao GitHub no commit `efe87b8`; o run `26605323289` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, `/app/integracoes`, `/app/sincronizacao`, widget, APIs, CORS, login demo e go-live readiness.
- Sprint 111 cria `/app/regras-de-importacao`: regras visuais por conexão para categoria, marca, gênero, faixa etária, status e modelagem, salvas em `platform_connections.import_rules` e usadas por dry-run/sync BigShop e XML/feed.
- Sprint 111 enviada ao GitHub no commit `5d938ba`; o run `26606288957` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, `/app/regras-de-importacao`, `/app/integracoes`, `/app/sincronizacao`, widget, APIs, CORS, login demo e go-live readiness.
- Sprint 112 evolui tabelas de medidas com `measurement_target`, `size_system`, `range_mode`, JSON flexível por linha e medida composta `fit_balance`, preservando compatibilidade com as colunas usadas pelo motor atual.
- Sprint 112 enviada ao GitHub no commit `2872cc7`; o run `26606965068` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, `/app/tabelas-de-medidas`, `/app/tabelas-de-medidas/nova`, `/app/regras-de-importacao`, `/app/integracoes`, `/app/sincronizacao`, widget, APIs, CORS, login demo e go-live readiness.
- Sprint 113 cria cadastro de modelagens em `fit_profiles`, com API `/api/v1/fit-profiles`, tela `/app/modelagens`, uso em produtos/tabelas e bloqueio de remoção quando a modelagem está vinculada.
- Sprint 113 enviada ao GitHub no commit `85f7cec`; o run `26607795341` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, `/app/modelagens`, `/app/tabelas-de-medidas`, `/app/produtos`, `/app/regras-de-importacao`, `/app/integracoes`, `/app/sincronizacao`, widget, APIs, CORS, login demo e go-live readiness.
- Sprint 114 separa rascunho e configuração publicada do widget, com `mode=draft/publish/discard`, botões Salvar rascunho/Publicar/Desfazer e preview desktop/mobile em `/app/widget`.
- Sprint 114 enviada ao GitHub no commit `a6e1ff1`; o run `26608432348` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, `/app/widget`, widget, APIs, CORS, login demo e go-live readiness.
- Sprint 115 usa sinais de pedido, devolução, troca e feedback para gerar insights por tabela de medidas, alimentar o Assistente de IA com contexto de aprendizado e mostrar sugestões limpas em `/app/analytics`.
- Sprint 115 enviada ao GitHub no commit `8277337`; o run `26609097848` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, `/app/analytics`, `/app/assistente`, `/app/widget`, widget, APIs, CORS, login demo e go-live readiness.
- Sprint 116 adiciona vínculo em lote de tabela de medidas na listagem `/app/produtos`, com barra sticky compacta de busca/filtros, seleção por checkbox, seletor de tabela e ação `Vincular`.
- Sprint 116 enviada ao GitHub no commit `e802ad6`; o run `26609619782` finalizou com sucesso e a validação de produção confirmou páginas públicas, SaaS, portal, `/app/produtos`, `/app/produtos/novo`, widget, APIs, CORS, login demo e go-live readiness.
- API limpa em produção usa redirect 307 para `/provadorvirtual_v2/public/api/...` no HostGator; `curl -L` e navegadores recebem JSON real.
- Painel autenticado em produção usa `/provadorvirtual_v2/public/api/v1` direto para evitar perda de `Authorization` em clientes que não preservam header durante redirect.
- A raiz `https://provadorvirtual.online/` é o endereço canônico das páginas públicas, SaaS e Portal da Empresa; rotas legadas de frontend em `/provadorvirtual_v2/` devem redirecionar para a raiz limpa.
- `/provadorvirtual_v2/` permanece como caminho técnico de backend, API, widget, assets internos e rollback.
- Falta chave de IA externa (`OPENAI_API_KEY` ou `GEMINI_API_KEY`) para OCR real de imagem.
- Credencial BigShop real da Zak foi recebida e cadastrada como piloto; a importacao final da Zak ainda nao deve ser rodada ate validar os mapeamentos e a tabela flexivel em dados reais. A Sprint 109 criou o dry-run paginado com `product_grids` para revisar os dados antes de qualquer gravação em massa.
- Falta cadastrar `BIGSHOP_ACTIVATION_SECRET` em `PRODUCTION_ENV` para habilitar ativação um clique real.
- Mercado Pago passa a ser a operadora de produção do checkout transparente; as chaves de referência do NoAzul devem ficar em `PRODUCTION_ENV`, `backend/.env` local ou `docs/credentials.local.md`, nunca versionadas.
- Pagar.me permanece no painel como alternativa selecionável, mas a finalização dela continua pendente das informações operacionais faltantes.
- Checkout público prioriza cartão quando disponível, com parcelas até 10x sem juros no anual, Pix como alternativa à vista com tag `5% off` apenas no plano anual com desconto real e boleto somente quando habilitado no SaaS; a regra comercial atual já tem planos mensal/anual por plataforma, aceite legal obrigatório, recorrência mensal no cartão, cancelamento de renovação futura pelo portal e coleta de empresa limitada a CNPJ. Pix e boleto Mercado Pago enviam `date_of_expiration` com milissegundos e timezone `America/Sao_Paulo`. A conclusão exibe Pix com QR Code/copia e cola, boleto com link/download/código de barras, cartão aprovado com sucesso e falhas com mensagem amigável mais código técnico. A trilha iniciada na Sprint 86 foi validada no Sprint 91; recorrência anual segue como validação futura para evitar dupla cobrança.
- Campos seguros do Mercado Pago no checkout mobile devem permanecer compactos: invólucro de 44px e `iframe` interno contido em 22px.
- Falta configurar/validar cron no cPanel e executar uma transação real Mercado Pago de baixo valor em produção.
- Teste real BigShop com Zak ja tem credencial cadastrada; a Sprint 109 cobre `Store-Id`, retorno paginado/envelopado e grade separada em `product_grids` no dry-run. A Sprint 111 cobre mapeamento visual de categoria/marca/genero/faixa etaria/status/modelagem. A Sprint 112 cobre modelo flexivel de tabelas, mas a importacao final em massa segue bloqueada ate validação dos dados reais.

## Superficie atual

- Painel protegido: `/app`, `/app/produtos`, `/app/tabelas-de-medidas`, `/app/modelagens`, `/app/assistente`, `/app/analytics`, `/app/widget`, `/app/integracoes`, `/app/regras-de-importacao`, `/app/sincronizacao`, `/app/usuarios`.
- Painel SaaS protegido por papel/permissão: `/saas`, `/saas/empresas`, `/saas/usuarios`, `/saas/checkout`, `/saas/pedidos` e `/saas/emails`, com entrada administrativa em `/saas/login`.
- CRUDs do SaaS seguem padrão list-first: listagem ocupa a tela e novo/editar abre rota própria.
- Login do portal da empresa: `/login`, aceitando e-mail ou CPF, campo de código/CNPJ para empresa e seletor quando o usuário tem multiplas empresas.
- CRUDs principais do portal da empresa também seguem padrão list-first: produtos, tabelas e usuários possuem listagem em tela própria e rotas separadas para novo/editar. Produtos permitem vínculo individual de tabela no formulário e vínculo em lote na listagem.
- Modelagens: `/app/modelagens` cadastra caimentos por código, intensidade, elasticidade, gênero, tipo, status e uso em produtos/tabelas; a API protegida é `/api/v1/fit-profiles`.
- Tabelas de medidas: `/app/tabelas-de-medidas` e formulários suportam base corpo/peça/mista, sistema BR letras/BR numérico/internacional/custom, ranges e medida composta por linha, mantendo o formato antigo para recomendação.
- Diretriz obrigatória de telas: `docs/portal_ui_guidelines.md`.
- Checkout público: `/checkout` e `/checkout/sucesso`, com aceite legal marcado por padrão, links para termos/privacidade, modal amigável de falha por meio de pagamento e conclusão específica para Pix, boleto e cartão. A conclusão pública mostra pedido, status do pagamento em português e forma de pagamento, sem expor a operadora.
- Site público raiz: landing comercial do Provador Virtual, com planos mensal/anual por plataforma, tags de economia anual nos cards, CTA para loja teste/checkout e cards de benefícios otimizados para mobile. A cópia pública usa `provador` em vez de `widget` para evitar jargão técnico. Quando há sessão autenticada, o cabeçalho mostra retorno para `Voltar ao SaaS` ou `Voltar ao portal`, conforme o papel carregado por `/me`.
- APIs protegidas: produtos, variações, tabelas, templates, widget-install e integrações, com middleware de permissão por módulo e escopo da empresa ativa. `PATCH /api/v1/products/bulk-measurement-table` vincula uma tabela a produtos selecionados. `PATCH /api/v1/integrations/{platform}` também salva `import_rules` para a tela de regras visuais.
- Importacoes protegidas: preview, commit e histórico em `/api/v1/imports`.
- Assistente protegido: status e sugestoes em `/api/v1/ai/*`; sugestões de tabela recebem contexto agregado de aprendizado quando existe histórico compatível de tipo, gênero e modelagem.
- Analytics/auditoria protegidos: `/api/v1/analytics/*`, `/api/v1/audit-logs` e `/api/v1/saas/*`. `/api/v1/analytics/recommendations` inclui KPIs de compra/devolução/troca e insights por tabela de medidas para revisão do lojista.
- Go-live protegido: `/api/v1/go-live/readiness` e `/app/go-live`.
- Pacote comercial protegido: `/app/go-live` mostra links de venda, onboarding, automações e pendências reais.
- Observabilidade pública: `/api/v1/ops/status`.
- BigShop protegido: probe, dry-run e sync em `/api/v1/integrations/bigshop/*`.
- Sincronização protegida: `GET /api/v1/integrations/sync-history` consolida eventos `dry_run_import`, `sync_products` e `xml_feed_sync` para `/app/sincronizacao`.
- Monitor BigShop protegido: `GET /api/v1/integrations/bigshop/activations`.
- Validação de instalação protegida: `POST /api/v1/integrations/{platform}/validate-install`.
- BigShop público assinado: ativação em `/api/v1/public/bigshop/activate`.
- APIs públicas: health, produto demo e recomendações do widget.
- APIs públicas inteligentes: `/api/v1/public/recommendations/{id}/signal` e `/api/v1/public/shopper-profiles/forget`.
- APIs públicas de checkout: `/api/v1/public/checkout/config`, `/api/v1/public/checkout`, `/api/v1/public/checkout/{reference}`, `/api/v1/webhooks/mercado-pago` e `/api/v1/webhooks/pagarme`. O `POST /public/checkout` exige `accepted_terms=true`, grava a prova técnica do aceite e retorna falhas da operadora com mensagem amigável, `error_code`, referência, operadora e meio de pagamento.
- APIs protegidas de assinatura: `/api/v1/billing/subscription` e `/api/v1/billing/subscription/auto-renewal` permitem consultar a assinatura da empresa ativa e desabilitar apenas a renovação futura.
- APIs públicas de empresa: `/api/v1/public/company-access`.
- APIs SaaS de e-mail: `/api/v1/saas/email-settings` e `/api/v1/saas/transactional-emails`.
- API SaaS de checkout: `/api/v1/saas/checkout-settings`.
- Histórico SaaS de e-mail: `/api/v1/saas/transactional-email-sends`.
- APIs de usuários/permissões: `/api/v1/merchant/users` e `/api/v1/saas/users`.
- Loja teste pública: `/produto-teste` e `/produto-teste/:slug`, usando a identidade oficial do Provador Virtual sem repetir a marca no topo da vitrine. O H1 orienta o teste da recomendação de tamanho, os produtos são fictícios/não estão à venda e carregam sem tamanho selecionado por padrão. Na página de produto, os tamanhos são ilustrativos; o fluxo principal é clicar no provador, aceitar a recomendação e ver o tamanho aplicado automaticamente.
- Seção protegida `/app/widget`: aparece no menu como `Provador`, mas mantém o termo técnico `Widget` no topo da seção com ícone `i` e explicação de que é o provador exibido na página de produto para abrir recomendação por IA, tabela de medidas e seleção de tamanho. A tela separa instalação, domínios e personalização, com plataforma/chave/status empilhados, cores em lista vertical, seleção vertical dos 10 modelos de botões da galeria Sprint 108, box de cores de fundo/texto dos botões, tooltip de domínios liberados, preview desktop/mobile, salvar rascunho, publicar/desfazer, código e guia lateral por plataforma para BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart e custom.
- Mobile do portal: header autenticado exibe somente marca e menu; o drawer concentra navegação, usuário, saída e fechamento explícito para evitar sobreposição e reduzir carga visual.
- Widget público: `/widget/v1/provador-virtual.js` e `/widget/v1/provador-virtual.css`. O tema aceita `button_style`, `button_background` e `button_text` para variar os botões públicos entre 10 modelos próprios da galeria Sprint 108, preservando os botões `PV Descubra seu tamanho` e `cm Tabela de Medidas`. Os valores legados `gradient`, `clean`, `outline` e `soft` seguem aceitos para compatibilidade.
- O widget público v2 mantém os botões `PV Descubra seu tamanho` e `cm Tabela de Medidas`, mas o fluxo de recomendação agora segue a ordem do v1: medidas básicas, gênero/formato corporal, medidas detalhadas, resultado e feedback. Mesmo com perfil salvo no navegador, a etapa 1 deve limitar a precisão aos campos básicos e nunca pular direto para o resultado; quando houver altura + peso, o rodapé já pode exibir recomendação parcial e incentivar o aumento de precisão. A etapa inicial usa um único aviso antes dos campos e tooltips de medidas com acentuação correta. O cabeçalho do drawer usa o mesmo gradiente dos botões e da barra de precisão, respeitando a cor de destaque configurada no tema da loja. O aviso de salvamento local das medidas aparece somente na etapa 1, no final do corpo rolável do widget, sem checkbox, em itálico e com fonte menor. O feedback final não exibe escala de nota de 1 a 5; usa apenas botões `Sim, ajudou` e `Não ajudou`, tamanho escolhido e comentário opcional. O tamanho recomendado é acionável no banner parcial, no rodapé e no resultado; ao tocar, o widget fecha e emite `provadorvirtual:size-selected` para a loja marcar a variação correspondente quando houver integração.
- A abertura do fluxo de recomendação pode ser configurada por loja como drawer lateral ou modal central. O modal central usa o mesmo conteúdo e ocupa a tela toda no mobile. A animação de confetes pode ser desligada por loja e o portal mostra a mesma prévia visual quando a empresa ativa a opção.
- Assets oficiais de marca ficam em `frontend/public/images/brand/` e alimentam o cabeçalho, rodapé, favicon e tags OG/Twitter.

## Próxima ação recomendada

Retestar uma contratação Pix real em produção e acompanhar webhook/cron; depois executar cartão de baixo valor, validar recorrência anual com a operadora, seguir com BigShop real e finalizar Pagar.me quando as pendências chegarem.
