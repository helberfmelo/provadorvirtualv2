<script setup lang="ts">
import { onMounted, reactive } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const summary = reactive({
  products: 0,
  measurement_tables: 0,
  widget_status: 'demo-ready',
  widget_active: false,
  integrations_configured: 0,
  recommendations_today: 0,
})

onMounted(() => {
  auth.loadMe().catch(() => undefined)
  api.get('/merchant/overview')
    .then(({ data }) => Object.assign(summary, data.summary))
    .catch(() => undefined)
})
</script>

<template>
  <section class="dashboard">
    <div>
      <span class="eyebrow">Painel</span>
      <h1>Fundacao pronta para configurar lojas</h1>
      <p>
        Acompanhe os cadastros principais, publique o widget e mantenha as
        conexoes da loja em um unico lugar.
      </p>
    </div>

    <div class="metric-grid">
      <article class="metric-card">
        <i class="fa-solid fa-box-open" aria-hidden="true"></i>
        <strong>{{ summary.products }} produto{{ summary.products === 1 ? '' : 's' }}</strong>
        <span>Cadastre e vincule tabelas aos produtos que vao usar o provador.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
        <strong>{{ summary.measurement_tables }} tabela{{ summary.measurement_tables === 1 ? '' : 's' }}</strong>
        <span>Organize faixas por tamanho, produto e modelagem.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-plug" aria-hidden="true"></i>
        <strong>{{ summary.widget_active ? 'Widget ativo' : 'Widget pendente' }}</strong>
        <span>{{ summary.recommendations_today }} recomendacoes registradas hoje.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-link" aria-hidden="true"></i>
        <strong>{{ summary.integrations_configured }} integracao{{ summary.integrations_configured === 1 ? '' : 'es' }}</strong>
        <span>BigShop, lojas externas e instalacoes manuais.</span>
      </article>
    </div>

    <div class="onboarding-grid">
      <RouterLink class="onboarding-step" to="/app/produtos">
        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
        <span>
          <strong>Produtos</strong>
          <small>{{ summary.products }} cadastrados</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/tabelas-de-medidas">
        <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
        <span>
          <strong>Tabelas</strong>
          <small>{{ summary.measurement_tables }} disponiveis</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/assistente">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        <span>
          <strong>Assistente</strong>
          <small>Texto e imagem</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/widget">
        <i class="fa-solid fa-code" aria-hidden="true"></i>
        <span>
          <strong>Widget</strong>
          <small>{{ summary.widget_active ? 'ativo' : 'pendente' }}</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/analytics">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        <span>
          <strong>Analytics</strong>
          <small>{{ summary.recommendations_today }} hoje</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/go-live">
        <i class="fa-solid fa-rocket" aria-hidden="true"></i>
        <span>
          <strong>Go-live</strong>
          <small>Checklist final</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/importacoes">
        <i class="fa-solid fa-file-import" aria-hidden="true"></i>
        <span>
          <strong>Importacoes</strong>
          <small>CSV e XML</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/integracoes">
        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
        <span>
          <strong>Integracoes</strong>
          <small>{{ summary.integrations_configured }} configuradas</small>
        </span>
      </RouterLink>
    </div>

    <div class="action-row">
      <RouterLink class="btn btn-primary" to="/app/produtos">
        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
        Produtos
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/tabelas-de-medidas">
        <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
        Tabelas de medidas
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/assistente">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        Assistente
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/analytics">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        Analytics
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/go-live">
        <i class="fa-solid fa-rocket" aria-hidden="true"></i>
        Go-live
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/widget">
        <i class="fa-solid fa-code" aria-hidden="true"></i>
        Widget
      </RouterLink>
    </div>
  </section>
</template>
