# Roadmap de Evolucao Inteligente

Atualizado em: 2026-05-23

Este roadmap continua depois da Sprint 12. O objetivo e transformar o v2 em um produto mais inteligente que o v1, mantendo uso e integracao simples.

Antes de iniciar qualquer sprint, reler os documentos obrigatorios em `docs/README.md`.

Depois de cada sprint:

1. rodar validacoes locais relacionadas;
2. atualizar docs e backlog;
3. fazer commit;
4. fazer push;
5. acompanhar GitHub Actions;
6. corrigir e reexecutar se o deploy falhar.

## Sprint 13 - Catalogo Inteligente de Medidas

Objetivo: trazer para o v2 a base padrao do v1 e preparar campos canonicos mais completos.

Entregas:

- importar `default_measurement_tables_data.json` como seed/catalogo versionado;
- normalizar genero, categoria, tipo de peca e campos de medida;
- ampliar campos suportados nas tabelas;
- diferenciar medidas de corpo e medidas da peca;
- criar score de qualidade de tabela;
- expor templates por categoria no painel.

Validacao:

- testes de importacao do catalogo;
- testes de template por categoria;
- teste de recomendacao com campos extras.

## Sprint 14 - Wizard de Tabelas para Lojista

Objetivo: tornar o cadastro de tabela muito simples.

Entregas:

- wizard em `/app/tabelas-de-medidas`;
- escolha por publico/categoria/modelagem;
- criacao a partir de modelo pronto;
- IA por descricao;
- OCR por imagem;
- importacao CSV/XML no mesmo fluxo;
- grade editavel com alertas;
- publicacao somente apos revisao.

Validacao:

- teste de rascunho por modelo;
- teste de OCR/provider quando chave existir;
- teste de bloqueio de publicacao com tabela invalida.

## Sprint 15 - Widget Inteligente e Gamificado

Objetivo: recuperar o melhor do v1 com UX melhor e persistencia.

Entregas:

- etapas progressivas no widget;
- barra de precisao;
- genero/formato corporal;
- medidas detalhadas por categoria;
- recomendacao basica rapida;
- refinamento opcional;
- edicao em modal;
- mensagens para perfil conhecido;
- persistencia anonima por cookie/localStorage;
- acessibilidade e mobile refinados.

Validacao:

- testes de widget em `/produto-teste`;
- smoke externo com `tools/widget-external-smoke.html`;
- verificacao manual desktop/mobile.

## Sprint 16 - Perfis de Consumidor e Consentimento

Objetivo: salvar dados para experiencias futuras com controle do usuario.

Entregas:

- `shopper_profiles`;
- perfil anonimo;
- perfil conhecido;
- versoes de perfil;
- consentimento operacional;
- reset/exclusao;
- mensagem "baseado em medidas anteriores";
- modal para editar dados salvos.

Validacao:

- testes de persistencia anonima;
- testes de associacao com usuario conhecido;
- testes de anonimizacao.

## Sprint 17 - Benchmark e Base por Marca

Objetivo: transformar observacoes de mercado em referencia operacional sem copiar algoritmos proprietarios.

Entregas:

- ferramenta interna de captura controlada de lojas publicas;
- base inicial Zak com tabelas capturadas;
- cadastro de fonte e confianca;
- comparador entre tabela de lojista e base universal/marca;
- lista de marcas/categorias prioritarias;
- relatorio de pendencias por origem.

Validacao:

- teste de importacao de fonte;
- teste de comparacao de tabela;
- docs juridico/comercial sobre uso de dados publicos.

## Sprint 18 - Pacotes de Integracao por Plataforma

Objetivo: lojista escolher a plataforma e seguir poucos passos.

Entregas:

- BigShop um clique pronto para piloto;
- Shopify;
- WooCommerce;
- Nuvemshop;
- VTEX;
- Tray;
- Loja Integrada;
- Magento;
- OpenCart;
- Custom HTML/JS;
- checklist de verificacao por plataforma;
- snippets com autodeteccao de produto quando possivel.

Validacao:

- pagina de guia por plataforma;
- teste BigShop em loja piloto;
- smoke custom;
- pelo menos um teste de ambiente simulado por plataforma prioritaria.

## Sprint 19 - IA Externa em Producao

Objetivo: ativar IA real com custos, logs e guardrails.

Entregas:

- provider Gemini usando `GEMINI_API_KEY`;
- provider OpenAI opcional se houver chave;
- prompt registry;
- OCR de imagem real;
- geracao de tabela por descricao;
- extracao de tabela de imagem/PDF;
- logs de custo;
- limites por lojista;
- retry e fallback local.

Validacao:

- testes com fake provider;
- teste manual com provider real;
- verificacao de custo/log;
- garantia de que conteudo bruto sensivel nao fica em logs.

## Sprint 20 - Pipeline de Aprendizado e Outliers

Objetivo: usar dados reais para melhorar o motor sem distorcer a base.

Entregas:

- eventos de aprendizado;
- outlier score;
- coortes por categoria/genero/marca/modelagem;
- feedback ponderado;
- pedidos e devolucoes como sinais;
- relatorio de anomalias;
- sugestao de ajuste de tabela;
- revisao humana antes de publicar mudancas.

Validacao:

- testes de deteccao de outlier;
- testes de promocao/bloqueio de sinais;
- teste de relatorio de sugestao;
- teste LGPD de anonimizacao.

## Sprint 21 - Recomendacao Contextual e Comercial

Objetivo: melhorar resultado comercial sem complicar a experiencia.

Entregas:

- preferencia de caimento;
- recomendacao alternativa por conforto;
- aviso quando produto nao tem tabela confiavel;
- "prove tambem" com tamanhos vizinhos;
- analytics de conversao por recomendacao;
- relatorio por produto/categoria.

Validacao:

- testes de recomendacao alternativa;
- testes de analytics;
- verificacao UX no produto teste.

## Sprint 22 - Preparacao Comercial Sizebay-like

Objetivo: empacotar a oferta para clientes reais.

Entregas:

- pagina comercial sem expor v2;
- demo publica com produto ficticio;
- onboarding de lojista;
- checklist de go-live por loja;
- materiais de suporte;
- relatorio comparativo com Sizebay;
- piloto Zak/BigShop, se aprovado.

Validacao:

- demo publica;
- piloto controlado;
- checklist de suporte.

## Informacoes pendentes

- Confirmar se devemos ativar Gemini em producao agora ou so na Sprint 19.
- Receber `BIGSHOP_ACTIVATION_SECRET` para teste real.
- Receber loja piloto BigShop com `store_id`, token `x-api` e webhook secret.
- Confirmar autorizacao para benchmark amplo em lojas terceiras.
- Confirmar se teremos OpenAI alem de Gemini.
- Definir prazo de retencao dos perfis anonimos.
