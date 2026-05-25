<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../services/api'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const session = ref<any>(null)
const payment = ref<any>(null)

onMounted(async () => {
  const reference = String(route.query.ref || '')
  if (!reference) {
    error.value = 'Referência de checkout não informada.'
    loading.value = false
    return
  }

  try {
    const { data } = await api.get(`/public/checkout/${reference}`)
    session.value = data.session
    payment.value = data.payment
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o checkout.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="checkout-page">
    <div class="checkout-result panel-main">
      <span class="eyebrow">Checkout</span>
      <h1>{{ session?.status_label || 'Pagamento iniciado' }}</h1>
      <p v-if="loading">Carregando pagamento...</p>
      <p v-else-if="error" class="form-error">{{ error }}</p>
      <template v-else-if="session">
        <div class="summary-strip">
          <span>
            <strong>{{ session.company?.access_code }}</strong>
            <small>Código da empresa</small>
          </span>
          <span>
            <strong>{{ session.company?.status }}</strong>
            <small>Status da empresa</small>
          </span>
          <span>
            <strong>{{ session.provider_label || session.provider }}</strong>
            <small>Operadora</small>
          </span>
          <span>
            <strong>{{ session.payment_method }}</strong>
            <small>Meio</small>
          </span>
        </div>

        <div v-if="payment?.pix?.qr_code" class="payment-box">
          <img v-if="payment.pix.qr_code_base64" class="pix-qr-image" :src="`data:image/jpeg;base64,${payment.pix.qr_code_base64}`" alt="QR Code Pix" />
          <strong>Pix copia e cola</strong>
          <textarea :value="payment.pix.qr_code" rows="4" readonly></textarea>
          <a v-if="payment.pix.ticket_url" class="btn btn-secondary" :href="payment.pix.ticket_url" target="_blank" rel="noopener">
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
            Abrir Pix
          </a>
          <small>O acesso da empresa será liberado automaticamente quando a operadora confirmar o pagamento.</small>
        </div>

        <div v-if="payment?.boleto?.ticket_url || payment?.boleto?.digitable_line || payment?.boleto?.barcode" class="payment-box">
          <strong>Boleto bancário</strong>
          <textarea v-if="payment.boleto.digitable_line || payment.boleto.barcode" :value="payment.boleto.digitable_line || payment.boleto.barcode" rows="3" readonly></textarea>
          <a v-if="payment.boleto.ticket_url" class="btn btn-secondary" :href="payment.boleto.ticket_url" target="_blank" rel="noopener">
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
            Abrir boleto
          </a>
          <small>O acesso da empresa será liberado automaticamente quando a operadora confirmar a compensação do boleto.</small>
        </div>

        <div class="action-row">
          <RouterLink to="/login" class="btn btn-primary">
            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
            Acessar painel
          </RouterLink>
          <RouterLink to="/" class="btn btn-secondary">
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            Voltar ao site
          </RouterLink>
        </div>
      </template>
    </div>
  </section>
</template>
