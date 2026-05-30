<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

type HelpTopic = {
  key: string
  title: string
  summary: string
  steps: string[]
  primaryTo: string
  primaryLabel: string
}

const route = useRoute()
const supportUrl = 'https://wa.me/5531993157573?text=Oi,%20preciso%20de%20ajuda%20no%20Provador%20Virtual.'

const topics: HelpTopic[] = [
  {
    key: 'painel',
    title: 'Painel da loja',
    summary: 'Use o painel para entender o que falta antes de publicar o provador.',
    steps: ['Confira produtos e tabelas cadastrados.', 'Resolva integrações ou sincronizações pendentes.', 'Abra Publicação antes de liberar a loja.'],
    primaryTo: '/app/produtos',
    primaryLabel: 'Ir para produtos',
  },
  {
    key: 'produtos',
    title: 'Produtos',
    summary: 'A linha do produto deve mostrar tabela, tamanhos, modelagem e pendências sem abrir cadastro por cadastro.',
    steps: ['Filtre itens pendentes.', 'Vincule tabelas em lote quando fizer sentido.', 'Abra o produto apenas para ajuste fino.'],
    primaryTo: '/app/produtos',
    primaryLabel: 'Abrir produtos',
  },
  {
    key: 'tabelas',
    title: 'Tabelas de medidas',
    summary: 'Tabelas boas deixam claro se a medida é do corpo, da peça ou do sistema de tamanho.',
    steps: ['Comece por modelo quando possível.', 'Revise ranges e medidas compostas.', 'Vincule a tabela aos produtos corretos.'],
    primaryTo: '/app/tabelas-de-medidas',
    primaryLabel: 'Abrir tabelas',
  },
  {
    key: 'modelagens',
    title: 'Modelagens',
    summary: 'Modelagens organizam caimento e ajudam a corrigir importações com diagnóstico mais claro.',
    steps: ['Mantenha nomes e códigos consistentes.', 'Use modelagens nas tabelas e produtos.', 'Aplique em massa quando vários produtos tiverem o mesmo caimento.'],
    primaryTo: '/app/modelagens',
    primaryLabel: 'Abrir modelagens',
  },
  {
    key: 'importacoes',
    title: 'Importações',
    summary: 'A importação deve começar por prévia e validação, nunca por gravação às cegas.',
    steps: ['Faça a prévia do arquivo ou feed.', 'Revise erros por linha e campos ausentes.', 'Grave somente depois de corrigir as regras necessárias.'],
    primaryTo: '/app/importacoes',
    primaryLabel: 'Abrir importações',
  },
  {
    key: 'regras',
    title: 'Regras de importação',
    summary: 'Regras reduzem retrabalho ao normalizar dados vindos da plataforma.',
    steps: ['Defina origem e obrigatoriedade dos campos.', 'Use fallback apenas quando for seguro.', 'Teste a próxima importação antes de gravar.'],
    primaryTo: '/app/regras-de-importacao',
    primaryLabel: 'Abrir regras',
  },
  {
    key: 'integracoes',
    title: 'Integrações',
    summary: 'Separe a plataforma da loja, a origem do catálogo, a instalação na página de produto e o rastreamento.',
    steps: ['Confira a plataforma operacional.', 'Salve credenciais ou feed apenas quando necessário.', 'Valide a URL pública da página de produto.'],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
  },
  {
    key: 'provador',
    title: 'Instalação do provador',
    summary: 'O provador só deve ser publicado quando domínio, botões, cores e prévia estiverem coerentes.',
    steps: ['Salve rascunho antes de publicar.', 'Confira a prévia desktop e mobile.', 'Publique depois de validar a instalação.'],
    primaryTo: '/app/widget',
    primaryLabel: 'Abrir instalação',
  },
  {
    key: 'assistente',
    title: 'Assistente IA',
    summary: 'O assistente acelera rascunhos, mas a publicação continua dependendo da revisão humana.',
    steps: ['Informe contexto de produto, público e modelagem.', 'Revise medidas e confiança.', 'Salve como rascunho e ajuste antes de usar.'],
    primaryTo: '/app/assistente',
    primaryLabel: 'Abrir assistente',
  },
  {
    key: 'publicacao',
    title: 'Publicação',
    summary: 'A publicação conecta catálogo, widget, integração e prontidão operacional.',
    steps: ['Resolva bloqueios críticos.', 'Confira avisos que afetam experiência ou dados.', 'Revalide depois de mudanças importantes.'],
    primaryTo: '/app/go-live',
    primaryLabel: 'Abrir publicação',
  },
  {
    key: 'sincronizacao',
    title: 'Sincronização',
    summary: 'Use o histórico para entender o que entrou, mudou, falhou ou precisa de regra antes da próxima importação.',
    steps: ['Abra a execução com erro.', 'Corrija a causa raiz em regras, produtos ou modelagens.', 'Reprocesse somente depois da correção.'],
    primaryTo: '/app/sincronizacao',
    primaryLabel: 'Abrir histórico',
  },
  {
    key: 'relatorios',
    title: 'Relatórios',
    summary: 'Relatórios mostram uso, feedback e sinais comerciais para priorizar melhorias de catálogo.',
    steps: ['Veja recomendações e feedbacks.', 'Revise tabelas com maior alerta.', 'Use os dados antes de mexer no catálogo.'],
    primaryTo: '/app/analytics',
    primaryLabel: 'Abrir relatórios',
  },
  {
    key: 'devolucoes',
    title: 'Devoluções',
    summary: 'Devoluções e trocas precisam de motivo normalizado, tamanho envolvido e vínculo com o uso do provador.',
    steps: ['Baixe o modelo ou exporte o arquivo da plataforma.', 'Revise o mapeamento de colunas na prévia.', 'Importe só depois de corrigir linhas inválidas e motivos ausentes.'],
    primaryTo: '/app/devolucoes',
    primaryLabel: 'Abrir devoluções',
  },
  {
    key: 'usuarios',
    title: 'Usuários',
    summary: 'Permissões devem acompanhar a função real de cada pessoa na operação da loja.',
    steps: ['Dê visualização para acompanhamento.', 'Libere edição só para quem configura.', 'Revise usuários inativos periodicamente.'],
    primaryTo: '/app/usuarios',
    primaryLabel: 'Abrir usuários',
  },
]

