# Pacote Comercial e Piloto Assistido

Atualizado em: 2026-05-23

## Objetivo

Dar ao suporte/comercial um roteiro objetivo para demonstrar, contratar, configurar e acompanhar um cliente piloto sem depender de improviso.

## Status Sprint 37

Implementado em `/app/go-live` via `GET /api/v1/go-live/readiness`:

- checklist técnico de prontidão;
- links comerciais principais;
- passos de onboarding;
- comandos de automação para cPanel;
- pendências reais que ainda dependem de credenciais ou loja piloto;
- checks de Pagar.me, cron, performance do widget, acessibilidade/mobile e BigShop.

Publicado e validado em produção no run `26340033238`.

## Materiais comerciais

- Site público: `https://provadorvirtual.online/`
- Produto teste: `https://provadorvirtual.online/produto-teste`
- Checkout: `https://provadorvirtual.online/checkout` com planos mensal e anual. Qualquer plataforma: `R$ 489,80/mes` no mensal ou `R$ 449,80/mes` no anual; BigShop: `R$ 389,80/mes` no mensal ou `R$ 349,90/mes` no anual. O anual mostra total do período e economia percentual.
- WhatsApp especialista: `https://wa.me/5531993157573`
- Política de privacidade: `https://provadorvirtual.online/privacidade`
- Termos: `https://provadorvirtual.online/termos`

## Onboarding do cliente

1. Cadastrar empresa no SaaS ou contratar pelo checkout.
2. Conferir plataforma contratada e código/CNPJ de acesso.
3. Cadastrar ou importar produtos, variações e tabelas de medidas.
4. Configurar domínio permitido do widget.
5. Instalar snippet ou usar integração BigShop.
6. Executar recomendação real e feedback no produto piloto.
7. Acompanhar analytics, outliers e prontidão de go-live.

## Comandos de operação

Cron principal recomendado no cPanel:

```cron
* * * * * cd /home1/opents62/provadorvirtual.online/provadorvirtual_v2 && /usr/local/bin/php artisan schedule:run >> /home1/opents62/provadorvirtual.online/provadorvirtual_v2/storage/logs/cron-schedule.log 2>&1
```

Comandos manuais uteis:

```bash
php artisan pv:payments-sync --limit=50
php artisan pv:emails-dispatch --limit=50
php artisan pv:integrations-sync-feeds --limit=50
php artisan pv:privacy-anonymize
```

Validação local/operacional:

```powershell
.\scripts\validate-production.ps1
```

## Pendências para piloto real

- Transação Mercado Pago Pix/cartão de baixo valor com webhook e cron.
- Ativação BigShop um clique com payload assinado real.
- Probe e sync em loja BigShop piloto com produto, grade e tabela.
- Teste de widget em página real de cliente com cache frio e mobile.

## Criterio de liberacao comercial

Demo assistida pode seguir quando:

- site público, produto teste, widget e API estiverem verdes;
- privacidade/termos estiverem publicados;
- analytics e go-live estiverem acessiveis;
- pendências de Pagar.me/BigShop estiverem explicitamente registradas.

Campanha pública deve aguardar:

- cron cPanel gerando log recente;
- transação real Mercado Pago aprovada;
- piloto BigShop real, quando a venda envolver BigShop um clique.
