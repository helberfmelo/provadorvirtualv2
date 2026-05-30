# Aprendizado de Dados, LGPD e Outliers

Atualizado em: 2026-05-30

Objetivo: definir como o v2 deve usar dados de consumidores e lojistas para ficar mais inteligente sem transformar excecoes em regra e sem violar privacidade.

Status Sprint 36: implementada e publicada no run `26339824157` a primeira base operacional com `shopper_profiles`, `recommendation_learning_events`, consentimento no widget, esquecimento por token local, sinais de feedback/compra/devolucao/troca e `outlier_score` antes de qualquer uso estatístico.

Status Sprint 115: os sinais comerciais passam a carregar contexto seguro de tamanho comprado/devolvido, motivo de devolução, plataforma, quantidade, valor e data do evento. Referências de pedido são persistidas somente como hash. A base gera insights por tabela para Analytics e Assistente de IA, sempre com revisão humana obrigatória.

Status Sprint 152: o analytics passa a expor `learning_pipeline` com padrões por produto/tabela/categoria/marca/modelagem, candidatas estáveis para IA, fila de revisão manual e sugestões explicadas de ajuste. A anonimização também passa a usar janelas separadas para dados do widget, comentários, perfis e payloads de aprendizado.

## Principios

- O consumidor deve entender quando seus dados anteriores estão sendo usados.
- O consumidor deve conseguir editar ou apagar dados pessoais quando aplicável.
- Dados anônimos e identificadores tecnicos devem ser pseudonimizados.
- Dados brutos sensíveis não devem ir para logs.
- IA pode sugerir, mas ativação de tabela exige revisão humana.
- Aprendizado automático deve depender de volume, consistencia e sinais de qualidade.
- Outliers devem ser guardados para investigacao, não aplicados diretamente.

## Dados do consumidor

Dados que podem existir no perfil anônimo ou conhecido:

- altura, peso e idade;
- gênero informado;
- formato corporal;
- medidas detalhadas;
- preferência de caimento;
- tamanho recomendado;
- tamanho escolhido;
- feedback de utilidade;
- eventos de carrinho/pedido;
- devolucao e motivo, se integrado;
- versao do perfil;
- origem do dado: widget, conta, importacao, suporte, IA.

## Perfil anônimo

O widget cria um identificador anônimo próprio em `localStorage` somente quando o consumidor consente salvar medidas neste navegador.

Regras:

- não salvar nome, email, telefone ou documento no perfil anônimo;
- manter data de criação e ultimo uso;
- permitir reset no widget, invalidando o perfil remoto por `profile_id` + token local;
- expirar ou anonimizar conforme política de retenção;
- associar ao usuário cadastrado somente com login/consentimento.

## Perfil conhecido

Quando houver usuário cadastrado:

- salvar multiplos perfis de medidas;
- permitir nome amigável do perfil;
- manter histórico de alteracoes;
- mostrar que a recomendação foi baseada em dados salvos;
- permitir edição em modal;
- permitir exclusao/anonimização.

## Dados do lojista

Dados usados para melhorar tabelas:

- tabelas criadas manualmente;
- tabelas importadas de CSV/XML;
- tabelas sugeridas por IA;
- imagens/tabelas OCR, somente quando permitido;
- vinculacao produto/tabela;
- categorias, marcas, modelagem e público;
- feedback agregado de recomendações por produto;
- devolucoes por tamanho.

Regras:

- IA não ativa tabela sozinha.
- Tabelas muito fora do padrão precisam alerta.
- Bases de marcas devem ter origem e confiança.
- Dados de uma loja não devem vazar para outra loja como informação identificavel.

## Deteccao de outliers

### Medidas do consumidor

Marcar como anomalia quando:

- altura, peso ou idade estiverem fora de faixas plausiveis;
- relacao altura/peso estiver extremamente fora da coorte;
- medida detalhada conflitar fortemente com altura/peso;
- usuário alterar para tamanho muito distante sem feedback consistente;
- perfil mudar muitas vezes em curto intervalo;
- mesmo identificador gerar perfis incompativeis repetidamente.

### Recomendação versus escolha

Não usar diretamente como aprendizado quando:

