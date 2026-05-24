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

A chave foi copiada para `docs/credentials.local.md`, que esta no `.gitignore`. O valor não deve aparecer em nenhum arquivo versionado, log de deploy, issue, PR ou resposta pública.

Configuração local recomendada para ativar o provider externo no v2:

```env
AI_PROVIDER=gemini
AI_MODEL=gemini-2.0-flash
GEMINI_API_KEY=valor_apenas_no_arquivo_local_ou_secret
```

Quando for ativar em produção, cadastrar `GEMINI_API_KEY` no GitHub Actions/ambiente remoto e ajustar `PRODUCTION_ENV`, sem commitar o valor.

## Conceitos uteis do v1

### Catálogo padrão de medidas

`default_measurement_tables_data.json` tem modelos por gênero e tipo de produto:

- Masculino: Camiseta, Calca Jeans, Camisa Social, Bermuda, Jaqueta.
- Feminino: Camiseta/Blusa, Calca Jeans, Vestido, Saia, Sutia.
- Unissex: Camiseta, Moletom.
- Infantil: Camiseta Infantil, Calca Infantil, Body Bebe.
- Outro: Calcado.

Cada modelo inclui campos de medida, tamanhos, altura/peso/idade recomendados e compatibilidade com formato corporal. O v2 deve importar esse conceito para uma base canonica, com normalizacao de nomes e unidades.

Status no v2: o arquivo foi versionado em `backend/database/data/default_measurement_tables_data.json` e normalizado por `App\Services\Measurement\StandardMeasurementCatalog`. A API `/api/v1/measurement-templates` agora entrega os modelos do v1 como templates inteligentes para a tela de criação/edição de tabelas.

### Modelo padrão com fallback de IA

`ajax_get_default_table.php` primeiro tenta buscar `standard_models` por gênero/tipo de peça. Se não encontra, chama Gemini para gerar um modelo. O v2 deve manter essa ordem:

1. catálogo interno validado;
2. histórico da marca/lojista quando existir;
3. sugestão por IA;
4. revisão obrigatória pelo lojista antes de ativar.

### OCR de tabela

`ajax_ocr_table.php` envia imagem para Gemini e espera JSON com `campos_medida` e `tamanhos`. No v2, OCR deve:

- aceitar imagem, PDF leve, CSV e texto colado;
- retornar rascunho;
- destacar confiança por campo;
- exigir revisão antes de salvar;
- registrar custo/uso em `ai_usage_logs`;
- nunca ativar tabela automaticamente.

### Cadastro de tabela pelo lojista

`table_new.php` tinha fluxo de escolha por gênero/tipo, modelo padrão, IA/OCR e edição manual. O v2 deve transformar isso em um wizard simples:

1. escolher público e categoria;
2. escolher origem: modelo pronto, IA por descrição, OCR/imagem, CSV/XML ou manual;
3. revisar tabela em grade editavel;
4. validar lacunas e medidas fora da curva;
5. vincular a produtos/variações;
6. publicar.

### Widget gamificado

`widget/widget.js` tinha etapas progressivas:

- altura, peso e idade;
- gênero e formato corporal;
- medidas detalhadas por tipo de produto;
- barra de precisao;
- mensagens de incentivo;
- confete em 100%;
- feedback da recomendação;
- configuração dinâmica por produto.

Status Sprint 66: o v2 recuperou esse fluxo no widget público `/widget/v1/provador-virtual.js`, refatorado para a identidade visual atual:

- drawer lateral no desktop e full-width no mobile;
- etapa 1 com altura, peso, idade opcional e consentimento;
- etapa 2 com gênero, formato corporal em cards e caimento desejado;
- etapa 3 com medidas detalhadas derivadas da tabela do produto;
- barra de precisão persistente no rodapé do drawer;
- recomendação disponível somente depois que o usuário chega visualmente à etapa de detalhes;
- confete próprio em CSS/JS ao chegar a 100% de precisão;
- resultado com tamanho, confiança e notas do motor;
- feedback final visível com sim/não, nota, tamanho escolhido e comentário.

