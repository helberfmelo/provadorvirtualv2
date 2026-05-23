# Migracao da Inteligencia do v1

Atualizado em: 2026-05-23

Este documento registra o que foi encontrado no `D:\Projetos\provadorvirtual_v1` e como o v2 deve aproveitar o conceito sem copiar as limitacoes tecnicas do v1.

## Arquivos estudados

- `D:\Projetos\provadorvirtual_v1\.env`
- `D:\Projetos\provadorvirtual_v1\includes\gemini-ai.php`
- `D:\Projetos\provadorvirtual_v1\includes\gemini-ai.example.php`
- `D:\Projetos\provadorvirtual_v1\default_measurement_tables_data.json`
- `D:\Projetos\provadorvirtual_v1\admin\ajax_get_default_table.php`
- `D:\Projetos\provadorvirtual_v1\admin\ajax_ocr_table.php`
- `D:\Projetos\provadorvirtual_v1\admin\table_new.php`
- `D:\Projetos\provadorvirtual_v1\widget\widget.js`

## Credencial de IA

Foi encontrada somente `GEMINI_API_KEY` no `.env` do v1.

A chave foi copiada para `docs/credentials.local.md`, que esta no `.gitignore`. O valor nao deve aparecer em nenhum arquivo versionado, log de deploy, issue, PR ou resposta publica.

Configuracao local recomendada para ativar o provider externo no v2:

```env
AI_PROVIDER=gemini
AI_MODEL=gemini-2.0-flash
GEMINI_API_KEY=valor_apenas_no_arquivo_local_ou_secret
```

Quando for ativar em producao, cadastrar `GEMINI_API_KEY` no GitHub Actions/ambiente remoto e ajustar `PRODUCTION_ENV`, sem commitar o valor.

## Conceitos uteis do v1

### Catalogo padrao de medidas

`default_measurement_tables_data.json` tem modelos por genero e tipo de produto:

- Masculino: Camiseta, Calca Jeans, Camisa Social, Bermuda, Jaqueta.
- Feminino: Camiseta/Blusa, Calca Jeans, Vestido, Saia, Sutia.
- Unissex: Camiseta, Moletom.
- Infantil: Camiseta Infantil, Calca Infantil, Body Bebe.
- Outro: Calcado.

Cada modelo inclui campos de medida, tamanhos, altura/peso/idade recomendados e compatibilidade com formato corporal. O v2 deve importar esse conceito para uma base canonica, com normalizacao de nomes e unidades.

Status no v2: o arquivo foi versionado em `backend/database/data/default_measurement_tables_data.json` e normalizado por `App\Services\Measurement\StandardMeasurementCatalog`. A API `/api/v1/measurement-templates` agora entrega os modelos do v1 como templates inteligentes para a tela de criacao/edicao de tabelas.

### Modelo padrao com fallback de IA

`ajax_get_default_table.php` primeiro tenta buscar `standard_models` por genero/tipo de peca. Se nao encontra, chama Gemini para gerar um modelo. O v2 deve manter essa ordem:

1. catalogo interno validado;
2. historico da marca/lojista quando existir;
3. sugestao por IA;
4. revisao obrigatoria pelo lojista antes de ativar.

### OCR de tabela

`ajax_ocr_table.php` envia imagem para Gemini e espera JSON com `campos_medida` e `tamanhos`. No v2, OCR deve:

- aceitar imagem, PDF leve, CSV e texto colado;
- retornar rascunho;
- destacar confianca por campo;
- exigir revisao antes de salvar;
- registrar custo/uso em `ai_usage_logs`;
- nunca ativar tabela automaticamente.

### Cadastro de tabela pelo lojista

`table_new.php` tinha fluxo de escolha por genero/tipo, modelo padrao, IA/OCR e edicao manual. O v2 deve transformar isso em um wizard simples:

1. escolher publico e categoria;
2. escolher origem: modelo pronto, IA por descricao, OCR/imagem, CSV/XML ou manual;
3. revisar tabela em grade editavel;
4. validar lacunas e medidas fora da curva;
5. vincular a produtos/variacoes;
6. publicar.

### Widget gamificado

`widget/widget.js` tinha etapas progressivas:

- altura, peso e idade;
- genero e formato corporal;
- medidas detalhadas por tipo de produto;
- barra de precisao;
- mensagens de incentivo;
- confete em 100%;
- feedback da recomendacao;
- configuracao dinamica por produto.

O v2 ja tem widget universal, mas as proximas sprints devem recuperar esse fluxo com mais fluidez, acessibilidade e persistencia.

## Melhorias obrigatorias para o v2

### Experiencia do consumidor

- Reconhecer consumidor anonimo por cookie/localStorage com identificador proprio do v2.
- Reutilizar medidas preenchidas anteriormente quando houver consentimento.
- Mostrar mensagem clara quando a recomendacao usa dados anteriores.
- Permitir editar medidas em modal, sem reiniciar a compra.
- Permitir multiplos perfis quando o usuario for conhecido/logado.
- Manter recomendacao inicial rapida com altura/peso/idade e refinar quando o usuario fornecer mais dados.
- Separar perfil anonimo, perfil cadastrado e perfil importado da loja.

### Experiencia do lojista

- Comecar por modelos prontos e sugestoes assistidas, nao por tabela vazia.
- Permitir IA gerar tabela por tipo de produto, publico, marca, modelagem e imagem.
- Permitir OCR de tabelas de medidas de fornecedores.
- Validar outliers antes de salvar.
- Oferecer base de dados universal como sugestao, sempre editavel.
- Mostrar lacunas de produtos sem tabela e produtos com tabela fraca.

### Dados e aprendizado

- Salvar snapshots anonimizados de recomendacao, medidas, tamanho indicado, tamanho escolhido, feedback e eventos de compra/devolucao quando existirem.
- Marcar anomalias: medidas improvaveis, escolha muito distante da recomendacao, retorno contraditorio, tabela de lojista muito fora do padrao.
- Usar dados anomalos para analise, mas nao para atualizar diretamente a base inteligente.
- Promover dados para aprendizado apenas quando houver volume e consistencia por coorte.

## Impacto tecnico esperado

Campos canonicos que o v2 deve suportar nas proximas sprints:

- `height`, `weight`, `age`
- `chest`, `bust`, `under_bust`
- `waist`, `hip`
- `shoulder`, `sleeve`, `biceps`, `wrist`, `fist`
- `neck`
- `length`, `inside_leg`, `thigh`
- `foot_length`, `foot_width`
- `body_shape_chest`, `body_shape_waist`, `body_shape_hip`
- `fit_preference`

As tabelas atuais do v2 ja aceitam `metadata`, mas o schema e as telas ainda precisam evoluir para tratar esses campos como parte do contrato principal.

## Pendencias

- Ativar provider Gemini no backend do v2 usando a chave ja copiada localmente.
- Evoluir o catalogo importado para salvar campos extras como idade, formato corporal, manga, entrepernas e pe em `metadata`.
- Definir imagens/ilustracoes proprias para formatos corporais do widget.
- Decidir se o v2 tambem tera OpenAI como provider alternativo para OCR e geracao.
- Cadastrar `GEMINI_API_KEY` em producao quando for liberar OCR real.
