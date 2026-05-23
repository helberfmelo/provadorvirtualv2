<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import type { MeasurementRow, MeasurementTable, MeasurementTemplate } from '../services/merchantTypes'

const route = useRoute()
const router = useRouter()
const tableId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(tableId.value))

const templates = ref<MeasurementTemplate[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')

const form = reactive({
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
  loadForm()
})

async function loadForm() {
  loading.value = true
  error.value = ''

  try {
    const templatesResponse = await api.get('/measurement-templates')
    templates.value = templatesResponse.data.data

    if (editing.value) {
      const { data } = await api.get(`/measurement-tables/${tableId.value}`)
      fillForm(data.data)
      return
    }

    newRows()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar a tabela.'
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
  saving.value = true
  error.value = ''

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

  try {
    editing.value
      ? await api.patch(`/measurement-tables/${tableId.value}`, payload)
      : await api.post('/measurement-tables', payload)
    await router.push('/app/tabelas-de-medidas')
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel salvar a tabela.'
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

    <div class="template-strip">
      <button v-for="template in templates" :key="template.key" type="button" @click="applyTemplate(template)">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        {{ template.name }}
      </button>
    </div>

    <form class="panel-main admin-form form-wide" @submit.prevent="saveTable">
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
        <button class="btn btn-primary" type="submit" :disabled="saving || loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar tabela' }}
        </button>
      </div>
    </form>
  </section>
</template>
