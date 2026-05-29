<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../services/api'
import type { MeasurementTableOption, Product } from '../services/merchantTypes'
import { showFeedback } from '../services/saveFeedback'

const products = ref<Product[]>([])
const measurementTables = ref<MeasurementTableOption[]>([])
const selectedProductIds = ref<number[]>([])
const route = useRoute()
const loading = ref(false)
const linking = ref(false)
const error = ref('')
const bootstrapped = ref(false)

const filters = reactive({
  search: '',
  status: '',
  table: '',
  readiness: '',
  category: '',
  normalized_category: '',
  brand: '',
  normalized_brand: '',
  gender: '',
  age_group: '',
  modeling: '',
  source: '',
  error: '',
})

const bulkMeasurementTableId = ref<number | ''>('')
const bulkPreview = ref<BulkPreview | null>(null)
const confirmBulkConflicts = ref(false)
const lastBulkAction = ref<{ batch_id: string; product_ids: number[]; label: string } | null>(null)

type ProductTabKey = 'all' | 'ready' | 'pending' | 'without_measurement_table' | 'sync_error' | 'inactive'

type BulkPreviewItem = {
  product_id: number
  name: string
  sku: string | null
  category: string | null
  brand: string | null
  fit_profile: string | null
  sizes: string[]
  current_table_id: number | null
  current_table_name: string | null
  target_table_id: number
  target_table_name: string
  conflict: boolean
  same_table: boolean
  without_table: boolean
  target_matches_recommendation: boolean
  recommendation?: {
    table_id: number
    table_name: string
    score: number
    reasons: string[]
  } | null
}

type BulkPreview = {
  summary: {
    requested: number
    target_table: {
      id: number
      name: string
      product_type: string
    }
    without_table: number
    same_table: number
    conflicts: number
    recommended_target_matches: number
  }
  preview: BulkPreviewItem[]
}

type ProductFilterOptions = {
  categories: string[]
  normalized_categories: string[]
  brands: string[]
  normalized_brands: string[]
  genders: string[]
  age_groups: string[]
  modelings: string[]
  sources: string[]
  statuses: string[]
}

const emptyTabs = () => ({
  all: 0,
  ready: 0,
  pending: 0,
  without_measurement_table: 0,
  sync_error: 0,
  inactive: 0,
})

const emptyFilterOptions = (): ProductFilterOptions => ({
  categories: [],
  normalized_categories: [],
  brands: [],
  normalized_brands: [],
  genders: [],
  age_groups: [],
  modelings: [],
  sources: [],
  statuses: [],
})

const summary = reactive({
  total: 0,
  filtered: 0,
  with_measurement_table: 0,
  tabs: emptyTabs(),
})

const filterOptions = ref<ProductFilterOptions>(emptyFilterOptions())

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
  from: null as number | null,
  to: null as number | null,
})

const selectedCount = computed(() => selectedProductIds.value.length)
const hasSelection = computed(() => selectedCount.value > 0)
const visibleProductIds = computed(() => products.value.map((product) => product.id))
const allVisibleSelected = computed(() => (
  visibleProductIds.value.length > 0
  && visibleProductIds.value.every((id) => selectedProductIds.value.includes(id))
))
const activeTab = computed<ProductTabKey>(() => {
  if (filters.readiness === 'ready') return 'ready'
  if (filters.readiness === 'pending') return 'pending'
  if (filters.readiness === 'without_measurement_table') return 'without_measurement_table'
  if (filters.readiness === 'sync_error' || filters.error === 'sync_error') return 'sync_error'
  if (filters.status === 'inactive') return 'inactive'

  return 'all'
})
const tabItems = computed(() => [
  { key: 'all' as ProductTabKey, label: 'Todos', count: summary.tabs.all, icon: 'fa-table-list' },
  { key: 'ready' as ProductTabKey, label: 'Prontos', count: summary.tabs.ready, icon: 'fa-circle-check' },
  { key: 'pending' as ProductTabKey, label: 'Pendentes', count: summary.tabs.pending, icon: 'fa-triangle-exclamation' },
  { key: 'without_measurement_table' as ProductTabKey, label: 'Sem tabela', count: summary.tabs.without_measurement_table, icon: 'fa-ruler-combined' },
  { key: 'sync_error' as ProductTabKey, label: 'Com erro', count: summary.tabs.sync_error, icon: 'fa-circle-exclamation' },
  { key: 'inactive' as ProductTabKey, label: 'Desativados', count: summary.tabs.inactive, icon: 'fa-pause' },
])
const pageRangeLabel = computed(() => {
  if (loading.value) {
    return 'carregando'
  }

  if (!pagination.total) {
    return '0 produtos'
  }

  return `${pagination.from || 1}-${pagination.to || products.value.length} de ${pagination.total} produtos`
})
const activeShortcutLabel = computed(() => {
  const labels: Record<string, string> = {
    ready: 'Prontos para publicar',
    pending: 'Pendentes',
    without_measurement_table: 'Sem tabela',
    without_modeling: 'Sem modelagem',
    without_category: 'Sem categoria',
    sync_error: 'Com erro de sincronização',
  }

  if (filters.readiness) {
    return labels[filters.readiness] || ''
  }

  if (filters.status === 'inactive') {
    return 'Produtos desativados'
  }

  if (filters.error === 'sync_error') {
    return 'Com erro de sincronização'
  }

  return ''
})
const activeFilterCount = computed(() => Object.entries(filters)
  .filter(([key, value]) => key !== 'readiness' && key !== 'status' ? Boolean(String(value).trim()) : Boolean(String(value).trim()))
  .length)
