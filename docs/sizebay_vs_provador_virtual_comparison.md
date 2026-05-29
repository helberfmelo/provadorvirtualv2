# Comparativo Sizebay x Provador Virtual - Portal do Cliente Zak

Atualizado em: 2026-05-29

## Escopo

Este documento compara o portal do cliente da Sizebay, acessado em modo somente leitura para a loja Zak, com o portal da empresa e o SaaS Admin do Provador Virtual.

Foram consideradas:

- telas recapturadas em 2026-05-29: Painel, Produtos, cadastro de produto, Guia de Medidas, Tabelas, Modelagens, Marcas, Categorias, Marcas Sizebay e Categorias Sizebay;
- telas ja estudadas na captura autenticada anterior: Configuracoes, Fontes de Dados, Sincronizacao, Regras de Importacao, Personalizacao de Botoes, Personalizacao do Provador, Relatorios, Pedidos, Devolucoes e Cobranca;
- estado atual do Provador Virtual apos as sprints de BigShop, sincronizacao, regras, tabelas, modelagens, personalizacao do widget, troca protegida de integracao e melhorias de interface.

Nenhuma alteracao foi feita na Sizebay. Nenhuma credencial, token, sessao ou segredo foi registrado neste documento.

## Como Ler

O comparativo foi dividido por area para evitar uma tabela unica muito larga e dificil de navegar. Cada tabela mantem as quatro colunas solicitadas:

| Coluna | Conteudo |
|---|---|
| Item comparado | O recurso, tela ou comportamento analisado. |
| Sizebay | Como a Sizebay apresenta ou resolve o item. |
| Provador Virtual | Como nosso sistema apresenta ou resolve o item hoje. |
| Quem esta melhor e por que | Avaliacao objetiva, incluindo impacto quando uma das plataformas nao possui o recurso. |

Termos foram ajustados para uma linguagem mais amigavel:

| Termo tecnico | Termo usado neste documento |
|---|---|
| Dashboard | Painel |
| Sync | Sincronizacao |
| Dry-run | Simulacao de importacao |
| Readiness | Prontidao |
| VFR | Provador Virtual |
| SFA | Assistente de tamanho |
| Data sources | Fontes de dados |
| Billing | Cobranca |
| Orders / Returns | Pedidos / Devolucoes |

## Resumo Executivo

A Sizebay ainda esta mais madura em operacao de catalogo, taxonomias, marcas, categorias, relatorios de uso, pedidos e devolucoes. Ela transmite muita organizacao porque transforma problemas complexos em telas curtas e objetivas.

O Provador Virtual ja esta competitivo ou melhor em pontos estrategicos: operacao BigShop, governanca comercial, troca protegida de integracao, SaaS Admin, assistente de IA, go-live e personalizacao recente dos botoes.

O caminho mais importante agora e transformar a profundidade tecnica que ja temos em experiencia simples: mostrar saude do catalogo, erros acionaveis, cobertura por produto, taxonomias normalizadas e relatorios que provem resultado.

## 1. Navegacao e Experiencia Geral

| Item comparado | Sizebay | Provador Virtual | Quem esta melhor e por que |
|---|---|---|---|
| Clareza visual | Telas limpas, poucos blocos por pagina e textos curtos. | Melhorou bastante, mas ainda concentra muitas capacidades por tela. | Sizebay. A vantagem dela e a sensacao de simplicidade. O Provador deve continuar usando uma coluna, secoes curtas e menos texto fixo. |
| Menu do portal | Menu curto: Painel, Produtos, Categorias, Marcas, Guia de Medidas, Pedidos, Relatorios, Cobranca e Suporte. | Menu mais completo: Painel, Produtos, Tabelas, Modelagens, IA, Importacoes, Regras, Provador, Integracoes, Sincronizacao, Analiticos, Go-live e Usuarios. | Empate com risco para o Provador. Nosso menu e mais completo, mas pode parecer pesado se nao for agrupado por jornada. |
| Cabecalho e identidade | Cabecalho simples e suporte visivel. | Cabecalho separa Portal da Empresa e SaaS Admin; logo respeita o contexto. | Provador em estrutura SaaS; Sizebay em suporte visivel. Falta ao Provador um caminho de ajuda mais evidente. |
| Linguagem da interface | Usa muitos termos em ingles no portal acessado. | Interface em portugues, com foco no lojista brasileiro. | Provador. E vantagem local, desde que evite termos tecnicos desnecessarios. |
| Ajuda e suporte | Suporte e chat aparecem de forma clara. | Ainda depende mais de documentacao interna e operacao do SaaS. | Sizebay. Falta ao Provador ajuda contextual dentro do portal do cliente. |

