# Automações de E-mail Transacional

Atualizado em: 2026-05-23

## Objetivo

Transformar os templates transacionais em disparos reais, com histórico consultavel e reprocessamento por cron.

## Status Sprint 31

Implementado:

- tabela `transactional_email_sends`;
- service `TransactionalEmailService`;
- histórico no SaaS em `GET /api/v1/saas/transactional-email-sends`;
- listagem do histórico na tela `/saas`;
- envio de `cadastro_realizado` após checkout público e cadastro interno de empresa com owner;
- envio de `aguardando_pagamento` quando checkout Pix fica pendente;
- envio de `pagamento_confirmado` quando checkout e aprovado;
- envio de `erro_pagamento` quando checkout falha, expira, e cancelado ou estornado;
- comando `php artisan pv:emails-dispatch --limit=50`;
- scheduler executando o dispatcher de e-mails a cada 10 minutos.

## Regras

- Se SMTP estiver inativo ou incompleto, o disparo não quebra o fluxo: o histórico registra `skipped`.
- Template inativo ou ausente também gera histórico `skipped`.
- Falha de transporte SMTP gera histórico `failed` e log operacional sem expor senha.
- E-mails `pagamento_confirmado` e `erro_pagamento` não são reenviados se já houver envio `sent` para o checkout.
- E-mail `aguardando_pagamento` pode ser reenviado, mas apenas após 6 horas desde o ultimo envio `sent`.
- Links usam `FRONTEND_URL` quando configurado; fallback usa `APP_URL`.

## Variáveis principais

- `nome`
- `empresa`
- `codigo_empresa`
- `email_acesso`
- `link_login`
- `link_checkout`
- `link_pix`
- `link_renovacao`
- `valor`

## Cron

O cron principal continua sendo o scheduler Laravel:

```bash
cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan schedule:run >> /home1/opents62/provadorvirtual.online/provadorvirtual_v2/storage/logs/cron-schedule.log 2>&1
```

Validação manual do dispatcher:

```bash
cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan pv:emails-dispatch --limit=10
```

## Pendências

- Criar fluxo completo de recuperacao de senha.
- Criar checkout de renovacao com referência de empresa/plano.
- Definir política de retenção do histórico de e-mails.
