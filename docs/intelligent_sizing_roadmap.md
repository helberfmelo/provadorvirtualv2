# Roadmap de Evolucao Inteligente

Atualizado em: 2026-05-23

Este roadmap continua depois da Sprint 12. O objetivo e transformar o v2 em um produto mais inteligente que o v1, mantendo uso e integração simples.

Antes de iniciar qualquer sprint, reler os documentos obrigatórios em `docs/README.md`.

Depois de cada sprint:

1. rodar validações locais relacionadas;
2. atualizar docs e backlog;
3. fazer commit;
4. fazer push;
5. acompanhar GitHub Actions;
6. corrigir e reexecutar se o deploy falhar.

## Sprint 13 - Catálogo Inteligente de Medidas

Objetivo: trazer para o v2 a base padrão do v1 e preparar campos canonicos mais completos.

Entregas:

- importar `default_measurement_tables_data.json` como seed/catálogo versionado;
- normalizar gênero, categoria, tipo de peça e campos de medida;
- ampliar campos suportados nas tabelas;
- diferenciar medidas de corpo e medidas da peça;
- criar score de qualidade de tabela;
- expor templates por categoria no painel.

Validação:

- testes de importacao do catálogo;
- testes de template por categoria;
- teste de recomendação com campos extras.

## Sprint 14 - Wizard de Tabelas para Lojista

Objetivo: tornar o cadastro de tabela muito simples.

Entregas:

- wizard em `/app/tabelas-de-medidas`;
- escolha por público/categoria/modelagem;
- criação a partir de modelo pronto;
- IA por descrição;
- OCR por imagem;
- importacao CSV/XML no mesmo fluxo;
- grade editavel com alertas;
- publicação somente após revisão.

Validação:

- teste de rascunho por modelo;
- teste de OCR/provider quando chave existir;
- teste de bloqueio de publicação com tabela invalida.

## Sprint 15 - Widget Inteligente e Gamificado

Objetivo: recuperar o melhor do v1 com UX melhor e persistência.

Entregas:

- etapas progressivas no widget;
- barra de precisao;
- gênero/formato corporal;
- medidas detalhadas por categoria;
- recomendação basica rapida;
- refinamento opcional;
- edição em modal;
- mensagens para perfil conhecido;
- persistência anonima por cookie/localStorage;
- acessibilidade e mobile refinados.

Validação:

- testes de widget em `/produto-teste`;
- smoke externo com `tools/widget-external-smoke.html`;
- verificacao manual desktop/mobile.

## Sprint 16 - Perfis de Consumidor e Consentimento

Objetivo: salvar dados para experiencias futuras com controle do usuário.

Entregas:

- `shopper_profiles`;
- perfil anônimo;
- perfil conhecido;
- versoes de perfil;
- consentimento operacional;
- reset/exclusao;
- mensagem "baseado em medidas anteriores";
- modal para editar dados salvos.

Validação:

- testes de persistência anonima;
- testes de associacao com usuário conhecido;
- testes de anonimização.

## Sprint 17 - Benchmark e Base por Marca

Objetivo: transformar observacoes de mercado em referência operacional sem copiar algoritmos proprietarios.

Entregas:

- ferramenta interna de captura controlada de lojas públicas;
- base inicial Zak com tabelas capturadas;
- cadastro de fonte e confiança;
- comparador entre tabela de lojista e base universal/marca;
- lista de marcas/categorias prioritarias;
- relatório de pendências por origem.

Validação:

- teste de importacao de fonte;
- teste de comparacao de tabela;
- docs juridico/comercial sobre uso de dados públicos.

## Sprint 18 - Pacotes de Integração por Plataforma

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
- snippets com autodeteccao de produto quando possível.

Validação:

- página de guia por plataforma;
- teste BigShop em loja piloto;
- smoke custom;
- pelo menos um teste de ambiente simulado por plataforma prioritaria.

## Sprint 19 - IA Externa em Produção

Objetivo: ativar IA real com custos, logs e guardrails.

Entregas:

- provider Gemini usando `GEMINI_API_KEY`;
- provider OpenAI opcional se houver chave;
- prompt registry;
- OCR de imagem real;
- geracao de tabela por descrição;
- extracao de tabela de imagem/PDF;
- logs de custo;
- limites por lojista;
- retry e fallback local.

Validação:

- testes com fake provider;
- teste manual com provider real;
- verificacao de custo/log;
- garantia de que conteúdo bruto sensível não fica em logs.

## Sprint 20 - Pipeline de Aprendizado e Outliers

Objetivo: usar dados reais para melhorar o motor sem distorcer a base.

Entregas:

- eventos de aprendizado;
- outlier score;
- coortes por categoria/gênero/marca/modelagem;
- feedback ponderado;
- pedidos e devolucoes como sinais;
- relatório de anomalias;
- sugestão de ajuste de tabela;
- revisão humana antes de publicar mudancas.

Validação:

- testes de deteccao de outlier;
- testes de promocao/bloqueio de sinais;
- teste de relatório de sugestão;
- teste LGPD de anonimização.

## Sprint 21 - Recomendação Contextual e Comercial

Objetivo: melhorar resultado comercial sem complicar a experiencia.

Entregas:

- preferência de caimento;
- recomendação alternativa por conforto;
- aviso quando produto não tem tabela confiavel;
- "prove também" com tamanhos vizinhos;
- analytics de conversão por recomendação;
- relatório por produto/categoria.

Validação:

- testes de recomendação alternativa;
- testes de analytics;
- verificacao UX no produto teste.

## Sprint 22 - Preparacao Comercial Sizebay-like

Objetivo: empacotar a oferta para clientes reais.

Entregas:

- página comercial sem expor v2;
- demo pública com produto fictício;
- onboarding de lojista;
- checklist de go-live por loja;
- materiais de suporte;
- relatório comparativo com Sizebay;
- piloto Zak/BigShop, se aprovado.

Validação:

- demo pública;
- piloto controlado;
- checklist de suporte.

## Informações pendentes

- Confirmar se devemos ativar Gemini em produção agora ou so na Sprint 19.
- Receber `BIGSHOP_ACTIVATION_SECRET` para teste real.
- Receber loja piloto BigShop com `store_id`, token `x-api` e webhook secret.
- Confirmar autorização para benchmark amplo em lojas terceiras.
- Confirmar se teremos OpenAI além de Gemini.
- Definir prazo de retenção dos perfis anônimos.
