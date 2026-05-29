<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import type { MerchantCategory, MerchantCategorySuggestion, TaxonomyCategory } from '../services/merchantTypes'
import { showFeedback } from '../services/saveFeedback'

type CategorySummary = {
  local_categories: number
  mapped_categories: number
  pending_categories: number
  taxonomy_categories: number
  products_with_category: number
  products_with_normalized_category: number
  duplicate_groups: number
}

type DuplicateGroup = {
  fingerprint: string
  suggested_name: string
  categories: string[]
  products_count: number
}

type CategoryTypeOption = {
  value: string
  label: string
}

const categories = ref<MerchantCategory[]>([])
const taxonomyCategories = ref<TaxonomyCategory[]>([])
const categoryTypes = ref<CategoryTypeOption[]>([])
const duplicateGroups = ref<DuplicateGroup[]>([])
const selectedId = ref<number | null>(null)
const loading = ref(false)
const saving = ref(false)
const importing = ref(false)
const applyingCategoryId = ref<number | null>(null)
const error = ref('')
const importContent = ref('')
const importPreview = ref<any[] | null>(null)
const mergeSourceIds = ref<number[]>([])

const emptySummary = (): CategorySummary => ({
  local_categories: 0,
  mapped_categories: 0,
  pending_categories: 0,
  taxonomy_categories: 0,
  products_with_category: 0,
  products_with_normalized_category: 0,
  duplicate_groups: 0,
})

const summary = reactive<CategorySummary>(emptySummary())
const form = reactive({
  name: '',
  taxonomy_category_id: '',
  taxonomy_name: '',
  category_type: 'other',
  gender: '',
  age_group: '',
  translation_pt_br: '',
  source: 'manual',
  status: 'active',
  apply_to_products: true,
})

const selectedCategory = computed(() => categories.value.find((category) => category.id === selectedId.value) || null)
const selectedSuggestion = computed(() => selectedCategory.value?.suggestion || null)
const pendingCategories = computed(() => categories.value.filter((category) => !category.taxonomy_category_id))
const mappedCategories = computed(() => categories.value.filter((category) => category.taxonomy_category_id))
const selectedDuplicateGroup = computed(() => duplicateGroups.value.find((group) => (
  selectedCategory.value ? group.categories.includes(selectedCategory.value.name) : false
)) || null)
const mergeCandidates = computed(() => {
  if (!selectedDuplicateGroup.value || !selectedCategory.value) {
    return []
  }

  return categories.value.filter((category) => (
    category.id !== selectedCategory.value?.id
    && selectedDuplicateGroup.value?.categories.includes(category.name)
  ))
})
const canMerge = computed(() => Boolean(selectedCategory.value) && mergeSourceIds.value.length > 0)
const importPreviewSummary = computed(() => {
  if (!importPreview.value) {
    return null
  }

  const valid = importPreview.value.filter((row) => row.valid).length

  return `${valid}/${importPreview.value.length} linhas válidas`
})

onMounted(() => {
  loadCategories()
})

async function loadCategories(preferredId: number | null = selectedId.value) {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/categories')
    categories.value = data.data || []
    taxonomyCategories.value = data.taxonomy_categories || []
    duplicateGroups.value = data.duplicate_groups || []
    categoryTypes.value = data.category_types || []
    Object.assign(summary, emptySummary(), data.summary || {})

    const preferred = categories.value.find((category) => category.id === preferredId) || categories.value[0] || null
    if (preferred) {
      selectCategory(preferred)
    } else {
      newCategory()
    }
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar as categorias.'
  } finally {
    loading.value = false
  }
}

function selectCategory(category: MerchantCategory) {
  selectedId.value = category.id
  form.name = category.name
  form.taxonomy_category_id = category.taxonomy_category_id ? String(category.taxonomy_category_id) : ''
  form.taxonomy_name = ''
  form.category_type = category.taxonomy_category?.category_type || category.suggestion?.category_type || 'other'
  form.gender = category.taxonomy_category?.gender || ''
  form.age_group = category.taxonomy_category?.age_group || ''
  form.translation_pt_br = category.taxonomy_category?.translations?.pt_BR || ''
  form.source = category.source || 'manual'
  form.status = category.status || 'active'
  form.apply_to_products = true
  mergeSourceIds.value = []
}

