# Aprendizado de Dados, LGPD e Outliers

Atualizado em: 2026-05-23

Objetivo: definir como o v2 deve usar dados de consumidores e lojistas para ficar mais inteligente sem transformar excecoes em regra e sem violar privacidade.

## Principios

- O consumidor deve entender quando seus dados anteriores estao sendo usados.
- O consumidor deve conseguir editar ou apagar dados pessoais quando aplicavel.
- Dados anonimos e identificadores tecnicos devem ser pseudonimizados.
- Dados brutos sensiveis nao devem ir para logs.
- IA pode sugerir, mas ativacao de tabela exige revisao humana.
- Aprendizado automatico deve depender de volume, consistencia e sinais de qualidade.
- Outliers devem ser guardados para investigacao, nao aplicados diretamente.

## Dados do consumidor

Dados que podem existir no perfil anonimo ou conhecido:

- altura, peso e idade;
- genero informado;
- formato corporal;
- medidas detalhadas;
- preferencia de caimento;
- tamanho recomendado;
- tamanho escolhido;
- feedback de utilidade;
- eventos de carrinho/pedido;
- devolucao e motivo, se integrado;
- versao do perfil;
- origem do dado: widget, conta, importacao, suporte, IA.

## Perfil anonimo

O widget deve criar um identificador anonimo proprio, armazenado em cookie/localStorage.

Regras:

- nao salvar nome, email, telefone ou documento no perfil anonimo;
- manter data de criacao e ultimo uso;
- permitir reset no widget;
- expirar ou anonimizar conforme politica de retencao;
- associar ao usuario cadastrado somente com login/consentimento.

## Perfil conhecido

Quando houver usuario cadastrado:

- salvar multiplos perfis de medidas;
- permitir nome amigavel do perfil;
- manter historico de alteracoes;
- mostrar que a recomendacao foi baseada em dados salvos;
- permitir edicao em modal;
- permitir exclusao/anonimizacao.

## Dados do lojista

Dados usados para melhorar tabelas:

- tabelas criadas manualmente;
- tabelas importadas de CSV/XML;
- tabelas sugeridas por IA;
- imagens/tabelas OCR, somente quando permitido;
- vinculacao produto/tabela;
- categorias, marcas, modelagem e publico;
- feedback agregado de recomendacoes por produto;
- devolucoes por tamanho.

Regras:

- IA nao ativa tabela sozinha.
- Tabelas muito fora do padrao precisam alerta.
- Bases de marcas devem ter origem e confianca.
- Dados de uma loja nao devem vazar para outra loja como informacao identificavel.

## Deteccao de outliers

### Medidas do consumidor

Marcar como anomalia quando:

- altura, peso ou idade estiverem fora de faixas plausiveis;
- relacao altura/peso estiver extremamente fora da coorte;
- medida detalhada conflitar fortemente com altura/peso;
- usuario alterar para tamanho muito distante sem feedback consistente;
- perfil mudar muitas vezes em curto intervalo;
- mesmo identificador gerar perfis incompativeis repetidamente.

### Recomendacao versus escolha

Nao usar diretamente como aprendizado quando:

- tamanho escolhido esta 2+ grades distante do recomendado;
- medidas do usuario estao incompletas;
- produto nao tem tabela confiavel;
- compra foi feita para presente ou terceiro;
- houve devolucao por motivo desconhecido;
- usuario informou feedback negativo mas nao comprou nem devolveu.

Esses casos entram como `learning_status=needs_review` ou `learning_status=anomaly`.

### Tabelas do lojista

Marcar tabela como suspeita quando:

- medidas nao crescem de forma monotona entre tamanhos;
- saltos entre tamanhos sao muito altos ou muito baixos;
- categoria nao combina com campos usados;
- tabela difere muito do catalogo universal sem justificativa de modelagem;
- OCR tem baixa confianca;
- medidas de produto foram confundidas com medidas de corpo sem marcacao.

## Promocao para aprendizado

Um evento so entra na base inteligente quando atender criterios minimos:

- produto tem tabela ativa e versionada;
- usuario tem perfil minimamente confiavel;
- recomendacao foi gerada por motor atual;
- existe feedback, compra, nao devolucao ou devolucao classificada;
- nao foi marcado como outlier critico;
- ha volume suficiente na coorte.

Sugestao inicial de pesos:

| Sinal | Peso |
| --- | ---: |
| feedback positivo sem compra | 1 |
| compra sem devolucao apos janela minima | 3 |
| devolucao por pequeno/grande | 4 |
| feedback negativo sem contexto | 0.5 |
| escolha muito distante da recomendacao | 0 ate revisao |

Capar contribuicao por usuario/perfil para evitar que uma pessoa distorca a base.

## Estrutura futura sugerida

Tabelas novas ou evolucao de tabelas atuais:

- `shopper_profiles`
- `shopper_profile_versions`
- `recommendation_events`
- `recommendation_learning_signals`
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
- recalcular em tempo real apos edicao.
- permitir voltar ao resultado sem perder contexto.

Quando nao houver dados:

- pedir altura/peso/idade primeiro;
- mostrar precisao basica;
- convidar a aumentar precisao com formato corporal e medidas detalhadas;
- salvar somente com consentimento operacional claro.

## Pendencias

- Definir texto final de consentimento no widget.
- Definir prazo de retencao de perfil anonimo.
- Implementar comando de exclusao/anonimizacao de perfis.
- Criar score de qualidade de tabela.
- Criar relatorio de outliers para o lojista.
- Integrar pedidos/devolucoes por plataforma.
