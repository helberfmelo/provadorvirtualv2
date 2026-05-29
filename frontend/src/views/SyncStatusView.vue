<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'

type SyncIssue = {
  severity: 'error' | 'warning'
  code: string
  product_id: string | null
  product_name: string | null
  grid_id: string | null
  line: number | null
  message: string
  action_url?: string | null
  action_label?: string | null
  rule_url?: string | null
  related?: {
    product: boolean
    rule: boolean
  }
}

type SyncSampleProduct = {
  external_product_id: string
  name: string | null
  sku?: string | null
  category?: string | null
  grid_count: number
  sizes: string[]
}

type SyncEvent = {
  id: number
  execution_key: string
  platform: string
  event_type: string
  title: string
  origin: {
    method: string
    source: string
    label: string
  }
  status: string
  occurred_at: string | null
  duration_seconds: number | null
  error: string | null
  counters: {
    total: number
    inserted: number
    updated: number
    ignored: number
    unknown: number
    unchanged: number
    products: number
    variants: number
    tables: number
    errors: number
    warnings: number
  }
  summary: Record<string, string | number | null>
  sample_products: SyncSampleProduct[]
  issues: SyncIssue[]
}

type SyncMeta = {
  total: number
  with_errors: number
  warnings: number
  last_status: string | null
  totals?: SyncEvent['counters']
  by_origin?: Record<string, number>
  by_status?: Record<string, number>
  timeline?: Array<{
    id: number
    status: string
    title: string
    origin: SyncEvent['origin']
    occurred_at: string | null
    total: number
    errors: number
    warnings: number
  }>
}

type CounterKey = keyof SyncEvent['counters']

const events = ref<SyncEvent[]>([])
const meta = ref<SyncMeta | null>(null)
const loading = ref(false)
const selectedId = ref<number | null>(null)
const compareBaseId = ref<number | null>(null)
const compareTargetId = ref<number | null>(null)
const statusFilter = ref('all')
const typeFilter = ref('all')
const originFilter = ref('all')

const filteredEvents = computed(() => events.value.filter((event) => {
  const matchesStatus = statusFilter.value === 'all'
    || (statusFilter.value === 'errors' && event.counters.errors > 0)
    || event.status === statusFilter.value
  const matchesType = typeFilter.value === 'all' || event.event_type === typeFilter.value
  const matchesOrigin = originFilter.value === 'all' || event.origin.method === originFilter.value

  return matchesStatus && matchesType && matchesOrigin
}))

const selectedEvent = computed(() => {
  return filteredEvents.value.find((event) => event.id === selectedId.value)
    || filteredEvents.value[0]
    || null
})

const summaryRows = computed(() => {
  if (!selectedEvent.value) {
    return []
  }

  return Object.entries(selectedEvent.value.summary)
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .slice(0, 10)
})

const summaryCounters: Array<{ key: CounterKey, label: string }> = [
  { key: 'total', label: 'total' },
  { key: 'inserted', label: 'inseridos' },
  { key: 'updated', label: 'atualizados' },
  { key: 'ignored', label: 'ignorados' },
  { key: 'unknown', label: 'desconhecidos' },
  { key: 'unchanged', label: 'sem alteração' },
  { key: 'errors', label: 'com erro' },
  { key: 'warnings', label: 'alertas' },
]

const detailCounters: Array<{ key: CounterKey, label: string }> = [
  { key: 'total', label: 'total' },
  { key: 'inserted', label: 'inseridos' },
  { key: 'updated', label: 'atualizados' },
  { key: 'ignored', label: 'ignorados' },
  { key: 'unknown', label: 'desconhecidos' },
  { key: 'unchanged', label: 'sem alteração' },
  { key: 'products', label: 'produtos' },
  { key: 'variants', label: 'variações' },
  { key: 'tables', label: 'tabelas' },
  { key: 'errors', label: 'erros' },
  { key: 'warnings', label: 'alertas' },
]

const comparisonCounters: Array<{ key: CounterKey, label: string }> = [
  { key: 'total', label: 'Total' },
  { key: 'inserted', label: 'Inseridos' },
  { key: 'updated', label: 'Atualizados' },
  { key: 'ignored', label: 'Ignorados' },
  { key: 'unknown', label: 'Desconhecidos' },
  { key: 'unchanged', label: 'Sem alteração' },
  { key: 'errors', label: 'Com erro' },
  { key: 'warnings', label: 'Alertas' },
]

const timeline = computed(() => {
  if (meta.value?.timeline?.length) {
    return meta.value.timeline
  }

  return events.value.slice(0, 16).map((event) => ({
    id: event.id,
    status: event.status,
    title: event.title,
    origin: event.origin,
    occurred_at: event.occurred_at,
    total: event.counters.total,
    errors: event.counters.errors,
    warnings: event.counters.warnings,
  }))
})

const originOptions = computed(() => {
  const origins = new Map<string, string>()
  events.value.forEach((event) => {
    origins.set(event.origin.method, originMethodLabel(event.origin.method))
  })

  return Array.from(origins.entries()).map(([value, label]) => ({ value, label }))
})