## 2. Painel e Saude da Operacao

| Item comparado | Sizebay | Provador Virtual | Quem esta melhor e por que |
|---|---|---|---|
| Painel inicial | Mostra cobertura, produtos ativos e pendentes como centro da operacao. | Possui painel e atalhos, mas a cobertura do provador ainda pode ficar mais evidente. | Sizebay. O lojista entende rapidamente o que falta resolver. |
| Cobertura do catalogo | Deixa claro quantos produtos estao cobertos, ativos ou pendentes. | Tem dados em Produtos, Sincronizacao, Analiticos e Go-live, mas nao em um placar unico. | Sizebay. O Provador precisa de um placar de cobertura no Painel. |
| Pendencias operacionais | Produtos pendentes aparecem como filtro e estado importante. | Erros aparecem na sincronizacao e go-live, mas precisam virar fila priorizada. | Sizebay. Falta transformar problemas em proximas acoes. |
| Prontidao para publicar | A maturidade aparece pela cobertura e configuracoes. | Tem Go-live e validacoes dedicadas. | Provador. O checklist de go-live e um diferencial, mas deve puxar dados reais de cobertura e instalacao. |

## 3. Produtos e Vinculo com Tabelas

| Item comparado | Sizebay | Provador Virtual | Quem esta melhor e por que |
|---|---|---|---|
| Listagem de produtos | Abas por status: todos, pendentes, ativos e inativos. | Lista com busca, filtros e selecao em massa. | Empate. Sizebay e melhor em estado operacional; Provador e melhor em acao em massa. |
| Colunas da lista | Mostra produto, categoria, tabela, tamanhos, marca, faixa etaria e modelagem. | Mostra dados principais e permite vincular tabela em massa, mas pode expor mais atributos. | Sizebay. O Provador deve adicionar categoria, marca, faixa etaria, modelagem, tamanhos e status de prontidao. |
| Vinculo de tabela ao produto | A tabela aparece diretamente na coluna do produto. | Permite selecionar produtos e vincular tabela em massa. | Provador na acao; Sizebay na visualizacao. O melhor caminho e juntar os dois modelos. |
| Filtros | Filtros claros e botao para limpar filtros. | Busca e filtros existem; devem crescer para marca, categoria, genero, faixa etaria, modelagem e status. | Sizebay por enquanto. O Provador tem boa base, mas precisa filtros mais ricos. |
| Detalhe do produto | Cadastro mostra informacoes de integracao, imagem, ativacao do provador e tamanhos. | Produto e mais orientado por importacao, regras e vinculos. | Sizebay. Falta ao Provador uma tela de detalhe com origem dos dados, diagnostico e ajustes manuais. |
| Ativar provador por produto | Controle explicito para ativar ou desativar o provador no produto. | Pode controlar por configuracao, regra e vinculo, mas precisa ficar claro por produto. | Sizebay. Se o Provador nao expuser esse controle, o lojista perde autonomia fina. |
| Origem dos dados | Separa melhor dados de integracao e campos editaveis. | Tem importacao e regras, mas deve mostrar se o dado veio da API, feed, regra, IA ou ajuste manual. | Sizebay. Mostrar origem do dado e essencial para confianca. |

## 4. Tabelas, Medidas e Modelagens

