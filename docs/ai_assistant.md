# Assistente de IA para Tabelas

Atualizado em: 2026-05-23

## Objetivo

Acelerar a criação de tabelas de medidas sem substituir a revisão do lojista.

## Status Sprint 9

Implementado:

- tela protegida `/app/assistente`;
- rota protegida `GET /api/v1/ai/status`;
- rota protegida `POST /api/v1/ai/measurement-table-suggestions`;
- tabela `ai_usage_logs`;
- parser local para texto e CSV colados;
- upload de imagem preparado no front;
- guardrail de revisão obrigatória;
- log de uso sem salvar conteúdo bruto.

Sem chave de IA, OCR real de imagem fica pendente e a API retorna `needs_provider` quando recebe somente imagem.

## Status Sprint 43

Implementado:

- catálogo inteligente importado do v1 em `backend/database/data/default_measurement_tables_data.json`;
- servico `StandardMeasurementCatalog` normalizando modelos de gênero, tipo, altura, peso, idade, formato corporal e campos de medidas;
- `/api/v1/measurement-templates` entrega a base brasileira para a tela de tabelas;
- tela `/app/tabelas-de-medidas/nova` permite iniciar por modelo inteligente e depois revisar manualmente;
- site público e assistente comunicam IA como ferramenta de automação, qualidade e aprendizado seguro.

## Variáveis

```env
AI_PROVIDER=local
AI_MODEL=local-table-parser-v1
OPENAI_API_KEY=
GEMINI_API_KEY=
```

Provider inicial:

- `local`: parser determinístico para texto/CSV;
- `openai`: reservado para OCR/imagem quando `OPENAI_API_KEY` for cadastrado e o provider externo for ativado;
- `gemini`: reservado para compatibilidade com legado do v1 quando `GEMINI_API_KEY` for cadastrado e o provider externo for ativado.

## Contrato de sugestão

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
- Conteúdo bruto não e salvo em `ai_usage_logs`.
- Logs guardam hash, provider, modelo, tokens estimados, custo estimado, status e resumo operacional.
- Imagem sem provider configurado não recebe OCR falso.
- Resultado precisa ser revisado e salvo pelo lojista.

## Formatos de texto aceitos

Cabecalhos recomendados:

```txt
Tamanho Busto Cintura Quadril
P 88-94 70-76 92-98
M 94-100 76-82 98-104
```

Também aceita CSV com nomes canonicos:

```csv
size_label,bust_min,bust_max,waist_min,waist_max,hip_min,hip_max
P,88,94,70,76,92,98
M,94,100,76,82,98,104
```

## Credencial legado v1

Foi encontrada `GEMINI_API_KEY` em `D:\Projetos\provadorvirtual_v1\.env`.

O valor foi copiado para `docs/credentials.local.md`, que esta ignorado pelo Git. Não registrar o valor em docs versionadas, logs, commits ou respostas. O v1 usava Gemini com modelo `gemini-2.0-flash` para gerar conteúdo, extrair tabela de imagem e sugerir modelos de medidas.

Para ativar em produção, cadastrar `GEMINI_API_KEY` no ambiente/Actions e trocar:

```env
AI_PROVIDER=gemini
AI_MODEL=gemini-2.0-flash
```

## Pendências

- Cadastrar `GEMINI_API_KEY` em produção e ativar provider externo para OCR real de imagem, se aprovado para a próxima sprint.
- Opcional: cadastrar `OPENAI_API_KEY` para provider alternativo.
- Definir prompt externo final e custo por 1k tokens quando provider externo for ativado.
- Usar logs de feedback para análise assistida em sprint futura.
