# Estado Atual da Plataforma

Atualizado em: 2026-05-23

## Estado do workspace

- `D:\Projetos\provadorvirtual_v2` estava vazio no inicio desta documentacao.
- Git local foi inicializado em `main` e conectado ao remoto `git@github.com:helberfmelo/provadorvirtualv2.git`.
- Foram criados documentos iniciais, `.gitignore` e workflow de deploy.
- Sprint 1 scaffoldou `backend/` Laravel 12 e `frontend/` Vue 3/Vite.
- Sprint 2 publicou CRUD de produtos, variacoes, tabelas de medidas e templates operacionais no painel e na API.
- Sprint 3 criou motor deterministico, endpoints publicos de recomendacao/config-check/feedback e conectou `/produto-teste` ao backend real.
- Sprint 4 criou widget publico em `/widget/v1/provador-virtual.js` e `/widget/v1/provador-virtual.css`, com modal, config-check, recomendacao e feedback.
- Sprint 5 criou configuracao operacional do widget no painel, catalogo de integracoes e persistencia de conexoes por plataforma.
- Sprint 6 criou importacao com preview/commit para CSV de produtos, CSV de tabelas e Google XML inicial.
- Sprint 7 criou conector BigShop base com probe, sync de produtos/grades/tabelas e eventos de integracao.
- Sprint 8 criou ativacao BigShop um clique por endpoint publico assinado com HMAC.
- Sprint 9 criou assistente de tabelas com parser local, logs de uso de IA e revisao obrigatoria.
- Sprint 10 criou analytics do lojista, SaaS admin basico e trilha `audit_logs`.
- Sprint 11 criou paginas legais, CORS por dominio do widget, rate limits, status operacional e rotinas de privacidade.
- Sprint 12 criou checklist de go-live, endpoint/tela de prontidao, script de validacao de producao e plano de cutover.
- Sprint 23 criou cadastro interno de empresas no SaaS, endereco completo, CPF de usuario, codigo de acesso `aaaa + id`, busca publica por codigo/CNPJ e comando para master admin.
- Sprint 24 criou loja teste realista com 4 produtos, 4 tabelas demo e widget com os botoes `Descubra seu tamanho` e `Tabela de Medidas`.
- Sprint 25 criou personalizador visual do widget/tabela com preview em tempo real no painel do lojista.
- Sprint 26 criou landing publica, checkout transparente Pagar.me, sessoes/eventos de pagamento e webhook de ativacao.
- Sprint 27 ajustou a landing para estrutura inspirada no v1, publicou build prevista para a raiz e simplificou o checkout para plano anual unico sem boleto.
- Sprint 28 criou monitor de pagamentos pendentes, agendamento de cron/scheduler e configuracao SaaS de SMTP/templates transacionais; publicado em producao no run `26336899986`.
- Sprint 29 preparou login por e-mail/CPF, acesso do portal por codigo/CNPJ e contexto de empresa no token; publicado em producao no run `26337254520`.
- Sprint 30 criou CRUD de usuarios no SaaS e no portal da empresa, permissoes por modulo/menu, status global/por empresa e acoes de editar/ativar/desativar; publicado em producao no run `26337792120`.
- Sprint 31 criou automacoes de e-mail transacional, historico de envios e comando/scheduler para pendencias financeiras; publicado em producao no run `26338061259`.
- Sprint 32 implementou refinamento da oferta publica, trava de integracao BigShop, favicon/OG, footer, imagens da loja teste e menu mobile em drawer; publicado em producao no run `26338411089`.
- Sprint 33 completou login multiempresa, seletor de empresa no painel, escopo por empresa nas APIs do portal, enforcement de permissoes por rota e auditoria com empresa/modulo/acao; publicado em producao no run `26338888072`.
- Sprint 34 criou guias de integracao por plataforma, snippets, checklist visual, matriz de dados suportados e validacao protegida de instalacao por URL publica; publicado em producao no run `26339199751`.
- Sprint 35 preparou o contrato BigShop um clique com snippet/contract na resposta da ativacao e monitor protegido de ativacoes no painel; publicado em producao no run `26339426665`.
- Sprint 36 criou perfis anonimos com consentimento, token local, esquecimento, eventos de aprendizado, sinais comerciais, outlier score e analytics de qualidade; publicado em producao no run `26339824157`.
- Sprint 37 ampliou o pacote de piloto/go-live com checks de Pagar.me, transacao real, cron, performance do widget, acessibilidade/mobile, comandos de automacao e onboarding comercial.