| Item comparado | Sizebay | Provador Virtual | Quem esta melhor e por que |
|---|---|---|---|
| Lista de tabelas | Possui exportar, importar, criar e filtrar. | Possui criacao e edicao de tabelas com tipos avancados. | Empate. Sizebay e melhor em importacao/exportacao visivel; Provador tem boa estrutura de dados. |
| Medidas do corpo | Possui aba propria para medidas do corpo. | Suporta medidas do corpo. | Empate. O Provador acompanha a logica principal. |
| Medidas da peca | Possui aba propria para medidas da peca. | Suporta medidas da peca. | Empate. |
| Sistema de tamanhos | Possui aba para sistema de tamanho. | Suporta sistema de tamanho. | Empate. |
| Faixas de medida | Permite mostrar medidas como intervalo. | Suporta intervalos de medida. | Empate. |
| Medidas compostas | Permite medidas compostas. | Suporta medidas compostas. | Empate. |
| Variacao personalizada | Possui campo especifico para variacoes personalizadas. | A estrutura existe, mas a interface pode ser mais clara. | Sizebay levemente. O Provador deve usar o termo "variacao personalizada" e explicar quando usar. |
| Observacoes | Possui observacoes na tabela. | Deve padronizar observacoes por tabela, tamanho e medida. | Sizebay. Observacoes ajudam muito no suporte e na revisao. |
| Desativar provador por tabela | Permite desativar o provador em uma tabela/modelagem. | Ainda precisa deixar esse controle mais evidente. | Sizebay. E importante para produtos que devem exibir apenas tabela de medidas. |
| Modelagens | Possui area dedicada de modelagens. | Possui cadastro de modelagens/perfis de caimento. | Empate. O Provador deve conectar modelagem a diagnosticos e sugestoes da IA. |
| Erros de modelagem | Mostra erro quando modelagem nao e encontrada. | Mostra erros por produto na sincronizacao. | Empate parcial. O Provador fica melhor se sugerir a correcao automaticamente. |

## 5. Marcas, Categorias e Taxonomia

| Item comparado | Sizebay | Provador Virtual | Quem esta melhor e por que |
|---|---|---|---|
| Marcas do lojista | Tela propria para marcas, com criar, importar, exportar e associar. | Usa marca em regras e importacao, mas ainda nao tem tela forte de mapeamento. | Sizebay. E uma lacuna relevante para catalogos grandes. |
| Marca normalizada | Associa a marca local a uma marca padronizada da Sizebay. | Ainda nao possui catalogo global de marcas equivalente. | Sizebay. O Provador precisa de marca local -> marca normalizada. |
| Categorias do lojista | Tela propria para categorias, tipo e importacao/exportacao. | Usa categoria em produtos e regras, mas precisa tela dedicada. | Sizebay. Sem mapeamento dedicado, a automacao fica menos confiavel. |
| Categoria normalizada | Possui catalogo de categorias e subcategorias Sizebay. | Ainda nao possui taxonomia propria madura. | Sizebay. Esta e uma das maiores diferencas de maturidade. |
| Traducoes de categorias | Possui traducoes no catalogo global. | Foco atual e portugues brasileiro. | Sizebay para operacao internacional. Para o Provador, nao e urgente, mas pode virar vantagem futura. |
| Importar e exportar cadastros | Exporta e importa tabelas, marcas e categorias. | Tem importacao por plataforma/feed, mas deve ampliar importacao/exportacao manual por entidade. | Sizebay. O Provador deve oferecer CSV/XLSX para produtos, tabelas, marcas e categorias. |

## 6. Integracoes, Fontes de Dados e Sincronizacao

