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
const recommending = ref(false)
const recommendation = ref<{
  recommendation_id: number
  recommended_size: string | null
  confidence: number
  fit_notes: string[]
  warnings: string[]
  needs_more_data: boolean
} | null>(null)
const recommendationError = ref('')
const feedbackSent = ref(false)

const selectedVariant = computed(() => {
  return payload.value?.variants.find((variant) => variant.id === selectedVariantId.value)
})

onMounted(async () => {
  const { data } = await api.get<DemoPayload>('/demo/product-test')
  payload.value = data
  selectedVariantId.value = data.variants[1]?.id ?? data.variants[0]?.id ?? null
  loading.value = false
  await requestRecommendation()
})

async function requestRecommendation() {
  if (!payload.value) {
    return
  }

  recommending.value = true
  recommendationError.value = ''
  feedbackSent.value = false

  try {
    const { data } = await api.post('/public/recommendations', {
      merchant_id: payload.value.product.merchant_id,
      store_id: payload.value.product.store_id,
      product_id: payload.value.product.id,
      variant_id: selectedVariantId.value,
      platform: payload.value.widget.platform,
      measurements: {
        bust: bust.value,
        waist: waist.value,
        hip: hip.value,
        height: height.value,
        weight: weight.value,
      },
      shopper_profile: {
        gender: 'female',
        fit_preference: 'regular',
      },
    })

    recommendation.value = data
  } catch {
    recommendationError.value = 'Nao foi possivel calcular agora.'
  } finally {
    recommending.value = false
  }
}

async function sendFeedback(wasHelpful: boolean) {
  if (!recommendation.value) {
    return
  }

  await api.post(`/public/recommendations/${recommendation.value.recommendation_id}/feedback`, {
    was_helpful: wasHelpful,
    selected_size: selectedVariant.value?.size_label,
  })
  feedbackSent.value = true
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

        <button class="btn btn-primary" type="button" :disabled="recommending" @click="requestRecommendation">
          <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
          {{ recommending ? 'Calculando...' : 'Calcular tamanho' }}
        </button>

        <p v-if="recommendationError" class="form-error">{{ recommendationError }}</p>

        <div v-if="recommendation" class="recommendation">
          <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
          <div>
            <span>Tamanho recomendado</span>
            <strong>{{ recommendation.recommended_size }}</strong>
            <small>{{ Math.round(recommendation.confidence) }}% de confianca com a tabela demo</small>
            <small v-for="note in recommendation.fit_notes" :key="note">{{ note }}</small>
            <small v-for="warning in recommendation.warnings" :key="warning">{{ warning }}</small>
            <div class="feedback-row" v-if="!feedbackSent">
              <button type="button" title="Ajudou" @click="sendFeedback(true)">
                <i class="fa-solid fa-thumbs-up" aria-hidden="true"></i>
              </button>
              <button type="button" title="Nao ajudou" @click="sendFeedback(false)">
                <i class="fa-solid fa-thumbs-down" aria-hidden="true"></i>
              </button>
            </div>
            <small v-else>Feedback registrado. Obrigado.</small>
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
