# Widget e Integração Universal

Atualizado em: 2026-05-23

## Objetivo

Permitir que qualquer e-commerce instale o Provador Virtual com um snippet simples, sem depender da plataforma.

## Snippet padrão

```html
<div id="provador-virtual-container"></div>
<script
  id="provadorVirtualScript"
  src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js"
  data-merchant-id="MERCHANT_ID"
  data-store-id="STORE_ID"
  data-product-id="PRODUCT_ID"
  data-variant-id="VARIANT_ID"
  data-sku="SKU_DO_PRODUTO"
  data-platform="custom"
  data-container-id="provador-virtual-container"
  defer>
</script>
```

## Atributos

- `data-merchant-id`: conta do lojista no Provador Virtual.
- `data-store-id`: loja/empresa dentro do lojista.
- `data-product-id`: produto na plataforma origem.
- `data-variant-id`: variação/grade na plataforma origem.
- `data-sku`: SKU usado como fallback de identificacao.
- `data-platform`: `bigshop`, `shopify`, `woocommerce`, `nuvemshop`, `vtex`, `tray` ou `custom`.
- `data-container-id`: container onde o botão inline deve aparecer.

## Onde instalar na página de produto

O script do widget deve ser instalado na página de produto, no template responsável pela PDP da loja.

O `div#provador-virtual-container` precisa ficar no ponto visual em que os botões devem aparecer, normalmente perto do seletor de tamanho/grade e antes ou próximo ao botão Comprar. Esse posicionamento é importante porque o consumidor decide o tamanho nesse trecho da página.

O `<script defer>` pode ficar no `head`, no fim do `body` ou no próprio template do produto, desde que o container exista quando o widget inicializar. Em lojas com SPA ou troca dinâmica de variante, produto, variação e SKU precisam refletir a opção atual do comprador.

Quando a grade/variação mudar depois que o widget já carregou, atualizar os atributos e recarregar:

```html
<script>
window.ProvadorVirtual?.reload({
  productId: 'ID_DO_PRODUTO',
  variantId: 'ID_DA_GRADE',
  sku: 'SKU_DA_GRADE'
})
</script>
```

Na BigShop, a instalação automática será feita futuramente no arquivo `produto.vue` da model3 plano pro, no repositório BigShop correto. Até lá, o fallback é usar o snippet no mesmo ponto visual da página de produto.

## Comportamento esperado

1. Widget carrega sem bloquear a loja.
2. Executa config-check.
3. Se produto não estiver configurado, não mostra botão ou mostra aviso discreto apenas em modo debug.
4. Se produto estiver configurado, mostra dois botões: `Descubra seu tamanho` e `Tabela de Medidas`.
5. `Descubra seu tamanho` abre modal/drawer de recomendação.
6. `Tabela de Medidas` abre a tabela do produto com as faixas cadastradas.
7. Coleta dados em etapas e reusa medidas salvas localmente no navegador quando houver.
8. Retorna recomendação.
9. Coleta consentimento para salvar medidas no perfil anônimo.
10. Coleta feedback.
11. Exibe `desenvolvido por provadorvirtual.online` com link para o site público.

Status Sprint 4: implementado em `/widget/v1/provador-virtual.js` com CSS escopado em `/widget/v1/provador-virtual.css`. A página `/produto-teste` carrega o widget real por snippet dinamico.

Status Sprint 5: o painel `/app/widget` gera o snippet a partir de `/api/v1/widget-install`, com tema, domínios liberados e produto de exemplo.

Status Sprint 11: as rotas públicas de recomendação validam `Origin` contra `allowed_domains` da instalação ativa. Requisições sem `Origin` continuam liberadas para smokes e chamadas server-to-server; domínios não cadastrados recebem `403`.

Status Sprint 24/25: o widget agora segue o padrão comercial de página de produto com os botões `Descubra seu tamanho` e `Tabela de Medidas`, modal de tabela, assinatura do Provador Virtual e tema ampliado. O painel `/app/widget` permite personalizar primaria, secundaria, destaque, fundo, texto, fonte, tamanho, peso e raio, com visualizador em tempo real.

