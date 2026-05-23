<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../services/api'

type Summary = Record<string, number>
type MerchantRow = {
  id: number
  name: string
  slug: string
  billing_status: string
  companies_count: number
  products_count: number
  measurement_tables_count: number
  widget_installs_count: number
  platform_connections_count: number
  recommendations_7d: number
  last_recommendation_at: string | null
}

const summary = ref<Summary>({})
const merchants = ref<MerchantRow[]>([])
const loading = ref(false)
const error = ref('')

onMounted(() => {
  loadSaas()
})

async function loadSaas() {
  loading.value = true
  error.value = ''

  try {
    const [overviewResponse, merchantsResponse] = await Promise.all([
      api.get('/saas/overview'),
      api.get('/saas/merchants'),
    ])

    summary.value = overviewResponse.data.data.summary
    merchants.value = merchantsResponse.data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar o painel SaaS.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Operacao dos lojistas</h1>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadSaas">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <template v-if="!error">
      <div class="metric-grid">
        <article class="metric-card">
          <i class="fa-solid fa-store" aria-hidden="true"></i>
          <strong>{{ summary.merchants || 0 }}</strong>
          <span>{{ summary.trialing_merchants || 0 }} em trial</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-code" aria-hidden="true"></i>
          <strong>{{ summary.active_widgets || 0 }}</strong>
          <span>Widgets ativos</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
          <strong>{{ summary.recommendations_7d || 0 }}</strong>
          <span>Recomendacoes em 7 dias</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <strong>{{ (summary.failed_imports_7d || 0) + (summary.failed_integrations_7d || 0) }}</strong>
          <span>Falhas recentes</span>
        </article>
      </div>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Lojistas</h2>
          <span>{{ merchants.length }} recentes</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Lojista</th>
                <th>Status</th>
                <th>Produtos</th>
                <th>Tabelas</th>
                <th>Integracoes</th>
                <th>7 dias</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!merchants.length">
                <td colspan="6">Sem lojistas.</td>
              </tr>
              <tr v-for="merchant in merchants" :key="merchant.id">
                <td>
                  <strong>{{ merchant.name }}</strong>
                  <small>{{ merchant.slug }}</small>
                </td>
                <td>{{ merchant.billing_status }}</td>
                <td>{{ merchant.products_count }}</td>
                <td>{{ merchant.measurement_tables_count }}</td>
                <td>{{ merchant.platform_connections_count }}</td>
                <td>{{ merchant.recommendations_7d }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </section>
</template>
