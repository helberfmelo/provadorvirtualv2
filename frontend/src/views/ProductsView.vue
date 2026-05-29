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

const filters = reactive({
  search: '',
  status: '',
  table: '',
  readiness: '',
})

const bulkMeasurementTableId = ref<number | ''>('')

const filteredProducts = computed(() => {
  const search = filters.search.trim().toLowerCase()

  return products.value.filter((product) => {
    const matchesSearch = !search || [
      product.name,
      product.sku,
      product.category,
      product.fit_profile,
      product.measurement_table?.name,
    ].some((value) => String(value || '').toLowerCase().includes(search))
    const matchesStatus = !filters.status || product.status === filters.status
    const matchesTable = !filters.table
      || (filters.table === 'with_table' && Boolean(product.measurement_table_id))
      || (filters.table === 'without_table' && !product.measurement_table_id)
      || String(product.measurement_table_id || '') === filters.table
    const matchesReadiness = !filters.readiness
      || (filters.readiness === 'ready' && product.readiness_status === 'ready')
      || (filters.readiness === 'pending' && product.readiness_status !== 'ready')
      || (filters.readiness === 'without_measurement_table' && !product.measurement_table_id)
      || (filters.readiness === 'without_modeling' && !product.fit_profile)
      || (filters.readiness === 'without_category' && !product.category)
      || (filters.readiness === 'sync_error' && Boolean(product.has_sync_error))

    return matchesSearch && matchesStatus && matchesTable && matchesReadiness
  })
})

const selectedCount = computed(() => selectedProductIds.value.length)
const hasSelection = computed(() => selectedCount.value > 0)
const visibleProductIds = computed(() => filteredProducts.value.map((product) => product.id))
const allVisibleSelected = computed(() => (
  visibleProductIds.value.length > 0
  && visibleProductIds.value.every((id) => selectedProductIds.value.includes(id))
))
const activeShortcutLabel = computed(() => {
  const labels: Record<string, string> = {
    ready: 'Prontos para publicar',
    pending: 'Pendentes',
    without_measurement_table: 'Sem tabela',
    without_modeling: 'Sem modelagem',
    without_category: 'Sem categoria',
    sync_error: 'Com erro de sincronização',
  }

  return filters.readiness ? labels[filters.readiness] || '' : ''
})

onMounted(() => {
  applyRouteFilters()
  loadData()
})

watch(() => route.query, () => {
  applyRouteFilters()
}, { deep: true })

function applyRouteFilters() {
  const shortcut = typeof route.query.filtro === 'string' ? route.query.filtro : ''
  const status = typeof route.query.status === 'string' ? route.query.status : ''
  const table = typeof route.query.tabela === 'string' ? route.query.tabela : ''

  filters.status = status
  filters.table = table
  filters.readiness = {
    prontos: 'ready',
    pendentes: 'pending',
    sem_tabela: 'without_measurement_table',
    sem_modelagem: 'without_modeling',
    sem_categoria: 'without_category',
    erro_sync: 'sync_error',
  }[shortcut] || ''
}

function clearOperationalFilter() {
  filters.readiness = ''
  filters.status = ''
  filters.table = ''
}

async function loadData() {
  loading.value = true
  error.value = ''

  try {
    const [productsResponse, tablesResponse] = await Promise.all([
      api.get('/products'),
      api.get('/measurement-tables'),
    ])
    products.value = productsResponse.data.data
    measurementTables.value = tablesResponse.data.data
    selectedProductIds.value = selectedProductIds.value.filter((id) => products.value.some((product) => product.id === id))
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os produtos.'
  } finally {
    loading.value = false
  }
}

async function loadProducts() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/products')
    products.value = data.data
    selectedProductIds.value = selectedProductIds.value.filter((id) => products.value.some((product) => product.id === id))
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os produtos.'
  } finally {
    loading.value = false
  }
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

async function linkSelectedProducts() {
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
      product_ids: selectedProductIds.value,
      measurement_table_id: bulkMeasurementTableId.value,
    })
    showFeedback({
      status: 'success',
      title: 'Tabela vinculada',
      message: `${data.summary?.updated || selectedCount.value} produto(s) atualizados com a tabela selecionada.`,
    })
    bulkMeasurementTableId.value = ''
    clearSelection()
    await loadProducts()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível vincular a tabela aos produtos selecionados.'
  } finally {
    linking.value = false
  }
}

async function removeProduct(product: Product) {
  await api.delete(`/products/${product.id}`)
  showFeedback({
    status: 'success',
    title: 'Produto removido',
    message: 'O produto foi removido da empresa.',
  })
  selectedProductIds.value = selectedProductIds.value.filter((id) => id !== product.id)
  await loadProducts()
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
        <span>{{ loading ? 'carregando' : `${filteredProducts.length}/${products.length} produtos` }}</span>
      </div>
      <div class="product-list-toolbar">
        <input v-model="filters.search" type="search" placeholder="Buscar produto, SKU ou tabela" />
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
        <span class="toolbar-divider"></span>
        <select v-model.number="bulkMeasurementTableId" :disabled="!hasSelection" aria-label="Tabela para vincular">
          <option value="">Vincular tabela</option>
          <option v-for="table in measurementTables" :key="table.id" :value="table.id">
            {{ table.name }}
          </option>
        </select>
        <button class="btn btn-primary btn-compact" type="button" :disabled="!hasSelection || linking" @click="linkSelectedProducts">
          <i class="fa-solid fa-link" aria-hidden="true"></i>
          Vincular
        </button>
        <button class="btn btn-secondary btn-compact" type="button" :disabled="!filteredProducts.length || allVisibleSelected" @click="selectAllVisible">
          Todos
        </button>
        <button class="btn btn-secondary btn-compact" type="button" :disabled="!hasSelection" @click="clearSelection">
          Limpar
        </button>
        <strong>{{ selectedCount }} sel.</strong>
      </div>
      <p v-if="activeShortcutLabel" class="filter-hint">
        Filtro aplicado pelo painel: <strong>{{ activeShortcutLabel }}</strong>
        <button type="button" @click="clearOperationalFilter">Limpar</button>
      </p>
      <div class="table-wrap products-table-wrap">
        <table>
          <thead>
            <tr>
              <th class="selection-column"></th>
              <th>Produto</th>
              <th>Categoria</th>
              <th>Modelagem</th>
              <th>Tabela</th>
              <th>Variações</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!filteredProducts.length">
              <td colspan="8">Nenhum produto encontrado.</td>
            </tr>
            <tr v-for="product in filteredProducts" :key="product.id" :class="{ 'is-selected': selectedProductIds.includes(product.id) }">
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
                <small>{{ product.sku || 'sem SKU' }}</small>
              </td>
              <td>{{ product.category || '-' }}</td>
              <td>{{ product.fit_profile || '-' }}</td>
              <td>{{ product.measurement_table?.name || 'Sem tabela' }}</td>
              <td>{{ product.variants_count ?? 0 }}</td>
              <td>
                <span class="status-pill" :class="{ ok: product.status === 'active', warning: product.status !== 'active' }">
                  {{ product.status === 'active' ? 'Ativo' : product.status }}
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
    </section>
  </section>
</template>