const canPreviousPage = computed(() => pagination.current_page > 1)
const canNextPage = computed(() => pagination.current_page < pagination.last_page)
const selectedTargetTable = computed(() => measurementTables.value.find((table) => table.id === Number(bulkMeasurementTableId.value)) || null)
const hasBulkPreview = computed(() => Boolean(bulkPreview.value))
const bulkPreviewHasConflicts = computed(() => (bulkPreview.value?.summary.conflicts || 0) > 0)
const canConfirmBulkPreview = computed(() => Boolean(bulkPreview.value) && (!bulkPreviewHasConflicts.value || confirmBulkConflicts.value))
const withoutTableCount = computed(() => summary.tabs.without_measurement_table || 0)
const bulkPreviewRows = computed(() => bulkPreview.value?.preview || [])
const bulkTargetLabel = computed(() => bulkPreview.value?.summary.target_table.name || selectedTargetTable.value?.name || 'tabela selecionada')

let filterTimer: ReturnType<typeof window.setTimeout> | null = null

onMounted(async () => {
  applyRouteFilters()
  await loadData()
  bootstrapped.value = true
})

watch(() => route.query, () => {
  applyRouteFilters()
}, { deep: true })

watch(filters, () => {
  if (!bootstrapped.value) {
    return
  }

  scheduleLoadProducts()
}, { deep: true })

watch([selectedProductIds, bulkMeasurementTableId], () => {
  bulkPreview.value = null
  confirmBulkConflicts.value = false
}, { deep: true })

function applyRouteFilters() {
  const shortcut = typeof route.query.filtro === 'string' ? route.query.filtro : ''
  const status = typeof route.query.status === 'string' ? route.query.status : ''
  const table = typeof route.query.tabela === 'string' ? route.query.tabela : ''
  const search = typeof route.query.busca === 'string' ? route.query.busca : ''

  filters.status = status
  filters.table = table
  filters.search = search
  filters.readiness = {
    prontos: 'ready',
    pendentes: 'pending',
    sem_tabela: 'without_measurement_table',
    sem_modelagem: 'without_modeling',
    sem_categoria: 'without_category',
    erro_sync: 'sync_error',
  }[shortcut] || ''
}

function clearAllFilters() {
  filters.search = ''
  filters.status = ''
  filters.table = ''
  filters.readiness = ''
  filters.category = ''
  filters.normalized_category = ''
  filters.brand = ''
  filters.normalized_brand = ''
  filters.gender = ''
  filters.age_group = ''
  filters.modeling = ''
  filters.source = ''
  filters.error = ''
}

function setTab(tab: ProductTabKey) {
  filters.readiness = ''
  filters.status = ''
  filters.table = ''
  filters.error = ''

  if (tab === 'ready') {
    filters.readiness = 'ready'
  } else if (tab === 'pending') {
    filters.readiness = 'pending'
  } else if (tab === 'without_measurement_table') {
    filters.readiness = 'without_measurement_table'
  } else if (tab === 'sync_error') {
    filters.readiness = 'sync_error'
  } else if (tab === 'inactive') {
    filters.status = 'inactive'
  }
}

