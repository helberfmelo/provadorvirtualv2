<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../services/api'

type Variant = {
  id: number
  sku: string
  size_label: string
  color: string
  price: string
  stock_quantity: number
}

type ProductSummary = {
  id: number
  merchant_id: number
  store_id: number
  external_product_id: string
  name: string
  slug: string
  description: string
  category: string
  gender: string
  fit_profile: string
  image_url: string
  price_from: string
  sizes: string[]
}

type MeasurementRow = {
  size_label: string
  bust: [string | null, string | null]
  waist: [string | null, string | null]
  hip: [string | null, string | null]
  height: [string | null, string | null]
  weight: [string | null, string | null]
}

type ProductPayload = {
  product: {
    id: number
    merchant_id: number
    store_id: number
    external_product_id: string
    name: string
    description: string
    image_url: string
    company: { name: string; platform: string }
  }
  variants: Variant[]
  measurement_table: { name: string; unit: string; rows: MeasurementRow[] }
  widget: { public_key: string; platform: string; theme?: Record<string, string> }
}

type StorefrontPayload = {
  store: { name: string; platform: string; domain: string }
  products: ProductSummary[]
  widget: { public_key: string; platform: string; theme?: Record<string, string> }
}

const route = useRoute()
const storefront = ref<StorefrontPayload | null>(null)
const payload = ref<ProductPayload | null>(null)
const selectedVariantId = ref<number | null>(null)
const loading = ref(true)
const productLoading = ref(false)
const error = ref('')

const selectedVariant = computed(() => {
  return payload.value?.variants.find((variant) => variant.id === selectedVariantId.value)
})

const activeSlug = computed(() => String(route.params.slug || ''))

onMounted(async () => {
  await loadStorefront()
})

watch(activeSlug, async () => {
  await loadProductForRoute()
})

async function loadStorefront() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get<StorefrontPayload>('/demo/storefront')
    storefront.value = data
    await loadProductForRoute()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar a loja teste.'
  } finally {
    loading.value = false
  }
}

async function loadProductForRoute() {
  if (!activeSlug.value) {
    payload.value = null
    cleanupWidget()
    return
  }

  productLoading.value = true
  error.value = ''

  try {
    const { data } = await api.get<ProductPayload>(`/demo/storefront/${activeSlug.value}`)
    payload.value = data
    selectedVariantId.value = data.variants[1]?.id ?? data.variants[0]?.id ?? null
    await nextTick()
    loadWidget()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Produto não encontrado.'
  } finally {
    productLoading.value = false
  }
}

function selectVariant(variantId: number) {
  selectedVariantId.value = variantId
  nextTick(() => loadWidget())
}

function loadWidget() {
  if (!payload.value) {
    return
  }

  cleanupWidget()

  const script = document.createElement('script')
  script.id = 'provadorVirtualScript'
  script.src = `${widgetBaseUrl()}/provador-virtual.js`
  script.defer = true
  script.dataset.publicKey = payload.value.widget.public_key
  script.dataset.merchantId = String(payload.value.product.merchant_id)
  script.dataset.storeId = String(payload.value.product.store_id)
  script.dataset.productId = String(payload.value.product.id)
  script.dataset.variantId = String(selectedVariantId.value ?? '')
  script.dataset.sku = selectedVariant.value?.sku ?? ''
  script.dataset.platform = payload.value.widget.platform
  script.dataset.containerId = 'provador-virtual-container'
  script.dataset.theme = JSON.stringify(payload.value.widget.theme || {})
  document.body.appendChild(script)
}

function cleanupWidget() {
  document.getElementById('provadorVirtualScript')?.remove()
  document.querySelector('#provador-virtual-container .pv-widget-root')?.remove()
}

function widgetBaseUrl() {
  const explicit = import.meta.env.VITE_WIDGET_BASE_URL
  if (explicit) {
    return explicit
  }

  const apiBase = api.defaults.baseURL || ''
  if (apiBase.includes('/api/v1')) {
    return apiBase.replace('/api/v1', '/widget/v1')
  }

  return '/widget/v1'
}

function price(value: string | number | null | undefined) {
  const numberValue = Number(value || 0)
  return numberValue.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
</script>

<template>
  <section v-if="loading" class="shop-page">
    <p>Carregando loja teste...</p>
  </section>

  <section v-else-if="error" class="shop-page">
    <p class="form-error">{{ error }}</p>
  </section>

  <section v-else-if="!activeSlug && storefront" class="shop-page">
    <div class="shop-heading">
      <div>
        <span class="eyebrow">{{ storefront.store.name }}</span>
        <h1>Loja teste do Provador Virtual</h1>
        <p>Escolha um produto e teste a recomendação de tamanho e a tabela de medidas como em uma loja real.</p>
      </div>
    </div>

    <div class="product-card-grid">
      <RouterLink
        v-for="product in storefront.products"
        :key="product.id"
        class="store-product-card"
        :to="`/produto-teste/${product.slug}`"
      >
        <img :src="product.image_url" :alt="product.name" />
        <span>{{ product.category }} - {{ product.gender === 'female' ? 'Feminino' : 'Masculino' }}</span>
        <strong>{{ product.name }}</strong>
        <small>{{ product.description }}</small>
        <em>{{ price(product.price_from) }}</em>
      </RouterLink>
    </div>
  </section>

  <section v-else-if="payload" class="product-page">
    <div class="product-media">
      <img :src="payload.product.image_url" :alt="payload.product.name" />
    </div>

    <div class="product-info">
      <RouterLink to="/produto-teste" class="back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar para a loja teste
      </RouterLink>
      <span class="eyebrow">{{ payload.product.company.name }}</span>
      <h1>{{ payload.product.name }}</h1>
      <p>{{ payload.product.description }}</p>

      <div class="price-row">
        <strong>{{ price(selectedVariant?.price ?? 0) }}</strong>
        <span>{{ selectedVariant?.stock_quantity ?? 0 }} pecas no tamanho selecionado</span>
      </div>

      <div class="size-picker" aria-label="Escolha de tamanho">
        <button
          v-for="variant in payload.variants"
          :key="variant.id"
          type="button"
          :class="{ active: selectedVariantId === variant.id }"
          @click="selectVariant(variant.id)"
        >
          {{ variant.size_label }}
        </button>
      </div>

      <div class="tester">
        <div>
          <span class="eyebrow">Provador Virtual</span>
          <h2>Encontre o tamanho antes de comprar</h2>
        </div>

        <div id="provador-virtual-container"></div>
      </div>

      <div v-if="productLoading" class="empty-state">Atualizando produto...</div>
    </div>
  </section>
</template>
