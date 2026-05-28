<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { Product } from '../services/merchantTypes'
import { showFeedback } from '../services/saveFeedback'

const products = ref<Product[]>([])
const loading = ref(false)
const error = ref('')

onMounted(() => {
  loadProducts()
})

async function loadProducts() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/products')
    products.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os produtos.'
  } finally {
    loading.value = false
  }
}

async function removeProduct(product: Product) {
  await api.delete(`/products/${product.id}`)
  showFeedback({
    status: 'success',
    title: 'Produto removido',
    message: 'O produto foi removido da empresa.',
  })
  await loadProducts()
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Produtos</span>
        <h1>Produtos</h1>
        <p>Organize catálogo, categorias e variações antes de vincular cada item à tabela de medidas certa.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadProducts">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink class="btn btn-primary" to="/app/produtos/novo">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          Novo produto
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Produtos cadastrados</h2>
        <span>{{ loading ? 'carregando' : `${products.length} produtos` }}</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Produto</th>
              <th>Categoria</th>
              <th>Modelagem</th>
              <th>Tabela</th>
              <th>Variações</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!products.length">
              <td colspan="7">Nenhum produto cadastrado.</td>
            </tr>
            <tr v-for="product in products" :key="product.id">
              <td>
                <strong>{{ product.name }}</strong>
                <small>{{ product.sku || 'sem SKU' }}</small>
              </td>
              <td>{{ product.category || '-' }}</td>
              <td>{{ product.fit_profile || '-' }}</td>
              <td>{{ product.measurement_table?.name || 'Sem tabela' }}</td>
              <td>{{ product.variants_count ?? 0 }}</td>
              <td>
                <span class="status-pill" :class="{ ok: product.status === 'active', warning: product.status !== 'active' }">
                  {{ product.status === 'active' ? 'Ativo' : product.status }}
                </span>
              </td>
              <td class="row-actions">
                <RouterLink class="icon-link" :to="`/app/produtos/${product.id}/editar`" title="Editar">
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button type="button" title="Remover produto" @click="removeProduct(product)">
                  <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
