# Importação e Qualidade de Dados

Atualizado em: 2026-05-29

## Objetivo

Reduzir cadastro manual sem deixar dados ruins entrarem direto no motor de recomendação.

## APIs

Rotas protegidas por Sanctum:

- `GET /api/v1/imports`
- `GET /api/v1/imports/{importJob}`
- `POST /api/v1/imports/preview`
- `POST /api/v1/imports`
- `POST /api/v1/integrations/{platform}/sync-xml`, busca `feed_url` salvo na integração e cria um import job de produtos
- `php artisan pv:integrations-sync-feeds --limit=50`, sincroniza automaticamente integrações com `feed_url` salvo
- `GET /api/v1/measurement-tables/export`, exporta tabelas filtradas em CSV ou XLSX
- `GET /api/v1/measurement-tables/template`, gera modelo CSV ou XLSX para base `body`, `garment` ou `mixed`
- `POST /api/v1/measurement-tables/import/preview`, valida planilhas de tabelas antes de gravar
- `POST /api/v1/measurement-tables/import`, cria ou atualiza tabelas de medidas após prévia sem falhas

Payload base:

```json
{
  "type": "products",
  "source_format": "csv",
  "filename": "produtos.csv",
  "content": "sku,name,..."
}
```

Tipos aceitos:

- `products`
- `measurement_tables`

Formatos aceitos:

- `csv`
- `xlsx`, para os endpoints dedicados de tabelas de medidas
- `google_xml`, apenas para produtos

## CSV de produtos

Cabecalhos recomendados:

```csv
sku,name,category,gender,fit_profile,size_label,variant_sku,price,stock_quantity,measurement_table
```

Regras:

- `sku` ou `external_product_id` identifica produto.
- `name` é obrigatório.
- `size_label` cria ou atualiza variação.
- `measurement_table` tenta vincular tabela existente pelo nome.
- importação atualiza produto por `sku` ou `external_product_id`.
- quando o feed/XML não informar `measurement_table`, a sincronização preserva a tabela já vinculada manualmente ao produto.

## CSV/XLSX de tabelas

Cabecalhos recomendados:

```csv
table_name,product_type,gender,fit_profile,measurement_target,size_system,range_mode,status,table_notes,size_label,size_note,bust_min,bust_max,bust_note,waist_min,waist_max,waist_note,hip_min,hip_max,hip_note,height_min,height_max,height_note,weight_min,weight_max,weight_note,length_min,length_max,length_note,shoulder_min,shoulder_max,shoulder_note,composite_min,composite_max,composite_note
```

Regras:

- `table_name` e `size_label` são obrigatórios.
- Linhas com o mesmo `table_name` compõem a mesma tabela.
- Dentro da mesma tabela, cada `size_label` deve aparecer uma única vez.
- `measurement_target` aceita `body`, `garment` ou `mixed`; quando ausente, entra como `body`.
- `size_system` aceita `br_alpha`, `br_numeric`, `international` ou `custom`.
- `range_mode` aceita `min_max`, `exact` ou `tolerance`.
- `status` aceita `active`, `draft` ou `inactive`.
- Medidas numéricas aceitam vírgula ou ponto decimal e precisam ficar entre 0 e 999,99.
- `table_notes` fica salvo como observação da tabela.
- `size_note` e `*_note` ficam em `measurement_table_rows.metadata`, permitindo observação por tamanho e por medida.
- Ao importar uma tabela existente com o mesmo nome, as linhas são substituídas pelo conteúdo importado.
- Fonte da tabela fica como `import`.
- A prévia de importação retorna `line`, `column`, `field`, `message` e `suggestion` para cada erro, e o commit é bloqueado enquanto houver qualquer falha.
- A exportação respeita os filtros aplicados em `/app/tabelas-de-medidas`: busca, status, base, tipo, modelagem e uso em produtos.

## Google Shopping XML

Parser de produtos lê campos comuns:

- `g:id`
- `g:item_group_id`
- `g:mpn`
- `title`
- `description`
- `g:product_type`
- `g:google_product_category`
- `g:image_link`
- `link`
- `g:gender`
- `g:age_group`
- `g:brand`
- `g:size`
- `g:color`
- `g:availability`
- `g:price`

Mapeamento:

- `g:item_group_id` vira o produto pai quando existir.
- `g:id` vira a variação.
- `g:size` cria ou atualiza `product_variants.size_label`.
- `g:color` cria ou atualiza `product_variants.color`.
- `g:availability` controla `product_variants.is_active` quando vier como `in stock` ou `out of stock`.
- `link`, `g:brand` e `g:age_group` ficam em metadata.

Não cria grade completa quando o feed não informa tamanho. Nesse caso, o catálogo entra como produto e a grade/tabela deve ser completada no painel ou por API.

## Jobs e logs

Cada commit cria `import_jobs` com:

- status;
- total de linhas;
- linhas importadas;
- linhas com erro;
- resumo;
- erros por linha.

`integration_events` foi criado como trilha para sync/webhook das próximas sprints.

## Sincronização automática

Sprint 53 adicionou o comando `php artisan pv:integrations-sync-feeds --limit=50`.

Regras:

- busca conexões com `feed_url` preenchido;
- ignora integrações `draft` e `disabled`;
- processa até o limite informado;
- registra `integration_events` com `event_type=xml_feed_sync` e `summary.trigger=scheduled`;
- atualiza `last_sync_at`, `status` e `last_error` da conexão.

O scheduler Laravel roda o comando 4 vezes por dia, às `00:00`, `06:00`, `12:00` e `18:00` em `America/Sao_Paulo`.
