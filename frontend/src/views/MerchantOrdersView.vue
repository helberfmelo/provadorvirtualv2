<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

type OrdersOverviewPayload = {
  filters: {
    period: string
    date_from: string
    date_to: string
    status: string
    assisted: 'all' | 'yes' | 'no'
    source: string
    search: string
    per_page: number
  }
  summary: {
    orders_total: number
    assisted_orders: number
    unassisted_orders: number
    revenue_cents: number
    assisted_revenue_cents: number
    items_total: number
    quantity_total: number
  }
  status_breakdown: { status: string; count: number }[]
  source_breakdown: { source: string; count: number }[]
  top_products: {
    product_name: string
    quantity: number
    revenue_cents: number
    assisted_quantity: number
  }[]
  filter_options: {
    statuses: string[]
    sources: string[]
  }
}

type MerchantOrderRow = {
  id: number
  order_reference: string
  source: string
  source_platform: string | null
  status: string
  ordered_at: string | null
  items_count: number
  total_quantity: number
  total_amount_cents: number
  currency: string
  used_virtual_try_on: boolean
  assisted_items_count: number
  assisted_revenue_cents: number
  items: {
    id: number
    sku: string | null
    product_name: string
    ordered_size: string | null
    recommended_size: string | null
    recommendation_confidence: string | number | null
    quantity: number
    unit_price_cents: number
    line_total_cents: number
    used_virtual_try_on: boolean
  }[]
}

type OrdersListPayload = {
  data: MerchantOrderRow[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

type ImportPreviewRow = {
  line: number
  valid: boolean
  errors: string[]
  order_reference: string
  ordered_at: string | null
  status: string
  currency: string
  total_amount_cents: number
  sku: string | null
  product_name: string
  ordered_size: string | null
  quantity: number
  unit_price_cents: number
  line_total_cents: number
  source_platform: string
  product_id: number | null
  product_variant_id: number | null
}

type ImportPreviewPayload = {
  summary: {
    rows: number
    valid: number
    invalid: number
    imported_orders?: number
  }
  rows: ImportPreviewRow[]
}

const overview = ref<OrdersOverviewPayload | null>(null)
const orders = ref<OrdersListPayload | null>(null)
const loading = ref(false)
const error = ref('')
const currentPage = ref(1)
const perPage = 20

const filters = reactive({
  period: '30d',
  date_from: '',
  date_to: '',
  status: '',
  assisted: 'all',
  source: '',
  search: '',
})

const importState = reactive({
  fileName: '',
  content: '',
  loading: false,
  error: '',
  success: '',
  preview: null as ImportPreviewPayload | null,
})

const activeRangeLabel = computed(() => {
  if (!overview.value?.filters.date_from || !overview.value?.filters.date_to) {
    return 'Sem período aplicado'
  }

  return `${formatDate(overview.value.filters.date_from)} até ${formatDate(overview.value.filters.date_to)}`
})

const previewRows = computed(() => importState.preview?.rows.slice(0, 12) || [])

onMounted(() => {
  loadOrders()
})

async function loadOrders() {
  loading.value = true
  error.value = ''

  try {
    const [overviewResponse, ordersResponse] = await Promise.all([
      api.get('/orders/overview', { params: filterParams() }),
      api.get('/orders', {
        params: {
          ...filterParams(),
          page: String(currentPage.value),
          per_page: String(perPage),
        },
      }),
    ])

    overview.value = overviewResponse.data.data
    orders.value = ordersResponse.data
    syncFiltersFromOverview()
    currentPage.value = orders.value.meta.current_page || 1
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os pedidos.'
  } finally {
    loading.value = false
  }
}

function syncFiltersFromOverview() {
  if (!overview.value) {
    return
  }

  filters.period = overview.value.filters.period || '30d'
  filters.date_from = overview.value.filters.date_from || ''
  filters.date_to = overview.value.filters.date_to || ''
  filters.status = overview.value.filters.status || ''
  filters.assisted = overview.value.filters.assisted || 'all'
  filters.source = overview.value.filters.source || ''
  filters.search = overview.value.filters.search || ''
}

function filterParams() {
  const params: Record<string, string> = {
    period: filters.period,
    assisted: filters.assisted,
  }

  if (filters.period === 'custom') {
    if (filters.date_from) {
      params.date_from = filters.date_from
    }

    if (filters.date_to) {
      params.date_to = filters.date_to
    }
  }

  if (filters.status) {
    params.status = filters.status
  }

  if (filters.source) {
    params.source = filters.source
  }

  if (filters.search) {
    params.search = filters.search
  }

  return params
}

function applyFilters() {
  currentPage.value = 1
  loadOrders()
}

function resetFilters() {
  filters.period = '30d'
  filters.date_from = ''
  filters.date_to = ''
  filters.status = ''
  filters.assisted = 'all'
  filters.source = ''
  filters.search = ''
  currentPage.value = 1
  loadOrders()
}

function changePage(page: number) {
  if (!orders.value) {
    return
  }

  if (page < 1 || page > orders.value.meta.last_page) {
    return
  }

  currentPage.value = page
  loadOrders()
}

async function handleImportFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  importState.error = ''
  importState.success = ''
  importState.preview = null

  if (!file) {
    importState.fileName = ''
    importState.content = ''
    return
  }

  importState.fileName = file.name
  importState.content = await file.text()
}

async function previewImport() {
  if (!importState.content.trim()) {
    importState.error = 'Selecione um CSV com os pedidos antes de gerar a prévia.'
    return
  }

  importState.loading = true
  importState.error = ''
  importState.success = ''

  try {
    const { data } = await api.post('/orders/import', {
      content: importState.content,
      commit: false,
    })

    importState.preview = data
  } catch (requestError: any) {
    importState.error = requestError.response?.data?.message || 'Não foi possível gerar a prévia do CSV.'
  } finally {
    importState.loading = false
  }
}

async function commitImport() {
  if (!importState.content.trim()) {
    importState.error = 'Selecione um CSV com os pedidos antes de importar.'
    return
  }

  importState.loading = true
  importState.error = ''
  importState.success = ''

  try {
    const { data } = await api.post('/orders/import', {
      content: importState.content,
      commit: true,
    })

    importState.preview = data
    importState.success = `${data.summary.imported_orders || 0} pedido(s) importado(s) com sucesso.`
    currentPage.value = 1
    await loadOrders()
  } catch (requestError: any) {
    importState.error = requestError.response?.data?.message || 'Não foi possível importar os pedidos.'
  } finally {
    importState.loading = false
  }
}

function clearImport() {
  importState.fileName = ''
  importState.content = ''
  importState.loading = false
  importState.error = ''
  importState.success = ''
  importState.preview = null
}

async function downloadTemplate() {
  const response = await api.get('/orders/template', {
    responseType: 'blob',
  })

  const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', 'modelo-pedidos-provador-virtual.csv')
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

function formatMoney(cents: number, currency = 'BRL') {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency,
  }).format((cents || 0) / 100)
}

