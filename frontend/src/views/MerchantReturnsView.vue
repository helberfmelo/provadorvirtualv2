<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { showFeedback } from '../services/saveFeedback'
import { api } from '../services/api'

type ReturnsOverviewPayload = {
  filters: {
    period: string
    date_from: string
    date_to: string
    status: string
    reason: string
    assisted: 'all' | 'yes' | 'no'
    source: string
    search: string
    per_page: number
  }
  summary: {
    returns_total: number
    assisted_returns: number
    unassisted_returns: number
    refund_amount_cents: number
    assisted_refund_cents: number
    items_total: number
    quantity_total: number
    exchanges_total: number
  }
  status_breakdown: { status: string; count: number }[]
  reason_breakdown: { reason: string; count: number }[]
  source_breakdown: { source: string; count: number }[]
  top_products: {
    product_name: string
    quantity: number
    refund_amount_cents: number
    assisted_quantity: number
  }[]
  filter_options: {
    statuses: string[]
    reasons: string[]
    sources: string[]
  }
}

type MerchantReturnRow = {
  id: number
  return_reference: string
  order_reference: string | null
  source: string
  source_platform: string | null
  status: string
  processed_at: string | null
  items_count: number
  total_quantity: number
  refund_amount_cents: number
  used_virtual_try_on: boolean
  assisted_items_count: number
  assisted_refund_cents: number
  items: {
    id: number
    sku: string | null
    product_name: string
    ordered_at: string | null
    returned_at: string | null
    ordered_size: string | null
    ideal_size: string | null
    returned_size: string | null
    exchanged_to_size: string | null
    return_reason: string
    status: string
    quantity: number
    refund_amount_cents: number
    used_virtual_try_on: boolean
    recommendation_confidence: string | number | null
  }[]
}

type ReturnsListPayload = {
  data: MerchantReturnRow[]
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
  line: number | null
  valid: boolean
  errors: string[]
  return_reference: string
  order_reference: string
  ordered_at: string | null
  processed_at: string | null
  status: string
  return_reason: string
  sku: string | null
  product_name: string
  ordered_size: string | null
  ideal_size: string | null
  returned_size: string | null
  exchanged_to_size: string | null
  quantity: number
  refund_amount_cents: number
  source_platform: string
}

type ImportPreviewPayload = {
  summary: {
    rows: number
    valid: number
    invalid: number
    imported_returns?: number
  }
  columns: {
    available: string[]
    mapping: Record<string, string | null>
    labels: Record<string, string>
    required: string[]
  }
  rows: ImportPreviewRow[]
}

const overview = ref<ReturnsOverviewPayload | null>(null)
const returnsList = ref<ReturnsListPayload | null>(null)
const loading = ref(false)
const error = ref('')
const currentPage = ref(1)
const perPage = 20

const filters = reactive({
  period: '30d',
  date_from: '',
  date_to: '',
  status: '',
  reason: '',
  assisted: 'all',
  source: '',
  search: '',
})

const importState = reactive({
  fileName: '',
  format: 'csv' as 'csv' | 'xlsx' | 'json',
  content: '',
  loading: false,
  preview: null as ImportPreviewPayload | null,
  mapping: {} as Record<string, string | null>,
})

const activeRangeLabel = computed(() => {
  if (!overview.value?.filters.date_from || !overview.value?.filters.date_to) {
    return 'Sem período aplicado'
  }

  return `${formatDate(overview.value.filters.date_from)} até ${formatDate(overview.value.filters.date_to)}`
})

const previewRows = computed(() => importState.preview?.rows.slice(0, 14) || [])
const mappingEntries = computed(() => {
  if (!importState.preview) {
    return []
  }

  return Object.entries(importState.preview.columns.labels).map(([field, label]) => ({
    field,
    label,
    required: importState.preview?.columns.required.includes(field) || false,
  }))
})

onMounted(() => {
  loadReturns()
})