function scheduleLoadProducts() {
  if (filterTimer) {
    window.clearTimeout(filterTimer)
  }

  filterTimer = window.setTimeout(() => {
    loadProducts(1)
  }, 250)
}

async function loadData() {
  loading.value = true
  error.value = ''

  try {
    const [productsResponse, tablesResponse] = await Promise.all([
      api.get('/products', { params: productQueryParams(1) }),
      api.get('/measurement-tables'),
    ])
    applyProductsResponse(productsResponse.data)
    measurementTables.value = tablesResponse.data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os produtos.'
  } finally {
    loading.value = false
  }
}

async function loadProducts(page = pagination.current_page) {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/products', { params: productQueryParams(page) })
    applyProductsResponse(data)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os produtos.'
  } finally {
    loading.value = false
  }
}

function productQueryParams(page: number) {
  const params: Record<string, string | number> = {
    page,
    per_page: pagination.per_page,
  }

  Object.entries(filters).forEach(([key, value]) => {
    const normalized = String(value || '').trim()
    if (!normalized) {
      return
    }

    if (key === 'error' && normalized === 'sync_error') {
      params.sync_error = 1
      return
    }

    params[key] = normalized
  })

  return params
}

function applyProductsResponse(data: any) {
  products.value = data.data || []
  const responseSummary = data.summary || {}
  summary.total = Number(responseSummary.total || 0)
  summary.filtered = Number(responseSummary.filtered || 0)
  summary.with_measurement_table = Number(responseSummary.with_measurement_table || 0)
  summary.tabs = { ...emptyTabs(), ...(responseSummary.tabs || {}) }
  filterOptions.value = { ...emptyFilterOptions(), ...(responseSummary.filters || {}) }

  const meta = data.meta || {}
  pagination.current_page = Number(meta.current_page || 1)
  pagination.last_page = Number(meta.last_page || 1)
  pagination.per_page = Number(meta.per_page || pagination.per_page)
  pagination.total = Number(meta.total || products.value.length)
  pagination.from = meta.from ?? (products.value.length ? 1 : null)
  pagination.to = meta.to ?? products.value.length
  selectedProductIds.value = selectedProductIds.value.filter((id) => products.value.some((product) => product.id === id))
}

function toggleProduct(productId: number) {
  selectedProductIds.value = selectedProductIds.value.includes(productId)
    ? selectedProductIds.value.filter((id) => id !== productId)
    : [...selectedProductIds.value, productId]
}

function selectAllVisible() {
  selectedProductIds.value = Array.from(new Set([
    ...selectedProductIds.value,
    ...visibleProductIds.value,
  ]))
}

function clearSelection() {
  selectedProductIds.value = []
}

async function previewSelectedProducts() {
  error.value = ''

  if (!hasSelection.value) {
    return
  }

  if (!bulkMeasurementTableId.value) {
    error.value = 'Escolha uma tabela para vincular aos produtos selecionados.'
    return
  }

  linking.value = true

  try {
    const { data } = await api.patch('/products/bulk-measurement-table', {
      action: 'preview',
      product_ids: selectedProductIds.value,
      measurement_table_id: bulkMeasurementTableId.value,
    })
    bulkPreview.value = data
    confirmBulkConflicts.value = !data.summary?.conflicts
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível montar a prévia do vínculo.'
  } finally {
    linking.value = false
  }
}

async function confirmSelectedProductsLink() {
  if (!bulkPreview.value || !bulkMeasurementTableId.value) {
    return
  }

  error.value = ''
  linking.value = true
  const productIds = [...selectedProductIds.value]

  try {
    const { data } = await api.patch('/products/bulk-measurement-table', {
      action: 'apply',
      product_ids: productIds,
      measurement_table_id: bulkMeasurementTableId.value,
      confirm_conflicts: confirmBulkConflicts.value,
    })
    showFeedback({
      status: 'success',
      title: 'Tabela vinculada',
      message: `${data.summary?.updated || selectedCount.value} produto(s) atualizados com a tabela selecionada.`,
    })
    if (data.summary?.batch_id) {
      lastBulkAction.value = {
        batch_id: data.summary.batch_id,
        product_ids: productIds,
        label: selectedTargetTable.value?.name || 'tabela selecionada',
      }
    }
    bulkMeasurementTableId.value = ''
    bulkPreview.value = null
    confirmBulkConflicts.value = false
    clearSelection()
    await loadProducts(pagination.current_page)
  } catch (requestError: any) {
    if (requestError.response?.status === 409 && requestError.response?.data?.preview) {
      bulkPreview.value = requestError.response.data
      confirmBulkConflicts.value = false
    }
    error.value = requestError.response?.data?.message || 'Não foi possível vincular a tabela aos produtos selecionados.'
  } finally {
    linking.value = false
  }
}

