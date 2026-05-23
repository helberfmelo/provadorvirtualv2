# Importacao e Qualidade de Dados

Atualizado em: 2026-05-23

## Objetivo

Reduzir cadastro manual sem deixar dados ruins entrarem direto no motor de recomendação.

## APIs

Rotas protegidas por Sanctum:

- `GET /api/v1/imports`
- `GET /api/v1/imports/{importJob}`
- `POST /api/v1/imports/preview`
- `POST /api/v1/imports`
- `POST /api/v1/integrations/{platform}/sync-xml`, busca `feed_url` salvo na integração e cria um import job de produtos

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
- `google_xml`, apenas para produtos

## CSV de produtos

Cabecalhos recomendados:

```csv
sku,name,category,gender,fit_profile,size_label,variant_sku,price,stock_quantity,measurement_table
```

Regras:

- `sku` ou `external_product_id` identifica produto.
- `name` e obrigatório.
- `size_label` cria ou atualiza variação.
- `measurement_table` tenta vincular tabela existente pelo nome.
- importacao atualiza produto por `sku` ou `external_product_id`.

## CSV de tabelas

Cabecalhos recomendados:

```csv
table_name,product_type,gender,fit_profile,size_label,bust_min,bust_max,waist_min,waist_max,hip_min,hip_max,height_min,height_max,weight_min,weight_max
```

Regras:

- `table_name` e `size_label` são obrigatórios.
- Linhas com o mesmo `table_name` compoem a mesma tabela.
- Ao importar uma tabela existente com o mesmo nome, as linhas são substituidas pelo conteúdo importado.
- Fonte da tabela fica como `import`.

## Google Shopping XML

Parser de produtos le campos comuns:

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