async function loadReturns() {
  loading.value = true
  error.value = ''

  try {
    const [overviewResponse, listResponse] = await Promise.all([
      api.get('/returns/overview', { params: filterParams() }),
      api.get('/returns', {
        params: {
          ...filterParams(),
          page: String(currentPage.value),
          per_page: String(perPage),
        },
      }),
    ])

    overview.value = overviewResponse.data.data
    returnsList.value = listResponse.data
    syncFiltersFromOverview()
    currentPage.value = returnsList.value.meta.current_page || 1
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar as devoluções.'
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
  filters.reason = overview.value.filters.reason || ''
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

  if (filters.reason) {
    params.reason = filters.reason
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
  loadReturns()
}

function resetFilters() {
  filters.period = '30d'
  filters.date_from = ''
  filters.date_to = ''
  filters.status = ''
  filters.reason = ''
  filters.assisted = 'all'
  filters.source = ''
  filters.search = ''
  currentPage.value = 1
  loadReturns()
}

function changePage(page: number) {
  if (!returnsList.value || page < 1 || page > returnsList.value.meta.last_page) {
    return
  }

  currentPage.value = page
  loadReturns()
}

async function handleImportFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  clearImport()

  if (!file) {
    return
  }

  importState.fileName = file.name
  importState.format = file.name.toLowerCase().endsWith('.xlsx')
    ? 'xlsx'
    : file.name.toLowerCase().endsWith('.json')
      ? 'json'
      : 'csv'

  try {
    importState.content = importState.format === 'xlsx'
      ? await readFileAsBase64(file)
      : await file.text()

    await previewImport()
  } catch (readError: any) {
    showFeedback({
      status: 'error',
      title: 'Arquivo inválido',
      message: readError?.message || 'Não foi possível ler o arquivo selecionado.',
    })
  } finally {
    input.value = ''
  }
}

function readFileAsBase64(file: File) {
  return new Promise<string>((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result || '').split(',')[1] || '')
    reader.onerror = () => reject(reader.error)
    reader.readAsDataURL(file)
  })
}

async function previewImport() {
  if (!importState.content.trim()) {
    showFeedback({
      status: 'info',
      title: 'Selecione um arquivo',
      message: 'Escolha um CSV, XLSX ou JSON antes de gerar a prévia.',
    })
    return
  }

  importState.loading = true

  try {
    const { data } = await api.post('/returns/import', {
      format: importState.format,
      filename: importState.fileName || undefined,
      content: importState.content,
      commit: false,
      mapping: importState.mapping,
    })

    importState.preview = data
    importState.mapping = { ...data.columns.mapping, ...importState.mapping }
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Prévia não gerada',
      message: requestError.response?.data?.message || 'Não foi possível gerar a prévia das devoluções.',
    })
  } finally {
    importState.loading = false
  }
}

async function commitImport() {
  if (!importState.content.trim()) {
    return
  }

  importState.loading = true

  try {
    const { data } = await api.post('/returns/import', {
      format: importState.format,
      filename: importState.fileName || undefined,
      content: importState.content,
      commit: true,
      mapping: importState.mapping,
    })

    importState.preview = data
    showFeedback({
      status: 'success',
      title: 'Devoluções importadas',
      message: `${data.summary.imported_returns || 0} devolução(ões)/troca(s) gravada(s) com sucesso.`,
    })
    currentPage.value = 1
    await loadReturns()
  } catch (requestError: any) {
    if (requestError.response?.data?.columns) {
      importState.preview = requestError.response.data
    }

    showFeedback({
      status: 'error',
      title: 'Importação bloqueada',
      message: requestError.response?.data?.message || 'Não foi possível importar as devoluções.',
    })
  } finally {
    importState.loading = false
  }
}

function clearImport() {
  importState.fileName = ''
  importState.format = 'csv'
  importState.content = ''
  importState.loading = false
  importState.preview = null
  importState.mapping = {}
}

async function downloadTemplate(format: 'csv' | 'xlsx') {
  const response = await api.get('/returns/template', {
    params: { format },
    responseType: 'blob',
  })

  const extension = format === 'xlsx' ? 'xlsx' : 'csv'
  const mimeType = format === 'xlsx'
    ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    : 'text/csv;charset=utf-8'
  const url = window.URL.createObjectURL(new Blob([response.data], { type: mimeType }))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `modelo-devolucoes-provador-virtual.${extension}`)
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
    returned: 'Devolvido',
    exchange: 'Trocado',
    pending: 'Pendente',
    rejected: 'Rejeitado',
  }

  return labels[status] || status
}

function statusTone(status: string) {
  const tones: Record<string, string> = {
    returned: 'danger',
    exchange: 'warning',
    pending: 'neutral',
    rejected: 'neutral',
  }

  return tones[status] || 'neutral'
}

function reasonLabel(reason: string) {
  const labels: Record<string, string> = {
    size_too_small: 'Ficou pequeno',
    size_too_large: 'Ficou grande',
    fit_issue: 'Caimento',
    defect: 'Defeito',
    changed_mind: 'Arrependimento',
    other: 'Outros',
    unknown: 'Não informado',
  }

  return labels[reason] || reason
}

