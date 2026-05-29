<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { MeasurementTable } from '../services/merchantTypes'
import { showFeedback } from '../services/saveFeedback'

const tables = ref<MeasurementTable[]>([])
const loading = ref(false)
const importing = ref(false)
const error = ref('')
const importFileInput = ref<HTMLInputElement | null>(null)
const importPayload = ref<{ format: 'csv' | 'xlsx'; filename: string; content: string } | null>(null)
const importPreview = ref<ImportPreview | null>(null)

const filters = reactive({
  search: '',
  status: '',
  measurement_target: '',
  product_type: '',
  fit_profile: '',
  usage: '',
})

const summary = reactive({
  total: 0,
  filtered: 0,
  active: 0,
  filters: {
    product_types: [] as string[],
    fit_profiles: [] as string[],
    statuses: [] as string[],
    measurement_targets: [] as string[],
  },
})

type ImportError = {
  line: number | null
  column: number | null
  field: string
  message: string
  suggestion: string
}

type ImportPreviewRow = {
  line: number | null
  valid: boolean
  errors: ImportError[]
  action: 'create' | 'update'
  data: {
    table_name: string | null
    product_type: string
    size_label: string | null
  }
}

type ImportPreview = {
  total_rows: number
  valid_rows: number
  failed_rows: number
  imported_rows?: number
  summary: {
    measurement_tables: number
    rows: number
    creates: number
    updates: number
  }
  rows: ImportPreviewRow[]
}

let filterTimer: ReturnType<typeof window.setTimeout> | null = null

const pageCountLabel = computed(() => {
  if (loading.value) {
    return 'carregando'
  }

  if (summary.filtered !== summary.total) {
    return `${summary.filtered} de ${summary.total} tabelas`
  }

  return `${tables.value.length} tabelas`
})

const hasImportPreview = computed(() => Boolean(importPreview.value))
const canCommitImport = computed(() => Boolean(importPayload.value && importPreview.value && importPreview.value.failed_rows === 0 && importPreview.value.valid_rows > 0))
const previewErrors = computed(() => (importPreview.value?.rows || []).flatMap((row) => row.errors || []))

onMounted(() => {
  loadTables()
})

watch(filters, () => {
  scheduleLoadTables()
}, { deep: true })

async function loadTables() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/measurement-tables', { params: tableQueryParams() })
    tables.value = data.data
    summary.total = Number(data.summary?.total || data.data.length)
    summary.filtered = Number(data.summary?.filtered || data.data.length)
    summary.active = Number(data.summary?.active || 0)
    summary.filters = {
      product_types: data.summary?.filters?.product_types || [],
      fit_profiles: data.summary?.filters?.fit_profiles || [],
      statuses: data.summary?.filters?.statuses || [],
      measurement_targets: data.summary?.filters?.measurement_targets || [],
    }
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar as tabelas.'
  } finally {
    loading.value = false
  }
}

function scheduleLoadTables() {
  if (filterTimer) {
    window.clearTimeout(filterTimer)
  }

  filterTimer = window.setTimeout(() => {
    loadTables()
  }, 250)
}

function tableQueryParams() {
  return Object.fromEntries(Object.entries(filters).filter(([, value]) => String(value || '').trim()))
}

function clearFilters() {
  filters.search = ''
  filters.status = ''
  filters.measurement_target = ''
  filters.product_type = ''
  filters.fit_profile = ''
  filters.usage = ''
}

async function removeTable(table: MeasurementTable) {
  await api.delete(`/measurement-tables/${table.id}`)
  showFeedback({
    status: 'success',
    title: 'Tabela removida',
    message: 'A tabela de medidas foi removida da empresa.',
  })
  await loadTables()
}

async function downloadExport(format: 'csv' | 'xlsx') {
  const { data } = await api.get('/measurement-tables/export', {
    params: { ...tableQueryParams(), format },
    responseType: 'blob',
  })
  downloadBlob(data, `tabelas-medidas.${format}`)
}

async function downloadTemplate(format: 'csv' | 'xlsx', target = 'body') {
  const { data } = await api.get('/measurement-tables/template', {
    params: { format, target },
    responseType: 'blob',
  })
  downloadBlob(data, `modelo-tabelas-${target}.${format}`)
}

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

