# Widget e Integração Universal

Atualizado em: 2026-05-29

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
4. Se produto estiver configurado, mostra os botões liberados pela resposta: `Descubra seu tamanho` e `Tabela de Medidas`, ou apenas `Tabela de Medidas` quando a tabela vinculada desativar o provador.
5. `Descubra seu tamanho` abre modal/drawer de recomendação.
6. `Tabela de Medidas` abre a tabela do produto com as faixas cadastradas.
7. Coleta dados em etapas e reusa medidas salvas localmente no navegador quando houver.
8. Retorna recomendação inicial assim que houver altura e peso, e refina a precisão conforme as etapas seguintes.
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

O feedback final fica visível no próprio resultado e salva `was_helpful`, `selected_size` e `comment` no endpoint público atual. O campo `rating` continua aceito pela API para compatibilidade com integrações ou feedbacks antigos, mas o widget público não exibe mais a escala de nota de 1 a 5. Além das medidas normalizadas usadas pelo motor, o widget envia `shopper_profile.raw_widget_data` com versão, origem, etapas concluídas, identidade técnica do produto, precisão, tabela e medidas brutas da jornada. Esse payload é persistido em `recommendation_logs.raw_widget_payload` e entra na rotina `pv:privacy-anonymize`.

Regra Sprint 67: o fluxo do drawer é obrigatoriamente sequencial. A etapa 1 pode pré-preencher dados salvos do navegador, mas a barra de precisão deve considerar somente altura, peso e idade nessa tela. O confete só pode disparar quando a precisão real chegar a 100%, nunca em recomendação básica ou por dados ocultos de etapas futuras.

Regra Sprint 68/78: a recomendação parcial volta a ficar disponível ao longo da jornada, como no v1. O widget não recomenda nada com apenas altura ou apenas peso; com altura + peso, já chama a API e mostra o tamanho recomendado no rodapé fixo. O botão dentro do corpo das etapas continua sendo `Aumentar precisão`, enquanto o rodapé mostra a barra `Nível de precisão da IA` e, quando houver retorno da API, `Usar tamanho X`.

Os passos 1, 2, 3 e 4 são clicáveis para avançar e voltar, mas respeitam bloqueios de dados: `Corpo` exige altura + peso, `Detalhes` exige gênero + formato corporal, e `Resultado` exige todas as medidas detalhadas da tabela. O confete só dispara ao entrar no resultado com 100% depois de preencher as medidas detalhadas. A opção `theme.confetti_enabled` permite desligar a celebração por loja; quando não configurada, o padrão é ativado.

As medidas salvas no navegador passam a usar chave por tabela de medidas (`pv_shopper_profile_v2_table_{id}`), além do fallback legado. Assim, produtos que compartilham a mesma tabela reabrem com dados e progresso preenchidos, mas continuam editáveis. Se o consumidor fechar o widget depois de uma recomendação e alterar algum dado, o widget salva o novo snapshot de forma silenciosa para manter o aprendizado atualizado. O aviso `Ao usar o Provador Virtual...` aparece somente na etapa 1, no fim do corpo rolável do widget, em itálico e com fonte menor que os demais microtextos.

Regra Sprint 76: o resultado final deve manter apenas a pergunta objetiva `Essa recomendação ajudou?`, com botões `Sim, ajudou` e `Não ajudou`, tamanho escolhido e comentário opcional. Não exibir escala redundante de nota de 1 a 5 no widget.

Regra Sprint 78: qualquer clique/toque no tamanho recomendado, seja no banner de recomendação parcial, no rodapé fixo ou no resultado, fecha o drawer e emite `provadorvirtual:size-selected` com `selected_size`, `recommended_size`, `confidence`, `precision` e o payload completo da recomendação. A loja pode ouvir esse evento para marcar o tamanho correspondente na página de produto. O widget também bloqueia o clique fantasma de touch que poderia reabrir o drawer imediatamente após aplicar o tamanho.

