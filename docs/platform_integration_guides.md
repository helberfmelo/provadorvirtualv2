# Guias de Integração por Plataforma

Atualizado em: 2026-05-29

## Objetivo

Permitir que o lojista escolha sua plataforma e tenha um passo a passo padrão para instalar o Provador Virtual com poucos cliques, mantendo BigShop como caminho preferencial de um clique.

## Status Sprint 139

Implementado:

- catálogo ampliado em `PlatformCatalog`;
- guias, snippets, checklist e matriz de dados retornados em `GET /api/v1/integrations`;
- plataformas: BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart, XML/feed, API e Personalizada;
- painel `/app/integracoes` com guia visual por plataforma, campos dinâmicos por tipo de conexão e ações coerentes com a fonte de dados;
- endpoint `POST /api/v1/integrations/{platform}/validate-install`;
- registro de validação em `integration_events` com `event_type=install_validation`;
- auditoria `integration.install_validated`;
- bloqueio comercial BigShop agora depende de `merchant_companies.bigshop_discount_active`: loja BigShop com benefício vê BigShop e solicita troca; loja sem benefício pode trocar a plataforma operacional no portal;
- SaaS admin recebe `integration_state` por empresa, com status técnico, status comercial, contagem de conexões e flags de API/feed/webhook sem expor token ou segredo.

Publicado no run `26647308642` e validado em produção com `scripts/validate-production.ps1`, incluindo `API integrations OK`.

## Onde a plataforma é informada

A plataforma da loja é gravada em `merchant_companies.platform`.

Locais de entrada:

- checkout público `/checkout`, no momento da contratação;
- SaaS admin `/saas/empresas/:id/editar`, para cadastro e correção operacional;
- portal da empresa `/app`, no bloco `Dados da empresa` do primeiro acesso quando o perfil ainda está incompleto;
- portal da empresa `/app/integracoes`, no bloco `Plataforma da loja`, para troca operacional entre plataformas não BigShop.

Regras:

- empresa que usa Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart, XML/feed, API ou personalizada pode trocar diretamente no portal para qualquer plataforma, inclusive BigShop, sem ativar desconto;
- empresa BigShop com `bigshop_discount_active=true` mantém o benefício comercial protegido: para sair da BigShop, precisa solicitar a troca no portal, aceitar os termos e aguardar revisão/pagamento pelo SaaS;
- o SaaS pode ajustar a plataforma e marcar/desmarcar o benefício BigShop no cadastro da empresa, preservando a diferença entre plataforma operacional e condição comercial.

Endpoint operacional:

```http
PATCH /api/v1/merchant/company-platform
```

Payload:

```json
{
  "platform": "shopify"
}
```

Permissão exigida: `integrations.edit`.

Fluxo protegido BigShop:

```http
POST /api/v1/merchant/integration-change-requests
```

Payload:

```json
{
  "to_platform": "shopify",
  "accepted_terms": true
}
```

O pedido aparece no SaaS em `Solicitações de troca` e na edição da empresa. O time interno registra link de pagamento, status e aplica a troca somente quando a solicitação estiver concluída.

## Checklist padrão

Todo guia usa os mesmos pontos de validação:

- domínio cadastrado no widget;
- página de produto publicada;
- container do Provador Virtual encontrado;
- script do widget carregado;
- plataforma informada no snippet;
- produto, variação ou SKU informados.

## Local correto de instalação

Em todas as plataformas, o widget deve ser instalado na página de produto. O container deve ficar no local em que os botões do Provador Virtual precisam aparecer, normalmente perto da seleção de tamanho/grade/cor e antes ou próximo ao botão Comprar.

O script pode carregar com `defer`, no `head`, no fim do `body` ou no template da PDP. O requisito é que o container esteja disponível quando o widget inicializar.

Para lojas que mudam a variação sem recarregar a página, a integração precisa atualizar produto, variação e SKU e chamar:

```js
window.ProvadorVirtual?.reload({
  productId: 'ID_DO_PRODUTO',
  variantId: 'ID_DA_GRADE',
  sku: 'SKU_DA_GRADE'
})
```

Na BigShop, o ponto oficial planejado para instalação automática é o `produto.vue` da model3 plano pro. Esse ajuste será feito em sprint futura no repositório BigShop correto; no SaaS, o fallback continua sendo o snippet gerado no painel.

## Google Tag Manager

A Sizebay documenta o Google Tag Manager como uma forma de implementação do provador: primeiro cria-se o elemento âncora na página de produto e depois uma tag HTML personalizada, disparada somente em páginas de produto, carrega o script/prescript do serviço. A própria documentação da Sizebay trata a conferência da instalação como equivalente para implantação direta ou por GTM.

No Provador Virtual, GTM deve ser oferecido como caminho opcional quando a plataforma não tem app, tema editável simples ou integração nativa pronta. Regras para usar com segurança:

- manter o container visual no template da página de produto, perto da grade/tamanho;
- disparar a tag somente em páginas de produto;
- ler produto, variação e SKU do DOM, variáveis GTM ou `dataLayer`;
- chamar `window.ProvadorVirtual.reload(...)` quando a loja troca variação sem recarregar a página;
- validar a URL no painel antes de publicar;
- para BigShop nativa, preferir instalação por app/tema oficial quando disponível, usando GTM apenas como fallback assistido.

## Endpoint de validação

Rota:

```http
POST /api/v1/integrations/{platform}/validate-install
```

Payload:

```json
{
  "url": "https://loja.com.br/produto-exemplo"
}
```

Se `url` não for enviada, a API tenta usar o domínio da empresa ativa.

Resposta:

```json
{
  "data": {
    "status": "passed",
    "url": "https://loja.com.br/produto-exemplo",
    "http_status": 200,
    "checks": []
  }
}
```

Regras:

- aceita somente URL pública `http` ou `https`;
- bloqueia `localhost`, IPs privados/reservados e hosts `.local`;
- não salva HTML da loja, apenas resumo dos checks;
- falha remota gera `status=failed` e erro operacional em `integration_events`.

## Matriz de dados

Campos avaliados por plataforma:

- `product_id`;
- `variant_id`;
- `sku`;
- troca de tamanho/variação;
- feed/API de produto;
- pedidos/devolucoes.

BigShop já possui probe/sync base. XML/feed possui fluxo próprio para salvar feed e sincronizar catálogo; API possui contrato de conexão para base autorizada, token criptografado e webhook opcional. As demais plataformas seguem em modo guia/snippet/manual, com API/plugin/webhook como evolução futura.

## Snippet universal

Base usada pelos guias:

```html
<div id="provador-virtual-container"></div>
<script
  id="provadorVirtualScript"
  src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js"
  data-platform="custom"
  data-store-id="STORE_ID"
  data-product-id="PRODUCT_ID"
  data-variant-id="VARIANT_ID"
  data-sku="SKU"
  data-container-id="provador-virtual-container"
  defer></script>
```

## Pendências

- Receber loja piloto BigShop real para validar instalação nativa.
- Criar plugins/apps oficiais para Shopify, WooCommerce e Nuvemshop quando houver demanda.
- Implementar tracking de carrinho, pedido e devolucao por plataforma.
- Validar cada guia em loja real ou homologação da respectiva plataforma.