function openImportFile() {
  importFileInput.value?.click()
}

async function readImportFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]

  if (!file) {
    return
  }

  error.value = ''
  importing.value = true

  try {
    const format = file.name.toLowerCase().endsWith('.xlsx') ? 'xlsx' : 'csv'
    const content = format === 'xlsx'
      ? await readFileAsBase64(file)
      : await file.text()
    importPayload.value = { format, filename: file.name, content }
    const { data } = await api.post('/measurement-tables/import/preview', importPayload.value)
    importPreview.value = data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível validar a planilha.'
  } finally {
    importing.value = false
    if (importFileInput.value) {
      importFileInput.value.value = ''
    }
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

async function commitImport() {
  if (!importPayload.value) {
    return
  }

  importing.value = true
  error.value = ''

  try {
    const { data } = await api.post('/measurement-tables/import', importPayload.value)
    showFeedback({
      status: 'success',
      title: 'Tabelas importadas',
      message: `${data.imported_rows || data.valid_rows || 0} linha(s) importadas com validação.`,
    })
    clearImport()
    await loadTables()
  } catch (requestError: any) {
    if (requestError.response?.data?.rows) {
      importPreview.value = requestError.response.data
    }
    error.value = requestError.response?.data?.message || 'Não foi possível importar a planilha.'
  } finally {
    importing.value = false
  }
}

function clearImport() {
  importPayload.value = null
  importPreview.value = null
}

function targetLabel(value: string | null | undefined) {
  return value === 'garment' ? 'Peça' : value === 'mixed' ? 'Corpo + peça' : 'Corpo'
}

function sizeSystemLabel(value: string | null | undefined) {
  return value === 'br_numeric' ? 'BR numérico' : value === 'international' ? 'Internacional' : value === 'custom' ? 'Personalizado' : 'BR letras'
}

function statusLabel(value: string) {
  return {
    active: 'Ativa',
    draft: 'Rascunho',
    inactive: 'Inativa',
  }[value] || value
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Tabelas</span>
        <h1>Tabelas de medidas</h1>
        <p>Defina faixas por tamanho e mantenha cada produto com uma base de recomendação confiável.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadTables">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="downloadExport('csv')">
          <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
          CSV
        </button>
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="downloadExport('xlsx')">
          <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
          XLSX
        </button>
        <button class="btn btn-secondary" type="button" @click="downloadTemplate('csv')">
          <i class="fa-solid fa-download" aria-hidden="true"></i>
          Modelo CSV
        </button>
        <button class="btn btn-secondary" type="button" @click="downloadTemplate('xlsx')">
          <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
          Modelo XLSX
        </button>
        <button class="btn btn-secondary" type="button" :disabled="importing" @click="openImportFile">
          <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i>
          Importar
        </button>
        <RouterLink class="btn btn-primary" to="/app/tabelas-de-medidas/nova">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          Nova tabela
        </RouterLink>
        <input ref="importFileInput" hidden type="file" accept=".csv,.xlsx" @change="readImportFile" />
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Tabelas cadastradas</h2>
        <span>{{ pageCountLabel }}</span>
      </div>

      <div class="measurement-table-toolbar">
        <input v-model="filters.search" type="search" placeholder="Buscar tabela, tipo, modelagem ou observação" />
        <select v-model="filters.status" aria-label="Filtrar status">
          <option value="">Status</option>
          <option value="active">Ativas</option>
          <option value="draft">Rascunhos</option>
          <option value="inactive">Inativas</option>
        </select>
        <select v-model="filters.measurement_target" aria-label="Filtrar base">
          <option value="">Base</option>
          <option value="body">Corpo</option>
          <option value="garment">Peça</option>
          <option value="mixed">Corpo + peça</option>
        </select>
        <select v-model="filters.product_type" aria-label="Filtrar tipo">
          <option value="">Tipo</option>
          <option v-for="type in summary.filters.product_types" :key="type" :value="type">
            {{ type }}
          </option>
        </select>
        <select v-model="filters.fit_profile" aria-label="Filtrar modelagem">
          <option value="">Modelagem</option>
          <option v-for="profile in summary.filters.fit_profiles" :key="profile" :value="profile">
            {{ profile }}
          </option>
        </select>
        <select v-model="filters.usage" aria-label="Filtrar uso">
          <option value="">Uso</option>
          <option value="with_products">Com produtos</option>
          <option value="without_products">Sem produtos</option>
        </select>
        <button class="btn btn-secondary btn-compact" type="button" @click="clearFilters">
          <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
          Filtros
        </button>
      </div>

      <section v-if="hasImportPreview" class="import-validation-panel">
        <div class="bulk-preview-header">
          <div>
            <span class="eyebrow">Importação validada</span>
            <h3>{{ importPayload?.filename }}</h3>
            <p>{{ importPreview?.valid_rows }} linha(s) prontas, {{ importPreview?.failed_rows }} com ajuste necessário.</p>
          </div>
          <button type="button" class="icon-link" title="Fechar prévia" @click="clearImport">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>

        <div class="bulk-preview-summary">
          <article>
            <span>Tabelas</span>
            <strong>{{ importPreview?.summary.measurement_tables || 0 }}</strong>
          </article>
          <article>
            <span>Linhas válidas</span>
            <strong>{{ importPreview?.valid_rows || 0 }}</strong>
          </article>
          <article>
            <span>Erros</span>
            <strong>{{ importPreview?.failed_rows || 0 }}</strong>
          </article>
          <article>
            <span>Atualizações</span>
            <strong>{{ importPreview?.summary.updates || 0 }}</strong>
          </article>
        </div>

        <div v-if="previewErrors.length" class="import-error-list">
          <article v-for="(item, index) in previewErrors.slice(0, 6)" :key="index">
            <strong>Linha {{ item.line || '-' }} · {{ item.field }}</strong>
            <span>{{ item.message }} {{ item.suggestion }}</span>
          </article>
        </div>

        <div class="table-wrap import-preview-table">
          <table>
            <thead>
              <tr>
                <th>Linha</th>
                <th>Tabela</th>
                <th>Tipo</th>
                <th>Tamanho</th>
                <th>Ação</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in importPreview?.rows || []" :key="`${row.line}-${row.data.table_name}-${row.data.size_label}`">
                <td>{{ row.line || '-' }}</td>
                <td>{{ row.data.table_name || '-' }}</td>
                <td>{{ row.data.product_type || '-' }}</td>
                <td>{{ row.data.size_label || '-' }}</td>
                <td>{{ row.action === 'update' ? 'Atualizar' : 'Criar' }}</td>
                <td>
                  <span class="status-pill" :class="{ ok: row.valid, danger: !row.valid }">
                    {{ row.valid ? 'Pronta' : 'Corrigir' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="bulk-preview-actions">
          <button class="btn btn-secondary btn-compact" type="button" @click="clearImport">
            Cancelar
          </button>
          <button class="btn btn-primary btn-compact" type="button" :disabled="!canCommitImport || importing" @click="commitImport">
            <i class="fa-solid fa-file-import" aria-hidden="true"></i>
            Importar tabelas
          </button>
        </div>
      </section>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tabela</th>
              <th>Tipo</th>
              <th>Base</th>
              <th>Sistema</th>
              <th>Gênero</th>
              <th>Modelagem</th>
              <th>Linhas</th>
              <th>Produtos</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!tables.length">
              <td colspan="10">Nenhuma tabela cadastrada.</td>
            </tr>
            <tr v-for="table in tables" :key="table.id">
              <td>
                <strong>{{ table.name }}</strong>
                <small>{{ table.notes || table.source }}</small>
              </td>
              <td>{{ table.product_type }}</td>
              <td>{{ targetLabel(table.measurement_target) }}</td>
              <td>{{ sizeSystemLabel(table.size_system) }}</td>
              <td>{{ table.gender || '-' }}</td>
              <td>{{ table.fit_profile || '-' }}</td>
              <td>{{ table.rows_count ?? 0 }}</td>
              <td>{{ table.products_count ?? 0 }}</td>
              <td>
                <span class="status-pill" :class="{ ok: table.status === 'active', warning: table.status !== 'active' }">
                  {{ statusLabel(table.status) }}
                </span>
              </td>
              <td class="row-actions">
                <RouterLink class="icon-link" :to="`/app/tabelas-de-medidas/${table.id}/editar`" title="Editar">
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button type="button" title="Remover tabela" @click="removeTable(table)">
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
