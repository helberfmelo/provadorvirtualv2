# Benchmark Sizebay e Zak

Atualizado em: 2026-05-28

Objetivo: registrar o que podemos aprender com a Sizebay e com a loja Zak para deixar o Provador Virtual v2 simples de integrar, inteligente e comparavel comercialmente.

## Fontes públicas consultadas

- Documentação inicial Sizebay: https://docs.sizebay.com/
- Implementacao por script: https://docs.sizebay.com/size-and-fit-implementation/service-implementation-script
- Implementacao por API: https://docs.sizebay.com/size-and-fit-implementation/service-implementation-api
- Integração de produto por API: https://docs.sizebay.com/integration-products/api-product-integration
- Integração de produto por XML: https://docs.sizebay.com/integration-products/feed-xml
- Integração OnPage: https://docs.sizebay.com/integration-products/integração-na-página
- Categorias aceitas: https://docs.sizebay.com/integration-products/categories-accepted-by-the-integration
- Order Tracking API: https://docs.sizebay.com/size-and-fit-order-tracking/order-tracking-api
- Return Data Integration: https://docs.sizebay.com/size-and-fit-data-integration/return-data-integration-csv
- Size Tailor: https://docs.sizebay.com/size-and-fit-data-integration/size-tailor
- Shopify: https://docs.sizebay.com/size-and-fit-implementation/service-implementation-shopify
- Blog Sizebay sobre produto e plataformas: https://sizebay.com/en/blog/what-is-sizebay/
- Loja Zak: https://www.zak.com.br/
- Produto Zak camisa: https://www.zak.com.br/704986-camisa-masculina-bambu-drop-areia-04
- Produto Zak calca: https://www.zak.com.br/calca-masculina-alfaiataria-genova-chumbo-63
- Exemplo público adicional encontrado usando Sizebay: https://www.shop2gether.com.br/calca-feminina-crepe-cintura-baixa-alfaiataria-off-white-191348
- Benchmark aprofundado do portal Sizebay da Zak: `docs/sizebay_zak_hyper_benchmark.md`

## O que a Sizebay faz bem

- Carregamento assincrono para não travar a página de produto.
- Snippet simples com prescript por tenant.
- Botão de provador e botão de tabela de medidas no ponto de decisao de tamanho.
- Identificacao anonima por cookie `SIZEBAY_SESSION_ID_V4`.
- Consulta de produto por permalink ou SKU antes de exibir botões.
- VFR e tabela em iframe/modal independente da plataforma.
- Recomendação passiva na PDP quando já existe perfil.
- Tracking de carrinho e pedido para medir funil.
- Integração de devolucoes para refinar recomendações.
- Integração de produtos por API, XML Google Shopping ou OnPage.
- Suporte a plataformas de mercado e plataformas proprietarias.

## Releitura Sprint 106

Consulta refeita em 2026-05-28 nas páginas públicas de implementação por script e API da Sizebay confirmou dois pontos relevantes para a personalização do Provador Virtual:

- o container dos botões deve ficar no ponto de decisão da PDP e o script deve ser carregado de forma não bloqueante;
- a exibição dos botões depende do produto identificado: produto inexistente não mostra botões, produto normal mostra provador e tabela, acessório mostra apenas tabela.

A folha pública observada na loja Zak usa dois botões lado a lado, texto em caixa alta, fonte Montserrat, ícones antes do texto e visual limpo com pouca interferência na página. Para o v2, a Sprint 106 transforma esse aprendizado em opções próprias, sem copiar assets da Sizebay: `Destaque com brilho`, `Minimal com ícones`, `Contorno leve` e `Pílulas suaves`, todas com cores configuráveis de fundo/texto e animações coerentes com nosso widget.

## Releitura Sprint 107

Em 2026-05-28, o portal do cliente Sizebay da Zak foi estudado em leitura, junto da galeria pública de botões e dos manuais de integração. O documento completo esta em `docs/sizebay_zak_hyper_benchmark.md`.

Principais aprendizados novos:

- a Sizebay separa muito bem produto, tabela, modelagem, marca, categoria, fontes de dados, sincronização, regras, customização e relatórios;
- a tela de sincronização com erros por produto e a tela de regras de importação são essenciais antes de importar a Zak em massa;
- a importação BigShop real da Zak exige `Store-Id`, paginação, endpoint `product_grids` e extração de tamanho de `caracteristicas`;
- nossas tabelas precisam evoluir para medidas corporais, medidas da peça, sistema de tamanho, medidas compostas e ranges;
- o portal deve continuar limpo, com cards/tabelas compactos, pouca cor, preview no contexto da PDP e ação clara de publicar/desfazer.

## Contrato técnico observado na documentação

### Script

Padrão de script:

```html
<div id="sizebay-container"></div>
<script defer id="sizebay-vfr-v4" src="https://static.sizebay.technology/TENANT_ID/prescript.js"></script>
```

