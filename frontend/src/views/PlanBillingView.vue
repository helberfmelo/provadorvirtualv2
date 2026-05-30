<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { showFeedback } from '../services/saveFeedback'
import { useAuthStore } from '../stores/auth'

type BillingLinkAccess = {
  type: 'checkout_status' | 'commercial_request'
  id: number
}

type BillingPlan = {
  plan_code: string | null
  plan_name: string | null
  billing_cycle: string | null
  billing_cycle_label: string
  amount_cents: number | null
  currency: string
  provider: string | null
  provider_label: string | null
  payment_method: string | null
  payment_method_label: string | null
  status: string | null
  status_label: string
  started_at: string | null
  next_due_at: string | null
  cancel_requested_at: string | null
  auto_renewal_enabled: boolean
}

type BillingSubscription = {
  id: number
  provider: string
  plan_code: string
  billing_cycle: string
  payment_method: string
  status: string
  auto_renewal_enabled: boolean
  amount_cents: number
  currency: string
  next_charge_at: string | null
  started_at: string | null
  cancel_requested_at: string | null
  cancelled_at: string | null
}

type BillingPaymentLink = {
  id: number
  type: BillingLinkAccess['type']
  title: string
  description: string
  status: string
  status_label: string
  host: string | null
  created_at: string | null
  expires_at: string | null
  access: BillingLinkAccess
}

type CommercialRequest = {
  id: number
  from_platform: string
  from_platform_label: string
  to_platform: string
  to_platform_label: string
  status: string
  status_label: string
  requested_at: string | null
  resolved_at: string | null
  financial_summary: {
    short_text?: string | null
  } | null
  payment_link_available: boolean
  payment_link_host: string | null
  payment_link_access: BillingLinkAccess | null
  history: Array<{
    id: number
    label: string
    severity: string
    actor_name: string | null
    occurred_at: string | null
  }>
}

type BillingData = {
  company: {
    merchant_name: string
    merchant_slug: string
    billing_status: string
    billing_status_label: string
    company: {
      id: number | null
      name: string | null
      access_code: string | null
      platform: string
      platform_label: string
      bigshop_discount_active: boolean
      commercial_status: string
      commercial_status_label: string
      bigshop_benefit_label: string | null
    }
  }
  plan: BillingPlan
  subscription: BillingSubscription | null
  payment_links: BillingPaymentLink[]
  commercial_requests: CommercialRequest[]
  actions: {
    can_disable_auto_renewal: boolean
    financial_changes_managed_by: string
    financial_changes_note: string
  }
}

const router = useRouter()
const auth = useAuthStore()
const loading = ref(false)
const resolvingLinkKey = ref('')
const data = ref<BillingData | null>(null)
const state = reactive({
  error: '',
  billingError: '',
  billingSaved: '',
  savingAutoRenewal: false,
})

const companyName = computed(() => data.value?.company.company.name || auth.activeCompany?.name || 'Sua empresa')
const bigShopBenefitActive = computed(() => Boolean(data.value?.company.company.bigshop_discount_active))
const nextDueLabel = computed(() => {
  const nextDueAt = data.value?.plan.next_due_at

  return nextDueAt ? formatDate(nextDueAt) : 'Sem vencimento futuro registrado'
})

onMounted(async () => {
  await auth.ensureLoaded().catch(() => undefined)

  if (!auth.canView('dashboard')) {
    await router.replace('/app')
    return
  }

  await loadBilling()
})

watch(() => auth.activeCompany?.id, async (companyId, previousCompanyId) => {
  if (!companyId || companyId === previousCompanyId) {
    return
  }

  await loadBilling()
})

async function loadBilling() {
  loading.value = true
  state.error = ''

  try {
    const response = await api.get('/billing/subscription')
    data.value = response.data.data
  } catch (requestError: any) {
    data.value = null
    state.error = requestError.response?.data?.message || 'Não foi possível carregar plano e cobrança.'
  } finally {
    loading.value = false
  }
}

async function openPaymentLink(item: BillingPaymentLink | CommercialRequest) {
  const access = 'access' in item ? item.access : item.payment_link_access

  if (!access) {
    return
  }

  const key = `${access.type}:${access.id}`
  resolvingLinkKey.value = key

  try {
    const response = await api.post('/billing/payment-links/resolve', access)
    const url = response.data.data?.url

    if (!url) {
      throw new Error('Link indisponível.')
    }

    window.open(url, '_blank', 'noopener')
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Não foi possível abrir o link',
      message: requestError.response?.data?.message || 'Não foi possível abrir o link financeiro agora.',
    })
  } finally {
    resolvingLinkKey.value = ''
  }
}

