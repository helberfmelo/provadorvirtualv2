# Documentação do Projeto Provador Virtual

Atualizado em: 2026-05-23  
Status: fonte inicial de verdade para iniciar desenvolvimento do `provadorvirtual_v2`.

## Documentos obrigatórios antes de qualquer sprint

Antes de desenvolver qualquer sprint, ajuste, correção ou deploy, reler nesta ordem:

1. `docs/project_overview.md`
2. `docs/master_spec.md`
3. `docs/current_platform_state.md`
4. `docs/development_guidelines.md`
5. `docs/technical_architecture.md`
6. `docs/widget_integration.md`
7. `docs/platform_integration_guides.md`
8. `docs/platform_integration_research_roadmap.md`
9. `docs/imports_data_quality.md`
10. `docs/ai_assistant.md`
11. `docs/v1_intelligence_migration.md`
12. `docs/sizebay_benchmark.md`
13. `docs/sizebay_zak_hyper_benchmark.md`
14. `docs/sizebay_vs_provador_virtual_comparison.html`
15. `docs/data_learning_lgpd_outliers.md`
16. `docs/analytics_admin.md`
17. `docs/user_access_permissions.md`
18. `docs/portal_ui_guidelines.md`
19. `docs/transactional_email_automation.md`
20. `docs/hardening_lgpd_observability.md`
21. `docs/go_live_cutover.md`
22. `docs/bigshop_integration.md`
23. `docs/bigshop_model3_pro_widget.md`
24. `docs/bigshop_one_click_contract.md`
25. `docs/commercial_pilot_package.md`
26. `docs/deploy_runbook.md`
27. `docs/sprint_governance.md`
28. `docs/roadmap_sprints.md`
29. `docs/intelligent_sizing_roadmap.md`
30. `docs/product_backlog.md`
31. `docs/security_compliance.md`
32. `docs/credentials.local.md`, somente quando a sprint envolver produção, banco, SMTP, deploy, IA ou integrações.

Nenhuma sprint deve começar sem essa releitura. Depois de executar uma sprint, é obrigatório fazer commit, push, acompanhar GitHub Actions/deploy até o status final e somente então passar para a próxima sprint.

Todo commit criado para uma sprint deve ter o título iniciado exatamente com `Sprint <numero> - `. Exemplo: `Sprint 86 - Atualiza governança comercial`.

## Fontes estudadas

- `D:\Projetos\bigshop\bigshop_helpdesk`
- `D:\Projetos\marcahora_novo`
- `D:\Projetos\bigshop360`
- `D:\Projetos\provadorvirtual_v1`
- `D:\Projetos\bigbangshop2.0`
- `D:\Projetos\bigshop\172.16.151.5`
- Documentação pública: `https://documenter.getpostman.com/view/4253101/2s93sdYrsi`
- Documentação pública Sizebay: `https://docs.sizebay.com/`
- Portal Sizebay do cliente Zak, estudado somente em leitura, registrado em `docs/sizebay_zak_hyper_benchmark.md`
- Comparativo Sizebay x Provador Virtual registrado em `docs/sizebay_vs_provador_virtual_comparison.html`
- Galeria pública de botões Sizebay: `https://sizebay-buttons-gallery.vercel.app/`
- Captura tecnica pública Zak/Sizebay em `https://www.zak.com.br/`

## Decisões iniciais

- Backend: Laravel 11 ou superior, PHP 8.2+, Sanctum, API REST versionada em `/api/v1`.
- Frontend: Vue 3, TypeScript, Vite, Pinia, Vue Router.
- Banco: MySQL/MariaDB com `utf8mb4` e `utf8mb4_unicode_ci`.
- Deploy: GitHub Actions via SSH para HostGator/opentshost.
- Publicação inicial: `https://provadorvirtual.online/provadorvirtual_v2/`, preservando o v1.
- Nome comercial: Provador Virtual, sem expor v1/v2 ao cliente final.
- Widget: SDK JavaScript universal e isolado, com snippet simples por plataforma.
- BigShop: integração nativa de um clique como objetivo prioritario, usando a API V3 e/ou encaixe direto no front da BigShop.

## Observação sobre segredos

`docs/credentials.local.md` existe apenas para referência local e esta no `.gitignore`. Não commitar esse arquivo.