## Referencias confirmadas

### BigShop HelpDesk

- Laravel 11 + Sanctum no backend.
- Vue 3 + TypeScript + Pinia + Vue Router no frontend.
- Deploy por GitHub Actions via SSH.
- Padrao visual: `#0f172a`, `#ff4d5e`, `#ff7a1a`, `#111827`, Manrope.
- Governanca forte: documentos obrigatorios antes de sprint, commit/push apos sprint, Actions acompanhado.

### Marca Hora

- Laravel com deploy FTP + SSH em HostGator/opents62.
- Uso de `SSH_USERNAME` nos secrets.
- Compatibilidade de MySQL compartilhado exige `DB_COLLATION=utf8mb4_unicode_ci`.
- Caminho de referencia no servidor: `/home1/opents62/public_html/...`.

### BigShop360

- Contrato de integracao BigShop ja analisado.
- API BigShop V3 observada em `https://api.bigshop.com.br`.
- Headers observados: `x-api` e `store-id`.
- Rotas publicas observadas: produtos, busca, marcas, categorias, grades, clientes e vendas.
- Lacunas importantes: carrinho, frete, checkout, webhooks e contrato formal de erro.

### Provador Virtual v1

- PHP puro, MySQL e JS vanilla.
- Widget com `data-merchant-id`, `data-sku-grade` e compatibilidade com atributos antigos.
- Endpoints de recomendacao e feedback.
- Tabelas de medidas, produtos, empresas, logs e feedbacks.
- Gemini usado para OCR/IA em partes do produto.
- Pagina de teste BigShop existente.

### BigShop front/back

- Front da loja BigShop tem pagina de produto Vue/Quasar em `front/stores/pro_store/produto.vue`.
- Produto possui `tabela_de_medidas`, grades/tamanhos, SKU e identificadores de grade.
- Front API V3 usa `get_contents_api3.php` e `auxiliar_functions_v3.php`.
- Back/API contem funcoes para produto, grade, tabela de medidas e busca.

## Decisoes ja tomadas

- Preservar v1 durante desenvolvimento.
- Publicar v2 inicialmente em `/provadorvirtual_v2/`.
- Usar Laravel/Vue em estrutura separada `backend/` e `frontend/`.
- Usar OpenAPI/documentacao para endpoints publicos e integraveis quando forem criados.
- Widget deve ser isolado e resiliente a CSS/JS do e-commerce.

## Bloqueios e informacoes faltantes

- GitHub Actions voltou a executar apos o repositorio ser alterado para publico.
- Path remoto confirmado pelo v1: `/home1/opents62/provadorvirtual.online/provadorvirtual_v2`.
- Acesso SSH local ao HostGator/opents62 foi validado; `https://provadorvirtual.online/provadorvirtual_v1/` responde a partir de `/home1/opents62/provadorvirtual.online/provadorvirtual_v1`.
- Sprint 1 publicada em producao pelo GitHub Actions no run `26326675713`.
- Sprint 2 publicada em producao pelo GitHub Actions no run `26326950616`.
- Sprint 3 publicada em producao pelo GitHub Actions no run `26327119754`.
- Sprint 4 publicada em producao pelo GitHub Actions no run `26331199145`.
- Sprint 5 publicada em producao pelo GitHub Actions no run `26331485173`.
- Sprint 6 publicada em producao pelo GitHub Actions no run `26331691701`.
- Sprint 7 publicada em producao pelo GitHub Actions no run `26331844564`.
- Sprint 8 publicada em producao pelo GitHub Actions no run `26332055677`.
- Sprint 9 publicada em producao pelo GitHub Actions no run `26332326042`.
- Sprint 10 publicada em producao pelo GitHub Actions no run `26332544138`.
- Sprint 11 publicada em producao pelo GitHub Actions no run `26332960822`.
- Sprint 12 publicada em producao pelo GitHub Actions no run `26333226813`.
- Sprint 33 publicada em producao pelo GitHub Actions no run `26338888072`.
- Sprint 34 publicada em producao pelo GitHub Actions no run `26339199751`.
- Sprint 35 publicada em producao pelo GitHub Actions no run `26339426665`.
- Sprint 36 publicada em producao pelo GitHub Actions no run `26339824157`; o run `26339739429` falhou por limite de tamanho de nome de foreign key MySQL e foi corrigido no commit `5d5b5dc`.
- API limpa em producao usa redirect 307 para `/provadorvirtual_v2/public/api/...` no HostGator; `curl -L` e navegadores recebem JSON real.
- Painel autenticado em producao usa `/provadorvirtual_v2/public/api/v1` direto para evitar perda de `Authorization` em clientes que nao preservam header durante redirect.
- A raiz `https://provadorvirtual.online/` passa a ser o site publico comercial; `/provadorvirtual_v2/` permanece como app/backend e rollback.
- Falta chave de IA externa (`OPENAI_API_KEY` ou `GEMINI_API_KEY`) para OCR real de imagem.
- Falta credencial BigShop real para loja de teste.
- Falta cadastrar `BIGSHOP_ACTIVATION_SECRET` em `PRODUCTION_ENV` para habilitar ativacao um clique real.
- Falta cadastrar as chaves Pagar.me em `PRODUCTION_ENV`, com URLs de retorno na raiz, configurar cron no cPanel e validar uma transacao real de baixo valor.
- Sprint 37 deixa essas pendencias visiveis em `/app/go-live`; teste real de Pagar.me e BigShop continua bloqueado ate receber/cadastrar as credenciais oficiais.

