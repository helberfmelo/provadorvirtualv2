<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { showFeedback } from '../services/saveFeedback'

type Primitive = string | number | boolean | null

type PreviewRow = {
  section?: string
  line: number | null
  valid: boolean
  errors: string[]
  action: string
  confidence?: string
  data: Record<string, Primitive>
}

type PreviewSection = {
  key: string
  label: string
  rows: number
  valid: number
  create?: number
  update?: number
  conflicts?: number
  low_confidence?: number
  variants?: number
  affected_products?: number
}

type ReviewQueueItem = {
  section: string
  severity: string
  target: string
  source_value: string | null
  suggested_value: string | null
  confidence: string
  reason: string
  sku?: string | null
}

type Coverage = {
  mode: string
  products_in_package: number
  products_in_reference: number
  products_matched: number
  tables_in_package: number
  tables_matched: number
  sizes_in_package: number
  sizes_matched: number
  conflicts: number
  warnings: string[]
}

type Preview = {
  type: string
  source_format: string
  filename: string | null
  total_rows: number
  valid_rows: number
  failed_rows: number
  summary: Record<string, number>
  rows: PreviewRow[]
  sections?: PreviewSection[]
  review_queue?: ReviewQueueItem[]
  coverage?: Coverage
  warnings?: string[]
  metadata?: Record<string, unknown>
}

type ImportJob = {
  id: number
  type: string
  source_format: string
  filename: string | null
  status: string
  total_rows: number
  imported_rows: number
  failed_rows: number
  summary: Record<string, number>
  errors: Array<Record<string, unknown>>
  metadata: Record<string, any>
  created_at: string | null
  finished_at?: string | null
}

const sectionOptions = [
  { value: 'products', label: 'Produtos' },
  { value: 'measurement_tables', label: 'Tabelas de medidas' },
  { value: 'brands', label: 'Marcas' },
  { value: 'categories', label: 'Categorias' },
  { value: 'fit_profiles', label: 'Modelagens' },
  { value: 'import_rules', label: 'Regras de importação' },
  { value: 'reports', label: 'Relatórios agregados' },
]

const preview = ref<Preview | null>(null)
const jobs = ref<ImportJob[]>([])
const selectedJob = ref<ImportJob | null>(null)
const loading = ref(false)
const saving = ref(false)
const rollingBack = ref(false)
const loadingJob = ref(false)
const error = ref('')
const selectedUploadName = ref('')
const selectedBinaryInfo = ref('')
const binaryContent = ref('')

const form = reactive({
  type: 'sizebay_migration',
  source_format: 'json',
  section: 'products',
  filename: '',
  content: '',
  compare_with_bigshop: true,
})

const isMigration = computed(() => form.type === 'sizebay_migration')
const requiresSection = computed(() => isMigration.value && ['csv', 'xlsx'].includes(form.source_format))
const usesBinaryUpload = computed(() => isMigration.value && ['xlsx', 'zip'].includes(form.source_format))
const showTextarea = computed(() => !usesBinaryUpload.value)
const canCommit = computed(() => Boolean(preview.value && preview.value.valid_rows > 0))
const canRollbackSelectedJob = computed(() => {
  if (!selectedJob.value || selectedJob.value.type !== 'sizebay_migration') {
    return false
  }

  return selectedJob.value.status !== 'rolled_back' && Boolean(selectedJob.value.metadata?.rollback)
})
const previewColumns = computed(() => {
  const first = preview.value?.rows[0]?.data
  return first ? Object.keys(first).slice(0, 8) : []
})
const previewSummaryEntries = computed(() => metricEntries(preview.value?.summary ?? {}))
const selectedJobSummaryEntries = computed(() => metricEntries(selectedJob.value?.summary ?? {}))
const selectedJobSections = computed<PreviewSection[]>(() => selectedJob.value?.metadata?.sections ?? [])
const selectedJobReviewQueue = computed<ReviewQueueItem[]>(() => selectedJob.value?.metadata?.review_queue ?? [])
const selectedJobCoverage = computed<Coverage | null>(() => selectedJob.value?.metadata?.coverage ?? null)
const selectedJobWarnings = computed<string[]>(() => selectedJob.value?.metadata?.warnings ?? [])
const selectedJobPostApplyReviewQueue = computed<ReviewQueueItem[]>(() => selectedJob.value?.metadata?.post_apply_review_queue ?? [])