async function undoLastBulkLink() {
  if (!lastBulkAction.value) {
    return
  }

  error.value = ''
  linking.value = true

  try {
    const { data } = await api.patch('/products/bulk-measurement-table', {
      action: 'undo',
      product_ids: lastBulkAction.value.product_ids,
      batch_id: lastBulkAction.value.batch_id,
    })
    showFeedback({
      status: 'success',
      title: 'Vínculo desfeito',
      message: `${data.summary?.updated || 0} produto(s) voltaram para a tabela anterior.`,
    })
    lastBulkAction.value = null
    await loadProducts(pagination.current_page)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível desfazer o vínculo em massa.'
  } finally {
    linking.value = false
  }
}

function dismissBulkPreview() {
  bulkPreview.value = null
  confirmBulkConflicts.value = false
}

async function removeProduct(product: Product) {
  await api.delete(`/products/${product.id}`)
  showFeedback({
    status: 'success',
    title: 'Produto removido',
    message: 'O produto foi removido da empresa.',
  })
  selectedProductIds.value = selectedProductIds.value.filter((id) => id !== product.id)
  await loadProducts(pagination.current_page)
}

function goToPage(page: number) {
  if (page < 1 || page > pagination.last_page || page === pagination.current_page) {
    return
  }

  loadProducts(page)
}

function genderLabel(value: string | null | undefined) {
  return {
    female: 'Feminino',
    male: 'Masculino',
    unisex: 'Unissex',
    kids: 'Infantil',
  }[String(value || '')] || value || '-'
}

function ageGroupLabel(value: string | null | undefined) {
  return {
    adult: 'Adulto',
    kids: 'Infantil',
    baby: 'Bebê',
    teen: 'Teen',
  }[String(value || '')] || value || '-'
}

function statusLabel(value: string) {
  return {
    active: 'Ativo',
    draft: 'Rascunho',
    inactive: 'Inativo',
  }[value] || value
}

function sourceLabel(value: string) {
  return {
    manual: 'Manual',
    import: 'Importação',
    bigshop: 'BigShop',
    api: 'API',
    ai: 'IA',
  }[value] || value
}

function productReadinessText(product: Product) {
  if (product.readiness_status === 'ready') {
    return 'Pronto'
  }

  if (product.has_sync_error) {
    return 'Erro sync'
  }

  if (!product.measurement_table_id) {
    return 'Sem tabela'
  }

  if (!product.fit_profile) {
    return 'Sem modelagem'
  }

  if (!product.category) {
    return 'Sem categoria'
  }

  if (product.status === 'inactive') {
    return 'Inativo'
  }

  return 'Pendente'
}

function readinessTone(product: Product) {
  if (product.readiness_status === 'ready') {
    return 'ok'
  }

  if (product.has_sync_error) {
    return 'danger'
  }

  return 'warning'
}

function compactSizes(product: Product) {
  const sizes = product.size_labels || []

  if (!sizes.length) {
    return product.variants_count ? `${product.variants_count} variações` : '-'
  }

  const visible = sizes.slice(0, 6).join(', ')
  return sizes.length > 6 ? `${visible} +${sizes.length - 6}` : visible
}

function compactPreviewSizes(item: BulkPreviewItem) {
  if (!item.sizes.length) {
    return '-'
  }

  const visible = item.sizes.slice(0, 5).join(', ')
  return item.sizes.length > 5 ? `${visible} +${item.sizes.length - 5}` : visible
}

function previewRecommendationText(item: BulkPreviewItem) {
  if (!item.recommendation) {
    return 'Sem recomendação'
  }

  const reasons = item.recommendation.reasons.length
    ? ` por ${item.recommendation.reasons.map(recommendationReasonLabel).join(', ')}`
    : ''
  return `${item.recommendation.table_name}${reasons}`
}

