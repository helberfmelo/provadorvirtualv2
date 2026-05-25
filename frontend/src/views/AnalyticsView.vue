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
    shopper_profiles_total: number
    shopper_profiles_known: number
    average_profile_quality: number
    learning_events_total: number
    learning_accepted: number
    learning_review: number
    learning_blocked_outliers: number
    average_outlier_score: number
  }
  daily: { date: string; count: number }[]
  sizes: { size: string; count: number }[]
  products: {
    product_id: number
    name: string | null
    recommendations: number
    average_confidence: number
    average_outlier_score: number
  }[]
  products_without_measurement_table: { id: number; name: string; sku: string | null; category: string | null }[]
  learning_statuses: { status: string; count: number }[]
  commerce_signals: { signal: string; count: number }[]
  outliers: {
    id: number
    event_type: string
    recommended_size: string | null
    selected_size: string | null
    outlier_score: number
    reason: string | null
    occurred_at: string | null
  }[]
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
    error.value = requestError.response?.data?.message || 'Não foi possível carregar analytics.'
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

function statusLabel(status: string) {
  const labels: Record<string, string> = {
    accepted: 'Aproveitado',
    review: 'Revisão',
    blocked_outlier: 'Bloqueado',
  }

  return labels[status] || eventLabel(status)
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Analytics</span>
        <h1>Recomendações e qualidade</h1>
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
          <span>Últimos 7 dias</span>
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
        <article class="metric-card">
          <i class="fa-solid fa-user-check" aria-hidden="true"></i>
          <strong>{{ analytics.summary.shopper_profiles_total }}</strong>
          <span>{{ analytics.summary.shopper_profiles_known }} reconhecidos</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-brain" aria-hidden="true"></i>
          <strong>{{ analytics.summary.average_profile_quality }}</strong>
          <span>qualidade média do perfil</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
          <strong>{{ analytics.summary.learning_blocked_outliers }}</strong>
          <span>{{ analytics.summary.average_outlier_score }} score medio outlier</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-database" aria-hidden="true"></i>
          <strong>{{ analytics.summary.learning_events_total }}</strong>
          <span>{{ analytics.summary.learning_accepted }} sinais aproveitados</span>
        </article>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Volume diario</h2>
            <span>{{ analytics.summary.average_confidence }} confiança média</span>
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
          <div v-if="!analytics.sizes.length" class="empty-state">Sem recomendações suficientes.</div>
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
            <span>{{ analytics.products.length }} com recomendação</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>Recomendações</th>
                  <th>Confiança</th>
                  <th>Outlier</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!analytics.products.length">
                  <td colspan="4">Sem dados.</td>
                </tr>
                <tr v-for="product in analytics.products" :key="product.product_id">
                  <td>{{ product.name || product.product_id }}</td>
                  <td>{{ product.recommendations }}</td>
                  <td>{{ product.average_confidence }}</td>
                  <td>{{ product.average_outlier_score }}</td>
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

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Aprendizado</h2>
            <span>{{ analytics.summary.learning_review }} para revisar</span>
          </div>
          <div v-if="!analytics.learning_statuses.length" class="empty-state">Sem sinais de aprendizado.</div>
          <div v-else class="summary-strip">
            <span v-for="status in analytics.learning_statuses" :key="status.status">
              <strong>{{ status.count }}</strong>
              <small>{{ statusLabel(status.status) }}</small>
            </span>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Sinais comerciais</h2>
            <span>{{ analytics.commerce_signals.length }} tipos</span>
          </div>
          <div v-if="!analytics.commerce_signals.length" class="empty-state">Nenhum sinal comercial registrado.</div>
          <div v-else class="summary-strip">
            <span v-for="signal in analytics.commerce_signals" :key="signal.signal">
              <strong>{{ signal.count }}</strong>
              <small>{{ eventLabel(signal.signal) }}</small>
            </span>
          </div>
        </section>
      </div>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Outliers bloqueados</h2>
          <span>{{ analytics.outliers.length }} recentes</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Sinal</th>
                <th>Recomendado</th>
                <th>Escolhido</th>
                <th>Score</th>
                <th>Motivo</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!analytics.outliers.length">
                <td colspan="5">Sem outliers recentes.</td>
              </tr>
              <tr v-for="outlier in analytics.outliers" :key="outlier.id">
                <td>{{ eventLabel(outlier.event_type) }}</td>
                <td>{{ outlier.recommended_size || '-' }}</td>
                <td>{{ outlier.selected_size || '-' }}</td>
                <td>{{ outlier.outlier_score }}</td>
                <td>{{ outlier.reason || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

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
                <th>Usuário</th>
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