onMounted(async () => {
  applyMigrationSample()
  await loadJobs()
})

async function loadJobs() {
  const { data } = await api.get('/imports')
  jobs.value = data.data

  if (selectedJob.value) {
    const refreshed = jobs.value.find((job) => job.id === selectedJob.value?.id)
    if (refreshed) {
      selectedJob.value = refreshed
    }
  } else if (jobs.value[0]) {
    selectedJob.value = jobs.value[0]
  }
}

async function loadJob(jobId: number) {
  loadingJob.value = true
  error.value = ''

  try {
    const { data } = await api.get(`/imports/${jobId}`)
    selectedJob.value = data.data
  } catch (requestError: any) {
    error.value = requestErrorMessage(requestError, 'Não foi possível carregar o lote.')
  } finally {
    loadingJob.value = false
  }
}

async function runPreview() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.post('/imports/preview', payload())
    preview.value = data.data
  } catch (requestError: any) {
    preview.value = null
    error.value = requestErrorMessage(requestError, 'Não foi possível analisar o arquivo.')
  } finally {
    loading.value = false
  }
}

async function commitImport() {
  saving.value = true
  error.value = ''

  try {
    const { data } = await api.post('/imports', payload())
    const job = data.data as ImportJob
    const label = statusLabel(job.status).toLowerCase()
    const destination =
      form.type === 'measurement_tables'
        ? '/app/tabelas-de-medidas'
        : form.type === 'sizebay_migration'
          ? '/app/importacoes'
          : '/app/produtos'

    showFeedback({
      status: job.status === 'failed' ? 'error' : 'success',
      title: 'Importação processada',
      message:
        form.type === 'sizebay_migration'
          ? `Lote de migração ${label}. Revise a fila pendente e, se necessário, use o desfazer do lote.`
          : `Importação ${label}. Acesse a página correspondente para revisar os dados importados.`,
      actionLabel: form.type === 'measurement_tables' ? 'Ver tabelas' : 'Abrir importações',
      actionTo: destination,
      duration: 0,
    })
    preview.value = null
    selectedJob.value = job
    await loadJobs()
    await loadJob(job.id)
  } catch (requestError: any) {
    error.value = requestErrorMessage(requestError, 'Não foi possível importar.')
  } finally {
    saving.value = false
  }
}

async function rollbackImport() {
  if (!selectedJob.value) {
    return
  }

  rollingBack.value = true
  error.value = ''

  try {
    const { data } = await api.post(`/imports/${selectedJob.value.id}/rollback`)
    selectedJob.value = data.data
    await loadJobs()
    showFeedback({
      status: 'success',
      title: 'Lote desfeito',
      message: 'A migração foi desfeita e o histórico do lote foi preservado para auditoria.',
      duration: 7000,
    })
  } catch (requestError: any) {
    error.value = requestErrorMessage(requestError, 'Não foi possível desfazer o lote.')
  } finally {
    rollingBack.value = false
  }
}

function payload() {
  const basePayload: Record<string, unknown> = {
    type: form.type,
    source_format: form.source_format,
    filename: form.filename || null,
    compare_with_bigshop: isMigration.value ? form.compare_with_bigshop : false,
  }

  if (requiresSection.value) {
    basePayload.section = form.section
  }

  if (usesBinaryUpload.value) {
    basePayload.content_base64 = binaryContent.value
  } else {
    basePayload.content = form.content
  }

  return basePayload
}

function resetInputState() {
  preview.value = null
  error.value = ''
}