Status Sprint 104/105: a etapa inicial do fluxo foi enxugada para evitar repetição sobre altura e peso. O estado padrão mostra apenas o aviso para preencher altura/peso antes dos campos, com blocos mais compactos. Tooltips de medidas devem exibir acentuação correta e não entidades HTML escapadas.

Status Sprint 131: o widget/API pública respeita a ativação individual do produto. `config-check` e `recommendations` retornam `configured=false` com motivo explícito quando o produto estiver inativo, sem tabela, com Provador Virtual desligado ou com Tabela de Medidas bloqueada no detalhe do produto.

Status Sprint 134: a ativação por tabela foi adicionada ao contrato público. Quando `measurement_tables.metadata.activation.virtual_try_on_enabled=false`, `config-check` retorna `configured=true`, `virtual_try_on_enabled=false`, mantém `measurement_table_enabled=true` e inclui a tabela normalizada para o modal público. O widget renderiza somente o botão `Tabela de Medidas`; tentativas de recomendação retornam `table_virtual_try_on_disabled`.

Status Sprint 147: o painel `/app/widget` passou a expor editor dedicado do modal do provador, com `theme.presentation_mode` e `theme.modal.*` para logo, textos, etapas, tabela, cores, bordas, tipografia e estilo da tabela. O preview desktop/mobile mostra o modal completo e a tabela integrada, enquanto o backend normaliza temas antigos e exige contraste mínimo antes de publicar.

Status Sprint 148: o widget público passou a registrar eventos operacionais de uso em `POST /api/v1/public/widget-events`, cobrindo impressões, abertura do provador, abertura da tabela, recomendação gerada, aplicação de tamanho e envio de feedback. O `event_id` é determinístico por visita/ação para evitar contagem duplicada em re-render, reabertura ou repetição da mesma recomendação.

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
- `POST /api/v1/public/widget-events`
- `POST /api/v1/public/shopper-profiles/forget`

`config-check` retorna também a tabela de medidas normalizada para o modal público, quando o produto estiver configurado. Quando bloqueado por produto, retorna `reason` como `virtual_try_on_disabled`, `measurement_table_disabled`, `product_inactive` ou `measurement_table_missing`. Quando apenas o provador da tabela estiver desativado, a resposta permanece configurada para permitir a tabela pública e informa `virtual_try_on_enabled=false`.

`recommendations` retorna `shopper_profile` com `id`, token inicial, qualidade do perfil e mensagem para o consumidor. O token nunca fica em log ou HTML do lojista; fica somente no navegador do comprador.

`recommendations` também aceita `shopper_profile.raw_widget_data` para registrar a jornada completa do widget. Esse campo deve conter apenas dados operacionais da recomendação, sem nome, e-mail, telefone, documento ou outros identificadores pessoais diretos.

`signal` registra eventos `add_to_cart`, `purchase`, `return` e `exchange` para aprendizado estatístico. Desde a Sprint 115, o payload pode incluir `ordered_size`, `returned_size`, `exchanged_to_size`, `return_reason`, `order_status`, `quantity`, `unit_price`, `source_platform` e `occurred_at`. `order_reference` é aceito apenas para gerar hash interno; a referência bruta não deve aparecer em telas, logs ou documentos. Plataformas que ainda não tiverem integração automática podem enviar esses sinais depois pelo próprio front ou por conector server-to-server.

`widget-events` registra eventos operacionais do funil público sem exigir autenticação de shopper. Os eventos aceitos hoje são `button_impression`, `virtual_try_on_open`, `measurement_table_open`, `recommendation_generated`, `size_selected` e `feedback_submitted`. O backend deriva `device_type` do `User-Agent`, aceita `session_key` e `visit_key` para análise agregada e deduplica por `client_event_id` dentro do merchant.

O widget resolve a base da API a partir do próprio `src`. Quando o script está em uma subpasta, como `/provadorvirtual_v2/widget/v1/provador-virtual.js`, a base padrão da API é calculada diretamente como `/provadorvirtual_v2/public/api/v1`, evitando redirect no preflight CORS do navegador. Em instalações fora desse padrão, `data-api-base-url` pode sobrescrever a base explicitamente.

