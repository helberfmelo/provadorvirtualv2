<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

type RecommendationAnalyticsPayload = {
  filters: {
    period: string
    date_from: string
    date_to: string
    product_id: number | null
    measurement_table_id: number | null
    platform: string | null
    device_type: string | null
    brand: string | null
    category: string | null
    page: number
    per_page: number
  }
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
    commerce_purchases: number
    commerce_returns: number
    commerce_exchanges: number
    commerce_return_rate: number | null
    measurement_table_insights_review: number
  }
  daily: { date: string; count: number }[]
  sizes: { size: string; count: number }[]
  products: {
    product_id: number
    name: string | null
    brand?: string | null
    normalized_brand?: string | null
    normalized_category?: string | null
    recommendations: number
    average_confidence: number
    average_outlier_score: number
  }[]
  brands: { brand: string; normalized: boolean; recommendations: number; average_confidence: number }[]
  categories: { category: string; normalized: boolean; category_type?: string | null; recommendations: number; average_confidence: number }[]
  products_without_measurement_table: { id: number; name: string; sku: string | null; category: string | null }[]
  learning_statuses: { status: string; count: number }[]
  commerce_signals: { signal: string; count: number }[]
  measurement_table_insights: {
    measurement_table_id: number
    table_name: string
    product_type: string
    gender: string | null
    fit_profile: string | null
    suggested_action: string
    reason: string
    priority_score: number
    confidence: string
    signals: {
      total: number
      accepted: number
      review: number
      blocked_outliers: number
      purchases: number
      returns: number
      positive_feedback: number
      negative_feedback: number
      size_too_small: number
      size_too_large: number
      fit_issue: number
      return_rate: number | null
    }
  }[]
  product_ranking: {
    product_id: number
    name: string | null
    sku: string | null
    brand: string
    category: string
    measurement_table_id: number | null
    measurement_table_name: string | null
    button_impressions: number
    virtual_try_on_opens: number
    measurement_table_opens: number
    recommendations_generated: number
    size_selections: number
    returns_exchanges: number
    blocked_outliers: number
    needs_more_data: number
    usage_rate: number | null
    selection_rate: number | null
    return_rate: number | null
    errors_total: number
    attention_flags: string[]
  }[]
  recommendation_report: {
    data: {
      recommendation_id: number
      created_at: string | null
      product_id: number | null
      product_name: string | null
      product_sku: string | null
      measurement_table_id: number | null
      measurement_table_name: string | null
      recommended_size: string | null
      confidence: number
      status: string
      platform: string | null
      device_type: string | null
      origin: string
      feedback_helpful: boolean | null
      feedback_rating: number | null
      purchases: number
      returns: number
      exchanges: number
      outlier_score: number
      brand: string
      category: string
    }[]
    meta: {
      current_page: number
      last_page: number
      per_page: number
      total: number
    }
  }
  filter_options: {
    products: { id: number; name: string; sku: string | null }[]
    measurement_tables: { id: number; name: string }[]
    brands: string[]
    categories: string[]
    platforms: string[]
    device_types: string[]
  }
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

