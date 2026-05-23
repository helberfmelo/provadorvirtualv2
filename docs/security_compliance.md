# Seguranca, Privacidade e Compliance

Atualizado em: 2026-05-23

## Principios

- Coletar o minimo necessario.
- Explicar ao consumidor por que a medida e pedida.
- Nao vender nem expor dados corporais.
- Nao armazenar dados sensiveis sem necessidade.
- Criptografar credenciais de integracao.
- Registrar auditoria de acoes sensiveis.

## LGPD

Dados do widget podem ser pessoais. Regras:

- evitar nome, email e telefone no fluxo padrao de recomendacao;
- usar session anonima;
- permitir retencao curta configuravel;
- permitir exclusao/anomizacao;
- termos e politica devem explicar finalidade.

## Segredos

Nunca commitar:

- `.env`;
- senhas de banco;
- senha SMTP;
- tokens BigShop;
- chaves IA;
- chaves SSH;
- secrets de pagamento.

`docs/credentials.local.md` fica no `.gitignore`.

## CORS e widget

- Durante desenvolvimento, CORS pode ser permissivo.
- Em producao, liberar dominios configurados por loja.
- Requisicoes publicas devem ter rate limit.
- `config-check` deve revelar apenas estado funcional, nao detalhes internos.

## Integracoes

- Tokens externos criptografados.
- Campo de senha/token write-only.
- Nao logar headers sensiveis.
- Webhooks com HMAC quando possivel.
- Payload bruto salvo com mascaramento quando contiver dados sensiveis.

## IA

- Nao enviar credenciais para prompts.
- Evitar enviar dados pessoais identificaveis.
- Guardar versao do prompt/provider/modelo quando usado.
- Toda tabela sugerida por IA requer revisao humana.
- Logs de IA guardam hash/resumo operacional, nao conteudo bruto enviado pelo lojista.

## Auditoria

Auditar:

- login e logout;
- criacao/alteracao de conexoes;
- alteracao de tabela de medidas;
- importacoes;
- mudanca de plano/status;
- uso de IA;
- falhas de webhook.

Status Sprint 10: `audit_logs` registra acoes em tabelas de medidas, widget e integracoes com hash de IP/user-agent e metadata sem secrets.
