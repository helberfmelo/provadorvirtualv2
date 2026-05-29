<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import type { MerchantBrand, MerchantBrandSuggestion, NormalizedBrand } from '../services/merchantTypes'
import { showFeedback } from '../services/saveFeedback'

type BrandSummary = {
  local_brands: number
  mapped_brands: number
  pending_brands: number
  normalized_brands: number
  products_with_brand: number
  products_with_normalized_brand: number
  duplicate_groups: number
}

type DuplicateGroup = {
  fingerprint: string
  suggested_name: string
  brands: string[]
  products_count: number
}

const brands = ref<MerchantBrand[]>([])
const normalizedBrands = ref<NormalizedBrand[]>([])
const duplicateGroups = ref<DuplicateGroup[]>([])
const selectedId = ref<number | null>(null)
const loading = ref(false)
const saving = ref(false)
const importing = ref(false)
const applyingBrandId = ref<number | null>(null)
const error = ref('')
const importContent = ref('')
const importPreview = ref<any[] | null>(null)
const mergeSourceIds = ref<number[]>([])

const emptySummary = (): BrandSummary => ({
  local_brands: 0,
  mapped_brands: 0,
  pending_brands: 0,
  normalized_brands: 0,
  products_with_brand: 0,
  products_with_normalized_brand: 0,
  duplicate_groups: 0,
})

const summary = reactive<BrandSummary>(emptySummary())
const form = reactive({
  name: '',
  normalized_brand_id: '',
  normalized_name: '',
  source: 'manual',
  status: 'active',
  apply_to_products: true,
})

const selectedBrand = computed(() => brands.value.find((brand) => brand.id === selectedId.value) || null)
const selectedSuggestion = computed(() => selectedBrand.value?.suggestion || null)
const pendingBrands = computed(() => brands.value.filter((brand) => !brand.normalized_brand_id))
const mappedBrands = computed(() => brands.value.filter((brand) => brand.normalized_brand_id))
const selectedDuplicateGroup = computed(() => duplicateGroups.value.find((group) => (
  selectedBrand.value ? group.brands.includes(selectedBrand.value.name) : false
)) || null)
const mergeCandidates = computed(() => {
  if (!selectedDuplicateGroup.value || !selectedBrand.value) {
    return []
  }

  return brands.value.filter((brand) => (
    brand.id !== selectedBrand.value?.id
    && selectedDuplicateGroup.value?.brands.includes(brand.name)
  ))
})
const canMerge = computed(() => Boolean(selectedBrand.value) && mergeSourceIds.value.length > 0)
const importPreviewSummary = computed(() => {
  if (!importPreview.value) {
    return null
  }

  const valid = importPreview.value.filter((row) => row.valid).length

  return `${valid}/${importPreview.value.length} linhas válidas`
})

onMounted(() => {
  loadBrands()
})

async function loadBrands(preferredId: number | null = selectedId.value) {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/brands')
    brands.value = data.data || []
    normalizedBrands.value = data.normalized_brands || []
    duplicateGroups.value = data.duplicate_groups || []
    Object.assign(summary, emptySummary(), data.summary || {})

    const preferred = brands.value.find((brand) => brand.id === preferredId) || brands.value[0] || null
    if (preferred) {
      selectBrand(preferred)
    } else {
      newBrand()
    }
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar as marcas.'
  } finally {
    loading.value = false
  }
}

function selectBrand(brand: MerchantBrand) {
  selectedId.value = brand.id
  form.name = brand.name
  form.normalized_brand_id = brand.normalized_brand_id ? String(brand.normalized_brand_id) : ''
  form.normalized_name = ''
  form.source = brand.source || 'manual'
  form.status = brand.status || 'active'
  form.apply_to_products = true
  mergeSourceIds.value = []
}

function newBrand() {
  selectedId.value = null
  form.name = ''
  form.normalized_brand_id = ''
  form.normalized_name = ''
  form.source = 'manual'
  form.status = 'active'
  form.apply_to_products = true
  mergeSourceIds.value = []
}

async function saveBrand() {
  saving.value = true
  error.value = ''

  const payload = {
    name: form.name,
    normalized_brand_id: form.normalized_brand_id ? Number(form.normalized_brand_id) : null,
    normalized_name: form.normalized_name || null,
    source: form.source,
    status: form.status,
    apply_to_products: form.apply_to_products,
  }

  try {
    const { data } = selectedId.value
      ? await api.patch(`/brands/${selectedId.value}`, payload)
      : await api.post('/brands', payload)

    showFeedback({
      status: 'success',
      title: 'Marca salva',
      message: `${data.summary?.updated || 0} produto(s) atualizados com marca normalizada.`,
    })
    await loadBrands(data.data?.id || selectedId.value)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar a marca.'
  } finally {
    saving.value = false
  }
}

