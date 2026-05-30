<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { showFeedback } from '../services/saveFeedback'
import type { MeasurementTable } from '../services/merchantTypes'

type MeasurementRow = {
  size_label: string
  sort_order?: number
  bust_min?: number | null
  bust_max?: number | null
  waist_min?: number | null
  waist_max?: number | null
  hip_min?: number | null
  hip_max?: number | null
  height_min?: number | null
  height_max?: number | null
  weight_min?: number | null
  weight_max?: number | null
  length_min?: number | null
  length_max?: number | null
  shoulder_min?: number | null
  shoulder_max?: number | null
}

type Suggestion = {
  name: string
  product_type: string
  gender: string
  fit_profile: string
  measurement_target: 'body' | 'garment' | 'mixed'
  size_system: 'br_alpha' | 'br_numeric' | 'international' | 'custom'
  range_mode: 'min_max' | 'exact' | 'tolerance'
  unit: string
  status: string
  source: string
  notes: string
  rows: MeasurementRow[]
  warnings: string[]
}

type LearningContext = {
  has_signals: boolean
  matching_insights: {
    measurement_table_id: number
    table_name: string
    suggested_action: string
    reason: string
    suggested_adjustment?: {
      direction: string
      focus_measurements: string[]
      review_required: boolean
      headline: string
      explanation: string
    } | null
    signals: {
      total: number
      purchases: number
      returns: number
      positive_feedback: number
      negative_feedback: number
    }
  }[]
}

type ReviewRisk = {
  level: 'low' | 'medium' | 'high'
  label: string
  message: string
}

type ReviewComparisonRow = {
  size_label: string
  status: 'changed' | 'new_size' | 'missing_in_suggestion'
  changes: Array<{
    field: string
    current: string | null
    suggested: string | null
    delta_min: number | null
    delta_max: number | null
  }>
}

type ReviewContext = {
  data_used: {
    source_type: string
    filename: string | null
    category: string | null
    brand: string | null
    measurement_target: Suggestion['measurement_target']
    size_system: Suggestion['size_system']
    range_mode: Suggestion['range_mode']
    rows_detected: number
    learning_signals: number
    comparison_table: { id: number; name: string } | null
  }
  confidence_breakdown: {
    parser: number
    structure: number
    learning: number
    comparison: number
    risk_discount: number
    final: number
  }
  risk_level: 'low' | 'medium' | 'high'
  risks: ReviewRisk[]
  merchant_explanation: string | null
  comparison: {
    current_table: {
      id: number
      name: string
      measurement_target: Suggestion['measurement_target']
      size_system: Suggestion['size_system']
      range_mode: Suggestion['range_mode']
      rows_count: number
    } | null
    suggested_table: {
      measurement_target: Suggestion['measurement_target']
      size_system: Suggestion['size_system']
      range_mode: Suggestion['range_mode']
      rows_count: number
    }
    overview: {
      changed_sizes: number
      changed_fields: number
      same_size_system: boolean
    }
    rows: ReviewComparisonRow[]
  }
  action_plan: string[]
}