async function updateAutoRenewal(event: Event) {
  const input = event.target as HTMLInputElement

  if (input.checked) {
    input.checked = false
    state.billingSaved = ''
    state.billingError = 'A reativação automática deve ser solicitada ao Admin.'
    return
  }

  state.savingAutoRenewal = true
  state.billingError = ''
  state.billingSaved = ''

  try {
    const response = await api.patch('/billing/subscription/auto-renewal', {
      auto_renewal_enabled: false,
    })

    if (data.value) {
      data.value.subscription = response.data.data
      data.value.plan.auto_renewal_enabled = Boolean(response.data.data?.auto_renewal_enabled)
      data.value.plan.cancel_requested_at = response.data.data?.cancel_requested_at || null
    }

    state.billingSaved = 'Renovação futura desativada.'
  } catch (requestError: any) {
    input.checked = true
    state.billingError = requestError.response?.data?.message || 'Não foi possível alterar a renovação automática.'
  } finally {
    state.savingAutoRenewal = false
  }
}

function formatMoney(value: number | null | undefined) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format((value || 0) / 100)
}

function formatDate(value: string | null | undefined) {
  if (!value) {
    return 'Não informado'
  }

  return new Date(value).toLocaleDateString('pt-BR')
}

function formatDateTime(value: string | null | undefined) {
  if (!value) {
    return 'Não informado'
  }

  return new Date(value).toLocaleString('pt-BR')
}

function statusTone(status: string | null | undefined) {
  if (['paid', 'authorized', 'active', 'completed', 'approved'].includes(String(status))) {
    return 'tone-good'
  }

  if (['payment_requested', 'pending', 'checkout_created', 'trialing', 'bigshop_benefit'].includes(String(status))) {
    return 'tone-warning'
  }

  return 'tone-muted'
}

function isResolving(access: BillingLinkAccess | null | undefined) {
  if (!access) {
    return false
  }

  return resolvingLinkKey.value === `${access.type}:${access.id}`
}
</script>

