# Visao Geral - Provador Virtual

Atualizado em: 2026-05-23

## Objetivo

O Provador Virtual e um SaaS para e-commerces de moda que recomenda o tamanho mais adequado de uma peca a partir de medidas do consumidor, tabela de medidas do produto e historico de recomendacoes.

O v2 deve manter a ideia do `provadorvirtual_v1`, mas com base tecnica robusta, integracao simples e experiencia de uso mais confiavel.

## Problema que resolve

- Reduz devolucoes e trocas por tamanho incorreto.
- Aumenta confianca do consumidor antes da compra.
- Ajuda lojistas a padronizar tabelas de medidas.
- Gera dados para melhorar grade, compra de estoque e conversao.

## Publicos

- Lojista/empresa: configura produtos, tabelas, integracoes, widget e acompanha resultados.
- Consumidor final: usa o widget na pagina do produto para descobrir tamanho recomendado.
- Suporte/SaaS: acompanha clientes, planos, uso, erros de integracao e saude operacional.
- BigShop: plataforma parceira com objetivo de integracao em um clique.

## Stack oficial

- Backend: Laravel 11+, PHP 8.2+, Sanctum, filas/jobs, mailer SMTP.
- Frontend painel: Vue 3, TypeScript, Vite, Pinia, Vue Router, Axios.
- Widget publico: JavaScript isolado, CSS escopado, sem dependencia obrigatoria do e-commerce.
- Banco: MySQL/MariaDB, `utf8mb4`, `utf8mb4_unicode_ci`.
- UI: identidade visual inspirada no BigShop HelpDesk.
- Icones: Font Awesome e/ou lucide quando fizer sentido no frontend.
- Testes: PHPUnit/Pest no backend, Vitest ou testes focados no frontend, smoke publico.
- Deploy: GitHub Actions via SSH.

## Superficies do produto

1. Site publico:
   - landing simples;
   - como funciona;
   - planos;
   - cadastro/login;
   - pagina de produto ficticia para testar o provador real.

2. Painel do lojista:
   - dashboard;
   - empresas/lojas;
   - produtos;
   - tabelas de medidas;
   - importacao por XML/feed/API;
   - instalacao do widget;
   - integracoes por plataforma;
   - analytics de recomendacoes.

3. Widget:
   - botao na pagina de produto;
   - modal/drawer responsivo;
   - coleta de altura, peso, idade, genero, formato corporal e medidas detalhadas;
   - recomendacao de tamanho com confianca e explicacao curta;
   - feedback do consumidor.

4. SaaS/admin:
   - clientes/lojistas;
   - planos e status;
   - integracoes;
   - logs de recomendacao;
   - incidentes e diagnosticos.

## Licoes do v1

- O conceito do widget funciona, mas o v1 ficou fragil por estar em PHP puro e com padroes mistos.
- A nomenclatura deve ser consistente em ingles no codigo e PT-BR nos textos do usuario.
- O widget precisa suportar compatibilidade com atributos antigos quando for util, mas o v2 deve padronizar `merchant_id`, `store_id`, `product_id`, `sku` e `variant_id`.
- A recomendacao precisa ter motor proprio deterministico antes de depender de IA externa.
- IA deve ajudar em OCR, criacao de tabela e analise, nao inventar tamanho sem dados.
- A pagina de produto de teste e obrigatoria para validar o fluxo real.

## Diferencial vs Sizebay

- Foco em e-commerce brasileiro e suporte local.
- Integracao simples e guiada para qualquer plataforma.
- BigShop com caminho de um clique.
- Preco e operacao em reais, sem complexidade de enterprise.
- Widget leve, com instalacao transparente e sem exigir troca de plataforma.

## Principios

- Simples de instalar.
- Preciso o suficiente para gerar confianca.
- Robusto o suficiente para operar em producao.
- Mobile first.
- API first.
- Segredos fora do Git.
- Documentacao viva como contrato.
