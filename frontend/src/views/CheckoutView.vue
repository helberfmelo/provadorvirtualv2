<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import { createMercadoPagoCardForm, tokenizePagarMeCard, type MercadoPagoCardFormData, type PublicCheckoutConfig } from '../services/pagarme'

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
const route = useRoute()
const loading = ref(true)
const submitting = ref(false)
const cepLoading = ref(false)
const error = ref('')
const checkoutConfig = ref<PublicCheckoutConfig | null>(null)
const plans = ref<Plan[]>([])
const pricing = ref<Record<string, PricingVariant>>({})
const mercadoPagoCardForm = ref<any>(null)
const mercadoPagoCardFormKey = ref('')
const installmentsTouched = ref(false)
let mercadoPagoInstallmentObserver: MutationObserver | null = null
const platformOptions = [
  { value: 'bigshop', label: 'BigShop' },
  { value: 'shopify', label: 'Shopify' },
  { value: 'woocommerce', label: 'WooCommerce' },
  { value: 'nuvemshop', label: 'Nuvemshop' },
  { value: 'vtex', label: 'VTEX' },
  { value: 'tray', label: 'Tray' },
  { value: 'loja_integrada', label: 'Loja Integrada' },
  { value: 'magento', label: 'Magento' },
  { value: 'opencart', label: 'OpenCart' },
  { value: 'custom', label: 'Personalizada' },
]
const allowedPlatforms = platformOptions.map((platform) => platform.value)

const form = reactive({
  plan_code: 'annual',
  payment_method: 'credit_card',
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
  installments: '',
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
const checkoutProvider = computed(() => checkoutConfig.value?.provider || checkoutConfig.value?.active_provider || '')
const isMercadoPago = computed(() => checkoutProvider.value === 'mercado_pago')
const activePricing = computed(() => pricing.value[form.platform === 'bigshop' ? 'bigshop' : 'default'])
const payableCents = computed(() => {
  const values = activePricing.value
  if (!values) {
    return 0
  }

  return form.payment_method === 'pix' ? values.annual_pix_cents : values.annual_card_cents
})
const monthlyCents = computed(() => activePricing.value?.monthly_cents || 0)
const annualCardCents = computed(() => activePricing.value?.annual_card_cents || 0)
const maxInstallments = computed(() => Math.max(1, Math.min(10, Number(activePricing.value?.max_installments || 10))))
const installmentOptions = computed(() => Array.from({ length: maxInstallments.value }, (_, index) => index + 1))
const selectedInstallments = computed(() => {
  const value = Number(form.installments)
  return Number.isFinite(value) && value >= 1 && value <= maxInstallments.value ? value : 0
})
const hasSelectedInstallments = computed(() => form.payment_method === 'credit_card' && selectedInstallments.value > 0)
const selectedInstallmentCents = computed(() => selectedInstallments.value > 0 ? installmentCents(selectedInstallments.value) : 0)
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
    form.payment_method = data.checkout?.credit_card_enabled ? 'credit_card' : 'pix'
    form.installments = ''
    installmentsTouched.value = false
    const queryPlatform = String(route.query.platform || '')
    if (allowedPlatforms.includes(queryPlatform)) {
      form.platform = queryPlatform
    }
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível iniciar o checkout.'
  } finally {
    loading.value = false
  }

  if (form.payment_method === 'credit_card' && isMercadoPago.value) {
    await prepareMercadoPagoCardForm()
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
      if (!hasSelectedInstallments.value) {
        throw new Error('Escolha o número de parcelas para continuar.')
      }

      if (isMercadoPago.value) {
        const cardData = await mercadoPagoCardData()
        cardTokenPayload = {
          card_token: cardData.token,
          payment_method_id: cardData.paymentMethodId,
          issuer_id: cardData.issuerId || null,
        }
        form.installments = String(selectedInstallments.value || cardData.installments || '1')
      } else {
        const token = await tokenizePagarMeCard(checkoutConfig.value, card)
        cardTokenPayload = {
          card_token: token.token,
          card_brand: token.brand,
          card_last_four_digits: token.last_four_digits,
        }
      }
    }

    const payload = {
      ...form,
      installments: form.payment_method === 'credit_card' ? selectedInstallments.value : null,
      ...cardTokenPayload,
    }

    const { data } = await api.post('/public/checkout', payload)
    await router.push(`/checkout/sucesso?ref=${encodeURIComponent(data.reference)}`)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || requestError.message || 'Não foi possível concluir o checkout.'
  } finally {
    submitting.value = false
  }
}

async function selectPaymentMethod(method: 'pix' | 'credit_card') {
  if (method === 'credit_card' && !canUseCreditCard.value) {
    return
  }

  form.payment_method = method
  form.installments = ''
  installmentsTouched.value = false

  if (method === 'credit_card' && isMercadoPago.value) {
    await prepareMercadoPagoCardForm()
  }
}