<template>
  <section class="billing-portal">
    <div class="billing-hero">
      <div>
        <span class="eyebrow">Conta</span>
        <h1>Plano e cobrança</h1>
        <p>
          Veja plano, cobrança, benefício BigShop e solicitações comerciais em um só lugar, sem depender do painel Admin.
        </p>
      </div>
    </div>

    <div v-if="loading" class="empty-state">Carregando plano e cobrança...</div>
    <div v-else-if="state.error" class="empty-state">{{ state.error }}</div>

    <template v-else-if="data">
      <section class="panel-main billing-spotlight">
        <div class="billing-spotlight-copy">
          <span class="eyebrow">Resumo atual</span>
          <h2>{{ data.plan.plan_name || 'Plano atual' }}</h2>
          <p>
            {{ companyName }} · {{ data.company.company.platform_label }} · {{ data.company.company.commercial_status_label }}
          </p>
        </div>

        <div class="billing-spotlight-badges">
          <span class="billing-pill" :class="statusTone(data.plan.status)">
            {{ data.plan.status_label }}
          </span>
          <span class="billing-pill" :class="statusTone(data.company.company.commercial_status)">
            {{ data.company.company.commercial_status_label }}
          </span>
          <span v-if="bigShopBenefitActive" class="billing-pill tone-warning">
            Benefício BigShop ativo
          </span>
        </div>
      </section>

      <section v-if="bigShopBenefitActive" class="panel-main billing-highlight-panel">
        <strong>{{ data.company.company.bigshop_benefit_label }}</strong>
        <p>
          Mudanças de plataforma e diferenças comerciais passam por revisão do Admin antes de qualquer ajuste financeiro.
        </p>
      </section>

      <div class="billing-summary-grid">
        <article class="panel-main billing-summary-card">
          <small>Plano atual</small>
          <strong>{{ data.plan.plan_name || 'Não identificado' }}</strong>
          <span>{{ data.plan.billing_cycle_label }} · {{ formatMoney(data.plan.amount_cents) }}</span>
        </article>

        <article class="panel-main billing-summary-card">
          <small>Próximo vencimento</small>
          <strong>{{ nextDueLabel }}</strong>
          <span>
            {{ data.plan.auto_renewal_enabled ? 'Renovação automática ativa' : 'Sem renovação automática ativa' }}
          </span>
        </article>

        <article class="panel-main billing-summary-card">
          <small>Plataforma da empresa</small>
          <strong>{{ data.company.company.platform_label }}</strong>
          <span>{{ data.company.merchant_name }}</span>
        </article>

        <article class="panel-main billing-summary-card">
          <small>Status comercial</small>
          <strong>{{ data.company.company.commercial_status_label }}</strong>
          <span>{{ data.company.billing_status_label }}</span>
        </article>
      </div>

      <div class="billing-columns">
        <section class="panel-main billing-links-panel">
          <div class="subsection-heading">
            <h2>Links financeiros</h2>
            <span>{{ data.payment_links.length }} disponível{{ data.payment_links.length === 1 ? '' : 'eis' }}</span>
          </div>

          <p class="billing-inline-note">
            Cada abertura registra usuário, empresa e origem do link para manter a trilha de auditoria.
          </p>

          <div v-if="!data.payment_links.length" class="empty-inline">
            Nenhum link financeiro foi gerado para esta empresa até agora.
          </div>

          <div v-else class="billing-link-list">
            <article v-for="item in data.payment_links" :key="`${item.type}-${item.id}`" class="billing-link-card">
              <div>
                <strong>{{ item.title }}</strong>
                <p>{{ item.description }}</p>
                <small>
                  {{ item.status_label }}
                  <template v-if="item.host"> · {{ item.host }}</template>
                  <template v-if="item.expires_at"> · vence em {{ formatDate(item.expires_at) }}</template>
                </small>
              </div>
              <button
                class="btn btn-secondary btn-compact"
                type="button"
                :disabled="isResolving(item.access)"
                @click="openPaymentLink(item)"
              >
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                {{ isResolving(item.access) ? 'Abrindo...' : 'Abrir' }}
              </button>
            </article>
          </div>
        </section>

        <section class="panel-main billing-controls-panel">
          <div class="subsection-heading">
            <h2>Configuração financeira</h2>
            <span>Autonomia com controle</span>
          </div>

          <div class="billing-detail-grid">
            <span>
              <small>Operadora</small>
              <strong>{{ data.plan.provider_label || 'Não informada' }}</strong>
            </span>
            <span>
              <small>Forma principal</small>
              <strong>{{ data.plan.payment_method_label || 'Não informada' }}</strong>
            </span>
            <span>
              <small>Início do plano</small>
              <strong>{{ formatDate(data.plan.started_at) }}</strong>
            </span>
            <span>
              <small>Status do plano</small>
              <strong>{{ data.plan.status_label }}</strong>
            </span>
          </div>

          <div v-if="data.subscription" class="billing-toggle-box">
            <label class="subtle-toggle">
              <input
                type="checkbox"
                :checked="data.subscription.auto_renewal_enabled"
                :disabled="state.savingAutoRenewal || !data.subscription.auto_renewal_enabled"
                @change="updateAutoRenewal"
              />
              <span>Renovação automática</span>
            </label>
            <small>
              Você pode desligar futuras renovações por aqui. Reativação, mudança de plano e ajustes comerciais seguem com o Admin.
            </small>
          </div>

          <small v-if="state.billingSaved" class="billing-note ok">{{ state.billingSaved }}</small>
          <small v-else-if="state.billingError" class="billing-note warning">{{ state.billingError }}</small>
          <small v-else-if="data.plan.cancel_requested_at" class="billing-note">Renovação futura já está desativada.</small>

          <div class="billing-governance-note">
            <strong>Controle financeiro crítico</strong>
            <p>{{ data.actions.financial_changes_note }}</p>
          </div>
        </section>
      </div>

      <section class="panel-main billing-history-panel">
        <div class="subsection-heading">
          <h2>Solicitações comerciais</h2>
          <span>{{ data.commercial_requests.length }} registrada{{ data.commercial_requests.length === 1 ? '' : 's' }}</span>
        </div>

        <div v-if="!data.commercial_requests.length" class="empty-inline">
          Nenhuma solicitação comercial foi registrada para esta empresa.
        </div>

        <div v-else class="billing-request-list">
          <article v-for="request in data.commercial_requests" :key="request.id" class="billing-request-card">
            <div class="billing-request-head">
              <div>
                <strong>{{ request.from_platform_label }} → {{ request.to_platform_label }}</strong>
                <p>{{ request.financial_summary?.short_text || 'Solicitação comercial registrada para revisão do Admin.' }}</p>
              </div>
              <span class="billing-pill" :class="statusTone(request.status)">
                {{ request.status_label }}
              </span>
            </div>

            <div class="billing-request-meta">
              <span>
                <small>Solicitada em</small>
                <strong>{{ formatDateTime(request.requested_at) }}</strong>
              </span>
              <span>
                <small>Resolvida em</small>
                <strong>{{ formatDateTime(request.resolved_at) }}</strong>
              </span>
              <span>
                <small>Link comercial</small>
                <strong>{{ request.payment_link_host || 'Ainda não enviado' }}</strong>
              </span>
            </div>

            <button
              v-if="request.payment_link_available"
              class="btn btn-secondary btn-compact"
              type="button"
              :disabled="isResolving(request.payment_link_access)"
              @click="openPaymentLink(request)"
            >
              <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
              {{ isResolving(request.payment_link_access) ? 'Abrindo...' : 'Abrir link comercial' }}
            </button>

            <ul v-if="request.history.length" class="billing-history-list">
              <li v-for="event in request.history" :key="event.id">
                <strong>{{ event.label }}</strong>
                <small>{{ formatDateTime(event.occurred_at) }}<template v-if="event.actor_name"> · {{ event.actor_name }}</template></small>
              </li>
            </ul>
          </article>
        </div>
      </section>
    </template>
  </section>
</template>