function recommendationReasonLabel(reason: string) {
  return {
    genero: 'gênero',
  }[reason] || reason
}

function previewTone(item: BulkPreviewItem) {
  if (item.same_table) {
    return 'neutral'
  }

  if (item.conflict) {
    return item.target_matches_recommendation ? 'warning' : 'danger'
  }

  if (item.target_matches_recommendation) {
    return 'ok'
  }

  return 'warning'
}

function previewStatusText(item: BulkPreviewItem) {
  if (item.same_table) {
    return 'Já usa esta tabela'
  }

  if (item.conflict) {
    return item.target_matches_recommendation ? 'Substituir com recomendação' : 'Conflito'
  }

  if (item.target_matches_recommendation) {
    return 'Recomendado'
  }

  return 'Novo vínculo'
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Produtos</span>
        <h1>Produtos</h1>
        <p>Organize catálogo, categorias e variações antes de vincular cada item à tabela de medidas certa.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadData">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink class="btn btn-primary" to="/app/produtos/novo">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          Novo produto
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Produtos cadastrados</h2>
        <span>{{ pageRangeLabel }}</span>
      </div>

      <div class="product-status-tabs" role="tablist" aria-label="Status operacional dos produtos">
        <button
          v-for="tab in tabItems"
          :key="tab.key"
          type="button"
          :class="{ active: activeTab === tab.key }"
          @click="setTab(tab.key)"
        >
          <i class="fa-solid" :class="tab.icon" aria-hidden="true"></i>
          <span>{{ tab.label }}</span>
          <strong>{{ tab.count }}</strong>
        </button>
      </div>

      <div v-if="withoutTableCount" class="measurement-queue-panel">
        <div>
          <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
          <span>
            <strong>{{ withoutTableCount }} produto(s) sem tabela</strong>
            <small>Fila operacional para revisar e vincular em lote.</small>
          </span>
        </div>
        <button class="btn btn-secondary btn-compact" type="button" @click="setTab('without_measurement_table')">
          Ver fila
        </button>
      </div>

      <div class="product-list-toolbar">
        <input v-model="filters.search" type="search" placeholder="Buscar produto, SKU, categoria ou marca" />
        <select v-model="filters.status" aria-label="Filtrar status">
          <option value="">Status</option>
          <option value="active">Ativos</option>
          <option value="draft">Rascunhos</option>
          <option value="inactive">Inativos</option>
        </select>
        <select v-model="filters.table" aria-label="Filtrar tabela">
          <option value="">Tabela</option>
          <option value="with_table">Com tabela</option>
          <option value="without_table">Sem tabela</option>
          <option v-for="table in measurementTables" :key="table.id" :value="String(table.id)">
            {{ table.name }}
          </option>
        </select>
        <select v-model="filters.readiness" aria-label="Filtrar pendência">
          <option value="">Pendência</option>
          <option value="ready">Prontos</option>
          <option value="pending">Pendentes</option>
          <option value="without_measurement_table">Sem tabela</option>
          <option value="without_modeling">Sem modelagem</option>
          <option value="without_category">Sem categoria</option>
          <option value="sync_error">Erro de sync</option>
        </select>
        <select v-model="filters.category" aria-label="Filtrar categoria">
          <option value="">Categoria</option>
          <option v-for="category in filterOptions.categories" :key="category" :value="category">
            {{ category }}
          </option>
        </select>
        <select v-model="filters.normalized_category" aria-label="Filtrar categoria normalizada">
          <option value="">Categoria normalizada</option>
          <option v-for="category in filterOptions.normalized_categories" :key="category" :value="category">
            {{ category }}
          </option>
        </select>
        <select v-model="filters.brand" aria-label="Filtrar marca">
          <option value="">Marca</option>
          <option v-for="brand in filterOptions.brands" :key="brand" :value="brand">
            {{ brand }}
          </option>
        </select>
        <select v-model="filters.normalized_brand" aria-label="Filtrar marca normalizada">
          <option value="">Marca normalizada</option>
          <option v-for="brand in filterOptions.normalized_brands" :key="brand" :value="brand">
            {{ brand }}
          </option>
        </select>
        <select v-model="filters.gender" aria-label="Filtrar gênero">
          <option value="">Gênero</option>
          <option v-for="gender in filterOptions.genders" :key="gender" :value="gender">
            {{ genderLabel(gender) }}
          </option>
        </select>
        <select v-model="filters.age_group" aria-label="Filtrar faixa etária">
          <option value="">Faixa etária</option>
          <option v-for="ageGroup in filterOptions.age_groups" :key="ageGroup" :value="ageGroup">
            {{ ageGroupLabel(ageGroup) }}
          </option>
        </select>
        <select v-model="filters.modeling" aria-label="Filtrar modelagem">
          <option value="">Modelagem</option>
          <option v-for="modeling in filterOptions.modelings" :key="modeling" :value="modeling">
            {{ modeling }}
          </option>
        </select>
        <select v-model="filters.source" aria-label="Filtrar origem do dado">
          <option value="">Origem</option>
          <option v-for="source in filterOptions.sources" :key="source" :value="source">
            {{ sourceLabel(source) }}
          </option>
        </select>
        <select v-model="filters.error" aria-label="Filtrar erro">
          <option value="">Erro</option>
          <option value="sync_error">Erro de sync</option>
        </select>
        <button class="btn btn-secondary btn-compact" type="button" :disabled="!activeFilterCount" @click="clearAllFilters">
          <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
          Filtros
        </button>
        <span class="toolbar-divider"></span>
        <select v-model.number="bulkMeasurementTableId" :disabled="!hasSelection" aria-label="Tabela para vincular">
          <option value="">Vincular tabela</option>
          <option v-for="table in measurementTables" :key="table.id" :value="table.id">
            {{ table.name }}
          </option>
        </select>
        <button class="btn btn-primary btn-compact" type="button" :disabled="!hasSelection || !bulkMeasurementTableId || linking" @click="previewSelectedProducts">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
          Prévia
        </button>
        <button
          v-if="lastBulkAction"
          class="btn btn-secondary btn-compact"
          type="button"
          :disabled="linking"
          :title="`Desfazer ${lastBulkAction.label}`"
          @click="undoLastBulkLink"
        >
          <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
          Desfazer
        </button>
        <button class="btn btn-secondary btn-compact" type="button" :disabled="!products.length || allVisibleSelected" @click="selectAllVisible">
          Todos
        </button>
        <button class="btn btn-secondary btn-compact" type="button" :disabled="!hasSelection" @click="clearSelection">
          Limpar
        </button>
        <strong>{{ selectedCount }} sel.</strong>
      </div>
      <p v-if="activeShortcutLabel || activeFilterCount" class="filter-hint">
        Filtro ativo: <strong>{{ activeShortcutLabel || `${activeFilterCount} filtro(s)` }}</strong>
        <button type="button" @click="clearAllFilters">Limpar</button>
      </p>

      <section v-if="hasBulkPreview" class="bulk-preview-panel" aria-live="polite">
        <div class="bulk-preview-header">
          <div>
            <span class="eyebrow">Vínculo em massa</span>
            <h3>Prévia para {{ bulkTargetLabel }}</h3>
            <p>{{ bulkPreview?.summary.requested }} produto(s) selecionados antes de aplicar a mudança.</p>
          </div>
          <button type="button" class="icon-link" title="Fechar prévia" @click="dismissBulkPreview">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>

        <div class="bulk-preview-summary">
          <article>
            <span>Sem tabela</span>
            <strong>{{ bulkPreview?.summary.without_table || 0 }}</strong>
          </article>
          <article>
            <span>Conflitos</span>
            <strong>{{ bulkPreview?.summary.conflicts || 0 }}</strong>
          </article>
          <article>
            <span>Já vinculados</span>
            <strong>{{ bulkPreview?.summary.same_table || 0 }}</strong>
          </article>
          <article>
            <span>Recomendados</span>
            <strong>{{ bulkPreview?.summary.recommended_target_matches || 0 }}</strong>
          </article>
        </div>

        <div class="table-wrap bulk-preview-table">
          <table>
            <thead>
              <tr>
                <th>Produto</th>
                <th>Tamanhos</th>
                <th>Tabela atual</th>
                <th>Recomendação</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in bulkPreviewRows" :key="item.product_id">
                <td>
                  <strong>{{ item.name }}</strong>
                  <small>{{ item.sku || item.category || 'sem SKU' }}</small>
                </td>
                <td>{{ compactPreviewSizes(item) }}</td>
                <td>{{ item.current_table_name || 'Sem tabela' }}</td>
                <td>{{ previewRecommendationText(item) }}</td>
                <td>
                  <span class="status-pill" :class="previewTone(item)">
                    {{ previewStatusText(item) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <label v-if="bulkPreviewHasConflicts" class="bulk-preview-confirm">
          <input v-model="confirmBulkConflicts" type="checkbox" />
          <span>Confirmo substituir a tabela atual dos produtos em conflito.</span>
        </label>

        <div class="bulk-preview-actions">
          <button class="btn btn-secondary btn-compact" type="button" @click="dismissBulkPreview">
            Cancelar
          </button>
          <button class="btn btn-primary btn-compact" type="button" :disabled="!canConfirmBulkPreview || linking" @click="confirmSelectedProductsLink">
            <i class="fa-solid fa-link" aria-hidden="true"></i>
            Confirmar vínculo
          </button>
        </div>
      </section>

      <div class="table-wrap products-table-wrap">
        <table>
          <thead>
            <tr>
              <th class="selection-column"></th>
              <th>Produto</th>
              <th>Categoria</th>
              <th>Marca</th>
              <th>Gênero</th>
              <th>Faixa</th>
              <th>Modelagem</th>
              <th>Tamanhos</th>
              <th>Tabela</th>
              <th>Prontidão</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!products.length">
              <td colspan="12">Nenhum produto encontrado.</td>
            </tr>
            <tr v-for="product in products" :key="product.id" :class="{ 'is-selected': selectedProductIds.includes(product.id) }">
              <td class="selection-column">
                <input
                  type="checkbox"
                  :checked="selectedProductIds.includes(product.id)"
                  :aria-label="`Selecionar ${product.name}`"
                  @change="toggleProduct(product.id)"
                />
              </td>
              <td>
                <strong>{{ product.name }}</strong>
                <small>{{ product.sku || product.external_product_id || 'sem SKU' }}</small>
              </td>
              <td>
                <strong class="brand-cell-name">{{ product.category || '-' }}</strong>
                <small v-if="product.normalized_category?.name">Normalizada: {{ product.normalized_category.name }}</small>
              </td>
              <td>
                <strong class="brand-cell-name">{{ product.brand || '-' }}</strong>
                <small v-if="product.normalized_brand?.name">Normalizada: {{ product.normalized_brand.name }}</small>
              </td>
              <td>{{ genderLabel(product.gender) }}</td>
              <td>{{ ageGroupLabel(product.age_group) }}</td>
              <td>{{ product.fit_profile || '-' }}</td>
              <td>
                <span class="sizes-chip-list">{{ compactSizes(product) }}</span>
              </td>
              <td>{{ product.measurement_table?.name || 'Sem tabela' }}</td>
              <td>
                <span class="status-pill" :class="readinessTone(product)">
                  {{ productReadinessText(product) }}
                </span>
                <small>{{ product.source_label || sourceLabel(product.data_source || 'manual') }}</small>
              </td>
              <td>
                <span class="status-pill" :class="{ ok: product.status === 'active', warning: product.status === 'draft', neutral: product.status === 'inactive' }">
                  {{ statusLabel(product.status) }}
                </span>
              </td>
              <td class="row-actions">
                <RouterLink class="icon-link" :to="`/app/produtos/${product.id}/editar`" title="Editar">
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button type="button" title="Remover produto" @click="removeProduct(product)">
                  <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination-bar">
        <span>{{ pageRangeLabel }}</span>
        <div>
          <button class="btn btn-secondary btn-compact" type="button" :disabled="!canPreviousPage || loading" @click="goToPage(pagination.current_page - 1)">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
          </button>
          <strong>Página {{ pagination.current_page }} de {{ pagination.last_page }}</strong>
          <button class="btn btn-secondary btn-compact" type="button" :disabled="!canNextPage || loading" @click="goToPage(pagination.current_page + 1)">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
          </button>
          <select v-model.number="pagination.per_page" aria-label="Produtos por página" @change="loadProducts(1)">
            <option :value="25">25 por página</option>
            <option :value="50">50 por página</option>
            <option :value="100">100 por página</option>
          </select>
        </div>
      </div>
    </section>
  </section>
</template>
