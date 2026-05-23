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

## Sprint 5 - Painel do Lojista

Objetivo: experiencia operacional completa para configurar a loja.

Entregas:

- dashboard;
- produtos/tabelas/integracoes;
- tela de instalacao;
- onboarding guiado;
- estados vazios uteis;
- ajustes mobile.

## Sprint 6 - Importacao e Templates Assistidos

Objetivo: reduzir trabalho manual do lojista.

Entregas:

- importacao CSV/XML;
- parser de feed Google Shopping quando possivel;
- assistente para criar tabela a partir de modelo;
- preview e validacao antes de importar;
- jobs e logs.

## Sprint 7 - Integracao BigShop Base

Objetivo: conectar BigShop por API e sincronizar dados reais.

Entregas:

- cadastro de conexao;
- token criptografado;
- probe remoto;
- sync produtos/grades;
- mapeamento tabela de medidas quando disponivel;
- relatorio de lacunas por loja.

## Sprint 8 - BigShop Um Clique

Objetivo: definir e implementar primeiro caminho nativo.

Entregas:

- especificacao de handshake com BigShop;
- endpoint de ativacao;
- snippet/tema automatico ou instrucao interna;
- teste em loja controlada;
- documentacao para time BigShop.

## Sprint 9 - IA para OCR e Tabelas

Objetivo: acelerar criacao de tabelas sem comprometer confianca.

Entregas:

- provider IA configuravel;
- OCR de imagem/texto;
- sugestao de tabela;
- revisao obrigatoria pelo lojista;
- logs de custo/uso;
- guardrails.

## Sprint 10 - Analytics e SaaS Admin

Objetivo: dar visibilidade de uso, qualidade e operacao.

Entregas:

- dashboard de recomendacoes;
- taxa de feedback positivo;
- produtos sem tabela;
- erros de widget;
- painel SaaS para lojistas;
- audit logs.

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

## Sprint 12 - Go-live Assistido

Objetivo: publicar v2 com seguranca e decidir cutover.

Entregas:

- deploy production verde;
- produto teste em producao;
- loja BigShop piloto;
- validacao de widget externo;
- checklist de cutover;
- plano para raiz `provadorvirtual.online`.