async function prepareMercadoPagoCardForm() {
  if (!checkoutConfig.value || !isMercadoPago.value || form.payment_method !== 'credit_card') {
    return
  }

  await nextTick()
  syncMercadoPagoDocumentFields()

  const key = `${checkoutConfig.value.public_key || ''}:${annualCardCents.value}`
  if (mercadoPagoCardForm.value && mercadoPagoCardFormKey.value === key) {
    return
  }

  mercadoPagoCardForm.value = await createMercadoPagoCardForm(checkoutConfig.value, annualCardCents.value)
  mercadoPagoCardFormKey.value = key
  setupMercadoPagoInstallments()
}

async function mercadoPagoCardData(): Promise<MercadoPagoCardFormData> {
  await prepareMercadoPagoCardForm()
  syncMercadoPagoDocumentFields()

  const cardData = await mercadoPagoCardForm.value?.getCardFormData()
  if (!cardData?.token || !cardData?.paymentMethodId) {
    throw new Error('Confira os dados do cartão antes de finalizar.')
  }

  return cardData
}

function syncMercadoPagoDocumentFields() {
  const identificationNumber = document.getElementById('mp-identification-number') as HTMLInputElement | null
  const identificationType = document.getElementById('mp-identification-type') as HTMLSelectElement | null

  if (identificationNumber) {
    identificationNumber.value = form.admin_cpf.replace(/\D+/g, '')
  }

  if (identificationType && !identificationType.value) {
    identificationType.value = 'CPF'
  }
}

function setupMercadoPagoInstallments() {
  const select = document.getElementById('mp-installments') as HTMLSelectElement | null
  if (!select) {
    return
  }

  select.removeEventListener('change', handleInstallmentSelection)
  select.addEventListener('change', handleInstallmentSelection)
  mercadoPagoInstallmentObserver?.disconnect()
  mercadoPagoInstallmentObserver = new MutationObserver(() => normalizeMercadoPagoInstallmentOptions())
  mercadoPagoInstallmentObserver.observe(select, { childList: true, subtree: true })
  normalizeMercadoPagoInstallmentOptions()
}

function normalizeMercadoPagoInstallmentOptions() {
  const select = document.getElementById('mp-installments') as HTMLSelectElement | null
  if (!select) {
    return
  }

  if (select.options.length === 0) {
    form.installments = ''
    return
  }

  Array.from(select.options).forEach((option) => {
    const installments = Number(option.value)
    if (option.value && installments > maxInstallments.value) {
      option.remove()
      return
    }

    if (option.value && installments >= 1) {
      const label = installmentLabel(installments)
      if (option.textContent !== label) {
        option.textContent = label
      }
    }
  })

  if (!Array.from(select.options).some((option) => option.value === '')) {
    select.insertBefore(new Option('Escolha as parcelas', '', true, !installmentsTouched.value), select.firstChild)
  }

  const placeholder = Array.from(select.options).find((option) => option.value === '')
  if (placeholder && placeholder.textContent !== 'Escolha as parcelas') {
    placeholder.textContent = 'Escolha as parcelas'
  }

  if (!installmentsTouched.value) {
    select.value = ''
    form.installments = ''
  }
}

function handleInstallmentSelection(event: Event) {
  const select = event.target as HTMLSelectElement
  const installments = Number(select.value)

  if (!Number.isFinite(installments) || installments < 1) {
    form.installments = ''
    installmentsTouched.value = false
    return
  }

  const normalized = Math.min(installments, maxInstallments.value)
  form.installments = String(normalized)
  installmentsTouched.value = true
}

function updateAdminCpf(event: Event) {
  form.admin_cpf = (event.target as HTMLInputElement).value
}

function installmentCents(installments: number) {
  return Math.ceil(annualCardCents.value / Math.max(1, installments))
}

