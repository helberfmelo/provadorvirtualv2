# Diretrizes de Desenvolvimento

Atualizado em: 2026-05-23

## Pilares

- Simplicidade real de integracao.
- Mobile first.
- API first.
- Multiempresa desde a primeira migration.
- UX premium, clara e operacional.
- Widget leve, isolado e seguro.
- IA assistiva, nao inventiva.
- Observabilidade desde a fundacao.
- Segredos fora do Git.
- Documentacao viva como contrato.

## Padroes de codigo

- Codigo, nomes de arquivos, classes, tabelas e colunas em ingles.
- Textos para usuario em PT-BR.
- Backend por dominios: auth, merchants, products, measurement, recommendation, integrations, widget, analytics, billing.
- Controllers focam em I/O.
- Services concentram regra de negocio.
- Form Requests validam entradas densas.
- Resources/DTOs padronizam saidas.
- Jobs/webhooks idempotentes.
- Toda credencial externa criptografada em repouso.
- API interna versionada em `/api/v1`.
- API publica/widget tambem versionada.

## Padroes Laravel

- PHP 8.2+.
- Laravel 11+.
- Sanctum para SPA.
- Migrations pequenas, reversiveis e compativeis com MySQL/MariaDB compartilhado.
- Seeders separados: base/producao/demo.
- `ProductionSeeder` deve ser idempotente e nao sobrescrever dados reais.
- Defaults de cache/session em `file` ate haver necessidade operacional diferente.
- Nunca depender de SQLite em runtime de producao.

## Padroes Vue

- Vue 3 + TypeScript + Vite.
- Pinia para estado.
- Vue Router para rotas.
- Axios para HTTP centralizado.
- Componentes por dominio e componentes compartilhados pequenos.
- Tabelas densas para CRUDs.
- Formularios dedicados quando houver muitos campos.
- Botoes de acao em tabela com icones, `title` e `aria-label`.

## UI e identidade

- Base visual inspirada no BigShop HelpDesk:
  - primaria: `#0f172a`;
  - secundaria: `#ff4d5e`;
  - acento: `#ff7a1a`;
  - sidebar: `#111827`;
  - fundo: `#f6f7fb`;
  - fonte: Manrope.
- CTAs principais podem usar gradiente quente `#ff4d5e` -> `#ff7a1a`.
- Painel deve parecer ferramenta de trabalho, nao landing page.
- Landing pode ser comercial, mas o app autenticado deve ser objetivo.
- Nao usar textos internos como sprint, payload, token ou schema em telas publicas.
- Cards apenas para itens repetidos, modais e ferramentas; evitar card dentro de card.
- Inputs de cor devem usar swatch/preview e nao apenas campo cru.
- Mobile deve funcionar primeiro, especialmente o widget.

## Regras do widget

- JS isolado, sem exigir Vue, jQuery ou Bootstrap na loja.
- CSS escopado com prefixo `pv-`.
- Carregar de forma assincrona/defer.
- Nao quebrar a pagina do cliente se a API falhar.
- Fazer `config-check` antes de exibir promessa de tamanho.
- CORS restrito por dominios configurados quando houver instalacao validada.
- Permitir modo demo na pagina `/produto-teste`.
- Nunca armazenar dados pessoais desnecessarios.

## Regras de IA

- Motor deterministico vem primeiro.
- IA nao pode inventar tamanho, tabela, estoque, preco, prazo ou politica.
- IA pode extrair tabela de medida de imagem/texto e sugerir ajustes.
- Toda saida de IA usada em tabela precisa passar por revisao do lojista.
- Prompts e versoes devem ser rastreaveis.
- Tokens/custos devem ser logados sem expor conteudo sensivel.

## Regras de banco

- Charset `utf8mb4`.
- Collation `utf8mb4_unicode_ci`.
- Soft delete em entidades com valor operacional.
- Indices por `merchant_id`, `company_id`, `store_id`, `external_id`, `sku`.
- Nunca confiar em `merchant_id` vindo do cliente sem resolver pelo usuario/token.
- Logs devem ser consultaveis e ter retencao planejada.

## Regras de integracao

- Cada plataforma deve passar por um adapter canonico.
- BigShop e provider prioritario.
- Tokens BigShop, Shopify, etc. devem ser write-only na API e criptografados.
- Webhooks externos devem salvar payload bruto antes de processar.
- HMAC/assinatura obrigatoria quando o provedor suportar.
- Falha remota nao pode virar sucesso silencioso.

## Regras operacionais

- Antes de qualquer sprint, reler `docs/README.md` e `docs/sprint_governance.md`.
- Ao fim de cada sprint, atualizar docs, executar validacoes, fazer commit, push e acompanhar Actions.
- Deploy com schema exige plano de migration e rollback.
- Incidente real deve atualizar `docs/incident_runbook.md` e `docs/execution_log.md`.
