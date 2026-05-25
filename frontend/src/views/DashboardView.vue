<script setup lang="ts">
import { onMounted, reactive } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
type BillingSubscription = {
  id: number
  status: string
  billing_cycle: string
  auto_renewal_enabled: boolean
  amount_cents: number
  next_charge_at: string | null
  cancel_requested_at: string | null
}
const summary = reactive({
  products: 0,
  measurement_tables: 0,
  widget_status: 'demo-ready',
  widget_active: false,
  integrations_configured: 0,
  recommendations_today: 0,
})
const billing = reactive({
  loading: false,
  saving: false,
  error: '',
  saved: '',
  subscription: null as BillingSubscription | null,
})

onMounted(() => {
  auth.loadMe().catch(() => undefined)
  api.get('/merchant/overview')
    .then(({ data }) => Object.assign(summary, data.summary))
    .catch(() => undefined)
  loadBillingSubscription()
})

async function loadBillingSubscription() {
  billing.loading = true
  billing.error = ''

  try {
    const { data } = await api.get('/billing/subscription')
    billing.subscription = data.data
  } catch {
    billing.subscription = null
  } finally {
    billing.loading = false
  }
}

async function updateAutoRenewal(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.checked) {
    input.checked = false
    billing.saved = ''
    billing.error = 'A reativação automática deve ser solicitada ao suporte.'
    return
  }

  billing.saving = true
  billing.error = ''
  billing.saved = ''

  try {
    const { data } = await api.patch('/billing/subscription/auto-renewal', {
      auto_renewal_enabled: false,
    })
    billing.subscription = data.data
    billing.saved = 'Renovação futura desativada.'
  } catch (requestError: any) {
    input.checked = true
    billing.error = requestError.response?.data?.message || 'Não foi possível alterar a renovação.'
  } finally {
    billing.saving = false
  }
}

function price(cents: number) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format((cents || 0) / 100)
}
</script>

<template>
  <section class="dashboard">
    <div>
      <span class="eyebrow">Painel</span>
      <h1>Sua loja pronta para configurar</h1>
      <p>
        Cadastre produtos, vincule tabelas, publique o provador e acompanhe as conexões em um só lugar.
      </p>
    </div>

    <div class="metric-grid">
      <article class="metric-card">
        <i class="fa-solid fa-box-open" aria-hidden="true"></i>
        <strong>{{ summary.products }} produto{{ summary.products === 1 ? '' : 's' }}</strong>
        <span>Cadastre e vincule tabelas aos produtos que vão usar o provador.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
        <strong>{{ summary.measurement_tables }} tabela{{ summary.measurement_tables === 1 ? '' : 's' }}</strong>
        <span>Organize faixas por tamanho, produto e modelagem.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-plug" aria-hidden="true"></i>
        <strong>{{ summary.widget_active ? 'Provador ativo' : 'Provador pendente' }}</strong>
        <span>{{ summary.recommendations_today }} recomendações registradas hoje.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-link" aria-hidden="true"></i>
        <strong>{{ summary.integrations_configured }} integração{{ summary.integrations_configured === 1 ? '' : 'es' }}</strong>
        <span>BigShop, lojas externas e instalações manuais.</span>
      </article>
    </div>

    <div class="onboarding-grid">
      <RouterLink class="onboarding-step" to="/app/produtos">
        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
        <span>
          <strong>Produtos</strong>
          <small>{{ summary.products }} cadastrados</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/tabelas-de-medidas">
        <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
        <span>
          <strong>Tabelas</strong>
          <small>{{ summary.measurement_tables }} disponíveis</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/assistente">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        <span>
          <strong>Assistente</strong>
          <small>Texto e imagem</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/widget">
        <i class="fa-solid fa-code" aria-hidden="true"></i>
        <span>
          <strong>Provador</strong>
          <small>{{ summary.widget_active ? 'ativo' : 'pendente' }}</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/analytics">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        <span>
          <strong>Analytics</strong>
          <small>{{ summary.recommendations_today }} hoje</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/go-live">
        <i class="fa-solid fa-rocket" aria-hidden="true"></i>
        <span>
          <strong>Go-live</strong>
          <small>Checklist final</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/importacoes">
        <i class="fa-solid fa-file-import" aria-hidden="true"></i>
        <span>
          <strong>Importações</strong>
          <small>CSV e XML</small>
        </span>
      </RouterLink>
      <RouterLink class="onboarding-step" to="/app/integracoes">
        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
        <span>
          <strong>Integrações</strong>
          <small>{{ summary.integrations_configured }} configuradas</small>
        </span>
      </RouterLink>
    </div>

    <div class="action-row">
      <RouterLink class="btn btn-primary" to="/app/produtos">
        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
        Produtos
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/tabelas-de-medidas">
        <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
        Tabelas de medidas
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/assistente">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        Assistente
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/analytics">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        Analytics
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/go-live">
        <i class="fa-solid fa-rocket" aria-hidden="true"></i>
        Go-live
      </RouterLink>
      <RouterLink class="btn btn-secondary" to="/app/widget">
        <i class="fa-solid fa-code" aria-hidden="true"></i>
        Provador
      </RouterLink>
    </div>

    <section v-if="billing.subscription" class="billing-preferences">
      <div>
        <strong>Preferências do plano</strong>
        <small>
          {{ price(billing.subscription.amount_cents) }}/mês
          <template v-if="billing.subscription.next_charge_at">
            - próxima renovação {{ new Date(billing.subscription.next_charge_at).toLocaleDateString('pt-BR') }}
          </template>
        </small>
      </div>

      <label class="subtle-toggle">
        <input
          type="checkbox"
          :checked="billing.subscription.auto_renewal_enabled"
          :disabled="billing.saving || !billing.subscription.auto_renewal_enabled"
          @change="updateAutoRenewal"
        />
        <span>Renovação automática</span>
      </label>

      <small v-if="billing.saved" class="billing-note ok">{{ billing.saved }}</small>
      <small v-else-if="billing.error" class="billing-note warning">{{ billing.error }}</small>
      <small v-else-if="billing.subscription.cancel_requested_at" class="billing-note">Renovação futura desativada.</small>
    </section>
  </section>
</template>