function formatDate(value: string | null) {
  if (!value) {
    return '-'
  }

  const date = value.includes('T') ? new Date(value) : new Date(`${value}T00:00:00`)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleDateString('pt-BR')
}

function formatDateTime(value: string | null) {
  if (!value) {
    return '-'
  }

  return value.slice(0, 16).replace('T', ' ')
}

function statusLabel(status: string) {
  const labels: Record<string, string> = {
    paid: 'Pago',
    pending: 'Pendente',
    cancelled: 'Cancelado',
    refunded: 'Estornado',
  }

  return labels[status] || status
}

function statusTone(status: string) {
  const tones: Record<string, string> = {
    paid: 'ok',
    pending: 'warning',
    cancelled: 'danger',
    refunded: 'neutral',
  }

  return tones[status] || 'neutral'
}

function assistedLabel(value: boolean) {
  return value ? 'Com provador' : 'Sem provador'
}

function confidenceLabel(value: string | number | null) {
  if (value === null || value === '') {
    return 'sem confiança'
  }

  return `${value}% de confiança`
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Pedidos</span>
        <h1>Pedidos e conversão assistida</h1>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadOrders">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p class="page-heading-help">
      Cruze pedidos, produtos, tamanhos comprados e uso do Provador sem expor dados pessoais do consumidor.
    </p>

    <p v-if="error" class="form-error">{{ error }}</p>

    <template v-if="overview && orders">
      <section class="panel-main admin-form analytics-filter-panel">
        <div class="subsection-heading">
          <h2>Filtros operacionais</h2>
          <span>{{ activeRangeLabel }}</span>
        </div>

        <form class="analytics-filter-grid" @submit.prevent="applyFilters">
          <label>
            <span>Período</span>
            <select v-model="filters.period">
              <option value="today">Hoje</option>
              <option value="7d">Últimos 7 dias</option>
              <option value="30d">Últimos 30 dias</option>
              <option value="90d">Últimos 90 dias</option>
              <option value="custom">Personalizado</option>
            </select>
          </label>

          <label v-if="filters.period === 'custom'">
            <span>De</span>
            <input v-model="filters.date_from" type="date" />
          </label>

          <label v-if="filters.period === 'custom'">
            <span>Até</span>
            <input v-model="filters.date_to" type="date" />
          </label>

          <label>
            <span>Status</span>
            <select v-model="filters.status">
              <option value="">Todos</option>
              <option v-for="status in overview.filter_options.statuses" :key="status" :value="status">
                {{ statusLabel(status) }}
              </option>
            </select>
          </label>

          <label>
            <span>Origem</span>
            <select v-model="filters.source">
              <option value="">Todas</option>
              <option v-for="source in overview.filter_options.sources" :key="source" :value="source">
                {{ source }}
              </option>
            </select>
          </label>

          <label>
            <span>Uso do provador</span>
            <select v-model="filters.assisted">
              <option value="all">Todos</option>
              <option value="yes">Somente assistidos</option>
              <option value="no">Somente sem provador</option>
            </select>
          </label>

          <label class="orders-search-field">
            <span>Busca</span>
            <input
              v-model="filters.search"
              type="search"
              placeholder="Pedido, SKU ou produto"
            />
          </label>

          <div class="analytics-filter-actions">
            <button class="btn" type="submit" :disabled="loading">Aplicar filtros</button>
            <button class="btn btn-secondary" type="button" :disabled="loading" @click="resetFilters">
              Limpar
            </button>
          </div>
        </form>
      </section>

      <div class="metric-grid analytics-metric-grid">
        <article class="metric-card">
          <i class="fa-solid fa-receipt" aria-hidden="true"></i>
          <strong>{{ overview.summary.orders_total }}</strong>
          <span>pedidos no período</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
          <strong>{{ overview.summary.assisted_orders }}</strong>
          <span>{{ overview.summary.unassisted_orders }} sem provador</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-sack-dollar" aria-hidden="true"></i>
          <strong>{{ formatMoney(overview.summary.revenue_cents) }}</strong>
          <span>{{ formatMoney(overview.summary.assisted_revenue_cents) }} assistidos</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-box-open" aria-hidden="true"></i>
          <strong>{{ overview.summary.items_total }}</strong>
          <span>{{ overview.summary.quantity_total }} unidades</span>
        </article>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Status dos pedidos</h2>
            <span>{{ overview.status_breakdown.length }} status</span>
          </div>
          <div v-if="!overview.status_breakdown.length" class="empty-state">Sem pedidos no período filtrado.</div>
          <div v-else class="summary-strip">
            <span v-for="row in overview.status_breakdown" :key="row.status">
              <strong>{{ row.count }}</strong>
              <small>{{ statusLabel(row.status) }}</small>
            </span>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Origem dos pedidos</h2>
            <span>{{ overview.source_breakdown.length }} fontes</span>
          </div>
          <div v-if="!overview.source_breakdown.length" class="empty-state">Sem origem registrada.</div>
          <div v-else class="summary-strip">
            <span v-for="row in overview.source_breakdown" :key="row.source">
              <strong>{{ row.count }}</strong>
              <small>{{ row.source }}</small>
            </span>
          </div>
        </section>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Produtos com mais pedidos</h2>
            <span>{{ overview.top_products.length }} em destaque</span>
          </div>
          <div v-if="!overview.top_products.length" class="empty-state">Sem produtos com pedidos no período.</div>
          <div v-else class="job-list">
            <article v-for="product in overview.top_products" :key="product.product_name" class="job-row">
              <i class="fa-solid fa-shirt" aria-hidden="true"></i>
              <span>
                <strong>{{ product.product_name }}</strong>
                <small>
                  {{ product.quantity }} unidades · {{ formatMoney(product.revenue_cents) }} em receita
                </small>
                <small>{{ product.assisted_quantity }} unidades com apoio do provador</small>
              </span>
            </article>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Importação CSV</h2>
            <div class="action-row">
              <button class="btn btn-secondary" type="button" @click="downloadTemplate">
                <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                Baixar modelo
              </button>
            </div>
          </div>

          <p class="page-heading-help analytics-section-help">
            Use o fallback por CSV quando a plataforma ainda não enviar pedidos automaticamente.
          </p>

          <div class="orders-import-stack">
            <label>
              <span>Arquivo CSV</span>
              <input type="file" accept=".csv,text/csv" @change="handleImportFile" />
              <small>{{ importState.fileName || 'Nenhum arquivo selecionado.' }}</small>
            </label>

            <div class="action-row compact">
              <button class="btn btn-secondary" type="button" :disabled="importState.loading" @click="previewImport">
                Prévia
              </button>
              <button
                class="btn"
                type="button"
                :disabled="importState.loading || !importState.preview || importState.preview.summary.invalid > 0"
                @click="commitImport"
              >
                Importar
              </button>
              <button class="btn btn-secondary" type="button" :disabled="importState.loading" @click="clearImport">
                Limpar
              </button>
            </div>

            <p v-if="importState.error" class="form-error">{{ importState.error }}</p>
            <p v-if="importState.success" class="form-success">{{ importState.success }}</p>

            <div v-if="importState.preview" class="orders-import-preview">
              <div class="summary-strip">
                <span>
                  <strong>{{ importState.preview.summary.rows }}</strong>
                  <small>linhas</small>
                </span>
                <span>
                  <strong>{{ importState.preview.summary.valid }}</strong>
                  <small>válidas</small>
                </span>
                <span>
                  <strong>{{ importState.preview.summary.invalid }}</strong>
                  <small>inválidas</small>
                </span>
              </div>

              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Linha</th>
                      <th>Pedido</th>
                      <th>Produto</th>
                      <th>Tamanho</th>
                      <th>Total</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in previewRows" :key="`${row.line}-${row.order_reference}-${row.product_name}`">
                      <td>
                        <strong>#{{ row.line }}</strong>
                        <small>{{ row.valid ? 'ok' : 'revisar' }}</small>
                      </td>
                      <td>
                        <strong>{{ row.order_reference || '-' }}</strong>
                        <small>{{ formatDateTime(row.ordered_at) }}</small>
                      </td>
                      <td>
                        <strong>{{ row.product_name || '-' }}</strong>
                        <small>{{ row.sku || 'Sem SKU' }}</small>
                      </td>
                      <td>
                        <strong>{{ row.ordered_size || '-' }}</strong>
                        <small>{{ row.quantity }} unidade(s)</small>
                      </td>
                      <td>
                        <strong>{{ formatMoney(row.line_total_cents, row.currency) }}</strong>
                        <small>{{ row.source_platform }}</small>
                      </td>
                      <td>
                        <span class="status-pill" :class="row.valid ? 'ok' : 'danger'">
                          {{ row.valid ? statusLabel(row.status) : 'Linha inválida' }}
                        </span>
                        <small v-if="row.errors.length">{{ row.errors.join(' | ') }}</small>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <p v-if="importState.preview.rows.length > previewRows.length" class="page-heading-help analytics-section-help">
                Mostrando {{ previewRows.length }} de {{ importState.preview.rows.length }} linhas da prévia.
              </p>
            </div>
          </div>
        </section>
      </div>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Pedidos registrados</h2>
          <span>{{ orders.meta.total }} no período</span>
        </div>

        <div class="table-wrap merchant-orders-table">
          <table>
            <thead>
              <tr>
                <th>Pedido</th>
                <th>Status</th>
                <th>Itens</th>
                <th>Valor</th>
                <th>Uso do provador</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!orders.data.length">
                <td colspan="5">Nenhum pedido encontrado com os filtros atuais.</td>
              </tr>
              <tr v-for="order in orders.data" :key="order.id">
                <td>
                  <strong>{{ order.order_reference }}</strong>
                  <small>{{ formatDateTime(order.ordered_at) }}</small>
                  <small>{{ order.source }} · {{ order.source_platform || 'sem plataforma' }}</small>
                </td>
                <td>
                  <span class="status-pill" :class="statusTone(order.status)">
                    {{ statusLabel(order.status) }}
                  </span>
                </td>
                <td>
                  <div class="order-line-list">
                    <article v-for="item in order.items" :key="item.id">
                      <strong>{{ item.product_name }}</strong>
                      <small>
                        {{ item.sku || 'Sem SKU' }} · tam. {{ item.ordered_size || '-' }} · qtd. {{ item.quantity }}
                      </small>
                      <small v-if="item.recommended_size">
                        recomendado {{ item.recommended_size }} · {{ confidenceLabel(item.recommendation_confidence) }}
                      </small>
                    </article>
                  </div>
                </td>
                <td>
                  <strong>{{ formatMoney(order.total_amount_cents, order.currency) }}</strong>
                  <small>{{ order.total_quantity }} unidades · {{ order.items_count }} item(ns)</small>
                </td>
                <td>
                  <span class="status-pill" :class="order.used_virtual_try_on ? 'ok' : 'neutral'">
                    {{ assistedLabel(order.used_virtual_try_on) }}
                  </span>
                  <small>{{ order.assisted_items_count }} item(ns) assistidos</small>
                  <small>{{ formatMoney(order.assisted_revenue_cents, order.currency) }} em receita assistida</small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="orders.meta.last_page > 1" class="analytics-pagination">
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="orders.meta.current_page <= 1"
            @click="changePage(orders.meta.current_page - 1)"
          >
            Anterior
          </button>
          <span>
            Página {{ orders.meta.current_page }} de {{ orders.meta.last_page }}
            · {{ orders.meta.total }} pedidos
          </span>
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="orders.meta.current_page >= orders.meta.last_page"
            @click="changePage(orders.meta.current_page + 1)"
          >
            Próxima
          </button>
        </div>
      </section>
    </template>
  </section>
</template>
