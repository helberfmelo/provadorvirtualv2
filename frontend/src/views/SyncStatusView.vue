<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../services/api'

type SyncIssue = {
  severity: 'error' | 'warning'
  code: string
  product_id: string | null
  product_name: string | null
  grid_id: string | null
  line: number | null
  message: string
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
  platform: string
  event_type: string
  title: string
  status: string
  occurred_at: string | null
  error: string | null
  counters: {
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
}

const events = ref<SyncEvent[]>([])
const meta = ref<SyncMeta | null>(null)
const loading = ref(false)
const selectedId = ref<number | null>(null)
const statusFilter = ref('all')
const typeFilter = ref('all')

const filteredEvents = computed(() => events.value.filter((event) => {
  const matchesStatus = statusFilter.value === 'all'
    || (statusFilter.value === 'errors' && event.counters.errors > 0)
    || event.status === statusFilter.value
  const matchesType = typeFilter.value === 'all' || event.event_type === typeFilter.value

  return matchesStatus && matchesType
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
      <span>
        <strong>{{ meta?.with_errors || 0 }}</strong>
        <small>com erro</small>
      </span>
      <span>
        <strong>{{ meta?.warnings || 0 }}</strong>
        <small>com alerta</small>
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
        Tipo
        <select v-model="typeFilter">
          <option value="all">Todos</option>
          <option value="dry_run_import">Prévia BigShop</option>
          <option value="sync_products">API BigShop</option>
          <option value="xml_feed_sync">XML/feed</option>
        </select>
      </label>
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
            <span>{{ event.platform }} · {{ formatDate(event.occurred_at) }}</span>
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

          <div class="summary-strip">
            <span>
              <strong>{{ selectedEvent.counters.products }}</strong>
              <small>produtos</small>
            </span>
            <span>
              <strong>{{ selectedEvent.counters.variants }}</strong>
              <small>variações</small>
            </span>
            <span>
              <strong>{{ selectedEvent.counters.tables }}</strong>
              <small>tabelas</small>
            </span>
            <span>
              <strong>{{ selectedEvent.counters.errors }}</strong>
              <small>erros</small>
            </span>
            <span>
              <strong>{{ selectedEvent.counters.warnings }}</strong>
              <small>alertas</small>
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
              </span>
              <em :class="issue.severity">{{ issue.severity === 'error' ? 'Erro' : 'Alerta' }}</em>
            </article>
          </div>
        </template>
      </section>
    </div>
  </section>
</template>
