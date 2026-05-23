# Importacao e Qualidade de Dados

Atualizado em: 2026-05-23

## Objetivo

Reduzir cadastro manual sem deixar dados ruins entrarem direto no motor de recomendacao.

## APIs

Rotas protegidas por Sanctum:

- `GET /api/v1/imports`
- `GET /api/v1/imports/{importJob}`
- `POST /api/v1/imports/preview`
- `POST /api/v1/imports`

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
- `name` e obrigatorio.
- `size_label` cria ou atualiza variacao.
- `measurement_table` tenta vincular tabela existente pelo nome.
- importacao atualiza produto por `sku` ou `external_product_id`.

## CSV de tabelas

Cabecalhos recomendados:

```csv
table_name,product_type,gender,fit_profile,size_label,bust_min,bust_max,waist_min,waist_max,hip_min,hip_max,height_min,height_max,weight_min,weight_max
```

Regras:

- `table_name` e `size_label` sao obrigatorios.
- Linhas com o mesmo `table_name` compoem a mesma tabela.
- Ao importar uma tabela existente com o mesmo nome, as linhas sao substituidas pelo conteudo importado.
- Fonte da tabela fica como `import`.

## Google Shopping XML

Parser inicial para produtos le campos comuns:

- `g:id`
- `g:mpn`
- `title`
- `description`
- `g:product_type`
- `g:google_product_category`
- `g:image_link`
- `g:price`

Nao cria grade completa quando o feed nao informa tamanho. O objetivo inicial e acelerar cadastro e permitir completar grade/tabela no painel.

## Jobs e logs

Cada commit cria `import_jobs` com:

- status;
- total de linhas;
- linhas importadas;
- linhas com erro;
- resumo;
- erros por linha.

`integration_events` foi criado como trilha para sync/webhook das proximas sprints.
