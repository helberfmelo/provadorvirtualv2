# Estado Atual da Plataforma

Atualizado em: 2026-05-23

## Estado do workspace

- `D:\Projetos\provadorvirtual_v2` estava vazio no inicio desta documentacao.
- Git local foi inicializado em `main` e conectado ao remoto `git@github.com:helberfmelo/provadorvirtualv2.git`.
- Foram criados documentos iniciais, `.gitignore` e workflow de deploy.
- Sprint 1 scaffoldou `backend/` Laravel 12 e `frontend/` Vue 3/Vite.

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
- Falta confirmar se o path remoto de v2 sera `/home1/opents62/public_html/provadorvirtual_v2`.
- Acesso SSH local ao HostGator/opents62 foi validado; `/home1/opents62/public_html` existe e a pasta `provadorvirtual_v2` ainda nao foi criada.
- Falta definir se o go-live final sera na raiz `https://provadorvirtual.online/` ou manter subpasta.
- Falta chave de IA se as primeiras sprints incluirem OCR/geracao assistida.
- Falta credencial BigShop real para loja de teste.
- Falta decidir provider de pagamento quando billing sair do modo preparado.

## Proxima acao recomendada

Concluir a publicacao da Sprint 1 pelo GitHub Actions e, se estiver verde, iniciar a Sprint 2: produtos, variacoes e tabelas de medidas.
