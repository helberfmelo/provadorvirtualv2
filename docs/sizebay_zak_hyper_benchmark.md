# Hyper Benchmark Sizebay - Zak

Atualizado em: 2026-05-28
Escopo: portal do cliente/empresa da Sizebay para a Zak, documentacao publica Sizebay, galeria publica de botoes e validacao BigShop/Zak para preparar o Provador Virtual.

## Regras de seguranca

- Acesso Sizebay usado somente em leitura.
- Nada foi alterado no portal da Sizebay.
- Nenhum contato foi feito com a Sizebay.
- Token BigShop, usuario e senha Sizebay recebidos para esta analise nao devem ser registrados em documentos versionados, commits, logs publicos ou respostas.
- O token BigShop da Zak foi gravado criptografado nas conexoes local e producao do Provador Virtual.
- Credenciais operacionais sensiveis ficam apenas em banco criptografado, `.env`, secrets do provedor ou `docs/credentials.local.md`, que e ignorado pelo Git.

## Cadastro Zak no Provador Virtual

Dados nao sensiveis registrados:

- Cliente: BigShop.
- Loja id BigShop: `124`.
- CNPJ: `15.179.121/0006-24`.
- Razao social: `RSP COMERCIO DE ROUPAS LTDA EPP`.
- Loja: `zak.com.br`.
- Feed: `https://www.zak.com.br/feed.xml`.
- Plataforma: `bigshop`.
- API base: `https://api.bigshop.com.br`.

Registros criados/atualizados:

| Ambiente | Merchant | Empresa | Conexao BigShop | Chave publica do widget | Status |
| --- | ---: | ---: | ---: | --- | --- |
| Local | 3 | 3 | 1 | `pv_bs_vyrahlvh1ljzfoxk7m` | token criptografado e dominio liberado |
| Producao | 7 | 7 | 2 | `pv_bs_gg8lgfhyasqdof5wob` | token criptografado e dominio liberado |

Dominios liberados para o widget:

- `zak.com.br`.
- `www.zak.com.br`.
- `provadorvirtual.online`.

Tema inicial aplicado:

- Cor primaria: `#111111`.
- Cor secundaria: `#c9a56a`.
- Cor de destaque: `#ffffff`.
- Modo: drawer.
- Confete: ativo.
- Botao: estilo `clean`, fundo `#111111`, texto `#ffffff`.

Validacoes feitas:

- `GET /v3/getEndPoints` na BigShop respondeu HTTP 200.
- `GET /v3/products` falhou com header `store-id`, mas respondeu HTTP 200 com header `Store-Id`.
- `GET /v3/product_grids` respondeu HTTP 200 com header `Store-Id`.
- Feed publico da Zak redireciona de `https://zak.com.br/feed.xml` para `https://www.zak.com.br/feed.xml` e responde HTTP 200.
- Health de producao do Provador Virtual respondeu HTTP 200.

## Fontes estudadas

- Portal do cliente Sizebay: `https://my.sizebay.com/login`.
- Galeria publica de botoes: `https://sizebay-buttons-gallery.vercel.app/`.
- Documentacao Sizebay: `https://docs.sizebay.com/`.
- Implementacao por script: `https://docs.sizebay.com/size-and-fit-implementation/service-implementation-script`.
- Integracao de produtos por XML: `https://docs.sizebay.com/size-and-fit-data-integration/product-integration-xml-feed`.
- Integracao de produtos por API: `https://docs.sizebay.com/size-and-fit-data-integration/product-integration-api`.
- Integracao de devolucoes por CSV/API e order tracking por script/API.
- Feed da Zak: `https://www.zak.com.br/feed.xml`.
- BigShop API V3, somente leitura, com loja `124`.

## Perfil Sizebay da Zak observado

Resumo de conta:

