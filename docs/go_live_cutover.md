# Go-live Assistido e Cutover

Atualizado em: 2026-05-23

## Decisao atual

Depois da Sprint 27, o site publico comercial do v2 deve rodar na raiz:

- `https://provadorvirtual.online/`

A aplicacao/backend v2 continua preservada em:

- `https://provadorvirtual.online/provadorvirtual_v2/`

Motivo: entregar a marca sem sufixo para visitantes e checkout, preservando o v1
em `/provadorvirtual_v1/` e mantendo rollback simples da aplicacao v2.

## Status Sprint 12

Implementado:

- rota protegida `GET /api/v1/go-live/readiness`;
- tela protegida `/app/go-live`;
- script `scripts/validate-production.ps1`;
- arquivo `tools/widget-external-smoke.html` para validar o widget fora do app;
- checklist de cutover e pendencias externas.

Publicado em producao no run `26333226813`. Validacao final com
`scripts/validate-production.ps1` retornou `PRODUCTION VALIDATION OK`.

## Checklist de go-live

Obrigatorio antes de campanha publica:

- GitHub Actions verde no ultimo commit;
- `GET /api/v1/health` retornando `ok`;
- `GET /api/v1/ops/status` retornando `ok`;
- `/produto-teste` funcionando em producao;
- widget carregando por snippet real;
- CORS bloqueando origem nao cadastrada e liberando dominio configurado;
- produto piloto com tabela de medidas revisada;
- backup criado no deploy;
- politica de privacidade e termos publicados;
- plano de rollback revisado.

## Validacao automatizada

Rodar:

```powershell
.\scripts\validate-production.ps1
```

O script valida:

- paginas publicas principais;
- health e ops status;
- produto teste;
- recomendacao publica;
- bloqueio/liberacao de CORS;
- login demo;
- endpoint de prontidao.

## Validacao externa do widget

Servir o arquivo de smoke por HTTP local:

```powershell
python -m http.server 8090 -d tools
```

Depois abrir:

```txt
http://localhost:8090/widget-external-smoke.html
```

`localhost` esta cadastrado nos dominios demo. Para loja real, cadastrar o dominio
final em `/app/widget` antes de instalar o snippet.

## Cutover para raiz

Estrategia executada:

- build estatica Vue com `VITE_APP_BASE_PATH=/` publicada no docroot;
- API e widget continuam servidos por `/provadorvirtual_v2/`;
- `.htaccess` da raiz preserva `/provadorvirtual_v1/` e `/provadorvirtual_v2/`;
- `/api/*`, `/widget/*` e `/up` na raiz encaminham para o v2 quando necessario;
- backup da raiz fica em `/home1/opents62/deploy_backups/provadorvirtual_root`.

Validar apos cada deploy:

- `/`;
- `/checkout`;
- `/produto-teste`;
- `/api/v1/health`;
- `/widget/v1/provador-virtual.js`;
- `/provadorvirtual_v2/` como caminho de rollback.

## BigShop piloto

Ainda falta receber:

- loja/tenant BigShop de teste;
- `store_id`;
- token `x-api`;
- `BIGSHOP_ACTIVATION_SECRET`;
- webhook secret, se existir.

Sem esses dados, o produto esta pronto para demo e instalacao universal, mas o
piloto BigShop real permanece pendente.

## IA/OCR

OCR real de imagem depende de:

- `OPENAI_API_KEY`; ou
- `GEMINI_API_KEY`.

Sem chave externa, o assistente continua operando com parser local para texto/CSV.