O v2 não copiou a dependência externa de confete do v1. A animação agora é isolada no CSS do widget, sem bloquear a loja e sem carregar biblioteca adicional.

Status Sprint 67: reforçada a paridade comportamental com o v1. O usuário precisa passar visualmente por `Medidas`, `Corpo` e `Detalhes` antes de obter o resultado pelo rodapé; dados salvos no navegador não elevam a etapa 1 para 100% e não disparam confete ou recomendação direta.

### Teste minucioso do demo v1

Em 2026-05-24, a página `https://provadorvirtual.online/provadorvirtual_v1/demo.php` foi testada com Playwright:

- todos os links principais responderam `200`: home, como funciona, planos, demo, login, cadastro, privacidade, termos e WhatsApp;
- o botão `Qual o seu tamanho?` abriu o drawer do v1;
- etapa 1 coletou altura, peso e idade, atualizando a precisão;
- etapa 2 coletou gênero e formato corporal com cards ilustrados;
- etapa 3 mostrou medidas detalhadas por gênero/produto;
- ao preencher 100%, o v1 disparou confete e retornou recomendação de tamanho;
- o endpoint `widget/recomendar.php` gravou dados em `widget_user_data` e, quando solicitado, `recommendation_logs`;
- o endpoint `widget/salvar_feedback.php` gravou feedback por `recommendation_log_id`.

Evidências locais da inspeção foram salvas em `.tmp/v1-widget/` e não devem ser versionadas.

## Melhorias obrigatorias para o v2

### Experiencia do consumidor

- Reconhecer consumidor anônimo por cookie/localStorage com identificador próprio do v2.
- Reutilizar medidas preenchidas anteriormente quando houver consentimento.
- Mostrar mensagem clara quando a recomendação usa dados anteriores.
- Permitir editar medidas em modal, sem reiniciar a compra.
- Permitir multiplos perfis quando o usuário for conhecido/logado.
- Manter recomendação inicial rapida com altura/peso/idade e refinar quando o usuário fornecer mais dados.
- Separar perfil anônimo, perfil cadastrado e perfil importado da loja.

### Experiencia do lojista

- Comecar por modelos prontos e sugestoes assistidas, não por tabela vazia.
- Permitir IA gerar tabela por tipo de produto, público, marca, modelagem e imagem.
- Permitir OCR de tabelas de medidas de fornecedores.
- Validar outliers antes de salvar.
- Oferecer base de dados universal como sugestão, sempre editavel.
- Mostrar lacunas de produtos sem tabela e produtos com tabela fraca.

### Dados e aprendizado

- Salvar snapshots anonimizados de recomendação, medidas, tamanho indicado, tamanho escolhido, feedback e eventos de compra/devolucao quando existirem.
- Marcar anomalias: medidas improvaveis, escolha muito distante da recomendação, retorno contraditorio, tabela de lojista muito fora do padrão.
- Usar dados anomalos para análise, mas não para atualizar diretamente a base inteligente.
- Promover dados para aprendizado apenas quando houver volume e consistencia por coorte.

## Impacto técnico esperado

Campos canonicos que o v2 deve suportar nas próximas sprints:

- `height`, `weight`, `age`
- `chest`, `bust`, `under_bust`
- `waist`, `hip`
- `shoulder`, `sleeve`, `biceps`, `wrist`, `fist`
- `neck`
- `length`, `inside_leg`, `thigh`
- `foot_length`, `foot_width`
- `body_shape_chest`, `body_shape_waist`, `body_shape_hip`
- `fit_preference`

As tabelas atuais do v2 já aceitam `metadata`, mas o schema e as telas ainda precisam evoluir para tratar esses campos como parte do contrato principal.

## Pendências

- Ativar provider Gemini no backend do v2 usando a chave já copiada localmente.
- Evoluir o catálogo importado para salvar campos extras como idade, formato corporal, manga, entrepernas e pe em `metadata`.
- Definir imagens/ilustracoes proprias para formatos corporais do widget.
- Decidir se o v2 também tera OpenAI como provider alternativo para OCR e geracao.
- Cadastrar `GEMINI_API_KEY` em produção quando for liberar OCR real.
