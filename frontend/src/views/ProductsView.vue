<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

type MeasurementTableOption = {
  id: number
  name: string
}

type ProductVariant = {
  id: number
  sku: string | null
  size_label: string
  color: string | null
  price: string | number | null
  stock_quantity: number | null
  is_active: boolean
}

type Product = {
  id: number
  measurement_table_id: number | null
  name: string
  sku: string | null
  category: string | null
  gender: string | null
  fit_profile: string | null
  status: string
  variants_count?: number
  variants?: ProductVariant[]
  measurement_table?: MeasurementTableOption | null
}

const products = ref<Product[]>([])
const measurementTables = ref<MeasurementTableOption[]>([])
const selected = ref<Product | null>(null)
const loading = ref(false)
const saving = ref(false)
const notice = ref('')

const form = reactive({
  id: null as number | null,
  name: '',
  sku: '',
  category: 'Vestidos',
  gender: 'female',
  fit_profile: 'regular',
  status: 'active',
  measurement_table_id: null as number | null,
})

const variantForm = reactive({
  sku: '',
  size_label: '',
  color: '',
  price: null as number | null,
  stock_quantity: null as number | null,
})

const hasProducts = computed(() => products.value.length > 0)

onMounted(() => {
  loadAll()
})

async function loadAll() {
  loading.value = true

  try {
    const [productsResponse, tablesResponse] = await Promise.all([
      api.get('/products'),
      api.get('/measurement-tables'),
    ])

    products.value = productsResponse.data.data
    measurementTables.value = tablesResponse.data.data

    if (!selected.value && products.value[0]) {
      await selectProduct(products.value[0])
    }
  } finally {
    loading.value = false
  }
}

async function selectProduct(product: Product) {
  const { data } = await api.get(`/products/${product.id}`)
  selected.value = data.data
  fillForm(data.data)
}

function fillForm(product: Product | null = null) {
  form.id = product?.id ?? null
  form.name = product?.name ?? ''
  form.sku = product?.sku ?? ''
  form.category = product?.category ?? 'Vestidos'
  form.gender = product?.gender ?? 'female'
  form.fit_profile = product?.fit_profile ?? 'regular'
  form.status = product?.status ?? 'active'
  form.measurement_table_id = product?.measurement_table_id ?? measurementTables.value[0]?.id ?? null
}

async function saveProduct() {
  saving.value = true
  notice.value = ''

  const payload = {
    name: form.name,
    sku: form.sku || null,
    category: form.category || null,
    gender: form.gender || null,
    fit_profile: form.fit_profile || null,
    status: form.status,
    measurement_table_id: form.measurement_table_id || null,
  }

  try {
    const { data } = form.id
      ? await api.patch(`/products/${form.id}`, payload)
      : await api.post('/products', payload)

    notice.value = form.id ? 'Produto atualizado.' : 'Produto criado.'
    selected.value = data.data
    fillForm(data.data)
    await loadAll()
  } finally {
    saving.value = false
  }
}

async function removeProduct(product: Product) {
  await api.delete(`/products/${product.id}`)
  selected.value = null
  fillForm()
  await loadAll()
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
  await selectProduct(selected.value)
  await loadAll()
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

  await selectProduct(selected.value)
}

async function removeVariant(variant: ProductVariant) {
  if (!selected.value) {
    return
  }

  await api.delete(`/products/${selected.value.id}/variants/${variant.id}`)
  await selectProduct(selected.value)
  await loadAll()
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Produtos</span>
        <h1>Produtos e grades</h1>
      </div>
      <button class="btn btn-secondary" type="button" @click="fillForm(null)">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        Novo produto
      </button>
    </div>

    <p v-if="notice" class="success-message">{{ notice }}</p>

    <div class="app-grid">
      <aside class="panel-list">
        <div v-if="loading" class="empty-state">Carregando produtos...</div>
        <div v-else-if="!hasProducts" class="empty-state">Nenhum produto cadastrado.</div>
        <template v-else>
          <button
            v-for="product in products"
            :key="product.id"
            class="list-row"
            :class="{ active: selected?.id === product.id }"
            type="button"
            @click="selectProduct(product)"
          >
            <strong>{{ product.name }}</strong>
            <span>{{ product.variants_count ?? 0 }} variacoes</span>
          </button>
        </template>
      </aside>

      <div class="panel-main">
        <form class="admin-form" @submit.prevent="saveProduct">
          <div class="form-grid">
            <label>
              Nome
              <input v-model="form.name" required maxlength="180" />
            </label>
            <label>
              SKU base
              <input v-model="form.sku" maxlength="120" />
            </label>
            <label>
              Categoria
              <input v-model="form.category" maxlength="120" />
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
              Tabela
              <select v-model.number="form.measurement_table_id">
                <option :value="null">Sem tabela</option>
                <option v-for="table in measurementTables" :key="table.id" :value="table.id">
                  {{ table.name }}
                </option>
              </select>
            </label>
          </div>

          <div class="action-row compact">
            <button class="btn btn-primary" :disabled="saving" type="submit">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
              Salvar produto
            </button>
            <button
              v-if="selected"
              class="btn btn-danger"
              type="button"
              title="Remover produto"
              @click="removeProduct(selected)"
            >
              <i class="fa-solid fa-trash" aria-hidden="true"></i>
            </button>
          </div>
        </form>

        <div v-if="selected" class="subsection">
          <div class="subsection-heading">
            <h2>Variacoes</h2>
            <span>{{ selected.variants?.length ?? 0 }} itens</span>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tamanho</th>
                  <th>SKU</th>
                  <th>Cor</th>
                  <th>Preco</th>
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
                    <button type="button" title="Salvar variacao" @click="updateVariant(variant)">
                      <i class="fa-solid fa-check" aria-hidden="true"></i>
                    </button>
                    <button type="button" title="Remover variacao" @click="removeVariant(variant)">
                      <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <form class="inline-form" @submit.prevent="addVariant">
            <input v-model="variantForm.size_label" required placeholder="Tamanho" />
            <input v-model="variantForm.sku" placeholder="SKU" />
            <input v-model="variantForm.color" placeholder="Cor" />
            <input v-model.number="variantForm.price" type="number" min="0" step="0.01" placeholder="Preco" />
            <input v-model.number="variantForm.stock_quantity" type="number" min="0" placeholder="Estoque" />
            <button class="btn btn-secondary" type="submit">
              <i class="fa-solid fa-plus" aria-hidden="true"></i>
              Variacao
            </button>
          </form>
        </div>
      </div>
    </div>
  </section>
</template>