const compareBase = computed(() => events.value.find((event) => event.id === compareBaseId.value) || null)
const compareTarget = computed(() => events.value.find((event) => event.id === compareTargetId.value) || null)
const comparisonRows = computed(() => {
  if (!compareBase.value || !compareTarget.value || compareBase.value.id === compareTarget.value.id) {
    return []
  }

  return comparisonCounters.map((row) => {
    const base = Number(compareBase.value?.counters[row.key] || 0)
    const target = Number(compareTarget.value?.counters[row.key] || 0)

    return {
      ...row,
      base,
      target,
      delta: target - base,
    }
  })
})

onMounted(() => {
  loadEvents()
})

async function loadEvents() {
  loading.value = true

  try {
    const { data } = await api.get('/integrations/sync-history')
    events.value = data.data || []
    meta.value = data.meta || null
    selectedId.value = events.value[0]?.id || null
    compareBaseId.value = events.value[1]?.id || events.value[0]?.id || null
    compareTargetId.value = events.value[0]?.id || null
  } finally {
    loading.value = false
  }
}

function statusLabel(status: string) {
  return {
    success: 'Sucesso',
    warning: 'Atenção',
    failed: 'Falhou',
    error: 'Erro',
    ready: 'Pronto',
    connected: 'Conectado',
  }[status] || status
}

function statusClass(status: string) {
  return ['success', 'ready', 'connected'].includes(status) ? 'ok' : status
}

function originMethodLabel(method: string) {
  return {
    manual: 'Manual',
    scheduled: 'Agendada',
    webhook: 'Webhook',
    xml_feed: 'XML/feed',
    api: 'API',
  }[method] || method
}

function deltaLabel(value: number) {
  if (value > 0) {
    return `+${value}`
  }

  return String(value)
}

function selectTimelineItem(id: number) {
  selectedId.value = id
}