function newCategory() {
  selectedId.value = null
  form.name = ''
  form.taxonomy_category_id = ''
  form.taxonomy_name = ''
  form.category_type = 'other'
  form.gender = ''
  form.age_group = ''
  form.translation_pt_br = ''
  form.source = 'manual'
  form.status = 'active'
  form.apply_to_products = true
  mergeSourceIds.value = []
}

async function saveCategory() {
  saving.value = true
  error.value = ''

  const payload = formPayload()

  try {
    const { data } = selectedId.value
      ? await api.patch(`/categories/${selectedId.value}`, payload)
      : await api.post('/categories', payload)

    showFeedback({
      status: 'success',
      title: 'Categoria salva',
      message: `${data.summary?.updated || 0} produto(s) atualizados com taxonomia normalizada.`,
    })
    await loadCategories(data.data?.id || selectedId.value)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar a categoria.'
  } finally {
    saving.value = false
  }
}

async function applySuggestion(category: MerchantCategory) {
  if (!category.suggestion) {
    return
  }

  applyingCategoryId.value = category.id
  error.value = ''

  try {
    const payload = suggestionPayload(category.suggestion)
    const { data } = await api.patch(`/categories/${category.id}`, {
      ...payload,
      apply_to_products: true,
    })
    showFeedback({
      status: 'success',
      title: 'Taxonomia aplicada',
      message: `${data.summary?.updated || 0} produto(s) receberam a categoria normalizada.`,
    })
    await loadCategories(category.id)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível aplicar a sugestão.'
  } finally {
    applyingCategoryId.value = null
  }
}

async function mergeSelected() {
  if (!selectedCategory.value || !canMerge.value) {
    return
  }

  saving.value = true
  error.value = ''

  try {
    const payload = {
      target_category_id: selectedCategory.value.id,
      source_category_ids: mergeSourceIds.value,
      taxonomy_category_id: form.taxonomy_category_id ? Number(form.taxonomy_category_id) : null,
      taxonomy_name: form.taxonomy_name || selectedCategory.value.taxonomy_category?.name || selectedCategory.value.suggestion?.taxonomy_name || selectedCategory.value.name,
      category_type: form.category_type || selectedCategory.value.taxonomy_category?.category_type || selectedCategory.value.suggestion?.category_type || 'other',
      apply_to_products: true,
    }
    const { data } = await api.post('/categories/merge', payload)
    showFeedback({
      status: 'success',
      title: 'Categorias mescladas',
      message: `${data.summary?.updated_products || 0} produto(s) atualizados sem perder a categoria original.`,
    })
    await loadCategories(selectedCategory.value.id)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível mesclar as categorias.'
  } finally {
    saving.value = false
  }
}

async function previewImport() {
  if (!importContent.value.trim()) {
    return
  }

  importing.value = true
  error.value = ''

  try {
    const { data } = await api.post('/categories/import', {
      content: importContent.value,
      commit: false,
    })
    importPreview.value = data.rows || []
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível validar o arquivo de categorias.'
  } finally {
    importing.value = false
  }
}

async function commitImport() {
  if (!importContent.value.trim()) {
    return
  }

  importing.value = true
  error.value = ''

  try {
    const { data } = await api.post('/categories/import', {
      content: importContent.value,
      commit: true,
      apply_to_products: true,
    })
    showFeedback({
      status: 'success',
      title: 'Categorias importadas',
      message: `${data.summary?.imported || 0} categoria(s) importadas.`,
    })
    importPreview.value = null
    importContent.value = ''
    await loadCategories()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível importar as categorias.'
  } finally {
    importing.value = false
  }
}

