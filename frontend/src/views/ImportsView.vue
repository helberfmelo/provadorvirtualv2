<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { showFeedback } from '../services/saveFeedback'

type PreviewRow = {
  line: number | null
  valid: boolean
  errors: string[]
  action: string
  data: Record<string, string | number | null>
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
  created_at: string | null
}

const preview = ref<Preview | null>(null)
const jobs = ref<ImportJob[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')

const form = reactive({
  type: 'products',
  source_format: 'csv',
  filename: '',
  content: '',
})

const canCommit = computed(() => preview.value && preview.value.valid_rows > 0)
const previewColumns = computed(() => {
  const first = preview.value?.rows[0]?.data
  return first ? Object.keys(first).slice(0, 7) : []
})

onMounted(() => {
  loadJobs()
  applySample('products')
})

async function loadJobs() {
  const { data } = await api.get('/imports')
  jobs.value = data.data
}

async function runPreview() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.post('/imports/preview', payload())
    preview.value = data.data
  } catch (requestError: any) {
    preview.value = null
    error.value = requestError.response?.data?.message || 'Não foi possível analisar o arquivo.'
  } finally {
    loading.value = false
  }
}

async function commitImport() {
  saving.value = true
  error.value = ''

  try {
    const { data } = await api.post('/imports', payload())
    const label = statusLabel(data.data.status).toLowerCase()
    const destination = form.type === 'measurement_tables' ? '/app/tabelas-de-medidas' : '/app/produtos'
    showFeedback({
      status: data.data.status === 'failed' ? 'error' : 'success',
      title: 'Importação processada',
      message: `Importação ${label}. Acesse a página correspondente para revisar os dados importados.`,
      actionLabel: form.type === 'measurement_tables' ? 'Ver tabelas' : 'Ver produtos',
      actionTo: destination,
      duration: 0,
    })
    preview.value = null
    await loadJobs()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível importar.'
  } finally {
    saving.value = false
  }
}

function payload() {
  return {
    type: form.type,
    source_format: form.source_format,
    filename: form.filename || null,
    content: form.content,
  }
}

function applySample(type = form.type) {
  form.type = type
  form.source_format = 'csv'
  preview.value = null

  if (type === 'measurement_tables') {
    form.filename = 'tabelas.csv'
    form.content = [
      'table_name,product_type,gender,fit_profile,size_label,bust_min,bust_max,waist_min,waist_max,hip_min,hip_max',
      'Camisas importadas,shirt,unisex,regular,P,88,94,70,76,92,98',
      'Camisas importadas,shirt,unisex,regular,M,94,100,76,82,98,104',
    ].join('\n')
    return
  }

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
  preview.value = null
  form.content = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">',
    '<channel>',
    '<item><g:id>VEST-123</g:id><title>Vestido Feed</title><g:mpn>VEST-123</g:mpn><g:product_type>Vestidos</g:product_type><g:price>189.90 BRL</g:price></item>',
    '</channel>',
    '</rss>',
  ].join('\n')
}

async function readFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) {
    return
  }

  form.filename = file.name
  form.content = await file.text()
  form.source_format = file.name.toLowerCase().endsWith('.xml') ? 'google_xml' : 'csv'
  form.type = form.source_format === 'google_xml' ? 'products' : form.type
  preview.value = null
}

function statusLabel(status: string) {
  return {
    pending: 'Pendente',
    running: 'Processando',
    completed: 'Concluída',
    completed_with_warnings: 'Concluída com avisos',
    failed: 'Falhou',
  }[status] || status
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Importações</span>
        <h1>Dados da loja</h1>
      </div>
      <div class="action-row compact">
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
        <div class="form-grid">
          <label>
            Tipo
            <select v-model="form.type" :disabled="form.source_format === 'google_xml'">
              <option value="products">Produtos</option>
              <option value="measurement_tables">Tabelas</option>
            </select>
          </label>
          <label>
            Formato
            <select v-model="form.source_format">
              <option value="csv">CSV</option>
              <option value="google_xml">Google XML</option>
            </select>
          </label>
          <label>
            Arquivo
            <input type="file" accept=".csv,.txt,.xml" @change="readFile" />
          </label>
        </div>

        <label>
          Conteúdo
          <textarea v-model="form.content" rows="14"></textarea>
        </label>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="loading">
            <i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i>
            Analisar
          </button>
          <button class="btn btn-secondary" type="button" :disabled="!canCommit || saving" @click="commitImport">
            <i class="fa-solid fa-file-import" aria-hidden="true"></i>
            Importar
          </button>
        </div>
      </form>

      <aside class="panel-main import-preview-panel">
        <div class="subsection-heading">
          <h2>Preview</h2>
          <span v-if="preview">{{ preview.valid_rows }} de {{ preview.total_rows }} válidas</span>
        </div>

        <div v-if="!preview" class="empty-state">Nenhum preview carregado.</div>
        <template v-else>
          <div class="summary-strip">
            <span v-for="(value, key) in preview.summary" :key="key">
              <strong>{{ value }}</strong>
              <small>{{ key }}</small>
            </span>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Linha</th>
                  <th>Status</th>
                  <th v-for="column in previewColumns" :key="column">{{ column }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in preview.rows" :key="row.line || JSON.stringify(row.data)">
                  <td>{{ row.line }}</td>
                  <td>
                    <span class="status-pill" :class="{ ok: row.valid }">
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
            <article v-for="job in jobs" :key="job.id" class="job-row">
              <i class="fa-solid fa-file-import" aria-hidden="true"></i>
              <span>
                <strong>{{ job.filename || job.type }}</strong>
                <small>{{ statusLabel(job.status) }} · {{ job.imported_rows }}/{{ job.total_rows }}</small>
              </span>
            </article>
          </div>
        </div>
      </aside>
    </div>
  </section>
</template>
