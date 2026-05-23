# Guias de Integração por Plataforma

Atualizado em: 2026-05-23

## Objetivo

Permitir que o lojista escolha sua plataforma e tenha um passo a passo padrão para instalar o Provador Virtual com poucos cliques, mantendo BigShop como caminho preferencial de um clique.

## Status Sprint 34

Implementado:

- catálogo ampliado em `PlatformCatalog`;
- guias, snippets, checklist e matriz de dados retornados em `GET /api/v1/integrations`;
- plataformas: BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart e Personalizada;
- painel `/app/integracoes` com guia visual por plataforma;
- endpoint `POST /api/v1/integrations/{platform}/validate-install`;
- registro de validação em `integration_events` com `event_type=install_validation`;
- auditoria `integration.install_validated`;
- bloqueio mantido: contrato BigShop vê e valida apenas BigShop.

Publicado e validado em produção no run `26339199751`.

## Checklist padrão

Todo guia usa os mesmos pontos de validação:

- domínio cadastrado no widget;
- página de produto publicada;
- container do Provador Virtual encontrado;
- script do widget carregado;
- plataforma informada no snippet;
- produto, variação ou SKU informados.

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

BigShop já possui probe/sync base. As demais plataformas estão em modo guia/snippet/manual, com API/plugin/webhook como evolucao futura.

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