| Item comparado | Sizebay | Provador Virtual | Quem esta melhor e por que |
|---|---|---|---|
| Escolha de plataforma | Adapta configuracoes conforme fonte de dados e plataforma. | Plataforma vem do SaaS, checkout ou portal; tela de Integracoes se adapta. | Empate. Sizebay e mais madura nas instrucoes; Provador tem melhor governanca comercial. |
| Integracao BigShop | Nao possui o mesmo foco comercial BigShop do Provador. | Tem cadastro BigShop, simulacao de importacao, sincronizacao, regras e troca protegida. | Provador. E diferencial competitivo claro. |
| Beneficio BigShop | Nao identificado fluxo equivalente. | Lojas BigShop com desconto precisam solicitar troca de integracao e aceitar termos. | Provador. Protege a regra comercial e cria auditoria. |
| Troca de integracao | Parece tratar como configuracao operacional. | Permite troca direta para nao BigShop e troca protegida para BigShop com beneficio. | Provador. Mais adequado ao modelo comercial do produto. |
| Fontes de dados | Mostra feed XML, API, Google Shopping, sincronizacao e plataformas. | Mostra URL da API, feed, token, segredo de webhook, validacao e snippet. | Empate. O Provador precisa instrucoes ainda mais especificas por plataforma. |
| Feed XML | Possui feed ativo e orientacoes de catalogo. | Possui URL do feed e sincronizacao XML/feed. | Empate. |
| API | Possui documentacao e uso maduro de API. | Possui campos e validacoes de API, token e webhook. | Sizebay. O Provador deve evoluir documentacao publica e exemplos por plataforma. |
| Webhook | Faz parte do ecossistema de fontes e rastreamento. | Exibe segredo de webhook e pode validar conexao. | Provador se entregar teste, logs e rotacao. |
| Validar instalacao | Orienta instalacao conforme plataforma. | Possui campo de URL e botao para validar instalacao. | Provador em acao direta. Falta mostrar diagnostico detalhado da pagina validada. |
| Local do botao na pagina | Permite configurar posicao antes, depois ou dentro de um seletor. | Ainda deve tornar seletor/ancora mais guiado. | Sizebay. O Provador deve adicionar teste visual de seletor CSS. |
| Google Tag Manager | Pode ser usado em cenarios de tag e rastreamento conforme implantacao. | Pode ser liberado como caminho opcional para script e eventos. | Sizebay se ja documenta melhor. Para o Provador, e util em lojas sem app/tema simples, mas nao deve substituir integracao nativa quando houver. |
| Historico de sincronizacao | Mostra historico, totais, inseridos, atualizados, desconhecidos e erros. | Possui eventos de simulacao, sincronizacao e feed. | Sizebay. O Provador deve padronizar contadores por execucao. |
| Erros por produto | Mostra produto, link, contexto e detalhes de tamanho. | Possui base de erros por produto. | Sizebay por enquanto. O Provador deve adicionar botoes de correcao: vincular tabela, criar modelagem, revisar categoria. |
| Simulacao antes de importar | Apresenta diagnosticos antes de consolidar dados. | Possui simulacao BigShop com paginacao, grades e extracao de tamanho. | Provador em BigShop. Precisa repetir o padrao para outras plataformas. |
| Regras de importacao | Usa logica visual "quando isso acontecer, faca aquilo". | Possui regras por categoria, marca, genero, faixa etaria, status e modelagem. | Empate. O Provador pode ficar melhor se mostrar o impacto da regra na simulacao. |

## 7. Personalizacao do Widget e do Provador

| Item comparado | Sizebay | Provador Virtual | Quem esta melhor e por que |
|---|---|---|---|
| Personalizacao de botoes | Possui pre-visualizacao para computador e celular, publicar e desfazer. | Possui 12 modelos, cores, icones, pre-visualizacao, publicar e desfazer. | Provador em variedade; Sizebay em acabamento. |
| Galeria de modelos | Possui modelos com animacoes sutis, incluindo cabide balancando. | Possui 12 opcoes em grade, icones de medida e animacao opcional do cabide. | Provador. Precisa manter o polimento visual no widget real. |
| Icones dos botoes | Usa icones coerentes com moda e medidas. | Usa catalogo com cabide, regua, fita metrica e outros icones. | Empate com vantagem para o Provador pela escolha do lojista. |
| Animacao | Animacoes pontuais e discretas. | Checkbox aparece quando o cabide e escolhido e anima o icone como pendurado. | Empate. A animacao do Provador deve continuar sutil. |
| Cores | Permite personalizacao com pre-visualizacao. | Permite cor de fundo e texto com pre-visualizacao. | Empate. |
| Pre-visualizacao | Mostra contexto de computador e celular. | Possui pre-visualizacao em modal e modos computador/celular. | Empate. |
| Customizacao do provador | Possui area separada para personalizar o provador. | Ainda deve separar melhor botoes, modal e experiencia completa do provador. | Sizebay. O Provador precisa de editor dedicado do modal/provador. |

## 8. Relatorios, Pedidos e Devolucoes

| Item comparado | Sizebay | Provador Virtual | Quem esta melhor e por que |
|---|---|---|---|
| Relatorio de uso | Mostra impressoes, recomendacoes, consultas de tabela e taxa de uso. | Analiticos ja olham vendas, devolucoes, trocas e tabelas. | Sizebay no funil do widget; Provador no potencial comercial. O ideal e unir os dois. |
| Uso por dispositivo | Separa computador e celular. | Ainda precisa segmentar relatorios por dispositivo. | Sizebay. Isso ajuda a entender comportamento real do comprador. |
| Ranking de produtos | Mostra produtos com mais uso/recomendacoes. | Pode cruzar produtos, tabelas e pedidos, mas precisa ranking claro. | Sizebay. O Provador deve listar produtos com maior uso, erro e devolucao. |
| Recomendacoes emitidas | Possui relatorio dedicado. | Deve criar relatorio especifico de recomendacoes do assistente de tamanho. | Sizebay. Sem isso, fica mais dificil provar valor. |
| Pedidos | Mostra pedidos com status, data, quantidade, valor e uso do assistente de tamanho. | Tem pedidos no SaaS, mas precisa levar a visao operacional para o portal da empresa. | Sizebay. E uma lacuna importante para aprendizado e prova de resultado. |
| Devolucoes | Possui importacao por arquivo e mapeamento de motivo/metodo. | Planeja usar pedidos, devolucoes e feedback para IA; precisa fluxo concreto. | Sizebay. O Provador deve implementar importacao e leitura de devolucoes. |
| Aprendizado com dados reais | Usa rastreamento e relatorios para melhorar recomendacoes. | Pode alimentar IA com pedidos, devolucoes e feedback. | Sizebay hoje; Provador tem maior potencial se fechar esse ciclo com IA explicavel. |

