# Segurança, Privacidade e Compliance

Atualizado em: 2026-05-23

## Principios

- Coletar o mínimo necessário.
- Explicar ao consumidor por que a medida e pedida.
- Não vender nem expor dados corporais.
- Não armazenar dados sensíveis sem necessidade.
- Criptografar credenciais de integração.
- Registrar auditoria de ações sensíveis.

## LGPD

Dados do widget podem ser pessoais. Regras:

- evitar nome, email e telefone no fluxo padrão de recomendação;
- usar session anonima;
- permitir retenção curta configuravel;
- permitir exclusao/anomizacao;
- termos e política devem explicar finalidade.

Status Sprint 36: `shopper_profiles` salva medidas somente com consentimento operacional no widget. O navegador guarda `profile_id` e token local; o banco guarda apenas hash do token. O comprador pode limpar o perfil pelo widget, e `pv:privacy-anonymize` também remove medidas/preferencias antigas.

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
Chaves reais do Mercado Pago, inclusive as usadas como referência a partir do projeto `D:\Projetos\NoAzul`, só podem ficar em `docs/credentials.local.md`, `.env` local/remoto ou secrets do provedor. A documentação versionada registra nomes de variáveis e origem operacional, nunca valores.

## CORS e widget

- Durante desenvolvimento, CORS pode ser permissivo.
- Em produção, liberar domínios configurados por loja.
- Requisições públicas devem ter rate limit.
- `config-check` deve revelar apenas estado funcional, não detalhes internos.

## Integrações

- Tokens externos criptografados.
- Campo de senha/token write-only.
- Não logar headers sensíveis.
- Webhooks com HMAC quando possível.
- Payload bruto salvo com mascaramento quando contiver dados sensíveis.

## IA

- Não enviar credenciais para prompts.
- Evitar enviar dados pessoais identificaveis.
- Guardar versao do prompt/provider/modelo quando usado.
- Toda tabela sugerida por IA requer revisão humana.
- Logs de IA guardam hash/resumo operacional, não conteúdo bruto enviado pelo lojista.

## Auditoria

Auditar:

- login e logout;
- criação/alteracao de conexões;
- alteracao de tabela de medidas;
- importacoes;
- mudanca de plano/status;
- uso de IA;
- falhas de webhook.

Status Sprint 10: `audit_logs` registra ações em tabelas de medidas, widget e integrações com hash de IP/user-agent e metadata sem secrets.

Status Sprint 11: `audit_logs` também registra login/logout; metadata sensível é mascarada recursivamente. Widget público valida `Origin` por domínio configurado, rotas públicas têm rate limit e existem comandos de anonimização/limpeza para retenção LGPD.