async function applySuggestion(brand: MerchantBrand) {
  if (!brand.suggestion) {
    return
  }

  applyingBrandId.value = brand.id
  error.value = ''

  try {
    const payload = suggestionPayload(brand.suggestion)
    const { data } = await api.patch(`/brands/${brand.id}`, {
      ...payload,
      apply_to_products: true,
    })
    showFeedback({
      status: 'success',
      title: 'Normalização aplicada',
      message: `${data.summary?.updated || 0} produto(s) receberam a marca normalizada.`,
    })
    await loadBrands(brand.id)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível aplicar a sugestão.'
  } finally {
    applyingBrandId.value = null
  }
}

async function mergeSelected() {
  if (!selectedBrand.value || !canMerge.value) {
    return
  }

  saving.value = true
  error.value = ''

  try {
    const payload = {
      target_brand_id: selectedBrand.value.id,
      source_brand_ids: mergeSourceIds.value,
      normalized_brand_id: form.normalized_brand_id ? Number(form.normalized_brand_id) : null,
      normalized_name: form.normalized_name || selectedBrand.value.normalized_brand?.name || selectedBrand.value.suggestion?.normalized_name || selectedBrand.value.name,
      apply_to_products: true,
    }
    const { data } = await api.post('/brands/merge', payload)
    showFeedback({
      status: 'success',
      title: 'Marcas mescladas',
      message: `${data.summary?.updated_products || 0} produto(s) atualizados sem perder o nome original.`,
    })
    await loadBrands(selectedBrand.value.id)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível mesclar as marcas.'
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
    const { data } = await api.post('/brands/import', {
      content: importContent.value,
      commit: false,
    })
    importPreview.value = data.rows || []
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível validar o arquivo de marcas.'
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
    const { data } = await api.post('/brands/import', {
      content: importContent.value,
      commit: true,
      apply_to_products: true,
    })
    showFeedback({
      status: 'success',
      title: 'Marcas importadas',
      message: `${data.summary?.imported || 0} marca(s) importadas.`,
    })
    importPreview.value = null
    importContent.value = ''
    await loadBrands()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível importar as marcas.'
  } finally {
    importing.value = false
  }
}

async function downloadExport() {
  await downloadCsv('/brands/export', 'provador-virtual-marcas.csv')
}

