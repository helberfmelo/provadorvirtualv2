# Roadmap e Sprints

Atualizado em: 2026-05-23

Este roadmap busca um produto enxuto, robusto e comercialmente usavel. Nao e MVP minimo; e uma primeira versao consistente.

## Sprint 0 - Documentacao e Preparacao

Objetivo: criar fonte de verdade, deploy inicial e regras de trabalho.

Entregas:

- docs iniciais;
- `.gitignore`;
- `.github/workflows/deploy.yml`;
- lista de secrets faltantes;
- backlog inicial.

## Sprint 1 - Fundacao Laravel/Vue

Objetivo: app rodando local e deployavel.

Entregas:

- scaffold `backend/` Laravel;
- scaffold `frontend/` Vue;
- `.env.example`;
- health endpoints;
- layout base;
- auth base;
- migrations iniciais;
- seed demo;
- pagina `/produto-teste` inicial.

## Sprint 2 - Produtos, Variacoes e Tabelas

Objetivo: lojista conseguir cadastrar produto e tabela de medidas.

Entregas:

- CRUD produtos;
- CRUD variacoes;
- CRUD tabelas;
- templates de medidas;
- vinculo produto/tabela;
- validacoes e testes.

Status: concluido e publicado em producao no run `26326950616`.

## Sprint 3 - Motor de Recomendacao

Objetivo: recomendacao real sem depender de IA externa.

Entregas:

- service de recomendacao;
- normalizacao de medidas;
- scoring por tamanho;
- confianca e explicacao;
- logs;
- endpoints publicos;
- testes de casos comuns e extremos.

Status: concluido e publicado em producao no run `26327119754`.

## Sprint 4 - Widget Universal v1

Objetivo: snippet funcionar em qualquer pagina.

Entregas:

- bundle JS/CSS;
- config-check;
- modal/drawer responsivo;
- fluxo de medidas;
- recomendacao e feedback;
- pagina `/produto-teste` usando widget real;
- guia custom/Shopify/WooCommerce/Nuvemshop.

Status: concluido e publicado em producao no run `26331199145`.

## Sprint 5 - Painel do Lojista

Objetivo: experiencia operacional completa para configurar a loja.

Entregas:

- dashboard;
- produtos/tabelas/integracoes;
- tela de instalacao;
- onboarding guiado;
- estados vazios uteis;
- ajustes mobile.

Status: concluido e publicado em producao no run `26331485173`.

## Sprint 6 - Importacao e Templates Assistidos

Objetivo: reduzir trabalho manual do lojista.

Entregas:

- importacao CSV/XML;
- parser de feed Google Shopping quando possivel;
- assistente para criar tabela a partir de modelo;
- preview e validacao antes de importar;
- jobs e logs.

Status: concluido e publicado em producao no run `26331691701`.

## Sprint 7 - Integracao BigShop Base

Objetivo: conectar BigShop por API e sincronizar dados reais.

Entregas:

- cadastro de conexao;
- token criptografado;
- probe remoto;
- sync produtos/grades;
- mapeamento tabela de medidas quando disponivel;
- relatorio de lacunas por loja.

Status: concluido e publicado em producao no run `26331844564`.

## Sprint 8 - BigShop Um Clique

Objetivo: definir e implementar primeiro caminho nativo.

Entregas:

- especificacao de handshake com BigShop;
- endpoint de ativacao;
- snippet/tema automatico ou instrucao interna;
- teste em loja controlada;
- documentacao para time BigShop.

Status: concluido e publicado em producao no run `26332055677`.

## Sprint 9 - IA para OCR e Tabelas

Objetivo: acelerar criacao de tabelas sem comprometer confianca.

Entregas:

- provider IA configuravel;
- OCR de imagem/texto;
- sugestao de tabela;
- revisao obrigatoria pelo lojista;
- logs de custo/uso;
- guardrails.

Status: concluido e publicado em producao no run `26332326042`. OCR de imagem real depende de `OPENAI_API_KEY` ou `GEMINI_API_KEY` e ativacao do provider externo.

## Sprint 10 - Analytics e SaaS Admin

Objetivo: dar visibilidade de uso, qualidade e operacao.

Entregas:

- dashboard de recomendacoes;
- taxa de feedback positivo;
- produtos sem tabela;
- erros de widget;
- painel SaaS para lojistas;
- audit logs.

Status: concluido e publicado em producao no run `26332544138`.

## Sprint 11 - Hardening, LGPD e Observabilidade