type WidgetUsagePayload = {
  filters: {
    period: string
    date_from: string
    date_to: string
    product_id: number | null
    measurement_table_id: number | null
    platform: string | null
    device_type: string | null
    brand: string | null
    category: string | null
  }
  summary: {
    button_impressions: number
    virtual_try_on_opens: number
    measurement_table_opens: number
    recommendations_generated: number
    size_selections: number
    feedback_submitted: number
    conversions: number
    usage_rate: number | null
    table_rate: number | null
    selection_rate: number | null
    conversion_rate: number | null
  }
  funnel: { key: string; label: string; count: number }[]
  daily: {
    date: string
    button_impressions: number
    virtual_try_on_opens: number
    measurement_table_opens: number
    recommendations_generated: number
    size_selections: number
    feedback_submitted: number
    conversions: number
  }[]
  device_distribution: { device_type: string; count: number; share: number }[]
  filter_options: {
    products: { id: number; name: string; sku: string | null }[]
    measurement_tables: { id: number; name: string }[]
    brands: string[]
    categories: string[]
    platforms: string[]
    device_types: string[]
  }
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

const recommendationAnalytics = ref<RecommendationAnalyticsPayload | null>(null)
const widgetUsage = ref<WidgetUsagePayload | null>(null)
const auditLogs = ref<AuditLog[]>([])
const loading = ref(false)
const error = ref('')
const recommendationPage = ref(1)

const widgetFilters = reactive({
  period: '30d',
  date_from: '',
  date_to: '',
  product_id: '',
  measurement_table_id: '',
  platform: '',
  device_type: '',
  brand: '',
  category: '',
})

const topRecommendationDay = computed(() =>
  Math.max(1, ...((recommendationAnalytics.value?.daily || []).map((item) => item.count))),
)

const topUsageDay = computed(() =>
  Math.max(
    1,
    ...((widgetUsage.value?.daily || []).map((item) =>
      Math.max(item.button_impressions, item.virtual_try_on_opens, item.recommendations_generated, item.size_selections),
    )),
  ),
)

onMounted(() => {
  loadAnalytics()
})

async function loadAnalytics() {
  loading.value = true
  error.value = ''

  try {
    const [recommendationResponse, widgetUsageResponse, auditResponse] = await Promise.all([
      api.get('/analytics/recommendations', { params: recommendationParams() }),
      api.get('/analytics/widget-usage', { params: widgetUsageParams() }),
      api.get('/audit-logs'),
    ])

    recommendationAnalytics.value = recommendationResponse.data.data
    widgetUsage.value = widgetUsageResponse.data.data
    auditLogs.value = auditResponse.data.data

    syncFiltersFromResponse()
    recommendationPage.value = recommendationAnalytics.value?.recommendation_report.meta.current_page || 1
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar analytics.'
  } finally {
    loading.value = false
  }
}

function syncFiltersFromResponse() {
  if (!widgetUsage.value) {
    return
  }

  widgetFilters.period = widgetUsage.value.filters.period || '30d'
  widgetFilters.date_from = widgetUsage.value.filters.date_from || ''
  widgetFilters.date_to = widgetUsage.value.filters.date_to || ''
  widgetFilters.product_id = widgetUsage.value.filters.product_id ? String(widgetUsage.value.filters.product_id) : ''
  widgetFilters.measurement_table_id = widgetUsage.value.filters.measurement_table_id
    ? String(widgetUsage.value.filters.measurement_table_id)
    : ''
  widgetFilters.platform = widgetUsage.value.filters.platform || ''
  widgetFilters.device_type = widgetUsage.value.filters.device_type || ''
  widgetFilters.brand = widgetUsage.value.filters.brand || ''
  widgetFilters.category = widgetUsage.value.filters.category || ''
}

function widgetUsageParams() {
  const params: Record<string, string> = {
    period: widgetFilters.period,
  }

  if (widgetFilters.period === 'custom') {
    if (widgetFilters.date_from) {
      params.date_from = widgetFilters.date_from
    }

    if (widgetFilters.date_to) {
      params.date_to = widgetFilters.date_to
    }
  }

  if (widgetFilters.product_id) {
    params.product_id = widgetFilters.product_id
  }

  if (widgetFilters.measurement_table_id) {
    params.measurement_table_id = widgetFilters.measurement_table_id
  }

  if (widgetFilters.platform) {
    params.platform = widgetFilters.platform
  }

  if (widgetFilters.device_type) {
    params.device_type = widgetFilters.device_type
  }

  if (widgetFilters.brand) {
    params.brand = widgetFilters.brand
  }

  if (widgetFilters.category) {
    params.category = widgetFilters.category
  }

  return params
}

function recommendationParams() {
  return {
    ...widgetUsageParams(),
    page: String(recommendationPage.value),
    per_page: '10',
  }
}

function applyFilters() {
  recommendationPage.value = 1
  loadAnalytics()
}

function resetWidgetFilters() {
  widgetFilters.period = '30d'
  widgetFilters.date_from = ''
  widgetFilters.date_to = ''
  widgetFilters.product_id = ''
  widgetFilters.measurement_table_id = ''
  widgetFilters.platform = ''
  widgetFilters.device_type = ''
  widgetFilters.brand = ''
  widgetFilters.category = ''
  recommendationPage.value = 1

  loadAnalytics()
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

function actionLabel(action: string) {
  const labels: Record<string, string> = {
    review_size_too_small: 'Revisar peça pequena',
    review_size_too_large: 'Revisar peça grande',
    review_fit_profile: 'Revisar modelagem',
    review_feedback: 'Revisar feedback',
    collect_more_data: 'Coletar dados',
    stable: 'Referência estável',
  }

  return labels[action] || eventLabel(action)
}

function deviceLabel(deviceType: string) {
  const labels: Record<string, string> = {
    desktop: 'Desktop',
    mobile: 'Mobile',
    tablet: 'Tablet',
  }

  return labels[deviceType] || eventLabel(deviceType)
}

function usageDaySummary(day: WidgetUsagePayload['daily'][number]) {
  return `${day.virtual_try_on_opens} aberturas · ${day.recommendations_generated} recomendações · ${day.size_selections} tamanhos`
}

function attentionLabel(flag: string) {
  const labels: Record<string, string> = {
    sem_tabela: 'Sem tabela',
    devolucao_ou_troca: 'Com devolução/troca',
    outlier_bloqueado: 'Com outlier',
    baixa_confianca: 'Baixa confiança',
  }

  return labels[flag] || eventLabel(flag)
}

function formatDateTime(value: string | null) {
  if (! value) {
    return '-'
  }

  return value.slice(0, 16).replace('T', ' ')
}

function applyRecommendationDrilldown(productId?: number | null, tableId?: number | null) {
  widgetFilters.product_id = productId ? String(productId) : ''
  widgetFilters.measurement_table_id = tableId ? String(tableId) : ''
  recommendationPage.value = 1
  loadAnalytics()
}

function changeRecommendationPage(page: number) {
  if (! recommendationAnalytics?.value) {
    return
  }

  if (page < 1 || page > recommendationAnalytics.value.recommendation_report.meta.last_page) {
    return
  }

  recommendationPage.value = page
  loadAnalytics()
}

async function downloadRecommendationExport(report: 'ranking' | 'recommendations') {
  const response = await api.get('/analytics/recommendations/export', {
    params: {
      ...recommendationParams(),
      report,
    },
    responseType: 'blob',
  })

  const filename = report === 'ranking'
    ? 'provador-virtual-ranking-produtos.csv'
    : 'provador-virtual-recomendacoes.csv'

  const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Analytics</span>
        <h1>Relatórios do provador e qualidade</h1>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadAnalytics">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <template v-if="widgetUsage && recommendationAnalytics">
      <section class="panel-main admin-form analytics-filter-panel">
        <div class="subsection-heading">
          <h2>Filtros dos relatórios</h2>
          <span>{{ widgetUsage.filters.date_from }} até {{ widgetUsage.filters.date_to }}</span>
        </div>

        <form class="analytics-filter-grid" @submit.prevent="applyFilters">
          <label>
            <span>Período</span>
            <select v-model="widgetFilters.period">
              <option value="today">Hoje</option>
              <option value="7d">Últimos 7 dias</option>
              <option value="30d">Últimos 30 dias</option>
              <option value="90d">Últimos 90 dias</option>
              <option value="custom">Personalizado</option>
            </select>
          </label>

          <label v-if="widgetFilters.period === 'custom'">
            <span>De</span>
            <input v-model="widgetFilters.date_from" type="date" />
          </label>

          <label v-if="widgetFilters.period === 'custom'">
            <span>Até</span>
            <input v-model="widgetFilters.date_to" type="date" />
          </label>

          <label>
            <span>Produto</span>
            <select v-model="widgetFilters.product_id">
              <option value="">Todos</option>
              <option
                v-for="product in widgetUsage.filter_options.products"
                :key="product.id"
                :value="String(product.id)"
              >
                {{ product.name }}{{ product.sku ? ` · ${product.sku}` : '' }}
              </option>
            </select>
          </label>

          <label>
            <span>Tabela</span>
            <select v-model="widgetFilters.measurement_table_id">
              <option value="">Todas</option>
              <option
                v-for="table in widgetUsage.filter_options.measurement_tables"
                :key="table.id"
                :value="String(table.id)"
              >
                {{ table.name }}
              </option>
            </select>
          </label>

          <label>
            <span>Marca</span>
            <select v-model="widgetFilters.brand">
              <option value="">Todas</option>
              <option v-for="brand in widgetUsage.filter_options.brands" :key="brand" :value="brand">
                {{ brand }}
              </option>
            </select>
          </label>

          <label>
            <span>Categoria</span>
            <select v-model="widgetFilters.category">
              <option value="">Todas</option>
              <option
                v-for="category in widgetUsage.filter_options.categories"
                :key="category"
                :value="category"
              >
                {{ category }}
              </option>
            </select>
          </label>

          <label>
            <span>Plataforma</span>
            <select v-model="widgetFilters.platform">
              <option value="">Todas</option>
              <option v-for="platform in widgetUsage.filter_options.platforms" :key="platform" :value="platform">
                {{ platform }}
              </option>
            </select>
          </label>

          <label>
            <span>Dispositivo</span>
            <select v-model="widgetFilters.device_type">
              <option value="">Todos</option>
              <option
                v-for="deviceType in widgetUsage.filter_options.device_types"
                :key="deviceType"
                :value="deviceType"
              >
                {{ deviceLabel(deviceType) }}
              </option>
            </select>
          </label>

          <div class="analytics-filter-actions">
            <button class="btn" type="submit" :disabled="loading">Aplicar filtros</button>
            <button class="btn btn-secondary" type="button" :disabled="loading" @click="resetWidgetFilters">
              Limpar
            </button>
          </div>
        </form>
      </section>

      <section class="panel-main analytics-section-panel">
        <div class="subsection-heading">
          <h2>Uso do widget</h2>
          <span>{{ percent(widgetUsage.summary.usage_rate) }} taxa de uso no período</span>
        </div>
        <p class="page-heading-help analytics-section-help">
          Acompanhe abertura do provador, consulta de tabela e aplicação do tamanho antes de chegar nas recomendações.
        </p>
      </section>

      <div class="metric-grid analytics-metric-grid">
        <article class="metric-card">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
          <strong>{{ widgetUsage.summary.button_impressions }}</strong>
          <span>botões exibidos</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-shirt" aria-hidden="true"></i>
          <strong>{{ widgetUsage.summary.virtual_try_on_opens }}</strong>
          <span>{{ percent(widgetUsage.summary.usage_rate) }} taxa de uso</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
          <strong>{{ widgetUsage.summary.recommendations_generated }}</strong>
          <span>recomendações geradas</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
          <strong>{{ widgetUsage.summary.measurement_table_opens }}</strong>
          <span>{{ percent(widgetUsage.summary.table_rate) }} consulta de tabela</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-tags" aria-hidden="true"></i>
          <strong>{{ widgetUsage.summary.size_selections }}</strong>
          <span>{{ percent(widgetUsage.summary.selection_rate) }} aplicação do tamanho</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
          <strong>{{ widgetUsage.summary.conversions }}</strong>
          <span>{{ percent(widgetUsage.summary.conversion_rate) }} compras sinalizadas</span>
        </article>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Funil do widget</h2>
            <span>{{ widgetUsage.funnel.length }} etapas</span>
          </div>
          <div class="summary-strip usage-funnel-strip">
            <span v-for="step in widgetUsage.funnel" :key="step.key">
              <strong>{{ step.count }}</strong>
              <small>{{ step.label }}</small>
            </span>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Dispositivos</h2>
            <span>{{ widgetUsage.device_distribution.length }} origens</span>
          </div>
          <div v-if="!widgetUsage.device_distribution.length" class="empty-state">Sem eventos no período filtrado.</div>
          <div v-else class="job-list">
            <article
              v-for="device in widgetUsage.device_distribution"
              :key="device.device_type"
              class="job-row analytics-device-row"
            >
              <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
              <span>
                <strong>{{ deviceLabel(device.device_type) }}</strong>
                <small>{{ device.count }} eventos · {{ device.share }}%</small>
              </span>
            </article>
          </div>
        </section>
      </div>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Evolução diária do uso</h2>
          <span>{{ widgetUsage.daily.length }} dias</span>
        </div>
        <div v-if="!widgetUsage.daily.length" class="empty-state">Sem eventos no período filtrado.</div>
        <div v-else class="bar-list">
          <div v-for="day in widgetUsage.daily" :key="day.date" class="bar-row analytics-bar-row-wide">
            <span>{{ day.date.slice(5) }}</span>
            <div><i :style="{ width: `${(day.button_impressions / topUsageDay) * 100}%` }"></i></div>
            <strong>{{ day.button_impressions }}</strong>
            <small>{{ usageDaySummary(day) }}</small>
          </div>
        </div>
      </section>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Qualidade da recomendação</h2>
          <span>{{ recommendationAnalytics.summary.average_confidence }} confiança média</span>
        </div>
      </section>

      <div class="metric-grid">
        <article class="metric-card">
          <i class="fa-solid fa-shirt" aria-hidden="true"></i>
          <strong>{{ recommendationAnalytics.summary.recommendations_total }}</strong>
          <span>{{ recommendationAnalytics.summary.recommendations_today }} hoje</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
          <strong>{{ recommendationAnalytics.summary.recommendations_7d }}</strong>
          <span>Últimos 7 dias</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-thumbs-up" aria-hidden="true"></i>
          <strong>{{ percent(recommendationAnalytics.summary.positive_feedback_rate) }}</strong>
          <span>{{ recommendationAnalytics.summary.feedback_total }} feedbacks</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <strong>{{ recommendationAnalytics.summary.widget_attention_items }}</strong>
          <span>{{ recommendationAnalytics.summary.products_without_measurement_table }} produtos sem tabela</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-user-check" aria-hidden="true"></i>
          <strong>{{ recommendationAnalytics.summary.shopper_profiles_total }}</strong>
          <span>{{ recommendationAnalytics.summary.shopper_profiles_known }} reconhecidos</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-brain" aria-hidden="true"></i>
          <strong>{{ recommendationAnalytics.summary.average_profile_quality }}</strong>
          <span>qualidade média do perfil</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
          <strong>{{ recommendationAnalytics.summary.learning_blocked_outliers }}</strong>
          <span>{{ recommendationAnalytics.summary.average_outlier_score }} score médio</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-database" aria-hidden="true"></i>
          <strong>{{ recommendationAnalytics.summary.learning_events_total }}</strong>
          <span>{{ recommendationAnalytics.summary.learning_accepted }} sinais aproveitados</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
          <strong>{{ percent(recommendationAnalytics.summary.commerce_return_rate) }}</strong>
          <span>
            {{ recommendationAnalytics.summary.commerce_returns + recommendationAnalytics.summary.commerce_exchanges }}
            devoluções/trocas
          </span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
          <strong>{{ recommendationAnalytics.summary.measurement_table_insights_review }}</strong>
          <span>tabelas com revisão sugerida</span>
        </article>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Categorias</h2>
            <span>{{ recommendationAnalytics.categories.length }} com uso</span>
          </div>
          <div v-if="!recommendationAnalytics.categories.length" class="empty-state">Sem categorias nas recomendações.</div>
          <div v-else class="summary-strip">
            <span v-for="category in recommendationAnalytics.categories.slice(0, 6)" :key="category.category">
              <strong>{{ category.recommendations }}</strong>
              <small>{{ category.category }}{{ category.normalized ? ' · normalizada' : '' }}</small>
            </span>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Marcas</h2>
            <span>{{ recommendationAnalytics.brands.length }} com uso</span>
          </div>
          <div v-if="!recommendationAnalytics.brands.length" class="empty-state">Sem marcas nas recomendações.</div>
          <div v-else class="summary-strip">
            <span v-for="brand in recommendationAnalytics.brands.slice(0, 6)" :key="brand.brand">
              <strong>{{ brand.recommendations }}</strong>
              <small>{{ brand.brand }}{{ brand.normalized ? ' · normalizada' : '' }}</small>
            </span>
          </div>
        </section>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Volume diário</h2>
            <span>{{ recommendationAnalytics.summary.average_confidence }} confiança média</span>
          </div>
          <div class="bar-list">
            <div v-for="day in recommendationAnalytics.daily" :key="day.date" class="bar-row">
              <span>{{ day.date.slice(5) }}</span>
              <div><i :style="{ width: `${(day.count / topRecommendationDay) * 100}%` }"></i></div>
              <strong>{{ day.count }}</strong>
            </div>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Tamanhos</h2>
            <span>{{ recommendationAnalytics.sizes.length }} com uso</span>
          </div>
          <div v-if="!recommendationAnalytics.sizes.length" class="empty-state">Sem recomendações suficientes.</div>
          <div v-else class="summary-strip">
            <span v-for="size in recommendationAnalytics.sizes" :key="size.size">
              <strong>{{ size.count }}</strong>
              <small>{{ size.size }}</small>
            </span>
          </div>
        </section>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Ranking de produtos</h2>
            <div class="action-row">
              <span>{{ recommendationAnalytics.product_ranking.length }} no período</span>
              <button class="btn btn-secondary" type="button" @click="downloadRecommendationExport('ranking')">
                <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                Exportar CSV
              </button>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>Uso</th>
                  <th>Recomendações</th>
                  <th>Erros</th>
                  <th>Ação</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!recommendationAnalytics.product_ranking.length">
                  <td colspan="4">Sem dados.</td>
                </tr>
                <tr v-for="product in recommendationAnalytics.product_ranking" :key="product.product_id">
                  <td>
                    <strong>{{ product.name || product.product_id }}</strong>
                    <small>
                      {{ product.category || '-' }} · {{ product.brand || '-' }} ·
                      {{ product.measurement_table_name || 'Sem tabela' }}
                    </small>
                  </td>
                  <td>
                    <strong>{{ product.button_impressions }}</strong>
                    <small>{{ percent(product.usage_rate) }} taxa de uso</small>
                  </td>
                  <td>
                    <strong>{{ product.recommendations_generated }}</strong>
                    <small>{{ percent(product.selection_rate) }} aplicação</small>
                  </td>
                  <td>
                    <strong>{{ product.errors_total }}</strong>
                    <small>
                      {{ product.returns_exchanges }} devoluções/trocas ·
                      {{ product.blocked_outliers }} outliers
                    </small>
                    <small v-if="product.attention_flags.length">
                      {{ product.attention_flags.map(attentionLabel).join(' · ') }}
                    </small>
                  </td>
                  <td>
                    <button
                      class="btn btn-secondary analytics-inline-button"
                      type="button"
                      @click="applyRecommendationDrilldown(product.product_id, product.measurement_table_id)"
                    >
                      Drill-down
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Sem tabela</h2>
            <span>{{ recommendationAnalytics.summary.products_without_measurement_table }} produtos</span>
          </div>
          <div class="job-list">
            <div v-if="!recommendationAnalytics.products_without_measurement_table.length" class="empty-state">
              Tudo configurado.
            </div>
            <article
              v-for="product in recommendationAnalytics.products_without_measurement_table"
              :key="product.id"
              class="job-row"
            >
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
          <h2>Recomendações emitidas</h2>
          <div class="action-row">
            <span>{{ recommendationAnalytics.recommendation_report.meta.total }} no período</span>
            <button class="btn btn-secondary" type="button" @click="downloadRecommendationExport('recommendations')">
              <i class="fa-solid fa-file-export" aria-hidden="true"></i>
              Exportar CSV
            </button>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Data</th>
                <th>Produto</th>
                <th>Tabela</th>
                <th>Recomendação</th>
                <th>Origem</th>
                <th>Sinais</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!recommendationAnalytics.recommendation_report.data.length">
                <td colspan="6">Sem recomendações no período filtrado.</td>
              </tr>
              <tr
                v-for="row in recommendationAnalytics.recommendation_report.data"
                :key="row.recommendation_id"
              >
                <td>
                  <strong>#{{ row.recommendation_id }}</strong>
                  <small>{{ formatDateTime(row.created_at) }}</small>
                </td>
                <td>
                  <strong>{{ row.product_name || row.product_id || '-' }}</strong>
                  <small>{{ row.product_sku || row.category || '-' }}</small>
                </td>
                <td>
                  <strong>{{ row.measurement_table_name || 'Sem tabela' }}</strong>
                  <small>{{ row.brand }}</small>
                </td>
                <td>
                  <strong>{{ row.recommended_size || '-' }}</strong>
                  <small>{{ row.confidence }} de confiança · {{ eventLabel(row.status) }}</small>
                </td>
                <td>
                  <strong>{{ row.origin }}</strong>
                  <small>{{ deviceLabel(row.device_type || 'desktop') }} · {{ row.platform || 'widget' }}</small>
                </td>
                <td>
                  <strong>{{ row.purchases }} compras</strong>
                  <small>{{ row.returns + row.exchanges }} devoluções/trocas</small>
                  <small>
                    Feedback:
                    {{ row.feedback_helpful === null ? 'sem retorno' : (row.feedback_helpful ? 'ajudou' : 'não ajudou') }}
                  </small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-if="recommendationAnalytics.recommendation_report.meta.last_page > 1"
          class="analytics-pagination"
        >
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="recommendationAnalytics.recommendation_report.meta.current_page <= 1"
            @click="changeRecommendationPage(recommendationAnalytics.recommendation_report.meta.current_page - 1)"
          >
            Anterior
          </button>
          <span>
            Página {{ recommendationAnalytics.recommendation_report.meta.current_page }}
            de {{ recommendationAnalytics.recommendation_report.meta.last_page }}
          </span>
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="
              recommendationAnalytics.recommendation_report.meta.current_page
                >= recommendationAnalytics.recommendation_report.meta.last_page
            "
            @click="changeRecommendationPage(recommendationAnalytics.recommendation_report.meta.current_page + 1)"
          >
            Próxima
          </button>
        </div>
      </section>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Aprendizado</h2>
            <span>{{ recommendationAnalytics.summary.learning_review }} para revisar</span>
          </div>
          <div v-if="!recommendationAnalytics.learning_statuses.length" class="empty-state">Sem sinais de aprendizado.</div>
          <div v-else class="summary-strip">
            <span v-for="status in recommendationAnalytics.learning_statuses" :key="status.status">
              <strong>{{ status.count }}</strong>
              <small>{{ statusLabel(status.status) }}</small>
            </span>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Sinais comerciais</h2>
            <span>{{ recommendationAnalytics.commerce_signals.length }} tipos</span>
          </div>
          <div v-if="!recommendationAnalytics.commerce_signals.length" class="empty-state">
            Nenhum sinal comercial registrado.
          </div>
          <div v-else class="summary-strip">
            <span v-for="signal in recommendationAnalytics.commerce_signals" :key="signal.signal">
              <strong>{{ signal.count }}</strong>
              <small>{{ eventLabel(signal.signal) }}</small>
            </span>
          </div>
        </section>
      </div>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Sugestões de tabela</h2>
          <span>{{ recommendationAnalytics.measurement_table_insights.length }} tabelas analisadas</span>
        </div>
        <div class="job-list">
          <div v-if="!recommendationAnalytics.measurement_table_insights.length" class="empty-state">
            Ainda não há sinais suficientes de pedidos, devoluções ou feedback.
          </div>
          <article
            v-for="insight in recommendationAnalytics.measurement_table_insights"
            :key="insight.measurement_table_id"
            class="job-row"
          >
            <i class="fa-solid fa-brain" aria-hidden="true"></i>
            <span>
              <strong>{{ insight.table_name }}</strong>
              <small>{{ actionLabel(insight.suggested_action) }} · {{ insight.reason }}</small>
              <small>
                {{ insight.signals.total }} sinais · {{ insight.signals.purchases }} pedidos ·
                {{ insight.signals.returns }} devoluções/trocas · confiança {{ insight.confidence }}
              </small>
            </span>
          </article>
        </div>
      </section>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Outliers bloqueados</h2>
          <span>{{ recommendationAnalytics.outliers.length }} recentes</span>
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
              <tr v-if="!recommendationAnalytics.outliers.length">
                <td colspan="5">Sem outliers recentes.</td>
              </tr>
              <tr v-for="outlier in recommendationAnalytics.outliers" :key="outlier.id">
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
