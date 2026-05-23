# Go-live Assistido e Cutover

Atualizado em: 2026-05-23

## Decisao atual

Manter o v2 publicado em:

- `https://provadorvirtual.online/provadorvirtual_v2/`

Nao mover para a raiz do dominio ate concluir o piloto BigShop ou haver aceite
comercial explicito. O v1 permanece preservado em `/provadorvirtual_v1/`.

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

Plano recomendado em duas etapas:

1. Cutover leve: raiz `https://provadorvirtual.online/` redireciona para
   `/provadorvirtual_v2/`, mantendo a aplicacao na subpasta.
2. Cutover pleno: publicar app na raiz apenas se for necessario para SEO, marca ou
   instalacao do widget.

Antes do cutover pleno:

- atualizar `APP_URL` e `FRONTEND_URL`;
- ajustar `VITE_APP_BASE_PATH`;
- revisar `VITE_API_BASE_URL`;
- manter backup do release atual;
- validar `/`, `/login`, `/produto-teste`, widget e APIs;
- preservar `/provadorvirtual_v1/` ate decisao explicita.

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