Em navegadores, o CORS permitido e calculado por lojista a partir do domínio da página de origem. O painel deve manter `allowed_domains` atualizado antes de instalar o widget em produção.

O widget expõe `window.ProvadorVirtual.reload(...)` para lojas que alteram tamanho/cor/grade sem recarregar a página. Esse método atualiza os identificadores do script, remove a instância anterior e executa novo `config-check` para o produto/variação atual.

O widget também expõe `window.ProvadorVirtual.diagnostics()` para depuração controlada. Em modo debug, falhas de carregamento emitem `provadorvirtual:config` com `api_base`, `request_url`, `error_name`, `error_message`, `http_status` e trecho do `response_body`, quando disponível.

O drawer do widget usa as cores configuradas no tema da loja para cabeçalho, CTAs e barra de precisão. Desde a Sprint 75, as silhuetas de formato corporal são assets públicos herdados do v1 em `/widget/v1/assets/body-shapes/` e renderizados como imagens reais, evitando falhas de máscara CSS em navegadores mobile. Desde a Sprint 78, essas imagens usam carregamento imediato dentro do drawer para evitar placeholders vazios em navegadores mobile.

Status Sprint 92: o tema do widget aceita `presentation_mode` com os valores `drawer` e `modal`. O padrão continua `drawer` para instalações existentes. Quando o lojista escolhe `modal` em `/app/widget`, o mesmo fluxo de recomendação abre em um modal central grande no desktop e ocupa a tela toda no mobile, sem alterar etapas, eventos, recomendação, feedback, tabela de medidas ou persistência local. Publicado no run `26413966332` e validado em produção.

Status Sprint 93: a opção `theme.confetti_enabled` continua controlando a celebração por loja. Em `/app/widget`, ao marcar `Animação de confetes`, o portal dispara uma prévia com a mesma classe `.pv-confetti-layer`, 42 peças, cores e keyframes do widget público, para a empresa ver exatamente o efeito que o comprador verá ao chegar ao resultado completo. Publicado no run `26414392783` e validado em produção.

Status Sprint 96: `/api/v1/widget-install` passa a retornar `platform_guide` e `platform_guides` com snippet, ponto de instalação, passos, matriz de dados e exemplo de `reload` por plataforma. A tela `/app/widget` foi reorganizada em blocos de instalação, domínios e personalização, com preview, código e guia lateral atualizados automaticamente conforme a plataforma escolhida. A validação de produção passa a cobrir também `/app/widget`.

Status Sprint 106: a personalização do widget passa a aceitar `theme.button_style`, `theme.button_background` e `theme.button_text`. Os estilos disponíveis são `gradient`, `clean`, `outline` e `soft`, inspirados no padrão público observado da Sizebay sem copiar seus assets: botões com ícones/texto curto, variações minimalistas ou preenchidas e animações de hover como brilho, elevação, sublinhado e preenchimento. Em `/app/widget`, a empresa escolhe o estilo em lista vertical, ajusta fundo/texto dos botões em um box próprio e vê a prévia antes de salvar.

Correção Sprint 108: depois da confirmação da galeria correta `https://sizebay-buttons-gallery.vercel.app/`, a personalização passa a exibir 10 modelos inspirados nos cards públicos da galeria, sem copiar assets da Sizebay. Os valores novos são `gallery_1_text_icons`, `gallery_2_side_icons`, `gallery_3_dark_outline`, `gallery_4_underlined_icons`, `gallery_5_pills`, `gallery_6_split_line`, `gallery_7_editorial_links`, `gallery_8_dotted_stack`, `gallery_9_light_block` e `gallery_10_badge_tooltip`. Os valores antigos `gradient`, `clean`, `outline` e `soft` continuam aceitos no backend/widget para compatibilidade, mas o portal passa a selecionar os 10 modelos novos.

## Guias por plataforma

