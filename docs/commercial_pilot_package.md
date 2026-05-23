# Pacote Comercial e Piloto Assistido

Atualizado em: 2026-05-23

## Objetivo

Dar ao suporte/comercial um roteiro objetivo para demonstrar, contratar, configurar e acompanhar um cliente piloto sem depender de improviso.

## Status Sprint 37

Implementado em `/app/go-live` via `GET /api/v1/go-live/readiness`:

- checklist tecnico de prontidao;
- links comerciais principais;
- passos de onboarding;
- comandos de automacao para cPanel;
- pendencias reais que ainda dependem de credenciais ou loja piloto;
- checks de Pagar.me, cron, performance do widget, acessibilidade/mobile e BigShop.

Publicado e validado em producao no run `26340033238`.

## Materiais comerciais

- Site publico: `https://provadorvirtual.online/`
- Produto teste: `https://provadorvirtual.online/produto-teste`
- Checkout: `https://provadorvirtual.online/checkout`
- WhatsApp especialista: `https://wa.me/5531993157573`
- Politica de privacidade: `https://provadorvirtual.online/privacidade`
- Termos: `https://provadorvirtual.online/termos`

## Onboarding do cliente

1. Cadastrar empresa no SaaS ou contratar pelo checkout.
2. Conferir plataforma contratada e codigo/CNPJ de acesso.
3. Cadastrar ou importar produtos, variacoes e tabelas de medidas.
4. Configurar dominio permitido do widget.
5. Instalar snippet ou usar integracao BigShop.
6. Executar recomendacao real e feedback no produto piloto.
7. Acompanhar analytics, outliers e prontidao de go-live.

## Comandos de operacao

Cron principal recomendado no cPanel:

```cron
* * * * * cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan schedule:run >> /home1/opents62/provadorvirtual.online/provadorvirtual_v2/storage/logs/cron-schedule.log 2>&1
```

Comandos manuais uteis:

```bash
php artisan pv:payments-sync --limit=50
php artisan pv:emails-dispatch --limit=50
php artisan pv:privacy-anonymize
```

Validacao local/operacional:

```powershell
.\scripts\validate-production.ps1
```

## Pendencias para piloto real

- Transacao Pagar.me Pix/cartao de baixo valor com webhook e cron.
- Ativacao BigShop um clique com payload assinado real.
- Probe e sync em loja BigShop piloto com produto, grade e tabela.
- Teste de widget em pagina real de cliente com cache frio e mobile.

## Criterio de liberacao comercial

Demo assistida pode seguir quando:

- site publico, produto teste, widget e API estiverem verdes;
- privacidade/termos estiverem publicados;
- analytics e go-live estiverem acessiveis;
- pendencias de Pagar.me/BigShop estiverem explicitamente registradas.

Campanha publica deve aguardar:

- chaves reais Pagar.me em producao;
- cron cPanel gerando log recente;
- transacao real aprovada;
- piloto BigShop real, quando a venda envolver BigShop um clique.