- tamanho escolhido esta 2+ grades distante do recomendado;
- medidas do usuário estão incompletas;
- produto não tem tabela confiavel;
- compra foi feita para presente ou terceiro;
- houve devolucao por motivo desconhecido;
- usuário informou feedback negativo mas não comprou nem devolveu.

Esses casos entram como `learning_status=review` ou `learning_status=blocked_outlier`.

### Tabelas do lojista

Marcar tabela como suspeita quando:

- medidas não crescem de forma monotona entre tamanhos;
- saltos entre tamanhos são muito altos ou muito baixos;
- categoria não combina com campos usados;
- tabela difere muito do catálogo universal sem justificativa de modelagem;
- OCR tem baixa confiança;
- medidas de produto foram confundidas com medidas de corpo sem marcacao.

## Promocao para aprendizado

Um evento so entra na base inteligente quando atender criterios minimos:

- produto tem tabela ativa e versionada;
- usuário tem perfil minimamente confiavel;
- recomendação foi gerada por motor atual;
- existe feedback, compra, não devolucao ou devolucao classificada;
- não foi marcado como outlier critico;
- ha volume suficiente na coorte.

Sugestão inicial de pesos:

| Sinal | Peso |
| --- | ---: |
| feedback positivo sem compra | 1 |
| compra sem devolucao após janela minima | 3 |
| devolucao por pequeno/grande | 4 |
| feedback negativo sem contexto | 0.5 |
| escolha muito distante da recomendação | 0 até revisão |

Capar contribuicao por usuário/perfil para evitar que uma pessoa distorca a base.

## Estrutura futura sugerida

Tabelas novas ou evolucao de tabelas atuais:

- `shopper_profiles` - implementada na Sprint 36;
- `shopper_profile_versions`
- `recommendation_events`
- `recommendation_learning_events` - implementada na Sprint 36;
- `merchant_table_quality_checks`
- `measurement_catalog_sources`
- `learning_cohorts`

Campos importantes:

- `anonymous_id_hash`
- `known_user_id`
- `profile_version`
- `consent_scope`
- `measurement_snapshot`
- `recommendation_snapshot`
- `chosen_size`
- `ordered_size`
- `return_reason`
- `source_confidence`
- `outlier_score`
- `learning_status`

## Experiencia no widget

Quando houver perfil anterior:

- mostrar mensagem curta: "Usamos suas medidas salvas para indicar este tamanho."
- oferecer link "Alterar medidas".
- abrir modal nas etapas do widget com valores preenchidos.
- recalcular em tempo real após edição.
- permitir voltar ao resultado sem perder contexto.

Quando não houver dados:

- pedir altura/peso/idade primeiro;
- mostrar precisao basica;
- convidar a aumentar precisao com formato corporal e medidas detalhadas;
- salvar somente com consentimento operacional claro.

## Payload bruto do widget

Status Sprint 66: além das medidas normalizadas usadas pelo motor, o widget v2 salva a jornada bruta em `recommendation_logs.raw_widget_payload`.

O payload permitido inclui:

- versão e origem do widget, por exemplo `v2_sprint_67` e `widget_v2_staged`;
- etapas concluídas;
- precisão calculada no front;
- identidade técnica do produto/loja/plataforma;
- tabela de medidas usada;
- medidas brutas informadas no fluxo, como altura, peso, idade, gênero, formato corporal, caimento, busto/tórax, cintura, quadril, comprimento e ombro.

Esse payload não deve conter nome, e-mail, telefone, documento, endereço ou qualquer identificador pessoal direto. O objetivo é preservar contexto operacional para auditoria, aprendizado e melhoria de UX, sem transformar o widget em cadastro de consumidor.

`pv:privacy-anonymize` limpa `raw_widget_payload` junto com `input_measurements`, `score_breakdown`, perfil da sessão e comentários antigos de feedback.

## Pendências

- Revisar texto final de consentimento no widget com juridico/comercial.
- Confirmar se retenção de perfil anônimo deve ficar em 180 dias ou ter prazo por contrato.
- Criar score de qualidade de tabela.
- Evoluir relatório de outliers para drill-down por coorte/produto.
- Integrar pedidos/devolucoes automaticamente por plataforma e oferecer importação CSV assistida.
