# Documentacao do Projeto Provador Virtual

Atualizado em: 2026-05-23  
Status: fonte inicial de verdade para iniciar desenvolvimento do `provadorvirtual_v2`.

## Documentos obrigatorios antes de qualquer sprint

Antes de desenvolver qualquer sprint, ajuste, correcao ou deploy, reler nesta ordem:

1. `docs/project_overview.md`
2. `docs/master_spec.md`
3. `docs/current_platform_state.md`
4. `docs/development_guidelines.md`
5. `docs/technical_architecture.md`
6. `docs/widget_integration.md`
7. `docs/imports_data_quality.md`
8. `docs/bigshop_integration.md`
9. `docs/deploy_runbook.md`
10. `docs/sprint_governance.md`
11. `docs/roadmap_sprints.md`
12. `docs/product_backlog.md`
13. `docs/security_compliance.md`
14. `docs/credentials.local.md`, somente quando a sprint envolver producao, banco, SMTP, deploy, IA ou integracoes.

Nenhuma sprint deve comecar sem essa releitura.

## Fontes estudadas

- `D:\Projetos\bigshop\bigshop_helpdesk`
- `D:\Projetos\marcahora_novo`
- `D:\Projetos\bigshop360`
- `D:\Projetos\provadorvirtual_v1`
- `D:\Projetos\bigbangshop2.0`
- `D:\Projetos\bigshop\172.16.151.5`
- Documentacao publica: `https://documenter.getpostman.com/view/4253101/2s93sdYrsi`

## Decisoes iniciais

- Backend: Laravel 11 ou superior, PHP 8.2+, Sanctum, API REST versionada em `/api/v1`.
- Frontend: Vue 3, TypeScript, Vite, Pinia, Vue Router.
- Banco: MySQL/MariaDB com `utf8mb4` e `utf8mb4_unicode_ci`.
- Deploy: GitHub Actions via SSH para HostGator/opentshost.
- Publicacao inicial: `https://provadorvirtual.online/provadorvirtual_v2/`, preservando o v1.
- Nome comercial: Provador Virtual, sem expor v1/v2 ao cliente final.
- Widget: SDK JavaScript universal e isolado, com snippet simples por plataforma.
- BigShop: integracao nativa de um clique como objetivo prioritario, usando a API V3 e/ou encaixe direto no front da BigShop.

## Observacao sobre segredos

`docs/credentials.local.md` existe apenas para referencia local e esta no `.gitignore`. Nao commitar esse arquivo.
