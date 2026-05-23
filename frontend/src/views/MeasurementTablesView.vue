<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

type MeasurementRow = {
  id?: number
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

type MeasurementTable = {
  id: number
  name: string
  product_type: string
  gender: string | null
  fit_profile: string | null
  status: string
  source: string
  rows_count?: number
  products_count?: number
  rows?: MeasurementRow[]
}

type MeasurementTemplate = {
  key: string
  name: string
  product_type: string
  gender: string
  fit_profile: string
  rows: MeasurementRow[]
}

const tables = ref<MeasurementTable[]>([])
const templates = ref<MeasurementTemplate[]>([])
const selected = ref<MeasurementTable | null>(null)
const notice = ref('')
const loading = ref(false)

const form = reactive({
  id: null as number | null,
  name: '',
  product_type: 'dress',
  gender: 'female',
  fit_profile: 'regular',
  status: 'active',
  source: 'manual',
  notes: '',
  rows: [] as MeasurementRow[],
})

onMounted(() => {
  loadAll()
})

async function loadAll() {
  loading.value = true

  try {
    const [tablesResponse, templatesResponse] = await Promise.all([
      api.get('/measurement-tables'),
      api.get('/measurement-templates'),
    ])

    tables.value = tablesResponse.data.data
    templates.value = templatesResponse.data.data

    if (!selected.value && tables.value[0]) {
      await selectTable(tables.value[0])
    } else if (!tables.value.length) {
      newTable()
    }
  } finally {
    loading.value = false
  }
}

async function selectTable(table: MeasurementTable) {
  const { data } = await api.get(`/measurement-tables/${table.id}`)
  selected.value = data.data
  fillForm(data.data)
}

function newTable() {
  selected.value = null
  form.id = null
  form.name = ''
  form.product_type = 'dress'
  form.gender = 'female'
  form.fit_profile = 'regular'
  form.status = 'active'
  form.source = 'manual'
  form.notes = ''
  form.rows = ['PP', 'P', 'M', 'G', 'GG'].map((size_label, sort_order) => ({ size_label, sort_order }))
}

function fillForm(table: MeasurementTable) {
  form.id = table.id
  form.name = table.name
  form.product_type = table.product_type
  form.gender = table.gender ?? 'female'
  form.fit_profile = table.fit_profile ?? 'regular'
  form.status = table.status
  form.source = table.source
  form.rows = JSON.parse(JSON.stringify(table.rows ?? []))
}

function applyTemplate(template: MeasurementTemplate) {
  form.name = template.name
  form.product_type = template.product_type
  form.gender = template.gender
  form.fit_profile = template.fit_profile
  form.source = 'template'
  form.rows = JSON.parse(JSON.stringify(template.rows))
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

async function saveTable() {
  const payload = {
    name: form.name,
    product_type: form.product_type,
    gender: form.gender,
    fit_profile: form.fit_profile,
    status: form.status,
    source: form.source,
    notes: form.notes || null,
    rows: form.rows.map((row, sort_order) => ({
      ...row,
      sort_order,
    })),
  }

  const { data } = form.id
    ? await api.patch(`/measurement-tables/${form.id}`, payload)
    : await api.post('/measurement-tables', payload)

  notice.value = form.id ? 'Tabela atualizada.' : 'Tabela criada.'
  selected.value = data.data
  fillForm(data.data)
  await loadAll()
}

async function removeTable(table: MeasurementTable) {
  await api.delete(`/measurement-tables/${table.id}`)
  selected.value = null
  newTable()
  await loadAll()
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Tabelas</span>
        <h1>Tabelas de medidas</h1>
      </div>
      <button class="btn btn-secondary" type="button" @click="newTable">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        Nova tabela
      </button>
    </div>

    <p v-if="notice" class="success-message">{{ notice }}</p>

    <div class="template-strip">
      <button v-for="template in templates" :key="template.key" type="button" @click="applyTemplate(template)">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        {{ template.name }}
      </button>
    </div>

    <div class="app-grid">
      <aside class="panel-list">
        <div v-if="loading" class="empty-state">Carregando tabelas...</div>
        <template v-else>
          <button
            v-for="table in tables"
            :key="table.id"
            class="list-row"
            :class="{ active: selected?.id === table.id }"
            type="button"
            @click="selectTable(table)"
          >
            <strong>{{ table.name }}</strong>
            <span>{{ table.rows_count ?? 0 }} linhas · {{ table.products_count ?? 0 }} produtos</span>
          </button>
        </template>
      </aside>

      <form class="panel-main admin-form" @submit.prevent="saveTable">
        <div class="form-grid">
          <label>
            Nome
            <input v-model="form.name" required maxlength="180" />
          </label>
          <label>
            Tipo
            <select v-model="form.product_type">
              <option value="dress">Vestido</option>
              <option value="shirt">Camiseta</option>
              <option value="pants">Calca</option>
              <option value="skirt">Saia</option>
            </select>
          </label>
          <label>
            Genero
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
          <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            Salvar tabela
          </button>
          <button
            v-if="selected"
            class="btn btn-danger"
            type="button"
            title="Remover tabela"
            @click="removeTable(selected)"
          >
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
          </button>
        </div>
      </form>
    </div>
  </section>
</template>
