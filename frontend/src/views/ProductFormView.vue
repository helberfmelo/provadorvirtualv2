<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import type { FitProfile, MeasurementTableOption, Product, ProductVariant } from '../services/merchantTypes'

type DetailTab = 'resumo' | 'origem' | 'tabela' | 'tamanhos' | 'midia' | 'diagnostico' | 'historico'

const route = useRoute()
const router = useRouter()
const productId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(productId.value))

const measurementTables = ref<MeasurementTableOption[]>([])
const fitProfiles = ref<FitProfile[]>([])
const selected = ref<Product | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const activeTab = ref<DetailTab>('resumo')

const form = reactive({
  external_product_id: '',
  name: '',
  sku: '',
  description: '',
  category: '',
  gender: 'unisex',
  brand: '',
  age_group: '',
  fit_profile: 'regular',
  status: 'active',
  image_url: '',
  measurement_table_id: null as number | null,
  virtual_try_on_enabled: true,
  measurement_table_enabled: true,
})

const variantForm = reactive({
  sku: '',
  size_label: '',
  color: '',
  price: null as number | null,
  stock_quantity: null as number | null,
})

const fallbackFitProfiles = [
  { code: 'slim', name: 'Slim' },
  { code: 'regular', name: 'Regular' },
  { code: 'oversized', name: 'Ampla' },
  { code: 'loose', name: 'Solta' },
  { code: 'comfort', name: 'Conforto' },
]

const tabOptions: Array<{ key: DetailTab; label: string; icon: string }> = [
  { key: 'resumo', label: 'Resumo', icon: 'fa-circle-info' },
  { key: 'origem', label: 'Origem', icon: 'fa-code-branch' },
  { key: 'tabela', label: 'Tabela', icon: 'fa-ruler-combined' },
  { key: 'tamanhos', label: 'Tamanhos', icon: 'fa-layer-group' },
  { key: 'midia', label: 'Mídia', icon: 'fa-image' },
  { key: 'diagnostico', label: 'Diagnóstico', icon: 'fa-triangle-exclamation' },
  { key: 'historico', label: 'Histórico', icon: 'fa-clock-rotate-left' },
]

const availableTabs = computed(() => (editing.value ? tabOptions : tabOptions.filter((tab) => ['resumo', 'midia'].includes(tab.key))))

const fitProfileOptions = computed(() => {
  const options = new Map(fallbackFitProfiles.map((profile) => [profile.code, profile.name]))
  fitProfiles.value
    .filter((profile) => profile.status === 'active' || profile.code === form.fit_profile)
    .forEach((profile) => options.set(profile.code, profile.name))

  return Array.from(options, ([code, name]) => ({ code, name }))
})

const diagnostics = computed(() => selected.value?.diagnostics ?? [])
const originFields = computed(() => selected.value?.origin_fields ?? [])
const historyRows = computed(() => selected.value?.history ?? [])
const activeVariants = computed(() => selected.value?.variants?.filter((variant) => variant.is_active).length ?? 0)

const readinessLabel = computed(() => {
  if (!selected.value) {
    return 'Novo'
  }

  return selected.value.readiness_status === 'ready' ? 'Pronto' : 'Pendente'
})

const readinessClass = computed(() => (selected.value?.readiness_status === 'ready' ? 'ok' : 'warning'))

const importedSnapshotEntries = computed(() => {
  const snapshot = selected.value?.imported_snapshot ?? {}

  return Object.entries(snapshot).map(([field, value]) => ({
    field,
    label: fieldLabel(field),
    value,
  }))
})

const manualOverrideEntries = computed(() => {
  const overrides = selected.value?.manual_overrides ?? {}

  return Object.entries(overrides).map(([field, override]) => ({
    field,
    label: fieldLabel(field),
    value: override?.value ?? null,
    imported_value: override?.imported_value ?? null,
    updated_at: override?.updated_at ?? null,
  }))
})

onMounted(() => {
  loadForm()
})

async function loadForm() {
  loading.value = true
  error.value = ''

  try {
    const [tablesResponse, profilesResponse] = await Promise.all([
      api.get('/measurement-tables'),
      api.get('/fit-profiles').catch(() => ({ data: { data: [] } })),
    ])
    measurementTables.value = tablesResponse.data.data
    fitProfiles.value = profilesResponse.data.data

    if (editing.value) {
      const { data } = await api.get(`/products/${productId.value}`)
      selected.value = data.data
      fillForm(data.data)
      return
    }

    selected.value = null
    form.measurement_table_id = null
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o produto.'
  } finally {
    loading.value = false
  }
}

