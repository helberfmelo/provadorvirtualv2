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
        Gerencie produtos, grades e tabelas de medidas da loja demo para preparar
        o motor de recomendacao e o widget universal.
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
        <strong>{{ summary.widget_status === 'demo-ready' ? 'Widget preparado' : 'Widget pendente' }}</strong>
        <span>{{ summary.recommendations_today }} recomendacoes registradas hoje.</span>
      </article>
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
    </div>
  </section>
</template>