- Tenant Sizebay: `1235`.
- Tenant: Zak.
- Usuario estudado: usuario de cliente, sem perfil de administrador global/moderador.
- Produto coberto no dashboard: 980.
- Produtos ativos: 912.
- Produtos pendentes: 68.
- Contagem em produtos: total 1.087, pendentes 68, ativos 912, inativos 107.

Sincronizacao observada:

- Ultimo status exibido no portal: 2026-05-28 06:33.
- Inseridos: 6.
- Atualizados: 595.
- Total: 737.
- Desconhecidos: 60.
- Erro recorrente exibido: `Modeling not found`, por produto/permalink.

Relatorios observados:

- Total de impressoes: 231.348.
- Total de recomendacoes: 8.095.
- Consultas a tabela de medidas: 927.
- Taxa de uso: 3,90%.
- Mobile: 87,12%.
- Desktop: 12,87%.

## Mapa do portal Sizebay do cliente

### Layout global

- Sidebar escura a esquerda, com navegacao curta e agrupada.
- Area principal branca, com cabecalhos simples.
- Tabelas densas, filtros discretos e botoes de acao no topo.
- Suporte "Talk to us" sempre disponivel.
- Pouca cor forte; a interface parece operacional e limpa.
- Acoes destrutivas ou publicacao ficam em cards/boxes laterais previsiveis.
- O portal nao explica demais; ele assume que o lojista esta fazendo uma tarefa.

### Dashboard

- Cards de cobertura: produtos cobertos, ativos e pendentes.
- Leitura rapida do status da operacao.
- Sem excesso de graficos na primeira tela.

O que copiar/adaptar:

- Dashboard do lojista com cobertura por catalogo.
- Pendencias importaveis direto da origem.
- Indicador de progresso por qualidade da configuracao, nao so por quantidade de cadastro.

### Products

- Abas por status: todos, pendentes, ativos e inativos.
- Colunas principais: produto, categoria, tabela, tamanhos, marca, faixa etaria e modelagem.
- Produto mostra imagem/nome/permalink.
- Associacao da tabela fica visivel sem abrir o produto.

O que copiar/adaptar:

- Listagem de produtos com status de configuracao do provador.
- Filtro rapido por pendente/ativo/inativo.
- Colunas de tabela de medidas, grade/tamanhos, categoria, marca e genero.
- Acao de "corrigir pendencia" levando direto para produto/tabela/regra.

### Products - form

Campos e secoes observados:

- Informacoes de integracao.
- Imagem/link do produto.
- Nome, permalink, genero, faixa etaria, categoria, marca, tipo de modelagem e sistema de tamanhos.
- Toggle para habilitar Virtual Fitting Room.
- Tamanhos do produto.
- Dados do Size & Fit Advisor.
- Nome da tabela de medidas.
- Tamanhos selecionados a partir da tabela.
- Cancelar/Salvar.

O que copiar/adaptar:

- Produto deve ser configuracao assistida, nao formulario solto.
- Produto precisa mostrar vinculo com tabela, tamanhos e fonte da integracao.
- Devemos separar dados importados, dados editaveis e dados inferidos por IA.

### Measurement Guide / Table Measurements

Listagem:

- Exportar.
- Importar.
- Criar tabela de medidas.
- Nome da tabela e tamanhos cobertos.

Formulario:

- Nome, marca, categoria, tipo, genero, faixa etaria, modelagem e sistema de tamanhos.
- Sistema de medida cm/in.
- Medidas como intervalo.
- Desabilitar VFR.
- Abas/pilhas: medida do corpo, medida da peca e sistema de tamanho.
- Grade por tamanho.
- Adicionar medida.
- Adicionar medida composta.
- Adicionar tamanho.
- Variacao customizada com escala restrita/ampla.
- Observacoes.
- Excluir tabela, cancelar e salvar.

O que copiar/adaptar:

- Nossa tabela atual e boa para o motor inicial, mas e estreita demais para competir.
- Precisamos suportar medidas de corpo e medidas da peca na mesma entidade, com origem/uso por coluna.
- Precisamos suportar medidas compostas, ranges, sistema de tamanho e observacoes operacionais.
- A tela deve parecer uma planilha controlada, com tabs e acoes claras, nao um formulario gigante.

