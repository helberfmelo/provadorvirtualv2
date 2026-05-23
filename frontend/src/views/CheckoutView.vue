<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { tokenizePagarMeCard, type PublicCheckoutConfig } from '../services/pagarme'

type Plan = {
  code: string
  name: string
  price_cents: number
  currency: string
  description: string
}

type PricingVariant = {
  label: string
  monthly_cents: number
  annual_card_cents: number
  annual_pix_cents: number
  pix_discount_percent: number
  max_installments: number
}

const router = useRouter()
const loading = ref(true)
const submitting = ref(false)
const cepLoading = ref(false)
const error = ref('')
const checkoutConfig = ref<PublicCheckoutConfig | null>(null)
const plans = ref<Plan[]>([])
const pricing = ref<Record<string, PricingVariant>>({})

const form = reactive({
  plan_code: 'annual',
  payment_method: 'pix',
  platform: 'bigshop',
  company_name: '',
  company_legal_name: '',
  company_document: '',
  company_domain: '',
  company_zip_code: '',
  company_address_street: '',
  company_address_number: '',
  company_address_complement: '',
  company_address_district: '',
  company_address_city: '',
  company_address_state: '',
  admin_name: '',
  admin_email: '',
  admin_cpf: '',
  admin_phone: '',
  password: '',
  password_confirmation: '',
  installments: '12',
})

const card = reactive({
  holder_name: '',
  number: '',
  exp_month: '',
  exp_year: '',
  cvv: '',
})

const selectedPlan = computed(() => plans.value.find((plan) => plan.code === form.plan_code))
const canUseCreditCard = computed(() => Boolean(checkoutConfig.value?.credit_card_enabled))
const activePricing = computed(() => pricing.value[form.platform === 'bigshop' ? 'bigshop' : 'default'])
const payableCents = computed(() => {
  const values = activePricing.value
  if (!values) {
    return 0
  }

  return form.payment_method === 'pix' ? values.annual_pix_cents : values.annual_card_cents
})
const monthlyCents = computed(() => activePricing.value?.monthly_cents || 0)
const cardInstallmentCents = computed(() => Math.ceil((activePricing.value?.annual_card_cents || 0) / Number(form.installments || 12)))
const pixDiscountCents = computed(() => {
  const values = activePricing.value
  return values ? values.annual_card_cents - values.annual_pix_cents : 0
})

onMounted(async () => {
  await loadConfig()
})

async function loadConfig() {
  loading.value = true
  try {
    const { data } = await api.get('/public/checkout/config')
    checkoutConfig.value = data.checkout
    plans.value = data.plans
    pricing.value = data.pricing || {}
    form.plan_code = data.plans?.[0]?.code || 'annual'
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel iniciar o checkout.'
  } finally {
    loading.value = false
  }
}