async function downloadExport() {
  await downloadCsv('/categories/export', 'provador-virtual-categorias.csv')
}

async function downloadTemplate() {
  await downloadCsv('/categories/template', 'modelo-categorias-provador-virtual.csv')
}

async function downloadCsv(path: string, filename: string) {
  const { data } = await api.get(path, { responseType: 'blob' })
  const url = URL.createObjectURL(new Blob([data], { type: 'text/csv;charset=utf-8;' }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

function formPayload() {
  return {
    name: form.name,
    taxonomy_category_id: form.taxonomy_category_id ? Number(form.taxonomy_category_id) : null,
    taxonomy_name: form.taxonomy_name || null,
    category_type: form.category_type || 'other',
    gender: form.gender || null,
    age_group: form.age_group || null,
    translation_pt_br: form.translation_pt_br || null,
    source: form.source,
    status: form.status,
    apply_to_products: form.apply_to_products,
  }
}

function suggestionPayload(suggestion: MerchantCategorySuggestion) {
  return suggestion.taxonomy_category_id
    ? { taxonomy_category_id: suggestion.taxonomy_category_id, category_type: suggestion.category_type }
    : { taxonomy_name: suggestion.taxonomy_name, category_type: suggestion.category_type }
}

function taxonomyLabel(category: TaxonomyCategory) {
  return category.parent?.name ? `${category.parent.name} / ${category.name}` : category.name
}

function sourceLabel(value: string) {
  return {
    manual: 'Manual',
    import: 'Importação',
    bigshop: 'BigShop',
    merge: 'Mescla',
    merchant_review: 'Revisão',
  }[value] || value
}

function confidenceLabel(value?: string) {
  return {
    high: 'alta',
    medium: 'média',
    low: 'baixa',
  }[value || 'low'] || 'baixa'
}

function statusLabel(value: string) {
  return {
    active: 'Ativa',
    draft: 'Rascunho',
    inactive: 'Inativa',
  }[value] || value
}

function typeLabel(value?: string | null) {
  return categoryTypes.value.find((type) => type.value === value)?.label || value || 'Outro'
}

function categoryTone(category: MerchantCategory) {
  if (category.taxonomy_category_id) {
    return 'ok'
  }

  if (category.product_count > 0) {
    return 'warning'
  }

  return 'neutral'
}
</script>

<template>
  <section class="dashboard app-workspace brands-page categories-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Categorias</span>
        <h1>Categorias</h1>
        <p>Organize categorias importadas, normalize a taxonomia e alimente filtros, regras, IA e relatórios com o mesmo vocabulário.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadCategories()">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <button class="btn btn-secondary" type="button" @click="downloadTemplate">
          <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
          Modelo
        </button>
        <button class="btn btn-secondary" type="button" @click="downloadExport">
          <i class="fa-solid fa-file-export" aria-hidden="true"></i>
          Exportar
        </button>
        <button class="btn btn-primary" type="button" @click="newCategory">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          Nova categoria
        </button>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="fit-diagnostic-panel brand-health-panel">
      <div class="subsection-heading">
        <div>
          <h2>Taxonomia do catálogo</h2>
          <span>{{ summary.products_with_normalized_category }} de {{ summary.products_with_category }} produto(s) com categoria normalizada</span>
        </div>
        <span class="status-pill" :class="{ ok: summary.pending_categories === 0, warning: summary.pending_categories > 0 }">
          {{ summary.pending_categories }} pendente(s)
        </span>
      </div>

      <div class="fit-diagnostic-metrics">
        <article>
          <span>Locais</span>
          <strong>{{ summary.local_categories }}</strong>
        </article>
        <article>
          <span>Mapeadas</span>
          <strong>{{ summary.mapped_categories }}</strong>
        </article>
        <article>
          <span>Taxonomia</span>
          <strong>{{ summary.taxonomy_categories }}</strong>
        </article>
        <article>
          <span>Duplicidades</span>
          <strong>{{ summary.duplicate_groups }}</strong>
        </article>
      </div>

      <div v-if="duplicateGroups.length" class="brand-duplicate-list">
        <article v-for="group in duplicateGroups.slice(0, 4)" :key="group.fingerprint" class="brand-duplicate-group">
          <div>
            <strong>{{ group.suggested_name }}</strong>
            <small>{{ group.products_count }} produto(s) · {{ group.categories.join(' · ') }}</small>
          </div>
          <button
            class="btn btn-secondary btn-compact"
            type="button"
            @click="selectCategory(categories.find((category) => category.name === group.categories[0]) || categories[0])"
          >
            <i class="fa-solid fa-code-merge" aria-hidden="true"></i>
            Revisar
          </button>
        </article>
      </div>
    </section>

    <div class="app-grid brand-workbench">
      <aside class="panel-list brand-list-panel">
        <div class="brand-list-tabs">
          <span><strong>{{ pendingCategories.length }}</strong> pendentes</span>
          <span><strong>{{ mappedCategories.length }}</strong> mapeadas</span>
        </div>
        <button
          v-for="category in categories"
          :key="category.id"
          class="list-row brand-list-row"
          :class="{ active: selectedId === category.id }"
          type="button"
          @click="selectCategory(category)"
        >
          <span class="status-pill" :class="categoryTone(category)">
            {{ category.taxonomy_category_id ? 'Mapeada' : 'Revisar' }}
          </span>
          <strong>{{ category.name }}</strong>
          <span>{{ category.product_count }} produto(s) · {{ sourceLabel(category.source) }}</span>
          <small v-if="category.taxonomy_category?.name">{{ taxonomyLabel(category.taxonomy_category) }} · {{ typeLabel(category.taxonomy_category.category_type) }}</small>
        </button>
        <p v-if="!categories.length && !loading" class="empty-state">Nenhuma categoria encontrada.</p>
      </aside>

      <form class="panel-main admin-form brand-form-panel" @submit.prevent="saveCategory">
        <div class="subsection-heading">
          <div>
            <h2>{{ selectedCategory ? 'Editar categoria' : 'Nova categoria' }}</h2>
            <span>{{ selectedCategory?.product_count || 0 }} produto(s) com esta categoria original</span>
          </div>
          <span v-if="selectedCategory" class="status-pill" :class="categoryTone(selectedCategory)">
            {{ statusLabel(selectedCategory.status) }}
          </span>
        </div>

        <div class="form-grid">
          <label>
            Nome local
            <input v-model.trim="form.name" required maxlength="160" />
          </label>
          <label>
            Categoria normalizada
            <select v-model="form.taxonomy_category_id">
              <option value="">Criar ou revisar</option>
              <option v-for="category in taxonomyCategories" :key="category.id" :value="String(category.id)">
                {{ taxonomyLabel(category) }}
              </option>
            </select>
          </label>
          <label>
            Nova normalizada
            <input v-model.trim="form.taxonomy_name" maxlength="160" :placeholder="selectedSuggestion?.taxonomy_name || 'Ex.: Camisas'" />
          </label>
          <label>
            Tipo
            <select v-model="form.category_type">
              <option v-for="type in categoryTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
          </label>
          <label>
            Gênero
            <select v-model="form.gender">
              <option value="">Todos</option>
              <option value="female">Feminino</option>
              <option value="male">Masculino</option>
              <option value="unisex">Unissex</option>
              <option value="kids">Infantil</option>
            </select>
          </label>
          <label>
            Faixa etária
            <select v-model="form.age_group">
              <option value="">Todas</option>
              <option value="adult">Adulto</option>
              <option value="kids">Infantil</option>
              <option value="baby">Bebê</option>
            </select>
          </label>
          <label>
            Tradução pt-BR
            <input v-model.trim="form.translation_pt_br" maxlength="160" :placeholder="form.taxonomy_name || selectedSuggestion?.taxonomy_name || 'Categoria em português'" />
          </label>
          <label>
            Origem
            <select v-model="form.source">
              <option value="manual">Manual</option>
              <option value="import">Importação</option>
              <option value="bigshop">BigShop</option>
              <option value="merchant_review">Revisão</option>
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
          <label class="brand-apply-toggle">
            Aplicar
            <span>
              <input v-model="form.apply_to_products" type="checkbox" />
              Atualizar produtos
            </span>
          </label>
        </div>

        <section v-if="selectedSuggestion" class="fit-impact-panel brand-suggestion-panel">
          <div>
            <strong>{{ selectedSuggestion.taxonomy_name }}</strong>
            <span>Confiança {{ confidenceLabel(selectedSuggestion.confidence) }} · {{ selectedSuggestion.mode === 'create' ? 'Criar taxonomia' : 'Usar taxonomia existente' }} · {{ typeLabel(selectedSuggestion.category_type) }}</span>
          </div>
          <small>{{ selectedSuggestion.reasons.join(' · ') }}</small>
          <button
            class="btn btn-primary btn-compact"
            type="button"
            :disabled="Boolean(applyingCategoryId)"
            @click="selectedCategory && applySuggestion(selectedCategory)"
          >
            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
            {{ applyingCategoryId === selectedCategory?.id ? 'Aplicando...' : 'Aplicar sugestão' }}
          </button>
        </section>

        <section v-if="mergeCandidates.length" class="brand-merge-panel">
          <div class="subsection-heading">
            <div>
              <h3>Mesclar variações</h3>
              <span>{{ selectedDuplicateGroup?.categories.join(' · ') }}</span>
            </div>
            <button class="btn btn-secondary btn-compact" type="button" :disabled="!canMerge || saving" @click="mergeSelected">
              <i class="fa-solid fa-code-merge" aria-hidden="true"></i>
              Mesclar
            </button>
          </div>
          <label v-for="category in mergeCandidates" :key="category.id" class="brand-merge-option">
            <input v-model="mergeSourceIds" type="checkbox" :value="category.id" />
            <span>{{ category.name }}</span>
            <small>{{ category.product_count }} produto(s)</small>
          </label>
        </section>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="saving">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            {{ saving ? 'Salvando...' : 'Salvar categoria' }}
          </button>
          <button class="btn btn-secondary" type="button" @click="newCategory">
            <i class="fa-solid fa-eraser" aria-hidden="true"></i>
            Limpar
          </button>
        </div>
      </form>
    </div>

    <section class="panel-main subsection brand-import-panel">
      <div class="subsection-heading">
        <div>
          <h2>Importar categorias</h2>
          <span>{{ importPreviewSummary || 'CSV com categoria local, taxonomia, tipo e tradução' }}</span>
        </div>
        <div class="action-row compact">
          <button class="btn btn-secondary btn-compact" type="button" :disabled="importing || !importContent.trim()" @click="previewImport">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
            Prévia
          </button>
          <button class="btn btn-primary btn-compact" type="button" :disabled="importing || !importContent.trim()" @click="commitImport">
            <i class="fa-solid fa-file-import" aria-hidden="true"></i>
            Importar
          </button>
        </div>
      </div>
      <textarea v-model="importContent" rows="5" placeholder="name,taxonomy_category,category_type,gender,age_group,status,source,translation_pt_br"></textarea>
      <div v-if="importPreview?.length" class="table-wrap brand-import-preview">
        <table>
          <thead>
            <tr>
              <th>Linha</th>
              <th>Categoria</th>
              <th>Normalizada</th>
              <th>Tipo</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in importPreview.slice(0, 8)" :key="row.line">
              <td>{{ row.line }}</td>
              <td>{{ row.name || row.errors?.join(', ') }}</td>
              <td>{{ row.taxonomy_category || '-' }}</td>
              <td>{{ typeLabel(row.category_type) }}</td>
              <td>
                <span class="status-pill" :class="{ ok: row.valid, danger: !row.valid }">
                  {{ row.valid ? 'Válida' : 'Erro' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