function applySample(type = form.type) {
  form.type = type
  form.section = 'products'
  form.compare_with_bigshop = false
  selectedUploadName.value = ''
  selectedBinaryInfo.value = ''
  binaryContent.value = ''
  resetInputState()

  if (type === 'measurement_tables') {
    form.source_format = 'csv'
    form.filename = 'tabelas.csv'
    form.content = [
      'table_name,product_type,gender,fit_profile,size_label,bust_min,bust_max,waist_min,waist_max,hip_min,hip_max',
      'Camisas importadas,shirt,unisex,regular,P,88,94,70,76,92,98',
      'Camisas importadas,shirt,unisex,regular,M,94,100,76,82,98,104',
    ].join('\n')
    return
  }

  form.source_format = 'csv'
  form.filename = 'produtos.csv'
  form.content = [
    'sku,name,category,gender,fit_profile,size_label,variant_sku,price,stock_quantity,measurement_table',
    'LINHO-IMP,Camisa Linho Importada,Camisas,unisex,regular,P,LINHO-IMP-P,199.90,8,Vestidos femininos - modelagem regular',
    'LINHO-IMP,Camisa Linho Importada,Camisas,unisex,regular,M,LINHO-IMP-M,199.90,6,Vestidos femininos - modelagem regular',
  ].join('\n')
}

function applyGoogleSample() {
  form.type = 'products'
  form.source_format = 'google_xml'
  form.filename = 'google-feed.xml'
  form.section = 'products'
  form.compare_with_bigshop = false
  selectedUploadName.value = ''
  selectedBinaryInfo.value = ''
  binaryContent.value = ''
  resetInputState()
  form.content = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">',
    '<channel>',
    '<item><g:id>VEST-123</g:id><title>Vestido Feed</title><g:mpn>VEST-123</g:mpn><g:product_type>Vestidos</g:product_type><g:price>189.90 BRL</g:price></item>',
    '</channel>',
    '</rss>',
  ].join('\n')
}

function applyMigrationSample() {
  form.type = 'sizebay_migration'
  form.source_format = 'json'
  form.section = 'products'
  form.filename = 'sizebay-zak-migration.json'
  form.compare_with_bigshop = true
  selectedUploadName.value = ''
  selectedBinaryInfo.value = ''
  binaryContent.value = ''
  resetInputState()
  form.content = JSON.stringify(
    {
      sections: {
        measurement_tables: [
          {
            table_name: 'Vestidos Zak regular',
            product_type: 'dress',
            gender: 'female',
            fit_profile: 'regular',
            size_system: 'br_alpha',
            size_label: 'P',
            bust_min: 84,
            bust_max: 90,
            waist_min: 68,
            waist_max: 74,
            hip_min: 92,
            hip_max: 98,
          },
          {
            table_name: 'Vestidos Zak regular',
            product_type: 'dress',
            gender: 'female',
            fit_profile: 'regular',
            size_system: 'br_alpha',
            size_label: 'M',
            bust_min: 90,
            bust_max: 96,
            waist_min: 74,
            waist_max: 80,
            hip_min: 98,
            hip_max: 104,
          },
        ],
        fit_profiles: [
          {
            name: 'Regular Zak',
            code: 'regular-zak',
            product_type: 'dress',
            gender: 'female',
            fit_intensity: 'regular',
            stretch_level: 'medium',
            status: 'active',
          },
        ],
        brands: [
          {
            name: 'Zak',
            normalized_name: 'Zak',
          },
        ],
        categories: [
          {
            name: 'Vestidos',
            taxonomy_name: 'Vestidos',
            category_type: 'dress',
          },
        ],
        products: [
          {
            external_product_id: 'zak-vestido-midi',
            sku: 'ZAK-MIDI',
            name: 'Vestido Midi Zak',
            category: 'Vestidos',
            brand: 'Zak',
            fit_profile: 'Regular Zak',
            measurement_table: 'Vestidos Zak regular',
            size_label: 'P',
            variant_sku: 'ZAK-MIDI-P',
            price: '249.90',
            stock_quantity: '4',
            public_url: 'https://www.zak.com.br/vestido-midi',
          },
          {
            external_product_id: 'zak-vestido-midi',
            sku: 'ZAK-MIDI',
            name: 'Vestido Midi Zak',
            category: 'Vestidos',
            brand: 'Zak',
            fit_profile: 'Regular Zak',
            measurement_table: 'Vestidos Zak regular',
            size_label: 'M',
            variant_sku: 'ZAK-MIDI-M',
            price: '249.90',
            stock_quantity: '6',
            public_url: 'https://www.zak.com.br/vestido-midi',
          },
        ],
        import_rules: [
          {
            field: 'gender',
            match_type: 'equals',
            match_value: 'feminino',
            target_value: 'female',
          },
        ],
        reports: [
          {
            period: '2026-05',
            dimension: 'Vestidos',
            metric: 'uso_widget',
            device: 'mobile',
            value: '182',
          },
        ],
      },
    },
    null,
    2,
  )
}