function installmentLabel(installments: number) {
  const suffix = installments > 1 ? ' sem juros' : ''
  return `${installments}x de ${price(installmentCents(installments))}${suffix}`
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
      <strong class="checkout-total">{{ price(monthlyCents) }}/mês</strong>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <div v-if="loading" class="empty-state">Carregando checkout...</div>

    <form v-else id="checkout-form" class="checkout-grid" @submit.prevent="submitCheckout">
      <section class="panel-main admin-form">
        <div class="subsection-heading">
          <h2>{{ selectedPlan?.name }}</h2>
          <span>Plano anual único</span>
        </div>

        <div class="annual-plan-summary">
          <div>
            <strong>{{ price(monthlyCents) }}/mês</strong>
            <span>{{ activePricing?.label }}</span>
          </div>
          <p>{{ selectedPlan?.description }}</p>
          <small>Cartão em até {{ maxInstallments }}x sem juros ou Pix à vista com {{ activePricing?.pix_discount_percent || 5 }}% de desconto.</small>
        </div>

        <div class="form-grid">
          <label>
            Plataforma
            <select v-model="form.platform">
              <option v-for="platform in platformOptions" :key="platform.value" :value="platform.value">
                {{ platform.label }}
              </option>
            </select>
            <small v-if="form.platform === 'bigshop'">Cliente BigShop tem preço especial.</small>
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
            Domínio
            <input v-model="form.company_domain" placeholder="loja.com.br" />
          </label>
          <label>
            CEP
            <input v-model="form.company_zip_code" inputmode="numeric" required @blur="lookupCep" />
            <small>{{ cepLoading ? 'Buscando endereço...' : ' ' }}</small>
          </label>
        </div>

        <div class="form-grid">
          <label>
            Rua
            <input v-model="form.company_address_street" required />
          </label>
          <label>
            Número
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
          <span>{{ form.payment_method === 'credit_card' ? 'Cartão' : 'Pix' }}</span>
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
          <button
            type="button"
            :disabled="!canUseCreditCard"
            :class="{ active: form.payment_method === 'credit_card' }"
            @click="selectPaymentMethod('credit_card')"
          >
            Cartão
          </button>
          <button type="button" :class="{ active: form.payment_method === 'pix' }" @click="selectPaymentMethod('pix')">
            Pix
            <span class="payment-tab-badge">5% off</span>
          </button>
        </div>

        <div v-if="form.payment_method === 'credit_card' && !isMercadoPago" class="form-grid">
          <label>
            Nome no cartão
            <input v-model="card.holder_name" :required="form.payment_method === 'credit_card'" />
          </label>
          <label>
            Número
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
            <select v-model="form.installments" required @change="handleInstallmentSelection">
              <option value="">Escolha as parcelas</option>
              <option v-for="item in installmentOptions" :key="item" :value="String(item)">
                {{ installmentLabel(item) }}
              </option>
            </select>
          </label>
        </div>

        <div v-if="form.payment_method === 'credit_card' && isMercadoPago" class="mercado-card-fields">
          <label class="wide">
            Nome no cartão
            <input id="mp-cardholder-name" v-model="card.holder_name" :required="form.payment_method === 'credit_card'" autocomplete="cc-name" />
          </label>
          <label class="wide">
            Número
            <div id="mp-card-number" class="mp-secure-field"></div>
          </label>
          <label>
            Validade
            <div id="mp-expiration-date" class="mp-secure-field"></div>
          </label>
          <label>
            CVV
            <div id="mp-security-code" class="mp-secure-field"></div>
          </label>
          <label class="mp-hidden-control">
            Bandeira
            <select id="mp-issuer"></select>
          </label>
          <label class="wide">
            Parcelas
            <select id="mp-installments" v-model="form.installments" required @change="handleInstallmentSelection"></select>
          </label>
          <label class="mp-hidden-control">
            Documento
            <select id="mp-identification-type" aria-label="Tipo de documento">
              <option value="CPF">CPF</option>
            </select>
          </label>
          <label class="wide">
            Número do documento
            <input id="mp-identification-number" :value="form.admin_cpf.replace(/\D+/g, '')" inputmode="numeric" required @input="updateAdminCpf" />
          </label>
        </div>

        <template v-if="form.payment_method === 'pix'">
          <div class="checkout-summary">
            <span>Total no Pix <small>5% off</small></span>
            <strong>{{ price(payableCents) }}</strong>
          </div>
          <div class="checkout-summary muted">
            <span>Desconto Pix</span>
            <strong>{{ price(pixDiscountCents) }}</strong>
          </div>
        </template>

        <template v-else-if="hasSelectedInstallments">
          <div class="checkout-summary card-installment-summary" :class="{ single: selectedInstallments === 1 }">
            <span>{{ selectedInstallments === 1 ? 'Pagamento no cartão' : `${selectedInstallments}x sem juros` }}</span>
            <strong>{{ selectedInstallments === 1 ? price(annualCardCents) : price(selectedInstallmentCents) }}</strong>
          </div>
          <div v-if="selectedInstallments > 1" class="checkout-summary muted">
            <span>Total anual</span>
            <strong>{{ price(annualCardCents) }}</strong>
          </div>
        </template>

        <div v-else class="checkout-summary muted">
          <span>Escolha as parcelas</span>
          <strong>Até {{ maxInstallments }}x sem juros</strong>
        </div>

        <button class="btn btn-primary" type="submit" :disabled="submitting">
          <i class="fa-solid fa-lock" aria-hidden="true"></i>
          {{ submitting ? 'Processando...' : 'Finalizar contratação' }}
        </button>
      </section>
    </form>
  </section>
</template>