function fillForm(product: Product) {
  form.external_product_id = product.external_product_id ?? ''
  form.name = product.name
  form.sku = product.sku ?? ''
  form.description = product.description ?? ''
  form.category = product.category ?? ''
  form.gender = product.gender ?? 'unisex'
  form.brand = product.brand ?? ''
  form.age_group = product.age_group ?? ''
  form.fit_profile = product.fit_profile ?? 'regular'
  form.status = product.status ?? 'active'
  form.image_url = product.image_url ?? ''
  form.measurement_table_id = product.measurement_table_id ?? null
  form.virtual_try_on_enabled = product.activation?.virtual_try_on_enabled ?? true
  form.measurement_table_enabled = product.activation?.measurement_table_enabled ?? true
}

async function saveProduct() {
  saving.value = true
  error.value = ''

  const payload = {
    external_product_id: form.external_product_id || null,
    name: form.name,
    sku: form.sku || null,
    description: form.description || null,
    category: form.category || null,
    gender: form.gender || null,
    brand: form.brand || null,
    age_group: form.age_group || null,
    fit_profile: form.fit_profile || null,
    status: form.status,
    image_url: form.image_url || null,
    measurement_table_id: form.measurement_table_id || null,
    virtual_try_on_enabled: form.virtual_try_on_enabled,
    measurement_table_enabled: form.measurement_table_enabled,
  }

  try {
    const { data } = editing.value
      ? await api.patch(`/products/${productId.value}`, payload)
      : await api.post('/products', payload)

    if (!editing.value) {
      await router.push(`/app/produtos/${data.data.id}/editar`)
      return
    }

    selected.value = data.data
    fillForm(data.data)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar o produto.'
  } finally {
    saving.value = false
  }
}

async function addVariant() {
  if (!selected.value) {
    return
  }

  await api.post(`/products/${selected.value.id}/variants`, {
    sku: variantForm.sku || null,
    size_label: variantForm.size_label,
    color: variantForm.color || null,
    price: variantForm.price,
    stock_quantity: variantForm.stock_quantity,
  })

  variantForm.sku = ''
  variantForm.size_label = ''
  variantForm.color = ''
  variantForm.price = null
  variantForm.stock_quantity = null
  await loadForm()
}

async function updateVariant(variant: ProductVariant) {
  if (!selected.value) {
    return
  }

  await api.patch(`/products/${selected.value.id}/variants/${variant.id}`, {
    sku: variant.sku || null,
    size_label: variant.size_label,
    color: variant.color || null,
    price: variant.price,
    stock_quantity: variant.stock_quantity,
    is_active: variant.is_active,
  })

  await loadForm()
}

async function removeVariant(variant: ProductVariant) {
  if (!selected.value) {
    return
  }

  await api.delete(`/products/${selected.value.id}/variants/${variant.id}`)
  await loadForm()
}

function fieldLabel(field: string) {
  const labels: Record<string, string> = {
    external_product_id: 'ID externo',
    sku: 'SKU base',
    name: 'Nome',
    slug: 'Slug',
    description: 'Descrição',
    category: 'Categoria',
    gender: 'Gênero',
    fit_profile: 'Modelagem',
    measurement_table_id: 'Tabela',
    image_url: 'Imagem',
    brand: 'Marca',
    age_group: 'Faixa etária',
  }

  return labels[field] ?? field
}

function statusLabel(status: string | null | undefined) {
  return {
    active: 'Ativo',
    draft: 'Rascunho',
    inactive: 'Inativo',
  }[status ?? ''] ?? 'Indefinido'
}

function formatValue(value: unknown) {
  if (value === null || value === undefined || value === '') {
    return '--'
  }

  if (typeof value === 'boolean') {
    return value ? 'Sim' : 'Não'
  }

  return String(value)
}

