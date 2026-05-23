<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
const bust = ref(90)
const waist = ref(72)
const hip = ref(98)
const height = ref(165)
const weight = ref(60)

const selectedVariant = computed(() => {
  return payload.value?.variants.find((variant) => variant.id === selectedVariantId.value)
})

const recommendation = computed(() => {
  if (!payload.value) {
    return null
  }

  const scored = payload.value.measurement_table.rows.map((row) => {
    const penalties = [
      rangePenalty(bust.value, row.bust),
      rangePenalty(waist.value, row.waist),
      rangePenalty(hip.value, row.hip),
      rangePenalty(height.value, row.height),
      rangePenalty(weight.value, row.weight),
    ]
    const score = penalties.reduce((sum, value) => sum + value, 0)
    return { size: row.size_label, score }
  })

  const best = scored.sort((a, b) => a.score - b.score)[0]
  const confidence = Math.max(62, Math.round(96 - best.score * 7))

  return { size: best.size, confidence }
})

function rangePenalty(value: number, range: [string | null, string | null]) {
  const min = Number(range[0])
  const max = Number(range[1])

  if (!Number.isFinite(min) || !Number.isFinite(max)) {
    return 0
  }

  if (value < min) {
    return (min - value) / 4
  }

  if (value > max) {
    return (value - max) / 4
  }

  return 0
}

onMounted(async () => {
  const { data } = await api.get<DemoPayload>('/demo/product-test')
  payload.value = data
  selectedVariantId.value = data.variants[1]?.id ?? data.variants[0]?.id ?? null
  loading.value = false
})
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
          @click="selectedVariantId = variant.id"
        >
          {{ variant.size_label }}
        </button>
      </div>

      <div class="tester">
        <div>
          <span class="eyebrow">Provador Virtual</span>
          <h2>Qual tamanho combina com voce?</h2>
        </div>

        <div class="measure-grid">
          <label> Busto <input v-model.number="bust" type="number" min="60" max="140" /> </label>
          <label> Cintura <input v-model.number="waist" type="number" min="50" max="130" /> </label>
          <label> Quadril <input v-model.number="hip" type="number" min="70" max="150" /> </label>
          <label> Altura <input v-model.number="height" type="number" min="130" max="210" /> </label>
          <label> Peso <input v-model.number="weight" type="number" min="35" max="160" /> </label>
        </div>

        <div v-if="recommendation" class="recommendation">
          <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
          <div>
            <span>Tamanho recomendado</span>
            <strong>{{ recommendation.size }}</strong>
            <small>{{ recommendation.confidence }}% de confianca com a tabela demo</small>
          </div>
        </div>
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
