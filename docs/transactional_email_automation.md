# Automacoes de E-mail Transacional

Atualizado em: 2026-05-23

## Objetivo

Transformar os templates transacionais em disparos reais, com historico consultavel e reprocessamento por cron.

## Status Sprint 31

Implementado:

- tabela `transactional_email_sends`;
- service `TransactionalEmailService`;
- historico no SaaS em `GET /api/v1/saas/transactional-email-sends`;
- listagem do historico na tela `/saas`;
- envio de `cadastro_realizado` apos checkout publico e cadastro interno de empresa com owner;
- envio de `aguardando_pagamento` quando checkout Pix fica pendente;
- envio de `pagamento_confirmado` quando checkout e aprovado;
- envio de `erro_pagamento` quando checkout falha, expira, e cancelado ou estornado;
- comando `php artisan pv:emails-dispatch --limit=50`;
- scheduler executando o dispatcher de e-mails a cada 10 minutos.

## Regras

- Se SMTP estiver inativo ou incompleto, o disparo nao quebra o fluxo: o historico registra `skipped`.
- Template inativo ou ausente tambem gera historico `skipped`.
- Falha de transporte SMTP gera historico `failed` e log operacional sem expor senha.
- E-mails `pagamento_confirmado` e `erro_pagamento` nao sao reenviados se ja houver envio `sent` para o checkout.
- E-mail `aguardando_pagamento` pode ser reenviado, mas apenas apos 6 horas desde o ultimo envio `sent`.
- Links usam `FRONTEND_URL` quando configurado; fallback usa `APP_URL`.

## Variaveis principais

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

Validacao manual do dispatcher:

```bash
cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan pv:emails-dispatch --limit=10
```

## Pendencias

- Criar fluxo completo de recuperacao de senha.
- Criar checkout de renovacao com referencia de empresa/plano.
- Definir politica de retencao do historico de e-mails.
