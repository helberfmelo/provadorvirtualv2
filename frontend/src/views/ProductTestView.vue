<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { api } from '../services/api'

type Variant = {
  id: number
  sku: string
  size_label: string
  color: string
  price: string
  stock_quantity: number
}

type MeasurementRow = {
  size_label: string
  bust: [string | null, string | null]
  waist: [string | null, string | null]
  hip: [string | null, string | null]
  height: [string | null, string | null]
  weight: [string | null, string | null]
}

type DemoPayload = {
  product: {
    id: number
    merchant_id: number
    store_id: number
    name: string
    description: string
    image_url: string
    company: { name: string; platform: string }
  }
  variants: Variant[]
  measurement_table: { name: string; unit: string; rows: MeasurementRow[] }
  widget: { public_key: string; platform: string }
}

const payload = ref<DemoPayload | null>(null)
const selectedVariantId = ref<number | null>(null)
const loading = ref(true)

const selectedVariant = computed(() => {
  return payload.value?.variants.find((variant) => variant.id === selectedVariantId.value)
})

onMounted(async () => {
  const { data } = await api.get<DemoPayload>('/demo/product-test')
  payload.value = data
  selectedVariantId.value = data.variants[1]?.id ?? data.variants[0]?.id ?? null
  loading.value = false
  await nextTick()
  loadWidget()
})

function selectVariant(variantId: number) {
  selectedVariantId.value = variantId
  nextTick(() => loadWidget())
}

function loadWidget() {
  if (!payload.value) {
    return
  }

  document.getElementById('provadorVirtualScript')?.remove()
  document.querySelector('#provador-virtual-container .pv-widget-root')?.remove()

  const script = document.createElement('script')
  script.id = 'provadorVirtualScript'
  script.src = `${import.meta.env.VITE_WIDGET_BASE_URL || '/widget/v1'}/provador-virtual.js`
  script.defer = true
  script.dataset.merchantId = String(payload.value.product.merchant_id)
  script.dataset.storeId = String(payload.value.product.store_id)
  script.dataset.productId = String(payload.value.product.id)
  script.dataset.variantId = String(selectedVariantId.value ?? '')
  script.dataset.sku = selectedVariant.value?.sku ?? ''
  script.dataset.platform = payload.value.widget.platform
  script.dataset.containerId = 'provador-virtual-container'
  script.dataset.theme = JSON.stringify({ primary: '#0f172a', accent: '#ff4d5e' })
  document.body.appendChild(script)
}
</script>

<template>
  <section v-if="loading" class="product-page">
    <p>Carregando produto...</p>
  </section>

  <section v-else-if="payload" class="product-page">
    <div class="product-media">
      <img :src="payload.product.image_url" :alt="payload.product.name" />
    </div>

    <div class="product-info">
      <span class="eyebrow">{{ payload.product.company.name }}</span>
      <h1>{{ payload.product.name }}</h1>
      <p>{{ payload.product.description }}</p>

      <div class="price-row">
        <strong>R$ {{ selectedVariant?.price ?? '189.90' }}</strong>
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
          <h2>Qual tamanho combina com voce?</h2>
        </div>

        <div id="provador-virtual-container"></div>
      </div>

      <div class="measurement-table">
        <h2>{{ payload.measurement_table.name }}</h2>
        <table>
          <thead>
            <tr>
              <th>Tam.</th>
              <th>Busto</th>
              <th>Cintura</th>
              <th>Quadril</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in payload.measurement_table.rows" :key="row.size_label">
              <td>{{ row.size_label }}</td>
              <td>{{ row.bust[0] }}-{{ row.bust[1] }}</td>
              <td>{{ row.waist[0] }}-{{ row.waist[1] }}</td>
              <td>{{ row.hip[0] }}-{{ row.hip[1] }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
