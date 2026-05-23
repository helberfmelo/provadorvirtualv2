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
- API limpa em producao usa redirect 307 para `/provadorvirtual_v2/public/api/...` no HostGator; `curl -L` e navegadores recebem JSON real.
- Painel autenticado em producao usa `/provadorvirtual_v2/public/api/v1` direto para evitar perda de `Authorization` em clientes que nao preservam header durante redirect.
- Falta definir se o go-live final sera na raiz `https://provadorvirtual.online/` ou manter subpasta.
- Falta chave de IA se as primeiras sprints incluirem OCR/geracao assistida.
- Falta credencial BigShop real para loja de teste.
- Falta decidir provider de pagamento quando billing sair do modo preparado.

## Superficie atual

- Painel protegido: `/app`, `/app/produtos`, `/app/tabelas-de-medidas`, `/app/widget`, `/app/integracoes`.
- APIs protegidas: produtos, variacoes, tabelas, templates, widget-install e integracoes.
- APIs publicas: health, produto demo e recomendacoes do widget.
- Widget publico: `/widget/v1/provador-virtual.js` e `/widget/v1/provador-virtual.css`.

## Proxima acao recomendada

Iniciar a Sprint 6: Importacao e Templates Assistidos.
