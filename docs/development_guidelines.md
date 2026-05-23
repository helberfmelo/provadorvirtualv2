# Diretrizes de Desenvolvimento

Atualizado em: 2026-05-23

## Pilares

- Simplicidade real de integração.
- Mobile first.
- API first.
- Multiempresa desde a primeira migration.
- UX premium, clara e operacional.
- Widget leve, isolado e seguro.
- IA assistiva, não inventiva.
- Observabilidade desde a fundação.
- Segredos fora do Git.
- Documentação viva como contrato.

## Padrões de código

- Código, nomes de arquivos, classes, tabelas e colunas em inglês.
- Textos para usuário em PT-BR, com acentos, til e cedilha corretos em toda interface, mensagem de erro, tooltip, e-mail e documentação.
- Backend por domínios: auth, merchants, products, measurement, recommendation, integrations, widget, analytics, billing.
- Controllers focam em I/O.
- Services concentram regra de negócio.
- Form Requests validam entradas densas.
- Resources/DTOs padronizam saídas.
- Jobs/webhooks idempotentes.
- Toda credencial externa criptografada em repouso.
- API interna versionada em `/api/v1`.
- API pública/widget também versionada.

## Padrões Laravel

- PHP 8.2+.
- Laravel 11+.
- Sanctum para SPA.
- Migrations pequenas, reversíveis e compatíveis com MySQL/MariaDB compartilhado.
- Seeders separados: base/produção/demo.
- `ProductionSeeder` deve ser idempotente e não sobrescrever dados reais.
- Defaults de cache/session em `file` até haver necessidade operacional diferente.
- Nunca depender de SQLite em runtime de produção.

## Padrões Vue

- Vue 3 + TypeScript + Vite.
- Pinia para estado.
- Vue Router para rotas.
- Axios para HTTP centralizado.
- Componentes por domínio e componentes compartilhados pequenos.
- Tabelas densas para CRUDs.
- Formulários dedicados quando houver muitos campos.
- Botões de ação em tabela com ícones, `title` e `aria-label`.
- Inputs, selects, textareas e botões devem usar o estilo global do portal; não deixar controles HTML crus nas telas SaaS ou empresa.

## UI e identidade

- Base visual inspirada no BigShop HelpDesk:
  - primária: `#0f172a`;
  - secundária: `#ff4d5e`;
  - acento: `#ff7a1a`;
  - sidebar: `#111827`;
  - fundo: `#f6f7fb`;
  - fonte: Manrope.
- CTAs principais podem usar gradiente quente `#ff4d5e` -> `#ff7a1a`.
- Painel deve parecer ferramenta de trabalho, não landing page.
- Landing pode ser comercial, mas o app autenticado deve ser objetivo.
- Não usar textos internos como sprint, payload, token ou schema em telas públicas.
- Cards apenas para itens repetidos, modais e ferramentas; evitar card dentro de card.
- Inputs de cor devem usar swatch/preview e não apenas campo cru.
- Mobile deve funcionar primeiro, especialmente o widget.

## Regras do widget

- JS isolado, sem exigir Vue, jQuery ou Bootstrap na loja.
- CSS escopado com prefixo `pv-`.
- Carregar de forma assíncrona/defer.
- Não quebrar a página do cliente se a API falhar.
- Fazer `config-check` antes de exibir promessa de tamanho.
- CORS restrito por domínios configurados quando houver instalação validada.
- Permitir modo demo na página `/produto-teste`.
- Nunca armazenar dados pessoais desnecessários.

## Regras de IA

- Motor determinístico vem primeiro.
- IA não pode inventar tamanho, tabela, estoque, preço, prazo ou política.
- IA pode extrair tabela de medida de imagem/texto e sugerir ajustes.
- Toda saída de IA usada em tabela precisa passar por revisão do lojista.
- Prompts e versões devem ser rastreáveis.
- Tokens/custos devem ser logados sem expor conteúdo sensível.

## Regras de banco

- Charset `utf8mb4`.
- Collation `utf8mb4_unicode_ci`.
- Soft delete em entidades com valor operacional.
- Índices por `merchant_id`, `company_id`, `store_id`, `external_id`, `sku`.
- Nunca confiar em `merchant_id` vindo do cliente sem resolver pelo usuário/token.
- Logs devem ser consultáveis e ter retenção planejada.

## Regras de integração

- Cada plataforma deve passar por um adapter canônico.
- BigShop é provider prioritário.
- Tokens BigShop, Shopify, etc. devem ser write-only na API e criptografados.
- Webhooks externos devem salvar payload bruto antes de processar.
- HMAC/assinatura obrigatória quando o provedor suportar.
- Falha remota não pode virar sucesso silencioso.

## Regras operacionais

- Antes de qualquer sprint, reler `docs/README.md` e `docs/sprint_governance.md`.
- Ao fim de cada sprint, atualizar docs, executar validações, fazer commit, push e acompanhar Actions.
- Deploy com schema exige plano de migration e rollback.
- Incidente real deve atualizar `docs/incident_runbook.md` e `docs/execution_log.md`.
