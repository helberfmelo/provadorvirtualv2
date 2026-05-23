# Benchmark Sizebay e Zak

Atualizado em: 2026-05-23

Objetivo: registrar o que podemos aprender com a Sizebay e com a loja Zak para deixar o Provador Virtual v2 simples de integrar, inteligente e comparavel comercialmente.

## Fontes publicas consultadas

- Documentacao inicial Sizebay: https://docs.sizebay.com/
- Implementacao por script: https://docs.sizebay.com/size-and-fit-implementation/service-implementation-script
- Implementacao por API: https://docs.sizebay.com/size-and-fit-implementation/service-implementation-api
- Integracao de produto por API: https://docs.sizebay.com/integration-products/api-product-integration
- Integracao de produto por XML: https://docs.sizebay.com/integration-products/feed-xml
- Integracao OnPage: https://docs.sizebay.com/integration-products/integracao-na-pagina
- Categorias aceitas: https://docs.sizebay.com/integration-products/categories-accepted-by-the-integration
- Order Tracking API: https://docs.sizebay.com/size-and-fit-order-tracking/order-tracking-api
- Return Data Integration: https://docs.sizebay.com/size-and-fit-data-integration/return-data-integration-csv
- Size Tailor: https://docs.sizebay.com/size-and-fit-data-integration/size-tailor
- Shopify: https://docs.sizebay.com/size-and-fit-implementation/service-implementation-shopify
- Blog Sizebay sobre produto e plataformas: https://sizebay.com/en/blog/what-is-sizebay/
- Loja Zak: https://www.zak.com.br/
- Produto Zak camisa: https://www.zak.com.br/704986-camisa-masculina-bambu-drop-areia-04
- Produto Zak calca: https://www.zak.com.br/calca-masculina-alfaiataria-genova-chumbo-63
- Exemplo publico adicional encontrado usando Sizebay: https://www.shop2gether.com.br/calca-feminina-crepe-cintura-baixa-alfaiataria-off-white-191348

## O que a Sizebay faz bem

- Carregamento assincrono para nao travar a pagina de produto.
- Snippet simples com prescript por tenant.
- Botao de provador e botao de tabela de medidas no ponto de decisao de tamanho.
- Identificacao anonima por cookie `SIZEBAY_SESSION_ID_V4`.
- Consulta de produto por permalink ou SKU antes de exibir botoes.
- VFR e tabela em iframe/modal independente da plataforma.
- Recomendacao passiva na PDP quando ja existe perfil.
- Tracking de carrinho e pedido para medir funil.
- Integracao de devolucoes para refinar recomendacoes.
- Integracao de produtos por API, XML Google Shopping ou OnPage.
- Suporte a plataformas de mercado e plataformas proprietarias.

## Contrato tecnico observado na documentacao

### Script

Padrao de script:

```html
<div id="sizebay-container"></div>
<script defer id="sizebay-vfr-v4" src="https://static.sizebay.technology/TENANT_ID/prescript.js"></script>
```

Para o v2, o equivalente deve continuar sendo:

```html
<div id="provador-virtual-container"></div>
<script defer id="provadorVirtualScript" src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js"></script>
```

O v2 deve manter configuracao por `data-*`, mas evoluir para autodetectar produto em plataformas conhecidas.

### Produto

Campos importantes vistos na integracao de produto:

- nome;
- genero: `M`, `F`, `U`;
- permalink;
- imagem;
- `feedProductId`;
- marca;
- categoria;
- tamanhos disponiveis;
- faixa etaria;
- tipo de tamanho.

Categorias de referencia para normalizacao:

- Top;
- Bottom;
- Full Body;
- Top Underwear;
- Bottom Underwear;
- Shoe.

### Usuario e recomendacao

Fluxo publico:

1. checar/criar SID;
2. identificar produto;
3. exibir botoes se o produto existir;
4. abrir VFR ou tabela;
5. buscar recomendacao por produto e perfil;
6. registrar abertura/eventos.

O v2 deve ter fluxo equivalente, mas com endpoint proprio:

- `config-check`;
- `profile/resolve`;
- `recommendations`;
- `recommendations/{id}/feedback`;
- futuros eventos `cart`, `order`, `return`.

### Tracking e aprendizado

Order tracking usa eventos de carrinho e pedido. Return Data usa devolucoes com motivo padronizado:

- desconhecido/outros;
- ficou pequeno;
- ficou grande.

O v2 deve implementar isso como recurso nativo, com privacidade e consentimento.

## Captura tecnica na Zak

Captura feita em 2026-05-23 por navegador headless em paginas publicas da Zak.

### Integracao detectada

- Plataforma da loja: BigShop.
- Tenant Sizebay: `1235`.
- Script: `https://static.sizebay.technology/1235/prescript.js`.
- CSS: `https://static.sizebay.technology/1235/styles/styles_v4.css`.
- Implantacao: `https://vfr-v3-production.sizebay.technology/V4/implantation/index.js`.
- Container: `#szb-container`.
- Botao VFR: `#szb-vfr-button`, texto `DESCUBRA SEU TAMANHO`.
- Botao tabela: `#szb-measurements-button`, texto `TABELA DE MEDIDAS`.
- Cookies usados: `SIZEBAY_SESSION_ID_V4` e `szb-current-product-id`.
- O front da BigShop chama `Sizebay.changePermalink(window.location.href)` ao montar produto.

O fluxo interno do VFR observado:

1. genero;
2. altura, peso e idade;
3. ajuste de formato corporal aproximado;
4. medidas corporais calculadas;
5. tamanho indicado;
6. opcoes vizinhas para provar;
7. editar medidas e fechar.

