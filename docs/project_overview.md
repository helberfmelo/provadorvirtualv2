# Visão Geral - Provador Virtual

Atualizado em: 2026-05-23

## Objetivo

O Provador Virtual e um SaaS para e-commerces de moda que recomenda o tamanho mais adequado de uma peça a partir de medidas do consumidor, tabela de medidas do produto e histórico de recomendações.

O v2 deve manter a ideia do `provadorvirtual_v1`, mas com base tecnica robusta, integração simples e experiencia de uso mais confiavel.

## Problema que resolve

- Reduz devolucoes e trocas por tamanho incorreto.
- Aumenta confiança do consumidor antes da compra.
- Ajuda lojistas a padronizar tabelas de medidas.
- Gera dados para melhorar grade, compra de estoque e conversão.

## Públicos

- Lojista/empresa: configura produtos, tabelas, integrações, widget e acompanha resultados.
- Consumidor final: usa o widget na página do produto para descobrir tamanho recomendado.
- Suporte/SaaS: acompanha clientes, planos, uso, erros de integração e saude operacional.
- BigShop: plataforma parceira com objetivo de integração em um clique.

## Stack oficial

- Backend: Laravel 11+, PHP 8.2+, Sanctum, filas/jobs, mailer SMTP.
- Frontend painel: Vue 3, TypeScript, Vite, Pinia, Vue Router, Axios.
- Widget público: JavaScript isolado, CSS escopado, sem dependencia obrigatória do e-commerce.
- Banco: MySQL/MariaDB, `utf8mb4`, `utf8mb4_unicode_ci`.
- UI: identidade visual inspirada no BigShop HelpDesk.
- Icones: Font Awesome e/ou lucide quando fizer sentido no frontend.
- Testes: PHPUnit/Pest no backend, Vitest ou testes focados no frontend, smoke público.
- Deploy: GitHub Actions via SSH.

## Superficies do produto

1. Site público:
   - landing simples;
   - como funciona;
   - planos;
   - cadastro/login;
   - página de produto ficticia para testar o provador real.

2. Painel do lojista:
   - dashboard;
   - empresas/lojas;
   - produtos;
   - tabelas de medidas;
   - importacao por XML/feed/API;
   - instalação do widget;
   - integrações por plataforma;
   - analytics de recomendações.

3. Widget:
   - botão na página de produto;
   - modal/drawer responsivo;
   - coleta de altura, peso, idade, gênero, formato corporal e medidas detalhadas;
   - recomendação de tamanho com confiança e explicacao curta;
   - feedback do consumidor.

4. SaaS/admin:
   - clientes/lojistas;
   - planos e status;
   - integrações;
   - logs de recomendação;
   - incidentes e diagnosticos.

## Licoes do v1

- O conceito do widget funciona, mas o v1 ficou fragil por estar em PHP puro e com padroes mistos.
- A nomenclatura deve ser consistente em ingles no código e PT-BR nos textos do usuário.
- O widget precisa suportar compatibilidade com atributos antigos quando for util, mas o v2 deve padronizar `merchant_id`, `store_id`, `product_id`, `sku` e `variant_id`.
- A recomendação precisa ter motor próprio determinístico antes de depender de IA externa.
- IA deve ajudar em OCR, criação de tabela e análise, não inventar tamanho sem dados.
- A página de produto de teste e obrigatória para validar o fluxo real.

## Diferencial vs Sizebay

- Foco em e-commerce brasileiro e suporte local.
- Integração simples e guiada para qualquer plataforma.
- BigShop com caminho de um clique.
- Preço e operação em reais, sem complexidade de enterprise.
- Widget leve, com instalação transparente e sem exigir troca de plataforma.

## Principios

- Simples de instalar.
- Preciso o suficiente para gerar confiança.
- Robusto o suficiente para operar em produção.
- Mobile first.
- API first.
- Segredos fora do Git.
- Documentação viva como contrato.