### Modelings

- Tela exposta como modelagens, com formulario semelhante a tabela.
- Modelagem entra como ponte entre produto, categoria, genero e tabela.
- Erros de importacao da Zak mostram "Modeling not found", entao esse cadastro e central na operacao.

O que copiar/adaptar:

- Criar cadastro de modelagens/caimento com regras por marca/categoria/genero.
- Usar modelagem como criterio de recomendacao e como diagnostico de importacao.
- Dar sugestoes por IA quando uma modelagem estiver ausente.

### Brands

- Exportar todas.
- Importar.
- Criar marca.
- Relacao entre marca do lojista e marca forte/global.

O que copiar/adaptar:

- Mapeamento de marca local para catalogo normalizado.
- Tela de reconciliacao de marcas vindas de feed/API.
- IA sugerindo merge ou associacao.

### Categories

- Exportar todas.
- Importar.
- Criar categoria.
- Relacao entre categoria do lojista e categoria Sizebay/subcategoria.

O que copiar/adaptar:

- Mapeamento de categoria local para taxonomia interna de provador.
- Tipos normalizados: superior, inferior, corpo inteiro, calcado, underwear e acessorios quando aplicavel.
- Regras de importacao por categoria, marca, status, genero e nome.

### Sizebay Brands e Sizebay Categories

- O cliente consegue ver catalogos globais de marcas/categorias.
- Ha busca e criacao de itens globais.
- Categorias globais possuem abas e subcategorias.

O que copiar/adaptar:

- Criar taxonomia interna do Provador Virtual.
- Deixar o lojista mapear suas categorias para nossa taxonomia.
- Suporte pode enriquecer essa base; o lojista so escolhe/associa.

### Settings - Service

Controles observados:

- Servicos: Size & Fit Advisor e Size Chart.
- Experiencia: 3D Feel e exibicao de calculos finalizados.
- Idioma do servico.
- Sistema de medida: metrico/imperial.
- Tons de pele.
- Size & Fit Advisor agenero.
- Posicao do botao: antes/depois/dentro do elemento selecionado.
- Campo/tag de ancora.

O que copiar/adaptar:

- Centralizar servicos do widget: provador, tabela de medidas, modo de apresentacao, idioma, unidade e inclusao.
- Trocar configuracoes tecnicas soltas por blocos curtos.
- Adicionar posicao por seletor/ancora como recurso avancado do snippet.

### Settings - Data Sources

Seções observadas:

- Processamento de dados com sincronizar e configurar regras de importacao.
- XML Feed ativo/inativo.
- Campo de link do feed.
- Documentacao de Google Shopping.
- Sizebay API com orientacao de contato/suporte.
- Integracoes de plataforma, com Shopify ativo e outras em desenvolvimento.

O que copiar/adaptar:

- Tela de fontes de dados por empresa.
- Status do feed e da API.
- Botao de sincronizar com historico e preview.
- Regra clara: importar produtos/grades primeiro, tabelas depois, associacoes por ultimo.

### Settings - Sync

Funcionalidades observadas:

- Historico de sincronizacao.
- Contadores de inseridos, atualizados, total e desconhecidos.
- Erros por produto/permalink.
- Campos de contexto no erro: categoria, marca, genero, faixa etaria e tamanhos.
- Acao para ver mais tamanhos.

O que copiar/adaptar:

- Esta e uma das telas mais importantes para o nosso piloto Zak.
- Precisamos de dry-run, commit, historico e erro por linha.
- Erro deve dizer o que falta criar/mapear: marca, categoria, modelagem, tamanho, tabela ou produto.

### Settings - Importation Rules

Funcionalidades observadas:

- Construtor visual "Where" e "Then".
- Condicoes por nome, categoria, marca, status e combinacoes AND/OR.
- Acoes depois da condicao.
- Grupos de regras.

O que copiar/adaptar:

- Criar rule builder simples para feeds/API BigShop.
- Comecar com condicoes: nome contem, categoria igual, marca igual, genero, status, tamanho, produto ativo.
- Acoes iniciais: mapear categoria, mapear marca, definir genero/faixa etaria, ignorar produto, definir modelagem, associar tabela.

### Settings - Buttons Customization

Funcionalidades observadas:

- Preview desktop/mobile da pagina de produto.
- Alternancia Mobile/Desktop.
- Box lateral de publicacao com desfazer/publicar.
- Botões do Size & Fit Advisor e Measurement Table mostrados no contexto da PDP.

O que copiar/adaptar:

- A Sprint 106 criou a base de personalizacao de botoes.
- Proximo passo: ampliar para a galeria completa de estilos, preview mobile/desktop e fluxo publicar/desfazer.
- Botao deve ser configurado visualmente no contexto da pagina, nao isolado.

### Settings - VFR Customization

Funcionalidades observadas:

- Estrutura preparada para preview em tempo real do provador.
- Mesmo padrao de publicacao/desfazer dos botoes.

O que copiar/adaptar:

- Criar customizacao por etapas do provador com preview vivo.
- Separar configuracao salva de configuracao publicada.
- Permitir testar antes de publicar.

### Reports - Usage Data

Funcionalidades observadas:

- Filtro por dispositivo e periodo.
- KPIs principais de impressoes, recomendacoes, consultas a tabela e taxa de uso.
- Distribuicao mobile/desktop.
- Ranking de produtos por recomendacao.

O que copiar/adaptar:

- Analytics do lojista deve priorizar poucos numeros acionaveis.
- Medir impressao do botao, abertura do provador, recomendacao, tabela, clique no tamanho e feedback.
- Separar mobile/desktop desde o inicio.

### Reports - Recommendations, Orders, Returns

Observado:

- Recomendacoes existe como area propria.
- Orders Overview mostra ausencia de dados quando Order Tracker nao esta configurado.
- Orders lista pedido, status, data, quantidade, preco e uso do Size & Fit Advisor.
- Returns aceita upload CSV e metodo de processamento por Product ID, SKU, ID no permalink ou ignorar tamanho pedido.

O que copiar/adaptar:

- Pedidos e devolucoes nao sao essenciais para o PDP, mas sao essenciais para aprendizagem e prova de valor.
- Criar importacao CSV de devolucoes com motivo "ficou pequeno", "ficou grande" e "outros".
- Preparar tracking de pedidos por script/API em fase seguinte.

## Documentacao Sizebay estudada

### Implementacao do servico

A documentacao orienta criar um container/ancora na PDP onde os botoes entram e carregar o script da Sizebay com dados do tenant. A ideia importante para nos nao e copiar o script, e sim manter o padrao:

- container simples no ponto exato da decisao de tamanho;
- script unico;
- configuracao resolvida por tenant/loja;
- adaptacao de responsividade.

No Provador Virtual:

- manter snippet universal;
- melhorar suporte a posicao por seletor;
- expor validacao de instalacao;
- preservar fallback BigShop por `data-store-id`, `data-product-id`, `data-variant-id` e `data-sku`.

### Produtos por XML/API

Campos importantes da Sizebay:

- nome.
- genero.
- permalink.
- imagem.
- id do produto no feed.
- marca.
- categoria.
- tamanhos disponiveis.
- faixa etaria.
- tipo de tamanho/modelagem.

No XML, a Sizebay enfatiza padrao Google Shopping e categorias normalizadas como Top, Bottom, Full Body, Top Underwear, Bottom Underwear e Shoe. Tambem valoriza mandar todos os tamanhos disponiveis, mesmo sem estoque, para melhorar a recomendacao.

No Provador Virtual:

- nosso importador precisa manter os campos acima como canonicos;
- `g:size`, `g:gender`, `g:age_group`, `g:product_type`, `g:brand`, `link`, `g:image_link` devem virar dados de qualidade, nao apenas metadados;
- para BigShop, o XML e a API devem se complementar.

### Pedidos e devolucoes

Campos de devolucao importantes:

- data da devolucao.
- id do pedido.
- data do pedido.
- id do produto.
- nome/url do produto.
- tamanho comprado.
- SKU/variacao.
- quantidade.
- motivo, com pelo menos pequeno/grande/outros.

No Provador Virtual:

- usar devolucoes como sinal de aprendizado por produto/tamanho/tabela.
- adicionar importacao CSV de devolucoes por empresa.
- criar indicadores de recomendacao com baixa confianca quando houver devolucao recorrente por tamanho.

## BigShop/Zak - dados observados e lacunas

### Produtos

O endpoint `products` da Zak retorna envelope paginado, nao uma lista simples. Campos vistos:

- `_id`.
- `nome`.
- `ref`.
- `codexterno`.
- `ativo`.
- `genero`.
- `tabela_de_medidas`.
- `medidas`.

Correcoes/decisoes:

- O cliente BigShop foi ajustado para usar header `Store-Id`.
- O cliente passa a extrair produtos quando o retorno vem como envelope paginado.
- Ainda nao devemos rodar sync real da Zak para alimentar tabelas, porque falta juntar produtos com `product_grids` e validar a logica de medidas.

### Grades

O endpoint `product_grids` da Zak retorna volume grande de grades/variacoes. Campos vistos:

- `_id`.
- `produtoid`.
- `sku`.
- `codexterno`.
- `estoque`.
- `preco`.
- `cor`.
- `cornome`.
- `caracteristicas`, incluindo tamanho.

Lacunas no Provador Virtual:

- sync atual espera grades dentro do produto, mas na Zak elas vem em endpoint separado.
- precisamos paginar `product_grids`.
- precisamos juntar grades por `produtoid`.
- precisamos extrair tamanho de `caracteristicas`.
- precisamos preservar estoque/preco/cor/SKU como metadados da variante.

### Feed XML

O feed publico da Zak e grande e responde como Google Merchant. Ele deve ser usado para:

- link publico.
- imagem.
- titulo.
- categoria/`product_type`.
- tamanho/grade.
- cor.
- genero.
- disponibilidade.

A API BigShop deve ser usada para:

- id interno confiavel.
- produto pai.
- grade/variacao.
- status.
- tabela/medidas quando houver.
- campos proprietarios nao expostos no feed.

## Comparacao de modelo de dados

### O que a Sizebay captura melhor

- Produto com marca/categoria/genero/faixa etaria/modelagem/tamanho.
- Marca e categoria do cliente mapeadas para catalogos globais.
- Tabela com medida do corpo, medida da peca e sistema de tamanho.
- Medidas compostas.
- Ranges e variacao customizada.
- Modelagem como entidade operacional.
- Historico de sync com erro por produto.
- Regras de importacao configuraveis pelo cliente/suporte.
- Relatorios de uso, pedidos e devolucoes.

### O que temos hoje

- Produtos, variacoes e tabelas de medidas.
- Linhas de tabela com busto, cintura, quadril, altura e peso min/max.
- Templates e assistente de IA.
- Widget publico com recomendacao, feedback, sinais de aprendizado e personalizacao visual.
- Importacao CSV/XML inicial.
- BigShop probe/sync base.

### Lacunas para ficar no mesmo nivel

- Modelo flexivel de medidas por chave/unidade/origem.
- Tabelas com medidas corporais e da peca no mesmo fluxo.
- Cadastro de modelagem.
- Taxonomia de categorias e marcas.
- Importacao BigShop com `product_grids`.
- Rule builder de importacao.
- Tela de sync com erros e dry-run.
- Preview/publicacao separados para personalizacao.
- Pedidos/devolucoes como sinais de IA.