Referências técnicas primárias consultadas para manter os guias alinhados com as plataformas: Shopify Liquid `product.selected_or_first_available_variant` (`https://shopify.dev/docs/api/liquid/objects/product`), WooCommerce template/hook de variações (`https://woocommerce.github.io/code-reference/files/woocommerce-templates-single-product-add-to-cart-variation-add-to-cart-button.html`), VTEX Product Context (`https://developers.vtex.com/docs/guides/vtex-product-context`), Nuvemshop `LS.registerOnChangeVariant` (`https://tiendanube.github.io/api-documentation/v1/intro`) e Adobe Commerce product layouts (`https://developer.adobe.com/commerce/frontend-core/guide/layouts/product-layouts`).

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
- quando o tema trocar variante sem recarregar a página, chamar `window.ProvadorVirtual.reload(...)` no evento de mudança de variante.

### Nuvemshop

Inserir no template de produto:

- id do produto;
- id/SKU da variante selecionada;
- atualizar atributo quando a variante mudar;
- usar `LS.registerOnChangeVariant(callback)` quando disponível para recarregar o provador com a variante escolhida.

### VTEX

Inserir em bloco/app de storefront ou no template da PDP:

- usar `productContext.product.productId` como produto;
- usar `productContext.selectedItem.itemId` como variação/SKU selecionado;
- recarregar o provador quando o SKU selector mudar.

### Tray

Inserir no template de produto:

- posicionar o container perto de `productHelper.variants()` e antes de `productHelper.form()` quando o tema usar esses helpers;
- usar `product.id` e id/reference da variação selecionada;
- se o tema expuser a variação apenas por JS, preencher os atributos e chamar `reload`.

### Loja Integrada

Inserir pelo editor do tema ou HTML/JS personalizado da página de produto:

- mapear produto, variação e SKU a partir das variáveis do tema ou do DOM;
- manter o container perto do seletor de tamanho;
- chamar `reload` quando o comprador trocar a variação.

### Magento / Adobe Commerce

Inserir via layout XML/bloco de produto:

- usar `catalog_product_view`/template de produto;
- usar `$block->getProduct()` para produto e SKU base;
- em produto configurável, atualizar variante simples por JS quando a opção mudar.

### OpenCart

Inserir no template `catalog/view/theme/{tema}/template/product/product.twig`:

- usar `product_id` como produto;
- usar `model`/SKU como identificador;
- em opções/tamanhos, recarregar o provador quando `#product input` ou `#product select` mudar.

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

Regra comercial atual: `platform=bigshop` define a plataforma operacional do widget; `bigshop_discount_active=true` define o benefício comercial. Empresas sem benefício podem trocar de plataforma em `/app/integracoes`, inclusive para BigShop sem desconto. Empresas BigShop com benefício ativo solicitam a troca para outra plataforma pelo portal, aceitam os termos de troca e aguardam o SaaS revisar diferença de plano, link de pagamento e aplicação da mudança.

Status Sprint 34: o catálogo de integrações passou a incluir `loja_integrada`, `magento` e `opencart`, além das plataformas anteriores. `GET /api/v1/integrations` retorna guia, snippet, checklist e matriz de dados por plataforma. `POST /api/v1/integrations/{platform}/validate-install` valida domínio público, container, script, plataforma e identificadores do produto sem salvar o HTML da loja.

Status Sprint 121: `GET /api/v1/integrations` passa a retornar também `setup` por plataforma, separando campos de conexão, fluxo de catálogo, ponto de instalação na página de produto e tracking/aprendizado. O status exposto pela API é efetivo: conexões antigas gravadas como `draft`, mas com dados mínimos de configuração, aparecem como `configured`; a interface exibe esse estado como `Configurada` e reserva `Pendente` para integrações sem dados suficientes.

Status Sprint 123: `/app/integracoes` mostra `Mudar integração` para BigShop com benefício ativo, abre modal com aceite dos termos e grava `integration_change_requests`. O SaaS lista solicitações pendentes na visão geral e na edição da empresa, podendo registrar link de pagamento/status e aplicar a troca quando concluída.

Credenciais de plataforma devem ser salvas apenas por endpoints protegidos e persistidas criptografadas. A API retorna somente flags como `has_access_token` e `has_webhook_secret`.