function formatDate(value: string | null | undefined) {
  if (!value) {
    return '--'
  }

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Produtos</span>
        <h1>{{ editing ? 'Detalhe do produto' : 'Novo produto' }}</h1>
        <p>Dados, origem, ativação e prontidão operacional do item.</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/app/produtos">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section v-if="selected" class="product-detail-overview">
      <article>
        <span>Prontidão</span>
        <strong class="status-pill" :class="readinessClass">{{ readinessLabel }}</strong>
        <small>{{ diagnostics.length }} diagnóstico(s)</small>
      </article>
      <article>
        <span>Origem</span>
        <strong>{{ selected.source_label || 'Manual' }}</strong>
        <small>{{ selected.external_product_id || selected.sku || 'Sem ID externo' }}</small>
      </article>
      <article>
        <span>Provador</span>
        <strong>{{ form.virtual_try_on_enabled ? 'Ativado' : 'Desativado' }}</strong>
        <small>{{ form.measurement_table_enabled ? 'Tabela liberada' : 'Tabela bloqueada' }}</small>
      </article>
      <article>
        <span>Tamanhos</span>
        <strong>{{ activeVariants }}/{{ selected.variants?.length ?? 0 }}</strong>
        <small>{{ selected.size_labels?.join(', ') || 'Sem grade' }}</small>
      </article>
    </section>

    <form class="panel-main admin-form form-wide product-detail-form" @submit.prevent="saveProduct">
      <div class="product-detail-tabs" role="tablist" aria-label="Seções do produto">
        <button
          v-for="tab in availableTabs"
          :key="tab.key"
          type="button"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          <i class="fa-solid" :class="tab.icon" aria-hidden="true"></i>
          <span>{{ tab.label }}</span>
        </button>
      </div>

      <section v-if="activeTab === 'resumo'" class="product-detail-section">
        <div class="form-grid">
          <label class="full-row">
            Nome
            <input v-model="form.name" required maxlength="180" />
          </label>
          <label>
            SKU base
            <input v-model="form.sku" maxlength="120" />
          </label>
          <label>
            ID externo
            <input v-model="form.external_product_id" maxlength="120" />
          </label>
          <label>
            Status
            <select v-model="form.status">
              <option value="active">Ativo</option>
              <option value="draft">Rascunho</option>
              <option value="inactive">Inativo</option>
            </select>
          </label>
          <label>
            Categoria
            <input v-model="form.category" maxlength="120" />
          </label>
          <label>
            Marca
            <input v-model="form.brand" maxlength="120" />
          </label>
          <label>
            Faixa etária
            <input v-model="form.age_group" maxlength="80" />
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
          <label class="full-row">
            Descrição
            <textarea v-model="form.description" rows="4"></textarea>
          </label>
        </div>
      </section>

      <section v-else-if="activeTab === 'origem'" class="product-detail-section">
        <div class="subsection-heading">
          <h2>Origem e ajustes</h2>
          <span>{{ manualOverrideEntries.length }} ajuste(s) manual(is)</span>
        </div>

        <div class="table-wrap origin-table-wrap">
          <table>
            <thead>
              <tr>
                <th>Campo</th>
                <th>Atual</th>
                <th>Importado</th>
                <th>Origem</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="field in originFields" :key="field.field">
                <td><strong>{{ field.label }}</strong></td>
                <td>{{ formatValue(field.value) }}</td>
                <td>{{ formatValue(field.imported_value) }}</td>
                <td>
                  <span class="source-badge" :class="`source-${field.source}`">{{ field.source_label }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="product-origin-columns">
          <section>
            <h3>Snapshot importado</h3>
            <dl v-if="importedSnapshotEntries.length" class="detail-list compact">
              <template v-for="entry in importedSnapshotEntries" :key="entry.field">
                <dt>{{ entry.label }}</dt>
                <dd>{{ formatValue(entry.value) }}</dd>
              </template>
            </dl>
            <p v-else class="empty-state">Sem snapshot importado para este produto.</p>
          </section>

          <section>
            <h3>Ajustes manuais</h3>
            <dl v-if="manualOverrideEntries.length" class="detail-list compact">
              <template v-for="entry in manualOverrideEntries" :key="entry.field">
                <dt>{{ entry.label }}</dt>
                <dd>{{ formatValue(entry.imported_value) }} → {{ formatValue(entry.value) }}</dd>
              </template>
            </dl>
            <p v-else class="empty-state">Nenhum ajuste manual registrado.</p>
          </section>
        </div>
      </section>

      <section v-else-if="activeTab === 'tabela'" class="product-detail-section">
        <div class="product-activation-grid">
          <label class="product-switch" :class="{ active: form.virtual_try_on_enabled }">
            <input v-model="form.virtual_try_on_enabled" type="checkbox" />
            <span>
              <strong>Provador Virtual</strong>
              <small>{{ form.virtual_try_on_enabled ? 'Ativado no widget' : 'Oculto no widget' }}</small>
            </span>
          </label>
          <label class="product-switch" :class="{ active: form.measurement_table_enabled }">
            <input v-model="form.measurement_table_enabled" type="checkbox" />
            <span>
              <strong>Tabela de Medidas</strong>
              <small>{{ form.measurement_table_enabled ? 'Disponível publicamente' : 'Bloqueada publicamente' }}</small>
            </span>
          </label>
        </div>

        <div class="form-grid">
          <label>
            Tabela vinculada
            <select v-model.number="form.measurement_table_id">
              <option :value="null">Sem tabela</option>
              <option v-for="table in measurementTables" :key="table.id" :value="table.id">
                {{ table.name }}
              </option>
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
            Status
            <select v-model="form.status">
              <option value="active">Ativo</option>
              <option value="draft">Rascunho</option>
              <option value="inactive">Inativo</option>
            </select>
          </label>
        </div>

        <div v-if="selected?.measurement_table" class="measurement-summary-strip">
          <span>Tabela</span>
          <strong>{{ selected.measurement_table.name }}</strong>
          <small>{{ selected.size_labels?.join(', ') || 'Sem tamanhos carregados' }}</small>
        </div>
      </section>

      <section v-else-if="activeTab === 'tamanhos'" class="product-detail-section">
        <div v-if="selected" class="subsection-heading">
          <h2>Variações</h2>
          <span>{{ selected.variants?.length ?? 0 }} itens</span>
        </div>

        <div v-if="selected" class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Tamanho</th>
                <th>SKU</th>
                <th>Cor</th>
                <th>Preço</th>
                <th>Estoque</th>
                <th>Ativa</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="variant in selected.variants" :key="variant.id">
                <td><input v-model="variant.size_label" class="table-input short" /></td>
                <td><input v-model="variant.sku" class="table-input" /></td>
                <td><input v-model="variant.color" class="table-input" /></td>
                <td><input v-model="variant.price" class="table-input short" type="number" min="0" step="0.01" /></td>
                <td><input v-model.number="variant.stock_quantity" class="table-input short" type="number" min="0" /></td>
                <td><input v-model="variant.is_active" type="checkbox" /></td>
                <td class="row-actions">
                  <button type="button" title="Salvar variação" @click="updateVariant(variant)">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                  </button>
                  <button type="button" title="Remover variação" @click="removeVariant(variant)">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <form v-if="selected" class="inline-form" @submit.prevent="addVariant">
          <input v-model="variantForm.size_label" required placeholder="Tamanho" />
          <input v-model="variantForm.sku" placeholder="SKU" />
          <input v-model="variantForm.color" placeholder="Cor" />
          <input v-model.number="variantForm.price" type="number" min="0" step="0.01" placeholder="Preço" />
          <input v-model.number="variantForm.stock_quantity" type="number" min="0" placeholder="Estoque" />
          <button class="btn btn-secondary" type="submit">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Variação
          </button>
        </form>

        <p v-else class="empty-state">Salve o produto antes de cadastrar tamanhos.</p>
      </section>

      <section v-else-if="activeTab === 'midia'" class="product-detail-section product-media-section">
        <div class="product-image-preview">
          <img v-if="form.image_url" :src="form.image_url" alt="" />
          <span v-else><i class="fa-solid fa-image" aria-hidden="true"></i></span>
        </div>
        <label>
          URL da imagem
          <input v-model="form.image_url" maxlength="500" placeholder="https://..." />
        </label>
      </section>

      <section v-else-if="activeTab === 'diagnostico'" class="product-detail-section">
        <ul class="diagnostic-list">
          <li v-for="diagnostic in diagnostics" :key="diagnostic.code" :class="`severity-${diagnostic.severity}`">
            <i
              class="fa-solid"
              :class="diagnostic.severity === 'danger' ? 'fa-circle-xmark' : diagnostic.severity === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation'"
              aria-hidden="true"
            ></i>
            <span>
              <strong>{{ diagnostic.title }}</strong>
              <small>{{ diagnostic.cause }}</small>
              <em>{{ diagnostic.action }}</em>
            </span>
          </li>
        </ul>
      </section>

      <section v-else-if="activeTab === 'historico'" class="product-detail-section">
        <div v-if="historyRows.length" class="history-list">
          <article v-for="row in historyRows" :key="`${row.event}-${row.created_at}`">
            <span>{{ formatDate(row.created_at) }}</span>
            <strong>{{ row.event }}</strong>
            <small>{{ row.category || row.source || 'produto' }}</small>
          </article>
        </div>
        <p v-else class="empty-state">Sem histórico registrado para este produto.</p>
      </section>

      <div class="action-row compact product-detail-save-row">
        <button class="btn btn-primary" :disabled="saving || loading" type="submit">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar produto' }}
        </button>
        <span v-if="selected">Último estado: {{ statusLabel(selected.status) }}</span>
      </div>
    </form>
  </section>
</template>
