<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import type { FitProfile, MeasurementRow, MeasurementTable, MeasurementTemplate } from '../services/merchantTypes'

const route = useRoute()
const router = useRouter()
const tableId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(tableId.value))

const templates = ref<MeasurementTemplate[]>([])
const fitProfiles = ref<FitProfile[]>([])
const selectedTemplateKey = ref('')
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
  rows: [] as MeasurementRow[],
})

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

function withCompositeFields(row: MeasurementRow): MeasurementRow {
  const composite = row.composite_measurements?.fit_balance || {}

  return {
    ...row,
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
    rows: form.rows.map((row, sort_order) => ({
      ...row,
      sort_order,
      measurements: buildMeasurements(row),
      composite_measurements: buildCompositeMeasurements(row),
    })),
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
      </div>

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
              <td class="row-actions">
                <button type="button" title="Remover linha" @click="removeRow(index)">
                  <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="action-row compact">
        <button class="btn btn-primary" type="submit" :disabled="saving || loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar tabela' }}
        </button>
      </div>
    </form>
  </section>
</template>
