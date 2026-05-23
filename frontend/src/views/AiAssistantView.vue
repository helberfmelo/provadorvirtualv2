<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

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
  unit: string
  status: string
  source: string
  notes: string
  rows: MeasurementRow[]
  warnings: string[]
}

const status = ref<Record<string, string | boolean | null>>({})
const suggestion = ref<Suggestion | null>(null)
const loading = ref(false)
const saving = ref(false)
const notice = ref('')
const error = ref('')
const warnings = ref<string[]>([])

const form = reactive({
  source_type: 'text',
  filename: '',
  name: 'Tabela assistida',
  product_type: 'shirt',
  gender: 'unisex',
  fit_profile: 'regular',
  content: '',
  image_data: '',
})

const canSave = computed(() => Boolean(suggestion.value?.rows.length))

onMounted(() => {
  loadStatus()
  applySample()
})

async function loadStatus() {
  const { data } = await api.get('/ai/status')
  status.value = data.data
}

async function suggestTable() {
  loading.value = true
  notice.value = ''
  error.value = ''
  warnings.value = []
  suggestion.value = null

  try {
    const { data } = await api.post('/ai/measurement-table-suggestions', {
      source_type: form.source_type,
      filename: form.filename || null,
      name: form.name || null,
      product_type: form.product_type,
      gender: form.gender,
      fit_profile: form.fit_profile,
      content: form.content || null,
      image_data: form.image_data || null,
    })

    suggestion.value = data.data.suggestion
    warnings.value = data.data.warnings || []
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel sugerir a tabela.'
  } finally {
    loading.value = false
  }
}

async function saveSuggestion() {
  if (!suggestion.value) {
    return
  }

  saving.value = true
  notice.value = ''
  error.value = ''

  try {
    const payload = {
      name: suggestion.value.name,
      product_type: suggestion.value.product_type,
      gender: suggestion.value.gender,
      fit_profile: suggestion.value.fit_profile,
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
    notice.value = 'Tabela criada como rascunho.'
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel criar a tabela.'
  } finally {
    saving.value = false
  }
}

function applySample() {
  form.source_type = 'text'
  form.filename = ''
  form.name = 'Camisas assistidas'
  form.product_type = 'shirt'
  form.gender = 'unisex'
  form.fit_profile = 'regular'
  form.image_data = ''
  form.content = [
    'Tamanho Busto Cintura Quadril',
    'P 88-94 70-76 92-98',
    'M 94-100 76-82 98-104',
    'G 100-108 82-90 104-112',
  ].join('\n')
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
        <h1>Tabelas por imagem ou texto</h1>
      </div>
      <button class="btn btn-secondary" type="button" @click="applySample">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        Exemplo
      </button>
    </div>

    <p v-if="notice" class="success-message">{{ notice }}</p>
    <p v-if="error" class="form-error">{{ error }}</p>

    <div class="assistant-status">
      <span class="status-pill ok">
        <i class="fa-solid fa-font" aria-hidden="true"></i>
        Texto ativo
      </span>
      <span class="status-pill" :class="{ ok: status.image_ocr }">
        <i class="fa-solid fa-image" aria-hidden="true"></i>
        {{ status.image_ocr ? 'Imagem ativa' : 'Imagem pendente' }}
      </span>
      <span class="status-pill ok">
        <i class="fa-solid fa-user-check" aria-hidden="true"></i>
        Revisao obrigatoria
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
              <option value="shirt">Camisa</option>
              <option value="dress">Vestido</option>
              <option value="pants">Calca</option>
              <option value="skirt">Saia</option>
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

        <label>
          Conteudo
          <textarea v-model="form.content" rows="14"></textarea>
        </label>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="loading">
            <i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i>
            Sugerir tabela
          </button>
        </div>
      </form>

      <aside class="panel-main import-preview-panel">
        <div class="subsection-heading">
          <h2>Sugestao</h2>
          <span v-if="suggestion">{{ suggestion.rows.length }} linhas</span>
        </div>

        <div v-if="warnings.length" class="warning-list">
          <span v-for="warning in warnings" :key="warning">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            {{ warning }}
          </span>
        </div>

        <div v-if="!suggestion" class="empty-state">Nenhuma sugestao carregada.</div>
        <template v-else>
          <div class="form-grid">
            <label>
              Nome
              <input v-model="suggestion.name" maxlength="180" />
            </label>
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
            Observacoes
            <textarea v-model="suggestion.notes" rows="3"></textarea>
          </label>

          <div class="action-row compact">
            <button class="btn btn-primary" type="button" :disabled="!canSave || saving" @click="saveSuggestion">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
              Criar rascunho
            </button>
          </div>
        </template>
      </aside>
    </div>
  </section>
</template>