## Superficie atual

- Painel protegido: `/app`, `/app/produtos`, `/app/tabelas-de-medidas`, `/app/assistente`, `/app/analytics`, `/app/widget`, `/app/integracoes`, `/app/usuarios`.
- Painel SaaS protegido por papel/permissao: `/saas` e `/saas/usuarios`.
- Login do portal: `/login`, aceitando e-mail ou CPF, campo de codigo/CNPJ para empresa e seletor quando o usuario tem multiplas empresas.
- Checkout publico: `/checkout` e `/checkout/sucesso`.
- APIs protegidas: produtos, variacoes, tabelas, templates, widget-install e integracoes, com middleware de permissao por modulo e escopo da empresa ativa.
- Importacoes protegidas: preview, commit e historico em `/api/v1/imports`.
- Assistente protegido: status e sugestoes em `/api/v1/ai/*`.
- Analytics/auditoria protegidos: `/api/v1/analytics/*`, `/api/v1/audit-logs` e `/api/v1/saas/*`.
- Go-live protegido: `/api/v1/go-live/readiness` e `/app/go-live`.
- Pacote comercial protegido: `/app/go-live` mostra links de venda, onboarding, automacoes e pendencias reais.
- Observabilidade publica: `/api/v1/ops/status`.
- BigShop protegido: probe e sync em `/api/v1/integrations/bigshop/*`.
- Monitor BigShop protegido: `GET /api/v1/integrations/bigshop/activations`.
- Validacao de instalacao protegida: `POST /api/v1/integrations/{platform}/validate-install`.
- BigShop publico assinado: ativacao em `/api/v1/public/bigshop/activate`.
- APIs publicas: health, produto demo e recomendacoes do widget.
- APIs publicas inteligentes: `/api/v1/public/recommendations/{id}/signal` e `/api/v1/public/shopper-profiles/forget`.
- APIs publicas de checkout: `/api/v1/public/checkout/config`, `/api/v1/public/checkout`, `/api/v1/public/checkout/{reference}` e `/api/v1/webhooks/pagarme`.
- APIs publicas de empresa: `/api/v1/public/company-access`.
- APIs SaaS de e-mail: `/api/v1/saas/email-settings` e `/api/v1/saas/transactional-emails`.
- Historico SaaS de e-mail: `/api/v1/saas/transactional-email-sends`.
- APIs de usuarios/permissoes: `/api/v1/merchant/users` e `/api/v1/saas/users`.
- Widget publico: `/widget/v1/provador-virtual.js` e `/widget/v1/provador-virtual.css`.

## Proxima acao recomendada

Aguardar credenciais oficiais de Pagar.me e BigShop para executar transacao real, ativacao BigShop assinada e piloto em loja real; enquanto isso, usar `/app/go-live` como roteiro de demo assistida.