## 9. IA, Go-live, SaaS Admin e Governanca

| Item comparado | Sizebay | Provador Virtual | Quem esta melhor e por que |
|---|---|---|---|
| Assistente para o lojista | Nao foi identificado assistente de IA no portal Zak. | Possui Assistente IA para apoiar configuracao e tabelas. | Provador. Diferencial forte se alimentado por dados reais. |
| Sugestao de tabela | Estrutura de tabela e modelagem e madura, mas sem assistente generativo observado. | Pode sugerir tabelas com IA e base importada. | Provador em potencial. Precisa fluxo guiado com revisao humana. |
| Go-live | Opera por configuracoes e suporte. | Possui tela dedicada de Go-live e validacoes. | Provador. Deve conectar o checklist a cobertura, instalacao, sincronizacao e analiticos. |
| Usuarios | Nao apareceu como foco principal no portal cliente observado. | Possui Usuarios no portal e usuarios das empresas no SaaS. | Provador. |
| SaaS Admin | Nao foi analisado porque o acesso era ao portal do cliente. | Possui empresas, usuarios, checkout, pedidos, emails e solicitacoes. | Provador. E uma vantagem estrutural para operacao interna. |
| Cobranca | Possui menu de cobranca no portal. | Possui checkout e pedidos no SaaS Admin, mas precisa area clara no portal do cliente. | Sizebay para autonomia do cliente; Provador para controle interno. |
| Seletor de empresa para admin | Nao aplicavel no portal cliente observado. | Admin SaaS pode escolher empresa ao acessar o portal. | Provador pela funcionalidade, mas precisa manter a persistencia sempre confiavel. |
| Termos e governanca | Possui ecossistema de termos e politicas. | Possui termos, privacidade e termos de troca BigShop. | Provador no contexto brasileiro e BigShop, desde que mantenha aceite e auditoria. |
| Auditoria | Historicos aparecem principalmente em sincronizacao e operacao. | Tem eventos e solicitacoes; deve ampliar trilha de auditoria visivel. | Empate parcial. O Provador deve mostrar quem publicou widget, mudou integracao ou vinculou tabela. |

## Prioridades Recomendadas

| Prioridade | O que fazer | Por que importa |
|---|---|---|
| 1 | Criar Painel de Cobertura. | O lojista precisa ver produtos totais, cobertos, ativos, pendentes, sem tabela, sem modelagem, sem categoria e com erro. |
| 2 | Melhorar a listagem de produtos. | Incluir categoria, marca, faixa etaria, modelagem, tamanhos, tabela vinculada e prontidao. |
| 3 | Criar mapeamento de marcas e categorias. | Aumenta qualidade da importacao, das regras e das recomendacoes. |
| 4 | Criar taxonomia normalizada do Provador. | Aproxima o Provador da maturidade operacional da Sizebay. |
| 5 | Evoluir Sincronizacao para central de diagnostico. | Cada erro deve ter causa, produto afetado e acao sugerida. |
| 6 | Ampliar Analiticos com funil do widget. | Medir impressoes, cliques, recomendacoes, consultas de tabela, uso por dispositivo e ranking de produtos. |
| 7 | Implementar pedidos, devolucoes e feedback no portal. | Fecha o ciclo de aprendizado e prova impacto em troca/devolucao. |
| 8 | Criar base de conhecimento no portal. | Reduz suporte manual e deixa a experiencia mais proxima da Sizebay. |
| 9 | Adaptar Integracoes por plataforma com mais profundidade. | Cada plataforma deve ter campos, instrucoes, validacao e instalacao especificos. |
| 10 | Separar editor de botoes do editor do provador completo. | A Sizebay separa bem botao, aparencia e experiencia; o Provador deve fazer o mesmo. |