### Configuracao Sizebay da Zak

`config_v4.json` retornou:

```json
{
  "general": {
    "theme": { "name": "zak", "logo": "" },
    "gender": "M",
    "language": ["br"],
    "customLanguage": true,
    "isMetric": false,
    "ageSwitcher": false,
    "measurementSwitcher": false
  },
  "optionalSteps": {
    "BodyAdjustment": true,
    "Calculating": false,
    "SizeHint": false,
    "SizeContent": false
  }
}
```

Observacao: apesar de `isMetric=false`, a experiencia da Zak mostra cm/kg na interface em portugues.

### Produto Zak: camisa

URL: https://www.zak.com.br/704986-camisa-masculina-bambu-drop-areia-04

Dados capturados:

- Product ID Sizebay: `102784065`.
- Nome: `camisa masculina bambu drop - areia 04`.
- Brand: Zak.
- Categoria: `CAMISA`.
- Modelagem: `ABNT CAMISAS (M)`.
- Tipo: `TOP`.
- Sistema: `BR`.

Tabela observada:

| Tamanho | Torax | Cintura |
| --- | ---: | ---: |
| 1 | 90 | 80 |
| 2 | 98 | 88 |
| 3 | 106 | 96 |
| 4 | 112 | 102 |
| 5 | 118 | 108 |
| 6 | 124 | 116 |
| 7 | 130 | 124 |
| 8 | 136 | 132 |

Recomendacoes capturadas:

| Perfil | Entrada | Medidas inferidas | Tamanho |
| --- | --- | --- | --- |
| masc_170_68_28 | 170 cm, 68 kg, 28 anos | torax 96, cintura 81, quadril 96 | 1 |
| masc_178_78_35 | 178 cm, 78 kg, 35 anos | torax 101, cintura 89, quadril 100 | 2 |
| masc_188_95_45 | 188 cm, 95 kg, 45 anos | torax 111, cintura 102, quadril 111 | 4 |

### Produto Zak: calca

URL: https://www.zak.com.br/calca-masculina-alfaiataria-genova-chumbo-63

Dados capturados:

- Product ID Sizebay: `102784201`.
- Nome: `calca alfaiataria masculina genova - chumbo 63`.
- Brand: Zak.
- Categoria: `INFERIOR`.
- Modelagem: `ABNT INFERIOR (M)`.
- Tipo: `BOTTOM`.
- Sistema: `BR`.

Tabela observada:

| Tamanho | Cintura | Quadril |
| --- | --- | --- |
| 34 | 58-62 | 78-82 |
| 36 | 64-66 | 84-90 |
| 38 | 72-76 | 92-96 |
| 40 | 80 | 100 |
| 42 | 86 | 106 |
| 44 | 90-94 | 110-114 |
| 46 | 98-102 | 118-122 |
| 48 | 106-110 | 126-130 |
| 50 | 114-118 | 134-138 |
| 52 | 122-126 | 142-146 |
| 54 | 130-134 | 150-154 |
| 56 | 138-142 | 158-162 |

Recomendacoes capturadas:

| Perfil | Entrada | Medidas inferidas | Tamanho |
| --- | --- | --- | --- |
| masc_170_68_28 | 170 cm, 68 kg, 28 anos | cintura 81, quadril 96 | 40 |
| masc_178_78_35 | 178 cm, 78 kg, 35 anos | cintura 89, quadril 100 | 42 |
| masc_188_95_45 | 188 cm, 95 kg, 45 anos | cintura 102, quadril 111 | 46 |

## Exemplo publico adicional

Shop2gether expunha em HTML publico o script:

```html
<script defer id="sizebay-vfr-v4" src="https://static.sizebay.technology/6463/prescript.js"></script>
```

Produto observado:

- https://www.shop2gether.com.br/calca-feminina-crepe-cintura-baixa-alfaiataria-off-white-191348

Uso recomendado: manter uma lista de lojas publicas com Sizebay para benchmarking visual e de integracao, sem copiar dados proprietarios nem sobrecarregar sites terceiros.

## Aprendizados para o Provador Virtual v2

- O botao precisa aparecer exatamente perto da decisao de tamanho.
- O widget nao pode bloquear a renderizacao da PDP.
- O config-check deve esconder o botao quando produto/tabela nao estiverem prontos.
- O usuario anonimo deve ser reconhecido por cookie proprio.
- A experiencia deve funcionar mesmo sem login.
- A tela deve avisar quando usa medidas anteriores.
- O usuario precisa editar medidas rapidamente.
- Deve haver recomendacao por perfil conhecido sem repetir o fluxo inteiro.
- O lojista deve ter integracao por script e por plataforma.
- Produto deve ser identificado por permalink, ID externo, SKU e variante.
- Carrinho, pedido e devolucao precisam alimentar analytics.
- A base inteligente deve aceitar retorno "ficou pequeno"/"ficou grande".
- Para BigShop, precisamos de um caminho nativo que injete o container e o script automaticamente.

## Pendencias para continuar o benchmark

- Receber autorizacao comercial para capturas em massa de lojas terceiras, mesmo que publicas.
- Definir lista de marcas/categorias prioritarias para montar base inteligente inicial.
- Capturar lojas femininas, infantis, calcados e plus size.
- Criar script interno de benchmark que salve apenas dados agregados e anonimos.
- Confirmar com juridico/comercial quais dados publicos de concorrentes podem entrar em base de treinamento.
- Receber loja piloto BigShop com acesso admin para validar o "um clique" do nosso lado e do lado da BigShop.