function handleTypeChange() {
  if (form.type === 'sizebay_migration') {
    applyMigrationSample()
    return
  }

  applySample(form.type)
}

function handleFormatChange() {
  resetInputState()
  selectedUploadName.value = ''
  selectedBinaryInfo.value = ''
  binaryContent.value = ''

  if (isMigration.value) {
    if (form.source_format === 'json') {
      applyMigrationSample()
      return
    }

    form.content = ''
    form.filename = ''
    if (!requiresSection.value) {
      form.section = 'products'
    }
    return
  }

  if (form.source_format === 'google_xml') {
    applyGoogleSample()
    return
  }

  applySample(form.type)
}

async function readFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) {
    return
  }

  form.filename = file.name
  selectedUploadName.value = file.name
  resetInputState()

  if (usesBinaryUpload.value) {
    const buffer = await file.arrayBuffer()
    binaryContent.value = arrayBufferToBase64(buffer)
    selectedBinaryInfo.value = `${file.name} · ${formatBytes(file.size)}`
    form.content = ''
    return
  }

  form.content = await file.text()
  binaryContent.value = ''
  selectedBinaryInfo.value = `${file.name} · ${formatBytes(file.size)}`

  const lowerName = file.name.toLowerCase()
  if (!isMigration.value) {
    form.source_format = lowerName.endsWith('.xml') ? 'google_xml' : 'csv'
    if (form.source_format === 'google_xml') {
      form.type = 'products'
    }
  }
}

function requestErrorMessage(requestError: any, fallback: string) {
  const messages = requestError?.response?.data?.errors

  if (messages && typeof messages === 'object') {
    const firstGroup = Object.values(messages)[0]
    if (Array.isArray(firstGroup) && firstGroup[0]) {
      return String(firstGroup[0])
    }
  }

  return requestError?.response?.data?.message || fallback
}

function arrayBufferToBase64(buffer: ArrayBuffer) {
  const bytes = new Uint8Array(buffer)
  let binary = ''

  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte)
  })

  return window.btoa(binary)
}

