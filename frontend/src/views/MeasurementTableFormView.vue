<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import type { FitProfile, MeasurementCustomVariation, MeasurementRow, MeasurementTable, MeasurementTemplate } from '../services/merchantTypes'

const route = useRoute()
const router = useRouter()
const tableId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(tableId.value))

const templates = ref<MeasurementTemplate[]>([])
const fitProfiles = ref<FitProfile[]>([])
const selectedTemplateKey = ref('')
const activeMeasureGroup = ref<'body' | 'garment' | 'system' | 'composite'>('body')
const loading = ref(false)
const saving = ref(false)
const error = ref('')

const baseProductTypes = [
  { value: 'dress', label: 'Vestido' },
  { value: 'shirt', label: 'Camisa/Camiseta' },
  { value: 'blouse', label: 'Blusa' },
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

const productTypeOptions = computed(() => {
  const options = new Map(baseProductTypes.map((option) => [option.value, option.label]))
  templates.value.forEach((template) => {
    options.set(template.product_type, template.product_type_label || options.get(template.product_type) || template.product_type)
  })

  return Array.from(options, ([value, label]) => ({ value, label }))
})

const filteredTemplates = computed(() => templates.value.filter((template) => {
  const typeOk = !form.product_type || template.product_type === form.product_type
  const genderOk = !form.gender || template.gender === form.gender || template.gender === 'unisex' || form.gender === 'unisex'

  return typeOk && genderOk
}))

const visibleTemplates = computed(() => filteredTemplates.value.length ? filteredTemplates.value : templates.value.slice(0, 12))
const selectedTemplate = computed(() => templates.value.find((template) => template.key === selectedTemplateKey.value) || null)

const form = reactive({
  name: '',
  product_type: 'dress',
  gender: 'female',
  fit_profile: 'regular',
  measurement_target: 'body',
  size_system: 'br_alpha',
  range_mode: 'min_max',
  status: 'active',
  source: 'manual',
  notes: '',
  virtual_try_on_enabled: true,
  custom_variations: [] as MeasurementCustomVariation[],
  rows: [] as MeasurementRow[],
})

const measurementFields = [
  { key: 'bust', label: 'Busto', short: 'Busto', group: 'body', helper: 'Corpo: tórax ou busto. Peça: largura na altura do busto.' },
  { key: 'waist', label: 'Cintura', short: 'Cint.', group: 'body', helper: 'Corpo: cintura natural. Peça: cintura da roupa fechada.' },
  { key: 'hip', label: 'Quadril', short: 'Quad.', group: 'body', helper: 'Corpo: ponto mais largo. Peça: região do quadril.' },
  { key: 'height', label: 'Altura', short: 'Alt.', group: 'body', helper: 'Altura total do consumidor, útil para vestidos e peças longas.' },
  { key: 'weight', label: 'Peso', short: 'Peso', group: 'body', helper: 'Ajuda a IA quando a tabela é corporal e não há medidas detalhadas.' },
  { key: 'length', label: 'Comprimento', short: 'Comp.', group: 'garment', helper: 'Medida da peça, como manga, corpo ou barra.' },
  { key: 'shoulder', label: 'Ombro', short: 'Ombro', group: 'garment', helper: 'Largura de ombro da peça ou do corpo, conforme a base escolhida.' },
] as const

const measurementGroups = [
  { key: 'body', title: 'Corpo', badge: 'Recomendação', description: 'Busto, cintura, quadril, altura e peso usados para calcular tamanho.' },
  { key: 'garment', title: 'Peça', badge: 'Tabela visual', description: 'Comprimento e ombro para conferir caimento e medidas da roupa.' },
  { key: 'system', title: 'Sistema', badge: 'Tamanhos', description: 'BR letras, numérico, internacional ou grade personalizada.' },
  { key: 'composite', title: 'Compostas', badge: 'Consistência', description: 'Soma ou balanço de medidas para revisar proporção da grade.' },
] as const

const activeGroup = computed(() => measurementGroups.find((group) => group.key === activeMeasureGroup.value) || measurementGroups[0])
const activeGroupFields = computed(() => measurementFields.filter((field) => field.group === activeMeasureGroup.value))
const variationFieldOptions = computed(() => [
  ...measurementFields.map((field) => ({ value: field.key, label: field.label })),
  { value: 'composite', label: 'Composta' },
])

const previewFields = computed(() => {
  const used = measurementFields.filter((field) => form.rows.some((row) => hasRange(row, field.key)))

  return used.length ? used : measurementFields.slice(0, 3)
})

const tableValidationErrors = computed(() => {
  const errors: string[] = []

  form.rows.forEach((row, index) => {
    measurementFields.forEach((field) => {
      const min = row[`${field.key}_min` as keyof MeasurementRow]
      const max = row[`${field.key}_max` as keyof MeasurementRow]

      if (typeof min === 'number' && typeof max === 'number' && min > max) {
        errors.push(`${row.size_label || `Linha ${index + 1}`}: ${field.label} com máximo menor que mínimo.`)
      }
    })

    if (typeof row.composite_min === 'number' && typeof row.composite_max === 'number' && row.composite_min > row.composite_max) {
      errors.push(`${row.size_label || `Linha ${index + 1}`}: medida composta com máximo menor que mínimo.`)
    }
  })

  form.custom_variations.forEach((variation, index) => {
    if (variation.mode === 'restricted' && (variation.min === null || variation.min === undefined || variation.max === null || variation.max === undefined)) {
      errors.push(`Variação ${index + 1}: informe mínimo e máximo para o modo restrito.`)
    }

    if (typeof variation.min === 'number' && typeof variation.max === 'number' && variation.min > variation.max) {
      errors.push(`Variação ${index + 1}: máximo menor que mínimo.`)
    }
  })

  return errors
})

const canSave = computed(() => !tableValidationErrors.value.length)

const fallbackFitProfiles = [
  { code: 'slim', name: 'Slim' },
  { code: 'regular', name: 'Regular' },
  { code: 'oversized', name: 'Ampla' },
  { code: 'loose', name: 'Solta' },
  { code: 'comfort', name: 'Conforto' },
]

const fitProfileOptions = computed(() => {
  const options = new Map(fallbackFitProfiles.map((profile) => [profile.code, profile.name]))
  fitProfiles.value
    .filter((profile) => profile.status === 'active' || profile.code === form.fit_profile)
    .forEach((profile) => options.set(profile.code, profile.name))

  return Array.from(options, ([code, name]) => ({ code, name }))
})

onMounted(() => {
  loadForm()
})

async function loadForm() {
  loading.value = true
  error.value = ''

  try {
    const [templatesResponse, profilesResponse] = await Promise.all([
      api.get('/measurement-templates'),
      api.get('/fit-profiles').catch(() => ({ data: { data: [] } })),
    ])
    templates.value = templatesResponse.data.data
    fitProfiles.value = profilesResponse.data.data

    if (editing.value) {
      const { data } = await api.get(`/measurement-tables/${tableId.value}`)
      fillForm(data.data)
      return
    }

    newRows()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar a tabela.'
  } finally {
    loading.value = false
  }
}

function newRows() {
  form.rows = ['PP', 'P', 'M', 'G', 'GG'].map((size_label, sort_order) => ({ size_label, sort_order }))
}

function fillForm(table: MeasurementTable) {
  form.name = table.name
  form.product_type = table.product_type
  form.gender = table.gender ?? 'female'
  form.fit_profile = table.fit_profile ?? 'regular'
  form.measurement_target = table.measurement_target ?? 'body'
  form.size_system = table.size_system ?? 'br_alpha'
  form.range_mode = table.range_mode ?? 'min_max'
  form.status = table.status
  form.source = table.source
  form.notes = table.notes ?? ''
  form.virtual_try_on_enabled = table.activation?.virtual_try_on_enabled ?? true
  form.custom_variations = JSON.parse(JSON.stringify(table.custom_variations ?? []))
  form.rows = (JSON.parse(JSON.stringify(table.rows ?? [])) as MeasurementRow[]).map(withCompositeFields)
}

function applyTemplate(template: MeasurementTemplate) {
  form.name = template.name
  form.product_type = template.product_type
  form.gender = template.gender
  form.fit_profile = template.fit_profile
  form.measurement_target = 'body'
  form.size_system = 'br_alpha'
  form.range_mode = 'min_max'
  form.source = 'template'
  form.virtual_try_on_enabled = true
  form.custom_variations = []
  form.notes = [
    template.market_basis,
    template.fields?.length ? `Campos originais: ${template.fields.join(', ')}` : '',
  ].filter(Boolean).join('\n')
  form.rows = (JSON.parse(JSON.stringify(template.rows)) as MeasurementRow[]).map(withCompositeFields)
}

function applySelectedTemplate() {
  const template = selectedTemplate.value || visibleTemplates.value[0]

  if (template) {
    selectedTemplateKey.value = template.key
    applyTemplate(template)
  }
}

function fieldSummary(template: MeasurementTemplate) {
  const labels: Record<string, string> = {
    bust: 'busto',
    waist: 'cintura',
    hip: 'quadril',
    height: 'altura',
    weight: 'peso',
    length: 'comprimento',
    shoulder: 'ombro',
  }
  const fields = Object.entries(labels)
    .filter(([field]) => template.rows.some((row) => row[`${field}_min` as keyof MeasurementRow] || row[`${field}_max` as keyof MeasurementRow]))
    .map(([, label]) => label)

  return fields.length ? fields.join(', ') : 'medidas principais'
}

function addRow() {
  form.rows.push({
    size_label: '',
    sort_order: form.rows.length,
  })
}

function removeRow(index: number) {
  form.rows.splice(index, 1)
}

function addCustomVariation() {
  form.custom_variations.push({
    field: 'bust',
    mode: 'restricted',
    min: 1,
    max: 3,
    note: 'Margem de conforto',
  })
}

function removeCustomVariation(index: number) {
  form.custom_variations.splice(index, 1)
}

function hasRange(row: MeasurementRow, field: string) {
  const min = row[`${field}_min` as keyof MeasurementRow]
  const max = row[`${field}_max` as keyof MeasurementRow]

  return (min !== null && min !== undefined && min !== '') || (max !== null && max !== undefined && max !== '')
}

function fieldLabel(field: string) {
  return variationFieldOptions.value.find((option) => option.value === field)?.label || field
}

function formatRange(row: MeasurementRow, field: string) {
  const min = row[`${field}_min` as keyof MeasurementRow]
  const max = row[`${field}_max` as keyof MeasurementRow]

  if ((min === null || min === undefined || min === '') && (max === null || max === undefined || max === '')) {
    return '-'
  }

  if (form.range_mode === 'exact' || min === max) {
    return `${min ?? max} cm`
  }

  return `${min ?? '-'}-${max ?? '-'} cm`
}

function withCompositeFields(row: MeasurementRow): MeasurementRow {
  const composite = row.composite_measurements?.fit_balance || {}

  return {
    ...row,
    note: row.note ?? row.size_note ?? null,
    measurement_notes: row.measurement_notes ?? {},
    composite_min: composite.min ?? null,
    composite_max: composite.max ?? null,
  }
}

function buildMeasurements(row: MeasurementRow) {
  const labels: Record<string, string> = {
    bust: 'Busto',
    waist: 'Cintura',
    hip: 'Quadril',
    height: 'Altura',
    weight: 'Peso',
    length: 'Comprimento',
    shoulder: 'Ombro',
  }

  return Object.fromEntries(Object.entries(labels).flatMap(([field, label]) => {
    const min = row[`${field}_min` as keyof MeasurementRow]
    const max = row[`${field}_max` as keyof MeasurementRow]

    return (min !== null && min !== undefined) || (max !== null && max !== undefined)
      ? [[field, { label, min, max }]]
      : []
  }))
}

function buildCompositeMeasurements(row: MeasurementRow) {
  if (
    (row.composite_min === null || row.composite_min === undefined)
    && (row.composite_max === null || row.composite_max === undefined)
  ) {
    return {}
  }

  return {
    fit_balance: {
      label: 'Busto + cintura + quadril',
      formula: 'bust+waist+hip',
      min: row.composite_min ?? null,
      max: row.composite_max ?? null,
    },
  }
}

async function saveTable() {
  saving.value = true
  error.value = ''

  const payload = {
    name: form.name,
    product_type: form.product_type,
    gender: form.gender,
    fit_profile: form.fit_profile,
    measurement_target: form.measurement_target,
    size_system: form.size_system,
    range_mode: form.range_mode,
    status: form.status,
    source: form.source,
    notes: form.notes || null,
    virtual_try_on_enabled: form.virtual_try_on_enabled,
    custom_variations: form.custom_variations,
    rows: form.rows.map((row, sort_order) => ({
      ...row,
      note: row.note || null,
      measurement_notes: row.measurement_notes || {},
      sort_order,
      measurements: buildMeasurements(row),
      composite_measurements: buildCompositeMeasurements(row),
    })),
  }

  if (!canSave.value) {
    error.value = tableValidationErrors.value[0] || 'Revise as faixas antes de salvar.'
    saving.value = false
    return
  }

  try {
    editing.value
      ? await api.patch(`/measurement-tables/${tableId.value}`, payload)
      : await api.post('/measurement-tables', payload)
    await router.push('/app/tabelas-de-medidas')
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar a tabela.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Tabelas</span>
        <h1>{{ editing ? 'Editar tabela' : 'Nova tabela' }}</h1>
        <p>Configure a tabela e suas faixas por tamanho em uma tela dedicada.</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/app/tabelas-de-medidas">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="panel-main smart-catalog-panel">
      <div>
        <span class="eyebrow">
          <i class="fa-solid fa-brain" aria-hidden="true"></i>
          Base inteligente + IA
        </span>
        <h2>Comece por uma tabela pronta do mercado brasileiro</h2>
        <p>
          A base herdada do v1 cruza gênero, categoria, altura, peso, idade e formato corporal. A IA ajuda a acelerar
          a criação, mas a tabela sempre fica sob revisão do lojista antes de ir para a loja.
        </p>
      </div>
      <div class="smart-catalog-actions">
        <label>
          Modelo
          <select v-model="selectedTemplateKey">
            <option value="">Selecionar modelo inteligente</option>
            <option v-for="template in visibleTemplates" :key="template.key" :value="template.key">
              {{ template.name }} - {{ template.gender_label || template.gender }} / {{ template.product_type_label || template.product_type }}
            </option>
          </select>
        </label>
        <button class="btn btn-primary" type="button" @click="applySelectedTemplate">
          <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
          Carregar modelo
        </button>
      </div>
      <div class="smart-catalog-meta">
        <span><strong>{{ templates.length }}</strong> modelos padrão</span>
        <span><strong>{{ visibleTemplates.length }}</strong> compativeis com os filtros atuais</span>
        <span v-if="selectedTemplate">Campos: {{ fieldSummary(selectedTemplate) }}</span>
      </div>
    </section>

    <form class="panel-main admin-form form-wide" @submit.prevent="saveTable">
      <div class="form-grid">
        <label>
          Nome
          <input v-model="form.name" required maxlength="180" />
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
            <option v-for="profile in fitProfileOptions" :key="profile.code" :value="profile.code">
              {{ profile.name }}
            </option>
          </select>
        </label>
        <label>
          Base da tabela
          <select v-model="form.measurement_target">
            <option value="body">Corpo</option>
            <option value="garment">Peça</option>
            <option value="mixed">Corpo + peça</option>
          </select>
        </label>
        <label>
          Sistema
          <select v-model="form.size_system">
            <option value="br_alpha">BR letras</option>
            <option value="br_numeric">BR numérico</option>
            <option value="international">Internacional</option>
            <option value="custom">Personalizado</option>
          </select>
        </label>
        <label>
          Ranges
          <select v-model="form.range_mode">
            <option value="min_max">Mínimo e máximo</option>
            <option value="exact">Medida exata</option>
            <option value="tolerance">Tolerância</option>
          </select>
        </label>
        <label>
          Status
          <select v-model="form.status">
            <option value="active">Ativa</option>
            <option value="draft">Rascunho</option>
            <option value="inactive">Inativa</option>
          </select>
        </label>
        <label class="full-row">
          Observações da tabela
          <textarea v-model="form.notes" rows="3" maxlength="1200" placeholder="Ex.: base do fornecedor, revisão feita, exceções por modelagem"></textarea>
        </label>
      </div>

      <section class="measurement-editor-grid">
        <article class="measurement-editor-card emphasis">
          <div>
            <span class="eyebrow">Uso público</span>
            <h3>{{ form.virtual_try_on_enabled ? 'Provador + tabela' : 'Somente tabela de medidas' }}</h3>
            <p>{{ form.virtual_try_on_enabled ? 'A tabela participa da recomendação de tamanho e também aparece no widget.' : 'Produtos vinculados mostram a tabela, mas não abrem a recomendação automática.' }}</p>
          </div>
          <label class="product-switch" :class="{ active: form.virtual_try_on_enabled }">
            <input v-model="form.virtual_try_on_enabled" type="checkbox" />
            <span>
              <strong>Provador Virtual</strong>
              <small>{{ form.virtual_try_on_enabled ? 'Liberado por esta tabela' : 'Desativado por esta tabela' }}</small>
            </span>
          </label>
        </article>

        <article
          v-for="group in measurementGroups"
          :key="group.key"
          class="measurement-editor-card"
          :class="{ active: activeMeasureGroup === group.key }"
          role="button"
          tabindex="0"
          @click="activeMeasureGroup = group.key"
          @keydown.enter.prevent="activeMeasureGroup = group.key"
        >
          <span>{{ group.badge }}</span>
          <strong>{{ group.title }}</strong>
          <small>{{ group.description }}</small>
        </article>
      </section>

      <section class="measurement-guided-block">
        <div class="subsection-heading">
          <div>
            <h2>{{ activeGroup.title }}</h2>
            <span>{{ activeGroup.description }}</span>
          </div>
        </div>

        <div v-if="activeMeasureGroup === 'system'" class="measurement-system-grid">
          <article>
            <span>Sistema</span>
            <strong>{{ form.size_system === 'br_numeric' ? 'BR numérico' : form.size_system === 'international' ? 'Internacional' : form.size_system === 'custom' ? 'Personalizado' : 'BR letras' }}</strong>
            <small>Use a coluna Tam. para manter a grade final que o consumidor verá.</small>
          </article>
          <article>
            <span>Faixas</span>
            <strong>{{ form.range_mode === 'exact' ? 'Medida exata' : form.range_mode === 'tolerance' ? 'Tolerância' : 'Mínimo e máximo' }}</strong>
            <small>As validações bloqueiam máximo menor que mínimo antes de salvar.</small>
          </article>
          <article>
            <span>Base</span>
            <strong>{{ form.measurement_target === 'garment' ? 'Peça' : form.measurement_target === 'mixed' ? 'Corpo + peça' : 'Corpo' }}</strong>
            <small>A base define como interpretar os campos de medida.</small>
          </article>
        </div>

        <div v-else-if="activeMeasureGroup === 'composite'" class="measurement-system-grid">
          <article>
            <span>Fórmula atual</span>
            <strong>Busto + cintura + quadril</strong>
            <small>Use a coluna Composta para revisar proporção geral da grade.</small>
          </article>
          <article>
            <span>Validação</span>
            <strong>Mínimo e máximo</strong>
            <small>O mesmo bloqueio de consistência vale para medidas compostas.</small>
          </article>
          <article>
            <span>Prévia</span>
            <strong>Consumidor</strong>
            <small>A tabela pública continua mostrando os campos reais preenchidos.</small>
          </article>
        </div>

        <div v-else class="measurement-field-grid">
          <article v-for="field in activeGroupFields" :key="field.key">
            <strong>{{ field.label }}</strong>
            <small>{{ field.helper }}</small>
          </article>
        </div>
      </section>

      <div class="subsection-heading">
        <h2>Faixas por tamanho</h2>
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
              <th>Comprimento</th>
              <th>Ombro</th>
              <th>Composta</th>
              <th>Obs.</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in form.rows" :key="index">
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
              <td class="range-cell">
                <input v-model.number="row.composite_min" class="table-input mini" type="number" min="0" />
                <input v-model.number="row.composite_max" class="table-input mini" type="number" min="0" />
              </td>
              <td><input v-model="row.note" class="table-input note" maxlength="500" placeholder="Revisão, peça ou medida" /></td>
              <td class="row-actions">
                <button type="button" title="Remover linha" @click="removeRow(index)">
                  <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <section class="custom-variation-panel">
        <div class="subsection-heading">
          <div>
            <h2>Variação personalizada</h2>
            <span>Exemplos: busto restrito +1 a +3 cm, quadril amplo sem limite rígido ou composta para revisar proporção.</span>
          </div>
          <button class="btn btn-secondary" type="button" @click="addCustomVariation">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Variação
          </button>
        </div>

        <div v-if="form.custom_variations.length" class="custom-variation-list">
          <article v-for="(variation, index) in form.custom_variations" :key="index">
            <label>
              Medida
              <select v-model="variation.field">
                <option v-for="field in variationFieldOptions" :key="field.value" :value="field.value">
                  {{ field.label }}
                </option>
              </select>
            </label>
            <label>
              Tipo
              <select v-model="variation.mode">
                <option value="restricted">Restrita</option>
                <option value="wide">Ampla</option>
              </select>
            </label>
            <label>
              Mín.
              <input v-model.number="variation.min" type="number" min="0" max="999.99" step="0.01" />
            </label>
            <label>
              Máx.
              <input v-model.number="variation.max" type="number" min="0" max="999.99" step="0.01" />
            </label>
            <label class="variation-note">
              Observação
              <input v-model="variation.note" maxlength="500" :placeholder="`Ex.: ajuste em ${fieldLabel(variation.field).toLowerCase()}`" />
            </label>
            <button type="button" class="icon-link" title="Remover variação" @click="removeCustomVariation(index)">
              <i class="fa-solid fa-trash" aria-hidden="true"></i>
            </button>
          </article>
        </div>
        <p v-else class="empty-state compact">Nenhuma variação personalizada aplicada.</p>
      </section>

      <section class="measurement-preview-panel">
        <div class="subsection-heading">
          <div>
            <h2>Prévia no widget</h2>
            <span>{{ form.virtual_try_on_enabled ? 'Botões Descubra seu tamanho e Tabela de Medidas' : 'Apenas Tabela de Medidas para produtos vinculados' }}</span>
          </div>
          <span class="status-pill" :class="{ ok: form.virtual_try_on_enabled, warning: !form.virtual_try_on_enabled }">
            {{ form.virtual_try_on_enabled ? 'Provador ativo' : 'Somente tabela' }}
          </span>
        </div>

        <div class="widget-table-preview">
          <div>
            <strong>{{ form.name || 'Tabela de medidas' }}</strong>
            <small>{{ form.range_mode === 'exact' ? 'Medidas exatas em cm' : 'Faixas em cm' }}</small>
          </div>
          <table>
            <thead>
              <tr>
                <th>Tamanho</th>
                <th v-for="field in previewFields" :key="field.key">{{ field.short }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in form.rows.slice(0, 8)" :key="`${row.size_label}-${index}`">
                <td>{{ row.size_label || '-' }}</td>
                <td v-for="field in previewFields" :key="field.key">{{ formatRange(row, field.key) }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="form.custom_variations.length" class="variation-preview-tags">
            <span v-for="(variation, index) in form.custom_variations" :key="index">
              {{ fieldLabel(variation.field) }} · {{ variation.mode === 'wide' ? 'ampla' : `${variation.min ?? '-'}-${variation.max ?? '-'} cm` }}
            </span>
          </div>
        </div>
      </section>

      <div v-if="tableValidationErrors.length" class="validation-list">
        <strong>Revise antes de salvar</strong>
        <span v-for="item in tableValidationErrors.slice(0, 5)" :key="item">{{ item }}</span>
      </div>

      <div class="action-row compact">
        <button class="btn btn-primary" type="submit" :disabled="saving || loading || !canSave">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar tabela' }}
        </button>
      </div>
    </form>
  </section>
</template>