## Plano seguro para importar Zak

Nao alimentar tabelas finais da Zak antes destes passos:

1. Corrigir e validar `Store-Id` na API BigShop.
2. Implementar paginacao controlada para `products` e `product_grids`.
3. Juntar grades por `produtoid`.
4. Normalizar tamanho a partir de `caracteristicas`.
5. Cruzar API BigShop com feed XML para enriquecer produto/variante.
6. Criar dry-run de importacao com contadores e erros por linha.
7. Criar fila de mapeamento para marca, categoria, genero, faixa etaria e modelagem.
8. Criar cadastro de modelagens.
9. Ampliar tabela de medidas para medidas flexiveis.
10. Importar produtos e variantes primeiro.
11. Importar/validar tabelas de medidas em estado `draft` ou `review`.
12. Associar produtos as tabelas somente depois da revisao.
13. Publicar widget para a Zak quando os produtos principais estiverem com tabela e config-check OK.

## IA e base de aprendizado

O que devemos aproveitar:

- descricoes de produto, categoria, marca, genero, faixa etaria e imagem do feed;
- tamanhos disponiveis e variacoes reais por produto;
- erros de importacao corrigidos manualmente;
- associacoes feitas entre categoria local e taxonomia interna;
- associacoes entre marca local e marca normalizada;
- tabelas revisadas pelo lojista;
- devolucoes por pequeno/grande/outros;
- feedback do consumidor no widget;
- clique no tamanho recomendado;
- uso por dispositivo e produto.

Usos praticos da IA:

- sugerir categoria normalizada;
- sugerir modelagem;
- detectar tabela provavel para produto novo;
- apontar campos ausentes antes do sync;
- sugerir medidas iniciais a partir de tabela parecida;
- detectar outlier em tamanho/medida;
- gerar explicacao operacional para erro de importacao;
- priorizar produtos que merecem revisao por impacto comercial.

## Recomendacoes priorizadas

### P0 - Antes de importar a Zak

- Completar BigShop sync real: `Store-Id`, paginacao, `product_grids`, join por produto e extracao de tamanho.
- Criar dry-run de importacao com erros por linha.
- Bloquear sync final de tabelas quando a estrutura de medidas nao for compativel.
- Criar tela de mapeamento pendente para categoria, marca, genero, faixa etaria e modelagem.

### P1 - Igualar operacao da Sizebay no portal do cliente

- Criar tela "Sincronizacao" com historico, contadores e erros.
- Criar "Regras de importacao" com construtor visual simples.
- Criar cadastro de modelagens.
- Melhorar lista de produtos com status de configuracao do provador.
- Melhorar tabela de medidas para medida corporal, medida da peca, sistema de tamanho, ranges e medidas compostas.

### P2 - Melhorar widget/personalizacao

- Ampliar a galeria de botoes para a colecao completa inspirada na Sizebay, com variacoes e animacoes.
- Adicionar preview desktop/mobile real.
- Separar configuracao salva de configuracao publicada.
- Criar "desfazer alteracoes" e "publicar" para widget.
- Adicionar posicao por seletor/ancora em modo avancado.

### P3 - Analytics e IA

- Criar KPIs de impressoes, aberturas, recomendacoes, consulta a tabela e taxa de uso.
- Separar mobile/desktop.
- Criar ranking de produtos com maior uso/pendencia.
- Importar devolucoes CSV.
- Planejar order tracking por script/API depois do piloto.

## Sprint sugerida depois deste benchmark

1. Sprint 108 - Importacao BigShop Zak em dry-run.
2. Sprint 109 - Modelagens, marcas e categorias normalizadas.
3. Sprint 110 - Tabela de medidas flexivel.
4. Sprint 111 - Sync visual com erros e regras de importacao.
5. Sprint 112 - Publicacao/preview do widget como Sizebay.
6. Sprint 113 - Analytics de uso e base de IA.