const selectedTopic = computed(() => {
  const requested = String(route.query.topico || '')
  return topics.find((topic) => topic.key === requested) || topics[0]
})
</script>

<template>
  <section class="dashboard app-workspace help-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Ajuda</span>
        <h1>Manual rápido</h1>
        <p class="page-heading-help">
          Guias curtos para operar o Provador Virtual sem perder o próximo passo.
        </p>
      </div>
      <a class="btn btn-secondary" :href="supportUrl" target="_blank" rel="noopener noreferrer">
        <i class="fa-solid fa-headset" aria-hidden="true"></i>
        Suporte
      </a>
    </div>

    <div class="help-grid">
      <aside class="panel-list help-topic-list" aria-label="Tópicos do manual">
        <RouterLink
          v-for="topic in topics"
          :key="topic.key"
          :class="['list-row', { active: selectedTopic.key === topic.key }]"
          :to="{ path: '/app/ajuda', query: { topico: topic.key } }"
        >
          <strong>{{ topic.title }}</strong>
          <span>{{ topic.summary }}</span>
        </RouterLink>
      </aside>

      <section class="panel-main help-topic-detail">
        <div class="subsection-heading">
          <h2>{{ selectedTopic.title }}</h2>
          <RouterLink class="btn btn-secondary" :to="selectedTopic.primaryTo">
            {{ selectedTopic.primaryLabel }}
          </RouterLink>
        </div>

        <p>{{ selectedTopic.summary }}</p>

        <ol class="help-step-list">
          <li v-for="step in selectedTopic.steps" :key="step">{{ step }}</li>
        </ol>
      </section>
    </div>
  </section>
</template>