Objetivo: preparar release publico com seguranca.

Entregas:

- politicas de privacidade/termos;
- CORS por dominio;
- rate limit;
- mascaramento de logs;
- retencao;
- incident runbook;
- smoke e rollback testados.

Status: concluido e publicado em producao no run `26332960822`.

## Sprint 12 - Go-live Assistido

Objetivo: publicar v2 com seguranca e decidir cutover.

Entregas:

- deploy production verde;
- produto teste em producao;
- loja BigShop piloto;
- validacao de widget externo;
- checklist de cutover;
- plano para raiz `provadorvirtual.online`.

Status: concluido e publicado em producao no run `26333226813`. Go-live assistido permanece na subpasta `/provadorvirtual_v2/`; cutover para a raiz depende de aceite comercial e piloto BigShop.

## Roadmap de Evolucao - Sprints 13 a 22

Documento detalhado: `docs/intelligent_sizing_roadmap.md`.

Resumo:

- Sprint 13: catalogo inteligente de medidas, importando e normalizando a base do v1.
- Sprint 14: wizard de tabelas para lojista com modelo pronto, IA, OCR e validacao.
- Sprint 15: widget inteligente e gamificado com precisao progressiva.
- Sprint 16: perfis anonimos/conhecidos de consumidor e consentimento.
- Sprint 17: benchmark e base por marca, com Zak como primeira referencia controlada.
- Sprint 18: pacotes de integracao por plataforma, priorizando BigShop um clique.
- Sprint 19: IA externa em producao com Gemini/OpenAI, custo e guardrails.
- Sprint 20: pipeline de aprendizado e outliers.
- Sprint 21: recomendacao contextual e comercial.
- Sprint 22: preparacao comercial Sizebay-like e piloto.

Status: Sprint 13 a 22 continuam como trilha evolutiva inteligente.

## Sprints 23 a 26 - Evolucao Comercial Executada

### Sprint 23 - SaaS admin, empresas e identidade

Objetivo: permitir operacao interna de empresas sem checkout publico e preparar acesso por codigo.

Entregas:

- `cpf` no usuario;
- endereco completo em `merchant_companies`;
- `access_code` no formato `aaaa + id com 4 digitos`;
- comando `php artisan pv:create-master-admin`;
- endpoints SaaS para listar/criar/editar empresas;
- endpoint publico para resolver empresa por codigo ou CNPJ;
- CEP primeiro no formulario SaaS com ViaCEP no frontend.

Status: implementado e testado.

### Sprint 24 - Loja teste realista e widget Sizebay-like

Objetivo: simular uma loja real com produtos e botoes do Provador Virtual dentro da pagina de produto.

Entregas:

- loja demo `Provador Virtual Loja Teste`;
- 4 produtos demo: 2 femininos e 2 masculinos;
- 4 tabelas de medidas por tipo de produto;
- storefront publica em `/produto-teste`;
- pagina de produto por slug;
- widget com botoes `Descubra seu tamanho` e `Tabela de Medidas`;
- modal de tabela de medidas;
- assinatura `desenvolvido por provadorvirtual.online`;
- reuso local de medidas anteriores pelo navegador.

Status: implementado e testado.

### Sprint 25 - Personalizador visual do widget

Objetivo: lojista ajustar o visual do widget/tabela e ver o resultado antes de publicar.

Entregas:

- tema ampliado: cores, fundo, texto, fonte, tamanho, peso e raio;
- validacao backend dos novos campos;
- visualizador em tempo real em `/app/widget`;
- snippet continua independente por plataforma.

Status: implementado e testado.

### Sprint 26 - Landing e checkout Pagar.me transparente

Objetivo: abrir contratacao publica com checkout transparente e provisionamento inicial.

Entregas:

- landing publica clean com CTAs;
- rota `/checkout`;
- checkout com CEP primeiro e ViaCEP;
- tokenizacao de cartao no navegador via chave publica Pagar.me;
- pedido direto na Pagar.me pelo backend;
- Pix, boleto e cartao;
- tabelas `checkout_sessions` e `payment_events`;
- webhook `POST /api/v1/webhooks/pagarme`;
- liberacao da empresa quando pagamento aprovado;
- tela `/checkout/sucesso`.

Status: implementado e testado. Producao depende de `PAGARME_SECRET_KEY`, `PAGARME_PUBLIC_KEY` e `PAGARME_WEBHOOK_SECRET` em `PRODUCTION_ENV`.
