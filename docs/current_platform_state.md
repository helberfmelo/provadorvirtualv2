# Estado Atual da Plataforma

Atualizado em: 2026-05-23

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
- Widget público: `/widget/v1/provador-virtual.js` e `/widget/v1/provador-virtual.css`.

## Próxima ação recomendada

Aguardar credenciais oficiais de Pagar.me e BigShop para executar transação real, ativação BigShop assinada e piloto em loja real; enquanto isso, usar `/app/go-live` como roteiro de demo assistida.
