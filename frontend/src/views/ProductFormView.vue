<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import type { MeasurementTableOption, Product, ProductVariant } from '../services/merchantTypes'

const route = useRoute()
const router = useRouter()
const productId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(productId.value))

const measurementTables = ref<MeasurementTableOption[]>([])
const selected = ref<Product | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref('')

const form = reactive({
  name: '',
  sku: '',
  category: '',
  gender: 'unisex',
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

onMounted(() => {
  loadForm()
})

async function loadForm() {
  loading.value = true
  error.value = ''

  try {
    const tablesResponse = await api.get('/measurement-tables')
    measurementTables.value = tablesResponse.data.data

    if (editing.value) {
      const { data } = await api.get(`/products/${productId.value}`)
      selected.value = data.data
      fillForm(data.data)
      return
    }

    form.measurement_table_id = null
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o produto.'
  } finally {
    loading.value = false
  }
}

function fillForm(product: Product) {
  form.name = product.name
  form.sku = product.sku ?? ''
  form.category = product.category ?? ''
  form.gender = product.gender ?? 'unisex'
  form.fit_profile = product.fit_profile ?? 'regular'
  form.status = product.status ?? 'active'
  form.measurement_table_id = product.measurement_table_id ?? measurementTables.value[0]?.id ?? null
}

async function saveProduct() {
  saving.value = true
  error.value = ''

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
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Produtos</span>
        <h1>{{ editing ? 'Editar produto' : 'Novo produto' }}</h1>
        <p>Dados principais do produto e grade de variações.</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/app/produtos">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <form class="panel-main admin-form form-page" @submit.prevent="saveProduct">
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
        <label>
          Tabela
          <select v-model.number="form.measurement_table_id">
            <option :value="null">Sem tabela</option>
            <option v-for="table in measurementTables" :key="table.id" :value="table.id">
              {{ table.name }}
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

      <div class="action-row compact">
        <button class="btn btn-primary" :disabled="saving || loading" type="submit">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar produto' }}
        </button>
      </div>
    </form>

    <section v-if="selected" class="panel-main subsection form-page">
      <div class="subsection-heading">
        <h2>Variações</h2>
        <span>{{ selected.variants?.length ?? 0 }} itens</span>
      </div>

      <div class="table-wrap">
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

      <form class="inline-form" @submit.prevent="addVariant">
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
    </section>
  </section>
</template>