Status Sprint 36: o widget usa `pv_shopper_profile_v2` em `localStorage`, envia `profile_id`/token quando houver consentimento, permite limpar medidas salvas, mostra precisao do perfil e envia gênero, formato corporal e preferência de caimento para melhorar recomendações futuras.

Status Sprint 66: o fluxo visual do v2 passou a seguir a lógica gamificada do v1, mantendo a identidade visual do v2. Ao clicar em `Descubra seu tamanho`, o consumidor entra em um drawer lateral com:

1. medidas básicas: altura, peso, idade opcional e consentimento local;
2. gênero, formato corporal e preferência de caimento;
3. medidas detalhadas derivadas da tabela configurada do produto;
4. resultado com tamanho recomendado, confiança, notas do motor e feedback final.

A barra `Nível de precisão da IA` usa pesos progressivos semelhantes ao v1: altura, peso, idade, gênero, formato corporal e medidas detalhadas. Quando chega a 100%, o widget dispara confete leve, sem dependência externa.

O feedback final fica visível no próprio resultado e salva `was_helpful`, `rating`, `selected_size` e `comment` no endpoint público atual. Além das medidas normalizadas usadas pelo motor, o widget envia `shopper_profile.raw_widget_data` com versão, origem, etapas concluídas, identidade técnica do produto, precisão, tabela e medidas brutas da jornada. Esse payload é persistido em `recommendation_logs.raw_widget_payload` e entra na rotina `pv:privacy-anonymize`.

Regra Sprint 67: o fluxo do drawer é obrigatoriamente sequencial. A etapa 1 pode pré-preencher dados salvos do navegador, mas a barra de precisão deve considerar somente altura, peso e idade nessa tela. O rodapé avança para `Corpo` e depois para `Detalhes`; ele só chama a API de recomendação na etapa 3. O confete só pode disparar quando a precisão real chegar a 100%, nunca em recomendação básica ou por dados ocultos de etapas futuras.

## Evolucao inteligente prevista

Benchmark Sizebay/Zak em `docs/sizebay_benchmark.md` confirmou que o widget deve evoluir para:

- carregar de forma assincrona;
- esconder o botão quando produto/tabela não estiver pronto;
- reconhecer consumidor anônimo por cookie/localStorage;
- reusar medidas anteriores com aviso claro;
- abrir edição de medidas em modal;
- mostrar recomendação rapida com altura/peso/idade;
- permitir refinamento por formato corporal e medidas detalhadas;
- registrar eventos de carrinho, pedido e devolucao quando a plataforma permitir.

## Contrato público atual

Endpoints usados pelo widget:

- `POST /api/v1/public/recommendations/config-check`
- `POST /api/v1/public/recommendations`
- `POST /api/v1/public/recommendations/{id}/feedback`
- `POST /api/v1/public/recommendations/{id}/signal`
- `POST /api/v1/public/shopper-profiles/forget`

`config-check` retorna também a tabela de medidas normalizada para o modal público, quando o produto estiver configurado.

`recommendations` retorna `shopper_profile` com `id`, token inicial, qualidade do perfil e mensagem para o consumidor. O token nunca fica em log ou HTML do lojista; fica somente no navegador do comprador.

`recommendations` também aceita `shopper_profile.raw_widget_data` para registrar a jornada completa do widget. Esse campo deve conter apenas dados operacionais da recomendação, sem nome, e-mail, telefone, documento ou outros identificadores pessoais diretos.

`signal` registra eventos `add_to_cart`, `purchase`, `return` e `exchange` para aprendizado estatístico. Plataformas que ainda não tiverem integração automática podem enviar esses sinais depois pelo próprio front ou por conector server-to-server.

O widget resolve a base da API a partir do próprio `src`. Quando o script está em uma subpasta, como `/provadorvirtual_v2/widget/v1/provador-virtual.js`, a base padrão da API é calculada diretamente como `/provadorvirtual_v2/public/api/v1`, evitando redirect no preflight CORS do navegador. Em instalações fora desse padrão, `data-api-base-url` pode sobrescrever a base explicitamente.