function formatBytes(bytes: number) {
  if (bytes < 1024) {
    return `${bytes} B`
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function statusLabel(status: string) {
  return {
    pending: 'Pendente',
    running: 'Processando',
    completed: 'Concluído',
    completed_with_warnings: 'Concluído com pendências',
    failed: 'Falhou',
    rolled_back: 'Desfeito',
  }[status] || status
}

function statusClass(status: string) {
  return {
    pending: 'neutral',
    running: 'warning',
    completed: 'ok',
    completed_with_warnings: 'warning',
    failed: 'danger',
    rolled_back: 'neutral',
  }[status] || 'neutral'
}

function severityLabel(severity: string) {
  return {
    conflict: 'Conflito',
    low_confidence: 'Baixa confiança',
  }[severity] || severity
}

function severityClass(severity: string) {
  return severity === 'conflict' ? 'danger' : 'warning'
}

function confidenceLabel(confidence: string) {
  return {
    high: 'Alta',
    medium: 'Média',
    low: 'Baixa',
  }[confidence] || confidence
}

function typeLabel(type: string) {
  return {
    products: 'Produtos',
    measurement_tables: 'Tabelas',
    sizebay_migration: 'Migração Sizebay',
  }[type] || type
}

function sectionLabel(section: string) {
  return sectionOptions.find((item) => item.value === section)?.label || section
}

function metricEntries(summary: Record<string, number>) {
  return Object.entries(summary)
    .filter(([, value]) => typeof value === 'number')
    .map(([key, value]) => ({
      key,
      label: metricLabel(key),
      value,
    }))
}

function metricLabel(key: string) {
  return {
    measurement_tables: 'Tabelas',
    products: 'Produtos',
    variants: 'Variações',
    brands: 'Marcas',
    categories: 'Categorias',
    fit_profiles: 'Modelagens',
    import_rules: 'Regras',
    reports: 'Relatórios',
    created: 'Criados',
    updated: 'Atualizados',
    ignored: 'Ignorados',
    conflicts: 'Conflitos',
    low_confidence: 'Baixa confiança',
    affected_products: 'Produtos afetados',
    review_queue: 'Pendências',
    applied_products: 'Produtos aplicados',
    applied_measurement_tables: 'Tabelas aplicadas',
    applied_fit_profiles: 'Modelagens aplicadas',
    applied_brands: 'Marcas aplicadas',
    applied_categories: 'Categorias aplicadas',
  }[key] || key
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Importações</span>
        <h1>Dados da loja</h1>
        <p class="page-subtitle">
          Traga arquivos do cliente com prévia, revisão das pendências, auditoria por lote e desfazer quando necessário.
        </p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" @click="applyMigrationSample">
          <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
          Migração assistida
        </button>
        <button class="btn btn-secondary" type="button" @click="applySample('products')">
          <i class="fa-solid fa-box-open" aria-hidden="true"></i>
          Produtos CSV
        </button>
        <button class="btn btn-secondary" type="button" @click="applySample('measurement_tables')">
          <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
          Tabelas CSV
        </button>
        <button class="btn btn-secondary" type="button" @click="applyGoogleSample">
          <i class="fa-solid fa-file-code" aria-hidden="true"></i>
          Feed XML
        </button>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <div class="import-grid">
      <form class="panel-main admin-form" @submit.prevent="runPreview">
        <div class="subsection-heading">
          <h2>Preparar lote</h2>
          <span>{{ typeLabel(form.type) }}</span>
        </div>

        <div class="form-grid import-form-grid">
          <label>
            Tipo
            <select v-model="form.type" @change="handleTypeChange">
              <option value="sizebay_migration">Migração Sizebay</option>
              <option value="products">Produtos</option>
              <option value="measurement_tables">Tabelas</option>
            </select>
          </label>
          <label>
            Formato
            <select v-model="form.source_format" @change="handleFormatChange">
              <template v-if="isMigration">
                <option value="json">JSON</option>
                <option value="csv">CSV</option>
                <option value="xlsx">XLSX</option>
                <option value="zip">ZIP</option>
              </template>
              <template v-else>
                <option value="csv">CSV</option>
                <option value="google_xml">Google XML</option>
              </template>
            </select>
          </label>
          <label v-if="requiresSection">
            Seção do pacote
            <select v-model="form.section">
              <option v-for="option in sectionOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>
          <label>
            Arquivo
            <input
              type="file"
              :accept="isMigration ? '.json,.csv,.xlsx,.zip' : '.csv,.txt,.xml'"
              @change="readFile"
            />
          </label>
        </div>

        <label v-if="isMigration" class="settings-check">
          <input v-model="form.compare_with_bigshop" type="checkbox" />
          <span>Comparar cobertura com o catálogo da BigShop quando a conexão da empresa estiver pronta.</span>
        </label>

        <div v-if="selectedBinaryInfo" class="import-file-chip">
          <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
          <span>{{ selectedBinaryInfo }}</span>
        </div>

        <label v-if="showTextarea">
          Conteúdo
          <textarea
            v-model="form.content"
            :rows="isMigration ? 20 : 14"
            :placeholder="
              isMigration
                ? 'Cole aqui o JSON do pacote ou o conteúdo CSV da seção selecionada.'
                : 'Cole aqui o conteúdo CSV ou XML.'
            "
          ></textarea>
        </label>

        <div v-else class="empty-state compact import-binary-state">
          <strong>Arquivo binário pronto para análise</strong>
          <span>
            Faça upload do XLSX ou ZIP e use a prévia para conferir antes de aplicar. O conteúdo não aparece em texto por segurança e legibilidade.
          </span>
        </div>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="loading">
            <i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i>
            Analisar
          </button>
          <button class="btn btn-secondary" type="button" :disabled="!canCommit || saving" @click="commitImport">
            <i class="fa-solid fa-file-import" aria-hidden="true"></i>
            Aplicar lote
          </button>
        </div>
      </form>

      <aside class="panel-main import-preview-panel">
        <div class="subsection-heading">
          <h2>Prévia</h2>
          <span v-if="preview">{{ preview.valid_rows }} de {{ preview.total_rows }} válidas</span>
        </div>

        <div v-if="!preview" class="empty-state">Nenhuma prévia carregada.</div>
        <template v-else>
          <div class="summary-strip">
            <span v-for="entry in previewSummaryEntries" :key="entry.key">
              <strong>{{ entry.value }}</strong>
              <small>{{ entry.label }}</small>
            </span>
          </div>

          <div v-if="preview.warnings?.length" class="import-insight-list">
            <article v-for="warning in preview.warnings" :key="warning" class="import-insight">
              <span class="status-pill warning">Aviso</span>
              <p>{{ warning }}</p>
            </article>
          </div>

          <div v-if="preview.sections?.length" class="import-sections-grid">
            <article v-for="section in preview.sections" :key="section.key">
              <header>
                <strong>{{ section.label }}</strong>
                <span>{{ section.rows }} linhas</span>
              </header>
              <small>{{ section.valid }} válidas</small>
              <small v-if="section.create">Criar: {{ section.create }}</small>
              <small v-if="section.update">Atualizar: {{ section.update }}</small>
              <small v-if="section.conflicts">Conflitos: {{ section.conflicts }}</small>
              <small v-if="section.low_confidence">Baixa confiança: {{ section.low_confidence }}</small>
            </article>
          </div>

          <div v-if="preview.coverage" class="import-coverage-card">
            <div class="subsection-heading">
              <h3>Cobertura</h3>
              <span>{{ preview.coverage.mode === 'bigshop_feed' ? 'BigShop' : 'Catálogo atual' }}</span>
            </div>
            <div class="summary-strip compact">
              <span>
                <strong>{{ preview.coverage.products_matched }}/{{ preview.coverage.products_in_package }}</strong>
                <small>Produtos cobertos</small>
              </span>
              <span>
                <strong>{{ preview.coverage.tables_matched }}/{{ preview.coverage.tables_in_package }}</strong>
                <small>Tabelas cobertas</small>
              </span>
              <span>
                <strong>{{ preview.coverage.sizes_matched }}/{{ preview.coverage.sizes_in_package }}</strong>
                <small>Tamanhos cobertos</small>
              </span>
              <span>
                <strong>{{ preview.coverage.conflicts }}</strong>
                <small>Pendências</small>
              </span>
            </div>
            <ul v-if="preview.coverage.warnings?.length" class="import-plain-list">
              <li v-for="warning in preview.coverage.warnings" :key="warning">{{ warning }}</li>
            </ul>
          </div>

          <div v-if="preview.review_queue?.length" class="subsection">
            <div class="subsection-heading">
              <h3>Fila de revisão</h3>
              <span>{{ preview.review_queue.length }} itens</span>
            </div>
            <div class="import-review-list">
              <article v-for="(item, index) in preview.review_queue" :key="`${item.section}-${index}`" class="import-review-item">
                <div class="import-review-head">
                  <span class="status-pill" :class="severityClass(item.severity)">
                    {{ severityLabel(item.severity) }}
                  </span>
                  <strong>{{ sectionLabel(item.section) }} · {{ item.target }}</strong>
                  <small>Confiança {{ confidenceLabel(item.confidence) }}</small>
                </div>
                <p>{{ item.reason }}</p>
                <small>
                  Origem: {{ item.source_value || 'não informada' }}
                  <template v-if="item.suggested_value"> · Sugestão: {{ item.suggested_value }}</template>
                  <template v-if="item.sku"> · SKU: {{ item.sku }}</template>
                </small>
              </article>
            </div>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Seção</th>
                  <th>Linha</th>
                  <th>Status</th>
                  <th v-for="column in previewColumns" :key="column">{{ column }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in preview.rows" :key="`${row.section || 'default'}-${row.line}-${JSON.stringify(row.data)}`">
                  <td>{{ row.section ? sectionLabel(row.section) : '-' }}</td>
                  <td>{{ row.line }}</td>
                  <td>
                    <span class="status-pill" :class="row.valid ? 'ok' : 'danger'">
                      {{ row.valid ? row.action : row.errors[0] }}
                    </span>
                  </td>
                  <td v-for="column in previewColumns" :key="column">{{ row.data[column] }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <div class="subsection">
          <div class="subsection-heading">
            <h2>Histórico</h2>
            <span>{{ jobs.length }} recentes</span>
          </div>
          <div class="job-list">
            <div v-if="!jobs.length" class="empty-state">Nenhuma importação registrada.</div>
            <button
              v-for="job in jobs"
              :key="job.id"
              type="button"
              class="job-row job-row-button"
              :class="{ active: selectedJob?.id === job.id }"
              @click="loadJob(job.id)"
            >
              <i class="fa-solid fa-file-import" aria-hidden="true"></i>
              <span>
                <strong>{{ job.filename || typeLabel(job.type) }}</strong>
                <small>{{ statusLabel(job.status) }} · {{ job.imported_rows }}/{{ job.total_rows }}</small>
              </span>
              <span class="status-pill" :class="statusClass(job.status)">
                {{ statusLabel(job.status) }}
              </span>
            </button>
          </div>
        </div>

        <div class="subsection">
          <div class="subsection-heading">
            <h2>Detalhes do lote</h2>
            <span v-if="selectedJob">#{{ selectedJob.id }}</span>
          </div>

          <div v-if="loadingJob" class="empty-state compact">Carregando lote...</div>
          <div v-else-if="!selectedJob" class="empty-state compact">Selecione um lote para revisar.</div>
          <template v-else>
            <div class="import-job-head">
              <div>
                <strong>{{ selectedJob.filename || typeLabel(selectedJob.type) }}</strong>
                <small>{{ typeLabel(selectedJob.type) }} · {{ selectedJob.source_format.toUpperCase() }}</small>
              </div>
              <div class="action-row compact">
                <span class="status-pill" :class="statusClass(selectedJob.status)">
                  {{ statusLabel(selectedJob.status) }}
                </span>
                <button
                  v-if="canRollbackSelectedJob"
                  class="btn btn-secondary"
                  type="button"
                  :disabled="rollingBack"
                  @click="rollbackImport"
                >
                  <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                  Desfazer lote
                </button>
              </div>
            </div>

            <div class="summary-strip" v-if="selectedJobSummaryEntries.length">
              <span v-for="entry in selectedJobSummaryEntries" :key="entry.key">
                <strong>{{ entry.value }}</strong>
                <small>{{ entry.label }}</small>
              </span>
            </div>

            <div v-if="selectedJobWarnings.length" class="import-insight-list">
              <article v-for="warning in selectedJobWarnings" :key="warning" class="import-insight">
                <span class="status-pill warning">Aviso</span>
                <p>{{ warning }}</p>
              </article>
            </div>

            <div v-if="selectedJobSections.length" class="import-sections-grid">
              <article v-for="section in selectedJobSections" :key="section.key">
                <header>
                  <strong>{{ section.label }}</strong>
                  <span>{{ section.rows }} linhas</span>
                </header>
                <small>{{ section.valid }} válidas</small>
                <small v-if="section.create">Criar: {{ section.create }}</small>
                <small v-if="section.update">Atualizar: {{ section.update }}</small>
                <small v-if="section.conflicts">Conflitos: {{ section.conflicts }}</small>
                <small v-if="section.low_confidence">Baixa confiança: {{ section.low_confidence }}</small>
              </article>
            </div>

            <div v-if="selectedJobCoverage" class="import-coverage-card">
              <div class="subsection-heading">
                <h3>Cobertura registrada</h3>
                <span>{{ selectedJobCoverage.mode === 'bigshop_feed' ? 'BigShop' : 'Catálogo atual' }}</span>
              </div>
              <div class="summary-strip compact">
                <span>
                  <strong>{{ selectedJobCoverage.products_matched }}/{{ selectedJobCoverage.products_in_package }}</strong>
                  <small>Produtos cobertos</small>
                </span>
                <span>
                  <strong>{{ selectedJobCoverage.tables_matched }}/{{ selectedJobCoverage.tables_in_package }}</strong>
                  <small>Tabelas cobertas</small>
                </span>
                <span>
                  <strong>{{ selectedJobCoverage.sizes_matched }}/{{ selectedJobCoverage.sizes_in_package }}</strong>
                  <small>Tamanhos cobertos</small>
                </span>
                <span>
                  <strong>{{ selectedJobCoverage.conflicts }}</strong>
                  <small>Pendências</small>
                </span>
              </div>
            </div>

            <div v-if="selectedJobReviewQueue.length" class="subsection">
              <div class="subsection-heading">
                <h3>Pendências registradas</h3>
                <span>{{ selectedJobReviewQueue.length }} itens</span>
              </div>
              <div class="import-review-list">
                <article
                  v-for="(item, index) in selectedJobReviewQueue"
                  :key="`history-${item.section}-${index}`"
                  class="import-review-item"
                >
                  <div class="import-review-head">
                    <span class="status-pill" :class="severityClass(item.severity)">
                      {{ severityLabel(item.severity) }}
                    </span>
                    <strong>{{ sectionLabel(item.section) }} · {{ item.target }}</strong>
                    <small>Confiança {{ confidenceLabel(item.confidence) }}</small>
                  </div>
                  <p>{{ item.reason }}</p>
                  <small>
                    Origem: {{ item.source_value || 'não informada' }}
                    <template v-if="item.suggested_value"> · Sugestão: {{ item.suggested_value }}</template>
                    <template v-if="item.sku"> · SKU: {{ item.sku }}</template>
                  </small>
                </article>
              </div>
            </div>

            <div v-if="selectedJobPostApplyReviewQueue.length" class="subsection">
              <div class="subsection-heading">
                <h3>Pendências após aplicação</h3>
                <span>{{ selectedJobPostApplyReviewQueue.length }} itens</span>
              </div>
              <ul class="import-plain-list">
                <li v-for="(item, index) in selectedJobPostApplyReviewQueue" :key="`post-${index}`">
                  {{ sectionLabel(item.section) }} · {{ item.reason }}
                </li>
              </ul>
            </div>
          </template>
        </div>
      </aside>
    </div>
  </section>
</template>