function formatDate(value: string | null) {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function formatDuration(seconds: number | null) {
  if (seconds === null || Number.isNaN(seconds)) {
    return '-'
  }

  if (seconds < 60) {
    return `${seconds}s`
  }

  return `${Math.floor(seconds / 60)}min ${seconds % 60}s`
}
</script>

<template>
  <section class="dashboard app-workspace sync-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Sincronização</span>
        <h1>Histórico e erros</h1>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadEvents">
        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <div class="summary-strip sync-summary">
      <span>
        <strong>{{ meta?.total || 0 }}</strong>
        <small>execuções</small>
      </span>
      <span v-for="counter in summaryCounters" :key="counter.key">
        <strong>{{ meta?.totals?.[counter.key] || 0 }}</strong>
        <small>{{ counter.label }}</small>
      </span>
      <span>
        <strong>{{ statusLabel(meta?.last_status || '-') }}</strong>
        <small>último status</small>
      </span>
    </div>

    <div class="sync-filters panel-main">
      <label>
        Status
        <select v-model="statusFilter">
          <option value="all">Todos</option>
          <option value="errors">Com erro</option>
          <option value="warning">Atenção</option>
          <option value="success">Sucesso</option>
        </select>
      </label>
      <label>
        Origem
        <select v-model="originFilter">
          <option value="all">Todas</option>
          <option v-for="origin in originOptions" :key="origin.value" :value="origin.value">
            {{ origin.label }}
          </option>
        </select>
      </label>
      <label>
        Tipo
        <select v-model="typeFilter">
          <option value="all">Todos</option>
          <option value="dry_run_import">Prévia BigShop</option>
          <option value="sync_products">API BigShop</option>
          <option value="xml_feed_sync">XML/feed</option>
        </select>
      </label>
    </div>

    <div v-if="timeline.length" class="sync-timeline panel-main" aria-label="Linha do tempo de sincronizações">
      <button
        v-for="item in timeline"
        :key="item.id"
        type="button"
        :class="{ active: selectedEvent?.id === item.id }"
        @click="selectTimelineItem(item.id)"
      >
        <i :class="statusClass(item.status)" aria-hidden="true"></i>
        <strong>{{ formatDate(item.occurred_at) }}</strong>
        <small>{{ item.origin.label }}</small>
        <em>{{ item.total }} itens</em>
      </button>
    </div>

    <div v-if="events.length > 1" class="sync-compare-panel panel-main">
      <div class="subsection-heading">
        <h2>Comparar execuções</h2>
        <span>{{ compareBase?.title || '-' }} vs. {{ compareTarget?.title || '-' }}</span>
      </div>
      <div class="sync-compare-controls">
        <label>
          Base
          <select v-model.number="compareBaseId">
            <option v-for="event in events" :key="`base-${event.id}`" :value="event.id">
              {{ formatDate(event.occurred_at) }} · {{ event.origin.label }}
            </option>
          </select>
        </label>
        <label>
          Comparar com
          <select v-model.number="compareTargetId">
            <option v-for="event in events" :key="`target-${event.id}`" :value="event.id">
              {{ formatDate(event.occurred_at) }} · {{ event.origin.label }}
            </option>
          </select>
        </label>
      </div>
      <div v-if="comparisonRows.length" class="sync-compare-grid">
        <span v-for="row in comparisonRows" :key="row.key">
          <small>{{ row.label }}</small>
          <strong>{{ row.target }}</strong>
          <em :class="{ positive: row.delta > 0, negative: row.delta < 0 }">
            {{ deltaLabel(row.delta) }}
          </em>
        </span>
      </div>
      <div v-else class="empty-state">Escolha duas execuções diferentes para comparar.</div>
    </div>

    <div class="app-grid sync-grid">
      <aside class="panel-list sync-event-list">
        <div v-if="loading" class="empty-state">Carregando sincronizações...</div>
        <div v-else-if="!filteredEvents.length" class="empty-state">Nenhuma sincronização encontrada.</div>
        <template v-else>
          <button
            v-for="event in filteredEvents"
            :key="event.id"
            class="list-row sync-list-row"
            :class="{ active: selectedEvent?.id === event.id }"
            type="button"
            @click="selectedId = event.id"
          >
            <span class="sync-row-title">
              <strong>{{ event.title }}</strong>
              <em :class="statusClass(event.status)">{{ statusLabel(event.status) }}</em>
            </span>
            <span class="sync-origin-pill">{{ event.origin.label }}</span>
            <span>{{ event.platform }} · {{ formatDate(event.occurred_at) }} · {{ formatDuration(event.duration_seconds) }}</span>
            <small>
              {{ event.counters.total }} total · {{ event.counters.inserted }} inseridos · {{ event.counters.updated }} atualizados
            </small>
            <small>{{ event.counters.errors }} erros · {{ event.counters.warnings }} alertas</small>
          </button>
        </template>
      </aside>

      <section class="panel-main sync-detail-panel">
        <div v-if="!selectedEvent" class="empty-state">Selecione uma execução para ver os detalhes.</div>
        <template v-else>
          <div class="subsection-heading">
            <h2>{{ selectedEvent.title }}</h2>
            <span>{{ formatDate(selectedEvent.occurred_at) }}</span>
          </div>
          <div class="sync-detail-meta">
            <span class="sync-origin-pill">{{ selectedEvent.origin.label }}</span>
            <span>{{ selectedEvent.execution_key }}</span>
            <span>Duração {{ formatDuration(selectedEvent.duration_seconds) }}</span>
          </div>

          <div class="summary-strip sync-counter-grid">
            <span v-for="counter in detailCounters" :key="counter.key">
              <strong>{{ selectedEvent.counters[counter.key] }}</strong>
              <small>{{ counter.label }}</small>
            </span>
          </div>

          <div v-if="summaryRows.length" class="sync-key-values">
            <span v-for="[key, value] in summaryRows" :key="key">
              <small>{{ key }}</small>
              <strong>{{ value }}</strong>
            </span>
          </div>

          <div v-if="selectedEvent.sample_products.length" class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>SKU</th>
                  <th>Categoria</th>
                  <th>Grades</th>
                  <th>Tamanhos</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product in selectedEvent.sample_products" :key="product.external_product_id">
                  <td>
                    <strong>{{ product.name || product.external_product_id }}</strong>
                    <small>{{ product.external_product_id }}</small>
                  </td>
                  <td>{{ product.sku || '-' }}</td>
                  <td>{{ product.category || '-' }}</td>
                  <td>{{ product.grid_count }}</td>
                  <td>{{ product.sizes?.length ? product.sizes.join(', ') : '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="subsection-heading compact-heading">
            <h2>Erros por produto</h2>
            <span>{{ selectedEvent.issues.length }}</span>
          </div>

          <div v-if="!selectedEvent.issues.length" class="empty-state">Nenhum erro por produto nesta execução.</div>
          <div v-else class="dry-run-issues sync-issues">
            <article v-for="issue in selectedEvent.issues" :key="`${issue.code}-${issue.product_id || issue.grid_id || issue.line || issue.message}`">
              <i class="fa-solid" :class="issue.severity === 'error' ? 'fa-circle-xmark' : 'fa-circle-exclamation'" aria-hidden="true"></i>
              <span>
                <strong>{{ issue.product_name || issue.product_id || issue.grid_id || issue.code }}</strong>
                <small>{{ issue.message }}</small>
                <small>{{ issue.code }}<template v-if="issue.line"> · linha {{ issue.line }}</template></small>
                <span class="sync-issue-actions">
                  <RouterLink v-if="issue.action_url" :to="issue.action_url">
                    {{ issue.action_label || 'Abrir item' }}
                  </RouterLink>
                  <RouterLink v-if="issue.rule_url && issue.related?.product" :to="issue.rule_url">
                    Revisar regra
                  </RouterLink>
                </span>
              </span>
              <em :class="issue.severity">{{ issue.severity === 'error' ? 'Erro' : 'Alerta' }}</em>
            </article>
          </div>
        </template>
      </section>
    </div>
  </section>
</template>
