# Assistente de IA para Tabelas

Atualizado em: 2026-05-23

## Objetivo

Acelerar a criacao de tabelas de medidas sem substituir a revisao do lojista.

## Status Sprint 9

Implementado:

- tela protegida `/app/assistente`;
- rota protegida `GET /api/v1/ai/status`;
- rota protegida `POST /api/v1/ai/measurement-table-suggestions`;
- tabela `ai_usage_logs`;
- parser local para texto e CSV colados;
- upload de imagem preparado no front;
- guardrail de revisao obrigatoria;
- log de uso sem salvar conteudo bruto.

Sem chave de IA, OCR real de imagem fica pendente e a API retorna `needs_provider` quando recebe somente imagem.

## Variaveis

```env
AI_PROVIDER=local
AI_MODEL=local-table-parser-v1
OPENAI_API_KEY=
GEMINI_API_KEY=
```

Provider inicial:

- `local`: parser deterministico para texto/CSV;
- `openai`: reservado para OCR/imagem quando `OPENAI_API_KEY` for cadastrado e o provider externo for ativado;
- `gemini`: reservado para compatibilidade com legado do v1 quando `GEMINI_API_KEY` for cadastrado e o provider externo for ativado.

## Contrato de sugestao

Payload:

```json
{
  "source_type": "text",
  "name": "Camisas assistidas",
  "product_type": "shirt",
  "gender": "unisex",
  "fit_profile": "regular",
  "content": "Tamanho Busto Cintura Quadril\nP 88-94 70-76 92-98"
}
```

Resposta:

```json
{
  "data": {
    "status": "completed",
    "review_required": true,
    "confidence": 0.72,
    "provider": "local_parser",
    "suggestion": {
      "status": "draft",
      "source": "ai",
      "rows": []
    }
  }
}
```

`suggestion` usa o mesmo formato aceito por `POST /api/v1/measurement-tables`.

## Guardrails

- IA/parser nunca cria tabela ativa automaticamente.
- Sugestoes sempre saem como `draft`.
- Conteudo bruto nao e salvo em `ai_usage_logs`.
- Logs guardam hash, provider, modelo, tokens estimados, custo estimado, status e resumo operacional.
- Imagem sem provider configurado nao recebe OCR falso.
- Resultado precisa ser revisado e salvo pelo lojista.

## Formatos de texto aceitos

Cabecalhos recomendados:

```txt
Tamanho Busto Cintura Quadril
P 88-94 70-76 92-98
M 94-100 76-82 98-104
```

Tambem aceita CSV com nomes canonicos:

```csv
size_label,bust_min,bust_max,waist_min,waist_max,hip_min,hip_max
P,88,94,70,76,92,98
M,94,100,76,82,98,104
```

## Pendencias

- Cadastrar `OPENAI_API_KEY` ou `GEMINI_API_KEY` e ativar provider externo para OCR real de imagem.
- Definir prompt externo final e custo por 1k tokens quando provider externo for ativado.
- Usar logs de feedback para analise assistida em sprint futura.