Em navegadores, o CORS permitido e calculado por lojista a partir do domínio da página de origem. O painel deve manter `allowed_domains` atualizado antes de instalar o widget em produção.

O widget expõe `window.ProvadorVirtual.reload(...)` para lojas que alteram tamanho/cor/grade sem recarregar a página. Esse método atualiza os identificadores do script, remove a instância anterior e executa novo `config-check` para o produto/variação atual.

O widget também expõe `window.ProvadorVirtual.diagnostics()` para depuração controlada. Em modo debug, falhas de carregamento emitem `provadorvirtual:config` com `api_base`, `request_url`, `error_name`, `error_message`, `http_status` e trecho do `response_body`, quando disponível.

## Guias por plataforma

### BigShop

Preferencialmente usar integração nativa de um clique. Fallback por snippet:

- inserir container perto do seletor de tamanho ou do botão de comprar;
- usar grade atual como `data-variant-id`;
- usar SKU ou grade id como `data-sku`;
- manter `data-platform="bigshop"`.

### WooCommerce

Usar hook/shortcode em página de produto:

- `woocommerce_before_add_to_cart_button`;
- `global $product`;
- SKU em `$product->get_sku()`;
- variação escolhida enviada pelo JS quando aplicável.

### Shopify

Inserir no template de produto:

- `product.id` em `data-product-id`;
- variant atual em `data-variant-id`;
- `product.selected_or_first_available_variant.sku` em `data-sku`.

### Nuvemshop

Inserir no template de produto:

- id do produto;
- id/SKU da variante selecionada;
- atualizar atributo quando a variante mudar.

### Custom

Usar SKU fixo ou atualizar dinamicamente com JS próprio da loja.

## Página de produto ficticia

`/produto-teste` deve usar o mesmo snippet e chamar os endpoints reais. Essa página será usada para:

- validação local;
- smoke de deploy;
- demonstracao comercial;
- debug de recomendação sem depender de loja externa.

## Smoke externo Sprint 12

Arquivo:

- `tools/widget-external-smoke.html`

Servir por `localhost` para simular uma loja externa usando o widget de produção.
Para domínios reais, cadastrar o domínio em `/app/widget` antes do teste.

## Compatibilidade com v1

Enquanto houver migracao, o widget pode aceitar aliases:

- `data-lojista-id` -> `data-merchant-id`;
- `data-produto-id-grade` -> `data-sku`;
- `data-sku-grade` -> `data-sku`.

O código novo deve gerar somente os atributos padrão em ingles.

## Painel do lojista

Rotas protegidas:

- `GET /api/v1/widget-install`
- `PATCH /api/v1/widget-install`
- `GET /api/v1/integrations`
- `PATCH /api/v1/integrations/{platform}`

Plataformas catalogadas: `bigshop`, `shopify`, `woocommerce`, `nuvemshop`, `vtex`, `tray`, `loja_integrada`, `magento`, `opencart` e `custom`.

Regra comercial Sprint 32: empresas que contrataram como BigShop recebem o desconto BigShop e, no painel, podem visualizar/configurar apenas instalação BigShop. O backend também bloqueia tentativas de salvar Shopify, WooCommerce, Nuvemshop, VTEX, Tray ou custom para esse contrato.

Status Sprint 34: o catálogo de integrações passou a incluir `loja_integrada`, `magento` e `opencart`, além das plataformas anteriores. `GET /api/v1/integrations` retorna guia, snippet, checklist e matriz de dados por plataforma. `POST /api/v1/integrations/{platform}/validate-install` valida domínio público, container, script, plataforma e identificadores do produto sem salvar o HTML da loja.

Credenciais de plataforma devem ser salvas apenas por endpoints protegidos e persistidas criptografadas. A API retorna somente flags como `has_access_token` e `has_webhook_secret`.