async function lookupCep() {
  const cep = form.company_zip_code.replace(/\D+/g, '')
  if (cep.length !== 8) {
    return
  }

  cepLoading.value = true

  try {
    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`)
    const data = await response.json()
    if (data?.erro) {
      return
    }

    form.company_address_street = data.logradouro || form.company_address_street
    form.company_address_district = data.bairro || form.company_address_district
    form.company_address_city = data.localidade || form.company_address_city
    form.company_address_state = data.uf || form.company_address_state
  } finally {
    cepLoading.value = false
  }
}

async function submitCheckout() {
  if (!checkoutConfig.value) {
    return
  }

  submitting.value = true
  error.value = ''

  try {
    let cardTokenPayload: Record<string, string | null> = {}

    if (form.payment_method === 'credit_card') {
      const token = await tokenizePagarMeCard(checkoutConfig.value, card)
      cardTokenPayload = {
        card_token: token.token,
        card_brand: token.brand,
        card_last_four_digits: token.last_four_digits,
      }
    }

    const payload = {
      ...form,
      installments: Number(form.installments || 12),
      ...cardTokenPayload,
    }

    const { data } = await api.post('/public/checkout', payload)
    await router.push(`/checkout/sucesso?ref=${encodeURIComponent(data.reference)}`)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || requestError.message || 'Nao foi possivel concluir o checkout.'
  } finally {
    submitting.value = false
  }
}

function price(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
</script>

<template>
  <section class="checkout-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Checkout</span>
        <h1>Contratar Provador Virtual</h1>
      </div>
      <strong class="checkout-total">{{ price(monthlyCents) }}/mes</strong>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <div v-if="loading" class="empty-state">Carregando checkout...</div>

    <form v-else class="checkout-grid" @submit.prevent="submitCheckout">
      <section class="panel-main admin-form">
        <div class="subsection-heading">
          <h2>{{ selectedPlan?.name }}</h2>
          <span>Plano anual unico</span>
        </div>

        <div class="annual-plan-summary">
          <div>
            <strong>{{ price(monthlyCents) }}/mes</strong>
            <span>{{ activePricing?.label }}</span>
          </div>
          <p>{{ selectedPlan?.description }}</p>
          <small>Cartao em ate 12x ou Pix a vista com {{ activePricing?.pix_discount_percent || 5 }}% de desconto.</small>
        </div>

        <div class="form-grid">
          <label>
            Plataforma
            <select v-model="form.platform">
              <option value="bigshop">BigShop</option>
              <option value="shopify">Shopify</option>
              <option value="woocommerce">WooCommerce</option>
              <option value="nuvemshop">Nuvemshop</option>
              <option value="vtex">VTEX</option>
              <option value="tray">Tray</option>
              <option value="custom">Personalizada</option>
            </select>
            <small v-if="form.platform === 'bigshop'">Cliente BigShop tem preco especial.</small>
          </label>
          <label>
            Empresa
            <input v-model="form.company_name" required />
          </label>
          <label>
            Razao social
            <input v-model="form.company_legal_name" required />
          </label>
        </div>

        <div class="form-grid">
          <label>
            CNPJ
            <input v-model="form.company_document" inputmode="numeric" required />
          </label>
          <label>
            Dominio
            <input v-model="form.company_domain" placeholder="loja.com.br" />
          </label>
          <label>
            CEP
            <input v-model="form.company_zip_code" inputmode="numeric" required @blur="lookupCep" />
            <small>{{ cepLoading ? 'Buscando endereco...' : ' ' }}</small>
          </label>
        </div>

        <div class="form-grid">
          <label>
            Rua
            <input v-model="form.company_address_street" required />
          </label>
          <label>
            Numero
            <input v-model="form.company_address_number" required />
          </label>
          <label>
            Complemento
            <input v-model="form.company_address_complement" />
          </label>
        </div>

        <div class="form-grid">
          <label>
            Bairro
            <input v-model="form.company_address_district" required />
          </label>
          <label>
            Cidade
            <input v-model="form.company_address_city" required />
          </label>
          <label>
            UF
            <input v-model="form.company_address_state" maxlength="2" required />
          </label>
        </div>
      </section>

      <section class="panel-main admin-form">
        <div class="subsection-heading">
          <h2>Acesso e pagamento</h2>
          <span>{{ form.payment_method === 'credit_card' ? 'Cartao' : 'Pix' }}</span>
        </div>

        <div class="form-grid">
          <label>
            Nome
            <input v-model="form.admin_name" required />
          </label>
          <label>
            E-mail
            <input v-model="form.admin_email" type="email" required />
          </label>
          <label>
            CPF
            <input v-model="form.admin_cpf" inputmode="numeric" required />
          </label>
        </div>

        <div class="form-grid">
          <label>
            Telefone
            <input v-model="form.admin_phone" inputmode="tel" required />
          </label>
          <label>
            Senha
            <input v-model="form.password" type="password" minlength="8" required autocomplete="new-password" />
          </label>
          <label>
            Confirmar senha
            <input v-model="form.password_confirmation" type="password" minlength="8" required autocomplete="new-password" />
          </label>
        </div>

        <div class="payment-tabs">
          <button type="button" :class="{ active: form.payment_method === 'pix' }" @click="form.payment_method = 'pix'">
            Pix
          </button>
          <button
            type="button"
            :disabled="!canUseCreditCard"
            :class="{ active: form.payment_method === 'credit_card' }"
            @click="form.payment_method = 'credit_card'"
          >
            Cartao
          </button>
        </div>

        <div v-if="form.payment_method === 'credit_card'" class="form-grid">
          <label>
            Nome no cartao
            <input v-model="card.holder_name" :required="form.payment_method === 'credit_card'" />
          </label>
          <label>
            Numero
            <input v-model="card.number" inputmode="numeric" :required="form.payment_method === 'credit_card'" />
          </label>
          <label>
            CVV
            <input v-model="card.cvv" inputmode="numeric" :required="form.payment_method === 'credit_card'" />
          </label>
          <label>
            Mes
            <input v-model="card.exp_month" inputmode="numeric" placeholder="MM" :required="form.payment_method === 'credit_card'" />
          </label>
          <label>
            Ano
            <input v-model="card.exp_year" inputmode="numeric" placeholder="AAAA" :required="form.payment_method === 'credit_card'" />
          </label>
          <label>
            Parcelas
            <select v-model="form.installments">
              <option v-for="item in 12" :key="item" :value="String(item)">
                {{ item }}x de {{ price(Math.ceil((activePricing?.annual_card_cents || 0) / item)) }}
              </option>
            </select>
          </label>
        </div>

        <div class="checkout-summary">
          <span>{{ form.payment_method === 'pix' ? 'Total no Pix' : `${form.installments}x no cartao` }}</span>
          <strong>{{ form.payment_method === 'pix' ? price(payableCents) : price(cardInstallmentCents) }}</strong>
        </div>
        <div class="checkout-summary muted">
          <span>{{ form.payment_method === 'pix' ? 'Desconto Pix' : 'Total anual' }}</span>
          <strong>{{ form.payment_method === 'pix' ? price(pixDiscountCents) : price(activePricing?.annual_card_cents || 0) }}</strong>
        </div>

        <button class="btn btn-primary" type="submit" :disabled="submitting">
          <i class="fa-solid fa-lock" aria-hidden="true"></i>
          {{ submitting ? 'Processando...' : 'Finalizar contratacao' }}
        </button>
      </section>
    </form>
  </section>
</template>