function assistedLabel(value: boolean) {
  return value ? 'Com provador' : 'Sem provador'
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Devoluções</span>
        <h1>Devoluções, trocas e motivos</h1>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadReturns">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p class="page-heading-help">
      Compare devoluções e trocas com uso do Provador para entender tamanho comprado, motivo e impacto real na operação.
    </p>

    <p v-if="error" class="form-error">{{ error }}</p>

    <template v-if="overview && returnsList">
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
            <span>Motivo</span>
            <select v-model="filters.reason">
              <option value="">Todos</option>
              <option v-for="reason in overview.filter_options.reasons" :key="reason" :value="reason">
                {{ reasonLabel(reason) }}
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
            <input v-model="filters.search" type="search" placeholder="Pedido, SKU, protocolo ou produto" />
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
          <i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i>
          <strong>{{ overview.summary.returns_total }}</strong>
          <span>devoluções e trocas</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
          <strong>{{ overview.summary.assisted_returns }}</strong>
          <span>{{ overview.summary.unassisted_returns }} sem provador</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i>
          <strong>{{ formatMoney(overview.summary.refund_amount_cents) }}</strong>
          <span>{{ formatMoney(overview.summary.assisted_refund_cents) }} assistidos</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-right-left" aria-hidden="true"></i>
          <strong>{{ overview.summary.exchanges_total }}</strong>
          <span>{{ overview.summary.quantity_total }} unidades envolvidas</span>
        </article>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Status das ocorrências</h2>
            <span>{{ overview.status_breakdown.length }} grupos</span>
          </div>
          <div v-if="!overview.status_breakdown.length" class="empty-state">Sem devoluções no período filtrado.</div>
          <div v-else class="summary-strip">
            <span v-for="row in overview.status_breakdown" :key="row.status">
              <strong>{{ row.count }}</strong>
              <small>{{ statusLabel(row.status) }}</small>
            </span>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Motivos normalizados</h2>
            <span>{{ overview.reason_breakdown.length }} motivos</span>
          </div>
          <div v-if="!overview.reason_breakdown.length" class="empty-state">Sem motivo registrado no período.</div>
          <div v-else class="summary-strip">
            <span v-for="row in overview.reason_breakdown" :key="row.reason">
              <strong>{{ row.count }}</strong>
              <small>{{ reasonLabel(row.reason) }}</small>
            </span>
          </div>
        </section>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Produtos mais impactados</h2>
            <span>{{ overview.top_products.length }} em destaque</span>
          </div>
          <div v-if="!overview.top_products.length" class="empty-state">Sem produtos impactados no período.</div>
          <div v-else class="job-list">
            <article v-for="product in overview.top_products" :key="product.product_name" class="job-row">
              <i class="fa-solid fa-shirt" aria-hidden="true"></i>
              <span>
                <strong>{{ product.product_name }}</strong>
                <small>{{ product.quantity }} unidade(s) · {{ formatMoney(product.refund_amount_cents) }}</small>
                <small>{{ product.assisted_quantity }} com uso do provador</small>
              </span>
            </article>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Importação assistida</h2>
            <div class="action-row">
              <button class="btn btn-secondary" type="button" @click="downloadTemplate('csv')">
                <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                Modelo CSV
              </button>
              <button class="btn btn-secondary" type="button" @click="downloadTemplate('xlsx')">
                <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                Modelo XLSX
              </button>
            </div>
          </div>

          <p class="page-heading-help analytics-section-help">
            Envie CSV, XLSX ou JSON exportado da plataforma. A prévia sugere o mapeamento, aponta linha e coluna com erro e só libera a gravação quando tudo estiver consistente.
          </p>

          <div class="orders-import-stack">
            <label>
              <span>Arquivo</span>
              <input type="file" accept=".csv,.xlsx,.json,text/csv,application/json" @change="handleImportFile" />
              <small>{{ importState.fileName || 'Nenhum arquivo selecionado.' }}</small>
            </label>

            <div v-if="importState.preview" class="returns-mapping-grid">
              <label v-for="entry in mappingEntries" :key="entry.field">
                <span>{{ entry.label }} <small v-if="entry.required">(obrigatório)</small></span>
                <select v-model="importState.mapping[entry.field]">
                  <option value="">Não mapear</option>
                  <option v-for="column in importState.preview.columns.available" :key="column" :value="column">
                    {{ column }}
                  </option>
                </select>
              </label>
            </div>

            <div class="action-row compact">
              <button class="btn btn-secondary" type="button" :disabled="importState.loading" @click="previewImport">
                Atualizar prévia
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
                      <th>Motivo</th>
                      <th>Status</th>
                      <th>Valor</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in previewRows" :key="`${row.line}-${row.return_reference}-${row.product_name}`">
                      <td>
                        <strong>#{{ row.line || '-' }}</strong>
                        <small>{{ row.valid ? 'ok' : 'revisar' }}</small>
                      </td>
                      <td>
                        <strong>{{ row.order_reference || '-' }}</strong>
                        <small>{{ row.return_reference || '-' }}</small>
                      </td>
                      <td>
                        <strong>{{ row.product_name || '-' }}</strong>
                        <small>{{ row.sku || 'Sem SKU' }}</small>
                      </td>
                      <td>
                        <strong>{{ reasonLabel(row.return_reason) }}</strong>
                        <small>tam. {{ row.ordered_size || '-' }} → {{ row.ideal_size || row.exchanged_to_size || '-' }}</small>
                      </td>
                      <td>
                        <span class="status-pill" :class="row.valid ? statusTone(row.status) : 'danger'">
                          {{ row.valid ? statusLabel(row.status) : 'Linha inválida' }}
                        </span>
                        <small v-if="row.errors.length">{{ row.errors.join(' | ') }}</small>
                      </td>
                      <td>
                        <strong>{{ formatMoney(row.refund_amount_cents) }}</strong>
                        <small>{{ row.quantity }} unidade(s)</small>
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
          <h2>Ocorrências registradas</h2>
          <span>{{ returnsList.meta.total }} no período</span>
        </div>

        <div class="table-wrap merchant-orders-table">
          <table>
            <thead>
              <tr>
                <th>Protocolo</th>
                <th>Status</th>
                <th>Itens</th>
                <th>Valor</th>
                <th>Uso do provador</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!returnsList.data.length">
                <td colspan="5">Nenhuma devolução ou troca encontrada com os filtros atuais.</td>
              </tr>
              <tr v-for="returnRow in returnsList.data" :key="returnRow.id">
                <td>
                  <strong>{{ returnRow.return_reference }}</strong>
                  <small>Pedido {{ returnRow.order_reference || '-' }}</small>
                  <small>{{ formatDateTime(returnRow.processed_at) }} · {{ returnRow.source }} · {{ returnRow.source_platform || 'sem plataforma' }}</small>
                </td>
                <td>
                  <span class="status-pill" :class="statusTone(returnRow.status)">
                    {{ statusLabel(returnRow.status) }}
                  </span>
                </td>
                <td>
                  <div class="order-line-list">
                    <article v-for="item in returnRow.items" :key="item.id">
                      <strong>{{ item.product_name }}</strong>
                      <small>{{ item.sku || 'Sem SKU' }} · {{ reasonLabel(item.return_reason) }}</small>
                      <small>
                        comprado {{ item.ordered_size || '-' }}
                        <template v-if="item.status === 'exchange'">
                          · trocado para {{ item.exchanged_to_size || '-' }}
                        </template>
                        <template v-else>
                          · ideal {{ item.ideal_size || '-' }}
                        </template>
                      </small>
                    </article>
                  </div>
                </td>
                <td>
                  <strong>{{ formatMoney(returnRow.refund_amount_cents) }}</strong>
                  <small>{{ returnRow.total_quantity }} unidade(s) · {{ returnRow.items_count }} item(ns)</small>
                </td>
                <td>
                  <span class="status-pill" :class="returnRow.used_virtual_try_on ? 'ok' : 'neutral'">
                    {{ assistedLabel(returnRow.used_virtual_try_on) }}
                  </span>
                  <small>{{ returnRow.assisted_items_count }} item(ns) assistidos</small>
                  <small>{{ formatMoney(returnRow.assisted_refund_cents) }} ligados ao provador</small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="returnsList.meta.last_page > 1" class="analytics-pagination">
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="returnsList.meta.current_page <= 1"
            @click="changePage(returnsList.meta.current_page - 1)"
          >
            Anterior
          </button>
          <span>
            Página {{ returnsList.meta.current_page }} de {{ returnsList.meta.last_page }}
            · {{ returnsList.meta.total }} ocorrências
          </span>
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="returnsList.meta.current_page >= returnsList.meta.last_page"
            @click="changePage(returnsList.meta.current_page + 1)"
          >
            Próxima
          </button>
        </div>
      </section>
    </template>
  </section>
</template>