async function downloadTemplate() {
  await downloadCsv('/brands/template', 'modelo-marcas-provador-virtual.csv')
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

function suggestionPayload(suggestion: MerchantBrandSuggestion) {
  return suggestion.normalized_brand_id
    ? { normalized_brand_id: suggestion.normalized_brand_id }
    : { normalized_name: suggestion.normalized_name }
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

function brandTone(brand: MerchantBrand) {
  if (brand.normalized_brand_id) {
    return 'ok'
  }

  if (brand.product_count > 0) {
    return 'warning'
  }

  return 'neutral'
}
</script>

<template>
  <section class="dashboard app-workspace brands-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Marcas</span>
        <h1>Marcas</h1>
        <p>Revise marcas importadas, normalize duplicidades e aplique o mesmo nome operacional em filtros, regras e relatórios.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadBrands()">
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
        <button class="btn btn-primary" type="button" @click="newBrand">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          Nova marca
        </button>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="fit-diagnostic-panel brand-health-panel">
      <div class="subsection-heading">
        <div>
          <h2>Normalização do catálogo</h2>
          <span>{{ summary.products_with_normalized_brand }} de {{ summary.products_with_brand }} produto(s) com marca normalizada</span>
        </div>
        <span class="status-pill" :class="{ ok: summary.pending_brands === 0, warning: summary.pending_brands > 0 }">
          {{ summary.pending_brands }} pendente(s)
        </span>
      </div>

      <div class="fit-diagnostic-metrics">
        <article>
          <span>Locais</span>
          <strong>{{ summary.local_brands }}</strong>
        </article>
        <article>
          <span>Mapeadas</span>
          <strong>{{ summary.mapped_brands }}</strong>
        </article>
        <article>
          <span>Normalizadas</span>
          <strong>{{ summary.normalized_brands }}</strong>
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
            <small>{{ group.products_count }} produto(s) · {{ group.brands.join(' · ') }}</small>
          </div>
          <button
            class="btn btn-secondary btn-compact"
            type="button"
            @click="selectBrand(brands.find((brand) => brand.name === group.brands[0]) || brands[0])"
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
          <span><strong>{{ pendingBrands.length }}</strong> pendentes</span>
          <span><strong>{{ mappedBrands.length }}</strong> mapeadas</span>
        </div>
        <button
          v-for="brand in brands"
          :key="brand.id"
          class="list-row brand-list-row"
          :class="{ active: selectedId === brand.id }"
          type="button"
          @click="selectBrand(brand)"
        >
          <span class="status-pill" :class="brandTone(brand)">
            {{ brand.normalized_brand_id ? 'Mapeada' : 'Revisar' }}
          </span>
          <strong>{{ brand.name }}</strong>
          <span>{{ brand.product_count }} produto(s) · {{ sourceLabel(brand.source) }}</span>
          <small v-if="brand.normalized_brand?.name">{{ brand.normalized_brand.name }}</small>
        </button>
        <p v-if="!brands.length && !loading" class="empty-state">Nenhuma marca encontrada.</p>
      </aside>

      <form class="panel-main admin-form brand-form-panel" @submit.prevent="saveBrand">
        <div class="subsection-heading">
          <div>
            <h2>{{ selectedBrand ? 'Editar marca' : 'Nova marca' }}</h2>
            <span>{{ selectedBrand?.product_count || 0 }} produto(s) com esta marca original</span>
          </div>
          <span v-if="selectedBrand" class="status-pill" :class="brandTone(selectedBrand)">
            {{ statusLabel(selectedBrand.status) }}
          </span>
        </div>

        <div class="form-grid">
          <label>
            Nome local
            <input v-model.trim="form.name" required maxlength="160" />
          </label>
          <label>
            Marca normalizada
            <select v-model="form.normalized_brand_id">
              <option value="">Criar ou revisar</option>
              <option v-for="brand in normalizedBrands" :key="brand.id" :value="String(brand.id)">
                {{ brand.name }}
              </option>
            </select>
          </label>
          <label>
            Nova normalizada
            <input v-model.trim="form.normalized_name" maxlength="160" :placeholder="selectedSuggestion?.normalized_name || 'Ex.: Zak'" />
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
            <strong>{{ selectedSuggestion.normalized_name }}</strong>
            <span>Confiança {{ confidenceLabel(selectedSuggestion.confidence) }} · {{ selectedSuggestion.mode === 'create' ? 'Criar marca normalizada' : 'Usar marca existente' }}</span>
          </div>
          <small>{{ selectedSuggestion.reasons.join(' · ') }}</small>
          <button
            class="btn btn-primary btn-compact"
            type="button"
            :disabled="Boolean(applyingBrandId)"
            @click="selectedBrand && applySuggestion(selectedBrand)"
          >
            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
            {{ applyingBrandId === selectedBrand?.id ? 'Aplicando...' : 'Aplicar sugestão' }}
          </button>
        </section>

        <section v-if="mergeCandidates.length" class="brand-merge-panel">
          <div class="subsection-heading">
            <div>
              <h3>Mesclar variações</h3>
              <span>{{ selectedDuplicateGroup?.brands.join(' · ') }}</span>
            </div>
            <button class="btn btn-secondary btn-compact" type="button" :disabled="!canMerge || saving" @click="mergeSelected">
              <i class="fa-solid fa-code-merge" aria-hidden="true"></i>
              Mesclar
            </button>
          </div>
          <label v-for="brand in mergeCandidates" :key="brand.id" class="brand-merge-option">
            <input v-model="mergeSourceIds" type="checkbox" :value="brand.id" />
            <span>{{ brand.name }}</span>
            <small>{{ brand.product_count }} produto(s)</small>
          </label>
        </section>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="saving">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            {{ saving ? 'Salvando...' : 'Salvar marca' }}
          </button>
          <button class="btn btn-secondary" type="button" @click="newBrand">
            <i class="fa-solid fa-eraser" aria-hidden="true"></i>
            Limpar
          </button>
        </div>
      </form>
    </div>

    <section class="panel-main subsection brand-import-panel">
      <div class="subsection-heading">
        <div>
          <h2>Importar marcas</h2>
          <span>{{ importPreviewSummary || 'CSV com nome local e marca normalizada' }}</span>
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
      <textarea v-model="importContent" rows="5" placeholder="name,normalized_brand,status,source"></textarea>
      <div v-if="importPreview?.length" class="table-wrap brand-import-preview">
        <table>
          <thead>
            <tr>
              <th>Linha</th>
              <th>Marca</th>
              <th>Normalizada</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in importPreview.slice(0, 8)" :key="row.line">
              <td>{{ row.line }}</td>
              <td>{{ row.name || row.errors?.join(', ') }}</td>
              <td>{{ row.normalized_brand || '-' }}</td>
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