Para o v2, o equivalente deve continuar sendo:

```html
<div id="provador-virtual-container"></div>
<script defer id="provadorVirtualScript" src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js"></script>
```

O v2 deve manter configuração por `data-*`, mas evoluir para autodetectar produto em plataformas conhecidas.

### Produto

Campos importantes vistos na integração de produto:

- nome;
- gênero: `M`, `F`, `U`;
- permalink;
- imagem;
- `feedProductId`;
- marca;
- categoria;
- tamanhos disponíveis;
- faixa etaria;
- tipo de tamanho.

Categorias de referência para normalizacao:

- Top;
- Bottom;
- Full Body;
- Top Underwear;
- Bottom Underwear;
- Shoe.

### Usuário e recomendação

Fluxo público:

1. checar/criar SID;
2. identificar produto;
3. exibir botões se o produto existir;
4. abrir VFR ou tabela;
5. buscar recomendação por produto e perfil;
6. registrar abertura/eventos.

O v2 deve ter fluxo equivalente, mas com endpoint próprio:

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

Captura feita em 2026-05-23 por navegador headless em páginas públicas da Zak.

### Integração detectada

- Plataforma da loja: BigShop.
- Tenant Sizebay: `1235`.
- Script: `https://static.sizebay.technology/1235/prescript.js`.
- CSS: `https://static.sizebay.technology/1235/styles/styles_v4.css`.
- Implantacao: `https://vfr-v3-production.sizebay.technology/V4/implantation/index.js`.
- Container: `#szb-container`.
- Botão VFR: `#szb-vfr-button`, texto `DESCUBRA SEU TAMANHO`.
- Botão tabela: `#szb-measurements-button`, texto `TABELA DE MEDIDAS`.
- Cookies usados: `SIZEBAY_SESSION_ID_V4` e `szb-current-product-id`.
- O front da BigShop chama `Sizebay.changePermalink(window.location.href)` ao montar produto.

O fluxo interno do VFR observado:

1. gênero;
2. altura, peso e idade;
3. ajuste de formato corporal aproximado;
4. medidas corporais calculadas;
5. tamanho indicado;
6. opções vizinhas para provar;
7. editar medidas e fechar.

### Configuração Sizebay da Zak

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

Observação: apesar de `isMetric=false`, a experiencia da Zak mostra cm/kg na interface em portugues.

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

Recomendações capturadas:

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

Recomendações capturadas:

| Perfil | Entrada | Medidas inferidas | Tamanho |
| --- | --- | --- | --- |
| masc_170_68_28 | 170 cm, 68 kg, 28 anos | cintura 81, quadril 96 | 40 |
| masc_178_78_35 | 178 cm, 78 kg, 35 anos | cintura 89, quadril 100 | 42 |
| masc_188_95_45 | 188 cm, 95 kg, 45 anos | cintura 102, quadril 111 | 46 |

## Exemplo público adicional

Shop2gether expunha em HTML público o script:

```html
<script defer id="sizebay-vfr-v4" src="https://static.sizebay.technology/6463/prescript.js"></script>
```

Produto observado:

- https://www.shop2gether.com.br/calca-feminina-crepe-cintura-baixa-alfaiataria-off-white-191348

Uso recomendado: manter uma lista de lojas públicas com Sizebay para benchmarking visual e de integração, sem copiar dados proprietarios nem sobrecarregar sites terceiros.

## Aprendizados para o Provador Virtual v2

- O botão precisa aparecer exatamente perto da decisao de tamanho.
- O widget não pode bloquear a renderizacao da PDP.
- O config-check deve esconder o botão quando produto/tabela não estiverem prontos.
- O usuário anônimo deve ser reconhecido por cookie próprio.
- A experiencia deve funcionar mesmo sem login.
- A tela deve avisar quando usa medidas anteriores.
- O usuário precisa editar medidas rapidamente.
- Deve haver recomendação por perfil conhecido sem repetir o fluxo inteiro.
- O lojista deve ter integração por script e por plataforma.
- Produto deve ser identificado por permalink, ID externo, SKU e variante.
- Carrinho, pedido e devolucao precisam alimentar analytics.
- A base inteligente deve aceitar retorno "ficou pequeno"/"ficou grande".
- Para BigShop, precisamos de um caminho nativo que injete o container e o script automaticamente.

## Pendências para continuar o benchmark

- Receber autorização comercial para capturas em massa de lojas terceiras, mesmo que públicas.
- Definir lista de marcas/categorias prioritarias para montar base inteligente inicial.
- Capturar lojas femininas, infantis, calcados e plus size.
- Criar script interno de benchmark que salve apenas dados agregados e anônimos.
- Confirmar com juridico/comercial quais dados públicos de concorrentes podem entrar em base de treinamento.
- Receber loja piloto BigShop com acesso admin para validar o "um clique" do nosso lado e do lado da BigShop.