const status = ref<Record<string, string | boolean | null>>({})
const suggestion = ref<Suggestion | null>(null)
const learningContext = ref<LearningContext | null>(null)
const reviewContext = ref<ReviewContext | null>(null)
const measurementTables = ref<MeasurementTable[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const warnings = ref<string[]>([])

const form = reactive({
  source_type: 'text',
  filename: '',
  name: 'Tabela assistida',
  product_type: 'shirt',
  gender: 'unisex',
  fit_profile: 'regular',
  category: '',
  brand: '',
  measurement_target: 'body' as Suggestion['measurement_target'],
  size_system: 'br_alpha' as Suggestion['size_system'],
  range_mode: 'min_max' as Suggestion['range_mode'],
  compare_table_id: '',
  explain_for_merchant: true,
  content: '',
  image_data: '',
})

const productTypeOptions = [
  { value: 'shirt', label: 'Camisa/Camiseta' },
  { value: 'blouse', label: 'Blusa' },
  { value: 'dress', label: 'Vestido' },
  { value: 'pants', label: 'Calça' },
  { value: 'skirt', label: 'Saia' },
  { value: 'shorts', label: 'Bermuda/Shorts' },
  { value: 'jacket', label: 'Jaqueta' },
  { value: 'sweatshirt', label: 'Moletom' },
  { value: 'bra', label: 'Sutiã' },
  { value: 'kids_shirt', label: 'Camiseta infantil' },
  { value: 'kids_pants', label: 'Calça infantil' },
  { value: 'baby_body', label: 'Body bebê' },
  { value: 'shoes', label: 'Calçado' },
  { value: 'custom', label: 'Personalizado' },
]

const canSave = computed(() => Boolean(suggestion.value?.rows.length))
const comparisonOptions = computed(() => {
  const relevant = measurementTables.value.filter((table) => {
    const productTypeOk = table.product_type === form.product_type
    const fitProfileOk = !table.fit_profile || table.fit_profile === form.fit_profile
    const genderOk = !table.gender || table.gender === form.gender || table.gender === 'unisex' || form.gender === 'unisex'

    return productTypeOk && fitProfileOk && genderOk
  })

  return relevant.length ? relevant : measurementTables.value
})

onMounted(() => {
  applySample()
  loadInitialContext()
})

async function loadInitialContext() {
  try {
    const [statusResponse, tablesResponse] = await Promise.all([
      api.get('/ai/status'),
      api.get('/measurement-tables'),
    ])

    status.value = statusResponse.data.data
    measurementTables.value = tablesResponse.data.data
    autoSelectComparisonTable()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o assistente.'
  }
}

async function suggestTable() {
  loading.value = true
  error.value = ''
  warnings.value = []
  suggestion.value = null
  learningContext.value = null
  reviewContext.value = null

  try {
    const { data } = await api.post('/ai/measurement-table-suggestions', {
      source_type: form.source_type,
      filename: form.filename || null,
      name: form.name || null,
      product_type: form.product_type,
      gender: form.gender,
      fit_profile: form.fit_profile,
      category: form.category || null,
      brand: form.brand || null,
      measurement_target: form.measurement_target,
      size_system: form.size_system,
      range_mode: form.range_mode,
      compare_table_id: form.compare_table_id ? Number(form.compare_table_id) : null,
      explain_for_merchant: form.explain_for_merchant,
      content: form.content || null,
      image_data: form.image_data || null,
    })

    suggestion.value = data.data.suggestion
    learningContext.value = data.data.learning_context || null
    reviewContext.value = data.data.review_context || null
    warnings.value = data.data.warnings || []
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível sugerir a tabela.'
  } finally {
    loading.value = false
  }
}

async function saveSuggestion() {
  if (!suggestion.value) {
    return
  }

  saving.value = true
  error.value = ''

  try {
    const payload = {
      name: suggestion.value.name,
      product_type: suggestion.value.product_type,
      gender: suggestion.value.gender,
      fit_profile: suggestion.value.fit_profile,
      measurement_target: suggestion.value.measurement_target,
      size_system: suggestion.value.size_system,
      range_mode: suggestion.value.range_mode,
      unit: 'cm',
      status: 'draft',
      source: 'ai',
      notes: suggestion.value.notes,
      rows: suggestion.value.rows.map((row, sort_order) => ({
        ...row,
        sort_order,
      })),
    }

    await api.post('/measurement-tables', payload)
    showFeedback({
      status: 'success',
      title: 'Tabela criada',
      message: 'A tabela foi criada como rascunho. Revise antes de publicar.',
      actionLabel: 'Ver tabelas',
      actionTo: '/app/tabelas-de-medidas',
      duration: 0,
    })
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível criar a tabela.'
  } finally {
    saving.value = false
  }
}

function applySample() {
  form.source_type = 'text'
  form.filename = ''
  form.name = 'Vestidos assistidos'
  form.product_type = 'dress'
  form.gender = 'female'
  form.fit_profile = 'regular'
  form.category = 'Vestidos'
  form.brand = 'Coleção própria'
  form.measurement_target = 'body'
  form.size_system = 'br_alpha'
  form.range_mode = 'min_max'
  form.compare_table_id = ''
  form.explain_for_merchant = true
  form.image_data = ''
  form.content = [
    'Tamanho Busto Cintura Quadril',
    'PP 80-84 62-66 88-92',
    'P 84-90 66-72 92-98',
    'M 90-96 72-78 98-104',
    'G 96-104 78-86 104-112',
  ].join('\n')

  autoSelectComparisonTable()
}

function autoSelectComparisonTable() {
  if (form.compare_table_id || !measurementTables.value.length) {
    return
  }

  const match = comparisonOptions.value[0]

  if (match) {
    form.compare_table_id = String(match.id)
  }
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

  return labels[action] || action.replaceAll('_', ' ')
}

function adjustmentDirectionLabel(direction: string) {
  const labels: Record<string, string> = {
    increase_tolerance: 'Ampliar folga',
    decrease_tolerance: 'Reduzir folga',
    review_fit_profile: 'Revisar modelagem',
    review_feedback: 'Revisar feedback',
    observe: 'Seguir observando',
  }

  return labels[direction] || direction.replaceAll('_', ' ')
}

function focusLabel(measurement: string) {
  const labels: Record<string, string> = {
    bust: 'Busto',
    waist: 'Cintura',
    hip: 'Quadril',
    height: 'Altura',
    weight: 'Peso',
    length: 'Comprimento',
    shoulder: 'Ombro',
    fit_profile: 'Modelagem',
  }

  return labels[measurement] || measurement
}

function targetLabel(target: string) {
  const labels: Record<string, string> = {
    body: 'Corpo',
    garment: 'Peça',
    mixed: 'Corpo + peça',
  }

  return labels[target] || target
}

function sizeSystemLabel(system: string) {
  const labels: Record<string, string> = {
    br_alpha: 'BR letras',
    br_numeric: 'BR numérico',
    international: 'Internacional',
    custom: 'Personalizado',
  }

  return labels[system] || system
}

function rangeModeLabel(mode: string) {
  const labels: Record<string, string> = {
    min_max: 'Mínimo e máximo',
    exact: 'Medida exata',
    tolerance: 'Tolerância',
  }

  return labels[mode] || mode
}

function riskClass(level: string) {
  if (level === 'high') {
    return 'danger'
  }

  if (level === 'medium') {
    return 'warning'
  }

  return 'neutral'
}

function riskLevelLabel(level: string) {
  const labels: Record<string, string> = {
    low: 'Baixo',
    medium: 'Médio',
    high: 'Alto',
  }

  return labels[level] || level
}

function comparisonStatusLabel(status: string) {
  const labels: Record<string, string> = {
    changed: 'Mudou',
    new_size: 'Novo tamanho',
    missing_in_suggestion: 'Ficou fora da sugestão',
  }

  return labels[status] || status
}

function comparisonStatusClass(status: string) {
  if (status === 'changed') {
    return 'warning'
  }

  if (status === 'new_size') {
    return 'ok'
  }

  return 'danger'
}

function formatDelta(value: number | null) {
  if (value === null) {
    return 'sem delta'
  }

  return `${value > 0 ? '+' : ''}${value} cm`
}

function tableLabel(table: MeasurementTable) {
  return `${table.name} · ${targetLabel(table.measurement_target)} · ${sizeSystemLabel(table.size_system)}`
}

function addRow() {
  suggestion.value?.rows.push({ size_label: '', sort_order: suggestion.value.rows.length })
}

function removeRow(index: number) {
  suggestion.value?.rows.splice(index, 1)
}

async function readFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) {
    return
  }

  form.filename = file.name
  suggestion.value = null

  if (file.type.startsWith('image/')) {
    form.source_type = 'image'
    form.image_data = await readAsDataUrl(file)
    return
  }

  form.source_type = file.name.toLowerCase().endsWith('.csv') ? 'csv' : 'text'
  form.content = await file.text()
  form.image_data = ''
}

function readAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result))
    reader.onerror = () => reject(reader.error)
    reader.readAsDataURL(file)
  })
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Assistente</span>
        <h1>Criação e revisão guiada de tabelas</h1>
        <p>
          O assistente sugere a tabela inicial, explica o que foi usado, aponta riscos e compara com a tabela atual antes de qualquer publicação.
        </p>
      </div>
      <button class="btn btn-secondary" type="button" @click="applySample">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        Exemplo
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <div class="assistant-status">
      <span class="status-pill ok">
        <i class="fa-solid fa-font" aria-hidden="true"></i>
        Texto ativo
      </span>
      <span class="status-pill ok">
        <i class="fa-solid fa-database" aria-hidden="true"></i>
        Base brasileira ativa
      </span>
      <span class="status-pill" :class="{ ok: status.image_ocr }">
        <i class="fa-solid fa-image" aria-hidden="true"></i>
        {{ status.image_ocr ? 'Imagem ativa' : 'Imagem pendente' }}
      </span>
      <span class="status-pill ok">
        <i class="fa-solid fa-user-check" aria-hidden="true"></i>
        Revisão obrigatória
      </span>
      <span class="status-pill ok">
        <i class="fa-solid fa-shield-heart" aria-hidden="true"></i>
        Aprendizado separado da publicação
      </span>
    </div>

    <div class="import-grid">
      <form class="panel-main admin-form" @submit.prevent="suggestTable">
        <div class="form-grid">
          <label>
            Origem
            <select v-model="form.source_type">
              <option value="text">Texto</option>
              <option value="csv">CSV</option>
              <option value="image">Imagem</option>
            </select>
          </label>
          <label>
            Tipo
            <select v-model="form.product_type">
              <option v-for="option in productTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>
          <label>
            Arquivo
            <input type="file" accept=".csv,.txt,image/*" @change="readFile" />
          </label>
        </div>

        <div class="form-grid">
          <label>
            Nome
            <input v-model="form.name" maxlength="180" />
          </label>
          <label>
            Gênero
            <select v-model="form.gender">
              <option value="female">Feminino</option>
              <option value="male">Masculino</option>
              <option value="unisex">Unissex</option>
              <option value="kids">Infantil</option>
            </select>
          </label>
          <label>
            Modelagem
            <select v-model="form.fit_profile">
              <option value="slim">Slim</option>
              <option value="regular">Regular</option>
              <option value="oversized">Ampla</option>
            </select>
          </label>
        </div>

        <div class="form-grid">
          <label>
            Categoria
            <input v-model="form.category" maxlength="120" placeholder="Ex.: Vestidos" />
          </label>
          <label>
            Marca
            <input v-model="form.brand" maxlength="120" placeholder="Ex.: Coleção própria" />
          </label>
          <label>
            Comparar com
            <select v-model="form.compare_table_id">
              <option value="">Selecionar automaticamente</option>
              <option v-for="table in comparisonOptions" :key="table.id" :value="String(table.id)">
                {{ tableLabel(table) }}
              </option>
            </select>
          </label>
        </div>

        <div class="form-grid">
          <label>
            Base da tabela
            <select v-model="form.measurement_target">
              <option value="body">Corpo</option>
              <option value="garment">Peça</option>
              <option value="mixed">Corpo + peça</option>
            </select>
          </label>
          <label>
            Sistema de tamanho
            <select v-model="form.size_system">
              <option value="br_alpha">BR letras</option>
              <option value="br_numeric">BR numérico</option>
              <option value="international">Internacional</option>
              <option value="custom">Personalizado</option>
            </select>
          </label>
          <label>
            Faixas
            <select v-model="form.range_mode">
              <option value="min_max">Mínimo e máximo</option>
              <option value="exact">Medida exata</option>
              <option value="tolerance">Tolerância</option>
            </select>
          </label>
        </div>

        <label class="assistant-toggle">
          <input v-model="form.explain_for_merchant" type="checkbox" />
          <span>
            <strong>Explicar para o lojista</strong>
            <small>Gera um resumo simples para revisão operacional antes de salvar o rascunho.</small>
          </span>
        </label>

        <label>
          Conteúdo
          <textarea v-model="form.content" rows="14"></textarea>
        </label>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="loading">
            <i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i>
            {{ loading ? 'Analisando...' : 'Sugerir tabela' }}
          </button>
        </div>
      </form>

      <aside class="panel-main import-preview-panel">
        <div class="subsection-heading">
          <h2>Revisão guiada</h2>
          <span v-if="suggestion">{{ suggestion.rows.length }} linhas sugeridas</span>
        </div>

        <div v-if="warnings.length" class="warning-list">
          <span v-for="warning in warnings" :key="warning">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            {{ warning }}
          </span>
        </div>

        <div v-if="reviewContext" class="summary-strip assistant-summary-strip">
          <span>
            <strong>{{ reviewContext.confidence_breakdown.final }}</strong>
            <small>Confiança final</small>
          </span>
          <span>
            <strong>{{ riskLevelLabel(reviewContext.risk_level) }}</strong>
            <small>Risco atual</small>
          </span>
          <span>
            <strong>{{ reviewContext.data_used.learning_signals }}</strong>
            <small>Sinais usados</small>
          </span>
          <span>
            <strong>{{ reviewContext.comparison.overview.changed_sizes }}</strong>
            <small>Tamanhos em destaque</small>
          </span>
        </div>

        <div v-if="reviewContext" class="assistant-review-grid">
          <article class="assistant-card">
            <span class="eyebrow">Dados usados</span>
            <strong>{{ targetLabel(reviewContext.data_used.measurement_target) }} · {{ sizeSystemLabel(reviewContext.data_used.size_system) }}</strong>
            <small>
              {{ rangeModeLabel(reviewContext.data_used.range_mode) }} ·
              {{ reviewContext.data_used.rows_detected }} linhas detectadas
            </small>
            <small v-if="reviewContext.data_used.category">Categoria: {{ reviewContext.data_used.category }}</small>
            <small v-if="reviewContext.data_used.brand">Marca: {{ reviewContext.data_used.brand }}</small>
            <small v-if="reviewContext.data_used.comparison_table">
              Comparando com {{ reviewContext.data_used.comparison_table.name }}
            </small>
          </article>

          <article class="assistant-card">
            <span class="eyebrow">Confiança</span>
            <strong>{{ reviewContext.confidence_breakdown.final }}</strong>
            <small>Parser {{ reviewContext.confidence_breakdown.parser }} · Estrutura {{ reviewContext.confidence_breakdown.structure }}</small>
            <small>Aprendizado {{ reviewContext.confidence_breakdown.learning }} · Comparação {{ reviewContext.confidence_breakdown.comparison }}</small>
            <small>Desconto por risco {{ reviewContext.confidence_breakdown.risk_discount }}</small>
          </article>

          <article class="assistant-card" :class="`assistant-card-${riskClass(reviewContext.risk_level)}`">
            <span class="eyebrow">Risco</span>
            <strong>{{ riskLevelLabel(reviewContext.risk_level) }}</strong>
            <small>{{ reviewContext.risks.length }} ponto(s) para revisar antes de salvar</small>
            <small>
              {{ reviewContext.comparison.overview.changed_fields }} campo(s) com mudança em relação à base atual
            </small>
          </article>
        </div>

        <article v-if="reviewContext?.merchant_explanation" class="assistant-explanation-panel">
          <span class="eyebrow">Explicação simples</span>
          <p>{{ reviewContext.merchant_explanation }}</p>
        </article>

        <div v-if="reviewContext?.risks.length" class="impact-warning-list compact">
          <article v-for="risk in reviewContext.risks" :key="`${risk.level}-${risk.label}`" :class="riskClass(risk.level)">
            <strong>{{ risk.label }}</strong>
            <small>{{ risk.message }}</small>
          </article>
        </div>

        <div v-if="learningContext?.matching_insights.length" class="job-list">
          <article
            v-for="insight in learningContext.matching_insights"
            :key="insight.measurement_table_id"
            class="job-row"
          >
            <i class="fa-solid fa-brain" aria-hidden="true"></i>
            <span>
              <strong>{{ insight.table_name }}</strong>
              <small>{{ actionLabel(insight.suggested_action) }} · {{ insight.reason }}</small>
              <small>
                {{ insight.signals.total }} sinais · {{ insight.signals.purchases }} pedidos ·
                {{ insight.signals.returns }} devoluções/trocas
              </small>
              <small v-if="insight.suggested_adjustment">
                {{ insight.suggested_adjustment.headline }} ·
                {{ adjustmentDirectionLabel(insight.suggested_adjustment.direction) }} ·
                {{ insight.suggested_adjustment.focus_measurements.map(focusLabel).join(', ') }}
              </small>
              <small v-if="insight.suggested_adjustment">
                {{ insight.suggested_adjustment.explanation }}
              </small>
            </span>
          </article>
        </div>

        <section v-if="reviewContext?.comparison.current_table" class="assistant-comparison-panel">
          <div class="subsection-heading">
            <div>
              <h2>Comparação com a tabela atual</h2>
              <span>
                {{ reviewContext.comparison.current_table.name }} vs sugestão atual
              </span>
            </div>
          </div>

          <div class="assistant-comparison-meta">
            <article>
              <span>Atual</span>
              <strong>{{ targetLabel(reviewContext.comparison.current_table.measurement_target) }}</strong>
              <small>{{ sizeSystemLabel(reviewContext.comparison.current_table.size_system) }} · {{ rangeModeLabel(reviewContext.comparison.current_table.range_mode) }}</small>
            </article>
            <article>
              <span>Sugestão</span>
              <strong>{{ targetLabel(reviewContext.comparison.suggested_table.measurement_target) }}</strong>
              <small>{{ sizeSystemLabel(reviewContext.comparison.suggested_table.size_system) }} · {{ rangeModeLabel(reviewContext.comparison.suggested_table.range_mode) }}</small>
            </article>
            <article>
              <span>Visão geral</span>
              <strong>{{ reviewContext.comparison.overview.changed_sizes }} tamanho(s)</strong>
              <small>
                {{ reviewContext.comparison.overview.same_size_system ? 'Mesmo sistema base' : 'Sistema de tamanho diferente' }}
              </small>
            </article>
          </div>

          <div v-if="reviewContext.comparison.rows.length" class="assistant-comparison-list">
            <article v-for="row in reviewContext.comparison.rows" :key="row.size_label">
              <div class="assistant-comparison-head">
                <strong>{{ row.size_label }}</strong>
                <span class="status-pill" :class="comparisonStatusClass(row.status)">{{ comparisonStatusLabel(row.status) }}</span>
              </div>
              <div v-if="row.changes.length" class="assistant-comparison-change-list">
                <span v-for="change in row.changes" :key="`${row.size_label}-${change.field}`">
                  <strong>{{ focusLabel(change.field) }}</strong>
                  <small>{{ change.current || '-' }} → {{ change.suggested || '-' }}</small>
                  <small>{{ formatDelta(change.delta_min) }} / {{ formatDelta(change.delta_max) }}</small>
                </span>
              </div>
            </article>
          </div>
        </section>

        <div v-if="!suggestion" class="empty-state">Nenhuma sugestão carregada.</div>
        <template v-else>
          <div class="form-grid">
            <label>
              Nome
              <input v-model="suggestion.name" maxlength="180" />
            </label>
            <label>
              Base
              <select v-model="suggestion.measurement_target">
                <option value="body">Corpo</option>
                <option value="garment">Peça</option>
                <option value="mixed">Corpo + peça</option>
              </select>
            </label>
            <label>
              Sistema
              <select v-model="suggestion.size_system">
                <option value="br_alpha">BR letras</option>
                <option value="br_numeric">BR numérico</option>
                <option value="international">Internacional</option>
                <option value="custom">Personalizado</option>
              </select>
            </label>
          </div>

          <div class="form-grid">
            <label>
              Status
              <select v-model="suggestion.status">
                <option value="draft">Rascunho</option>
              </select>
            </label>
            <label>
              Fonte
              <select v-model="suggestion.source">
                <option value="ai">Assistente</option>
              </select>
            </label>
            <label>
              Faixas
              <select v-model="suggestion.range_mode">
                <option value="min_max">Mínimo e máximo</option>
                <option value="exact">Medida exata</option>
                <option value="tolerance">Tolerância</option>
              </select>
            </label>
          </div>

          <div v-if="reviewContext?.action_plan.length" class="assistant-action-plan">
            <strong>Fluxo recomendado antes de salvar</strong>
            <span v-for="step in reviewContext.action_plan" :key="step">
              <i class="fa-solid fa-check" aria-hidden="true"></i>
              {{ step }}
            </span>
          </div>

          <div class="subsection-heading">
            <h2>Medidas</h2>
            <button class="btn btn-secondary" type="button" @click="addRow">
              <i class="fa-solid fa-plus" aria-hidden="true"></i>
              Linha
            </button>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tam.</th>
                  <th>Busto</th>
                  <th>Cintura</th>
                  <th>Quadril</th>
                  <th>Altura</th>
                  <th>Peso</th>
                  <th>Comp.</th>
                  <th>Ombro</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in suggestion.rows" :key="index">
                  <td><input v-model="row.size_label" class="table-input short" required /></td>
                  <td class="range-cell">
                    <input v-model.number="row.bust_min" class="table-input mini" type="number" min="0" />
                    <input v-model.number="row.bust_max" class="table-input mini" type="number" min="0" />
                  </td>
                  <td class="range-cell">
                    <input v-model.number="row.waist_min" class="table-input mini" type="number" min="0" />
                    <input v-model.number="row.waist_max" class="table-input mini" type="number" min="0" />
                  </td>
                  <td class="range-cell">
                    <input v-model.number="row.hip_min" class="table-input mini" type="number" min="0" />
                    <input v-model.number="row.hip_max" class="table-input mini" type="number" min="0" />
                  </td>
                  <td class="range-cell">
                    <input v-model.number="row.height_min" class="table-input mini" type="number" min="0" />
                    <input v-model.number="row.height_max" class="table-input mini" type="number" min="0" />
                  </td>
                  <td class="range-cell">
                    <input v-model.number="row.weight_min" class="table-input mini" type="number" min="0" />
                    <input v-model.number="row.weight_max" class="table-input mini" type="number" min="0" />
                  </td>
                  <td class="range-cell">
                    <input v-model.number="row.length_min" class="table-input mini" type="number" min="0" />
                    <input v-model.number="row.length_max" class="table-input mini" type="number" min="0" />
                  </td>
                  <td class="range-cell">
                    <input v-model.number="row.shoulder_min" class="table-input mini" type="number" min="0" />
                    <input v-model.number="row.shoulder_max" class="table-input mini" type="number" min="0" />
                  </td>
                  <td class="row-actions">
                    <button type="button" title="Remover linha" @click="removeRow(index)">
                      <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <label>
            Observações
            <textarea v-model="suggestion.notes" rows="4"></textarea>
          </label>

          <div class="action-row compact">
            <button class="btn btn-primary" type="button" :disabled="!canSave || saving" @click="saveSuggestion">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
              {{ saving ? 'Salvando...' : 'Criar rascunho' }}
            </button>
          </div>
        </template>
      </aside>
    </div>
  </section>
</template>
