<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../services/api'

type AnalyticsPayload = {
  summary: {
    recommendations_total: number
    recommendations_today: number
    recommendations_7d: number
    average_confidence: number
    feedback_total: number
    positive_feedback_rate: number | null
    products_without_measurement_table: number
    widget_attention_items: number
  }
  daily: { date: string; count: number }[]
  sizes: { size: string; count: number }[]
  products: { product_id: number; name: string | null; recommendations: number; average_confidence: number }[]
  products_without_measurement_table: { id: number; name: string; sku: string | null; category: string | null }[]
}

type AuditLog = {
  id: number
  event: string
  category: string
  severity: string
  metadata: Record<string, string | number | boolean | null>
  created_at: string | null
  user: { name: string; email: string } | null
}

const analytics = ref<AnalyticsPayload | null>(null)
const auditLogs = ref<AuditLog[]>([])
const loading = ref(false)
const error = ref('')

const topDay = computed(() => Math.max(1, ...((analytics.value?.daily || []).map((item) => item.count))))

onMounted(() => {
  loadAnalytics()
})

async function loadAnalytics() {
  loading.value = true
  error.value = ''

  try {
    const [analyticsResponse, auditResponse] = await Promise.all([
      api.get('/analytics/recommendations'),
      api.get('/audit-logs'),
    ])

    analytics.value = analyticsResponse.data.data
    auditLogs.value = auditResponse.data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar analytics.'
  } finally {
    loading.value = false
  }
}

function percent(value: number | null) {
  return value === null ? 'Sem dados' : `${value}%`
}

function eventLabel(event: string) {
  return event.replaceAll('_', ' ').replaceAll('.', ' / ')
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Analytics</span>
        <h1>Recomendacoes e qualidade</h1>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadAnalytics">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <template v-if="analytics">
      <div class="metric-grid">
        <article class="metric-card">
          <i class="fa-solid fa-shirt" aria-hidden="true"></i>
          <strong>{{ analytics.summary.recommendations_total }}</strong>
          <span>{{ analytics.summary.recommendations_today }} hoje</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
          <strong>{{ analytics.summary.recommendations_7d }}</strong>
          <span>Ultimos 7 dias</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-thumbs-up" aria-hidden="true"></i>
          <strong>{{ percent(analytics.summary.positive_feedback_rate) }}</strong>
          <span>{{ analytics.summary.feedback_total }} feedbacks</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <strong>{{ analytics.summary.widget_attention_items }}</strong>
          <span>{{ analytics.summary.products_without_measurement_table }} produtos sem tabela</span>
        </article>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Volume diario</h2>
            <span>{{ analytics.summary.average_confidence }} confianca media</span>
          </div>
          <div class="bar-list">
            <div v-for="day in analytics.daily" :key="day.date" class="bar-row">
              <span>{{ day.date.slice(5) }}</span>
              <div><i :style="{ width: `${(day.count / topDay) * 100}%` }"></i></div>
              <strong>{{ day.count }}</strong>
            </div>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Tamanhos</h2>
            <span>{{ analytics.sizes.length }} com uso</span>
          </div>
          <div v-if="!analytics.sizes.length" class="empty-state">Sem recomendacoes suficientes.</div>
          <div v-else class="summary-strip">
            <span v-for="size in analytics.sizes" :key="size.size">
              <strong>{{ size.count }}</strong>
              <small>{{ size.size }}</small>
            </span>
          </div>
        </section>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Produtos</h2>
            <span>{{ analytics.products.length }} com recomendacao</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>Recomendacoes</th>
                  <th>Confianca</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!analytics.products.length">
                  <td colspan="3">Sem dados.</td>
                </tr>
                <tr v-for="product in analytics.products" :key="product.product_id">
                  <td>{{ product.name || product.product_id }}</td>
                  <td>{{ product.recommendations }}</td>
                  <td>{{ product.average_confidence }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Sem tabela</h2>
            <span>{{ analytics.summary.products_without_measurement_table }} produtos</span>
          </div>
          <div class="job-list">
            <div v-if="!analytics.products_without_measurement_table.length" class="empty-state">Tudo configurado.</div>
            <article v-for="product in analytics.products_without_measurement_table" :key="product.id" class="job-row">
              <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
              <span>
                <strong>{{ product.name }}</strong>
                <small>{{ product.sku || product.category || 'Sem SKU' }}</small>
              </span>
            </article>
          </div>
        </section>
      </div>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Auditoria</h2>
          <span>{{ auditLogs.length }} recentes</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Evento</th>
                <th>Usuario</th>
                <th>Categoria</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!auditLogs.length">
                <td colspan="4">Sem eventos recentes.</td>
              </tr>
              <tr v-for="log in auditLogs" :key="log.id">
                <td>{{ eventLabel(log.event) }}</td>
                <td>{{ log.user?.name || 'Sistema' }}</td>
                <td>{{ log.category }}</td>
                <td>{{ log.created_at?.slice(0, 16).replace('T', ' ') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </section>
</template>
