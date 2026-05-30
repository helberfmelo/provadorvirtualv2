<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
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
  { value: 'xml_feed', label: 'XML/feed' },
  { value: 'api', label: 'API' },
  { value: 'custom', label: 'Personalizada' },
]
type BillingSubscription = {
  id: number
  status: string
  billing_cycle: string
  auto_renewal_enabled: boolean
  amount_cents: number
  next_charge_at: string | null
  cancel_requested_at: string | null
}
type CoverageSummary = {
  total_products: number
  covered_products: number
  active_products: number
  pending_products: number
  inactive_products: number
  without_measurement_table: number
  without_modeling: number
  without_category: number
  sync_errors: number
  installation_not_validated: number
  coverage_rate: number
}
type NextAction = {
  key: string
  title: string
  description: string
  to: string
  priority: 'high' | 'medium' | 'low'
}
type CoverageTrend = {
  available: boolean
  period_days: number
  message: string | null
  series: Array<{
    date: string
    covered_products: number
    total_products: number
    coverage_rate: number
  }>
}
const summary = reactive({
  products: 0,
  measurement_tables: 0,
  widget_status: 'demo-ready',
  widget_active: false,
  integrations_configured: 0,
  recommendations_today: 0,
})
const coverage = reactive<CoverageSummary>({
  total_products: 0,
  covered_products: 0,
  active_products: 0,
  pending_products: 0,
  inactive_products: 0,
  without_measurement_table: 0,
  without_modeling: 0,
  without_category: 0,
  sync_errors: 0,
  installation_not_validated: 0,
  coverage_rate: 0,
})
const nextActions = ref<NextAction[]>([])
const coverageTrend = reactive<CoverageTrend>({
  available: false,
  period_days: 7,
  message: null,
  series: [],
})
const billing = reactive({
  loading: false,
  saving: false,
  error: '',
  saved: '',
  subscription: null as BillingSubscription | null,
})
const companyProfile = reactive({
  name: '',
  legal_name: '',
  domain: '',
  platform: 'bigshop',
  zip_code: '',
  street: '',
  number: '',
  complement: '',
  district: '',
  city: '',
  state: '',
})
const profileState = reactive({
  saving: false,
  cepLoading: false,
  error: '',
  saved: '',
})
const showCompanyProfile = computed(() => Boolean(auth.activeCompany && !auth.activeCompany.profile_completed))
const activeCompanyDocument = computed(() => auth.activeCompany?.document || '')
const coverageRateLabel = computed(() => `${Math.round(coverage.coverage_rate || 0)}%`)
const coverageRingStyle = computed(() => ({
  background: `conic-gradient(#ff4d5e 0 ${coverage.coverage_rate || 0}%, #e5e7eb ${coverage.coverage_rate || 0}% 100%)`,
}))
const coverageCards = computed(() => [
  {
    key: 'total',
    label: 'Produtos totais',
    value: coverage.total_products,
    hint: 'Catálogo monitorado',
    icon: 'fa-boxes-stacked',
    to: '/app/produtos',
    tone: 'neutral',
  },
  {
    key: 'covered',
    label: 'Cobertos',
    value: coverage.covered_products,
    hint: 'Ativos com tabela, categoria e modelagem',
    icon: 'fa-circle-check',
    to: '/app/produtos?filtro=prontos',
    tone: 'good',
  },
  {
    key: 'active',
    label: 'Ativos',
    value: coverage.active_products,
    hint: 'Produtos publicados no catálogo',
    icon: 'fa-toggle-on',
    to: '/app/produtos?status=active',
    tone: 'neutral',
  },
  {
    key: 'pending',
    label: 'Pendentes',
    value: coverage.pending_products,
    hint: 'Precisam de revisão antes da publicação',
    icon: 'fa-triangle-exclamation',
    to: '/app/produtos?filtro=pendentes',
    tone: coverage.pending_products > 0 ? 'warning' : 'good',
  },
  {
    key: 'without_table',
    label: 'Sem tabela',
    value: coverage.without_measurement_table,
    hint: 'Vincule tabela em lote',
    icon: 'fa-ruler-combined',
    to: '/app/produtos?filtro=sem_tabela',
    tone: coverage.without_measurement_table > 0 ? 'warning' : 'good',
  },
  {
    key: 'without_modeling',
    label: 'Sem modelagem',
    value: coverage.without_modeling,
    hint: 'Complete o caimento',
    icon: 'fa-sliders',
    to: '/app/produtos?filtro=sem_modelagem',
    tone: coverage.without_modeling > 0 ? 'warning' : 'good',
  },
  {
    key: 'without_category',
    label: 'Sem categoria',
    value: coverage.without_category,
    hint: 'Base para regras e relatórios',
    icon: 'fa-tags',
    to: '/app/produtos?filtro=sem_categoria',
    tone: coverage.without_category > 0 ? 'warning' : 'good',
  },
  {
    key: 'sync_errors',
    label: 'Erro de sync',
    value: coverage.sync_errors,
    hint: 'Corrija a origem antes de importar',
    icon: 'fa-rotate',
    to: '/app/produtos?filtro=erro_sync',
    tone: coverage.sync_errors > 0 ? 'danger' : 'good',
  },
  {
    key: 'install',
    label: 'Instalação pendente',
    value: coverage.installation_not_validated,
    hint: 'Produtos ativos afetados',
    icon: 'fa-code',
    to: '/app/go-live',
    tone: coverage.installation_not_validated > 0 ? 'warning' : 'good',
  },
])
const trendBars = computed(() => coverageTrend.series.map((point) => ({
  ...point,
  height: `${Math.max(8, Math.round(point.coverage_rate || 0))}%`,
  label: new Date(`${point.date}T00:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }),
})))

onMounted(() => {
  auth.loadMe()
    .then(syncCompanyProfile)
    .catch(() => syncCompanyProfile())
  loadOverview()
  loadBillingSubscription()
})

async function loadOverview() {
  try {
    const { data } = await api.get('/merchant/overview')
    Object.assign(summary, data.summary)
    Object.assign(coverage, data.coverage || {})
    nextActions.value = data.next_actions || []
    Object.assign(coverageTrend, data.coverage_trend || {})
  } catch {
    // O painel preserva os atalhos básicos se o agregado ainda não estiver disponível.
  }
}

function syncCompanyProfile() {
  const company = auth.activeCompany
  if (!company) {
    return
  }

  Object.assign(companyProfile, {
    name: isPlaceholderCompanyName(company.name) ? '' : company.name || '',
    legal_name: company.legal_name || '',
    domain: company.domain || '',
    platform: company.platform || 'bigshop',
    zip_code: company.zip_code || '',
    street: company.street || '',
    number: company.number || '',
    complement: company.complement || '',
    district: company.district || '',
    city: company.city || '',
    state: company.state || '',
  })
}

async function lookupCompanyCep() {
  const cep = companyProfile.zip_code.replace(/\D+/g, '')
  if (cep.length !== 8) {
    return
  }

  profileState.cepLoading = true

  try {
    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`)
    const data = await response.json()
    if (data?.erro) {
      return
    }

    companyProfile.street = data.logradouro || companyProfile.street
    companyProfile.district = data.bairro || companyProfile.district
    companyProfile.city = data.localidade || companyProfile.city
    companyProfile.state = data.uf || companyProfile.state
  } finally {
    profileState.cepLoading = false
  }
}

async function saveCompanyProfile() {
  profileState.saving = true
  profileState.error = ''
  profileState.saved = ''

  try {
    await api.patch('/merchant/company-profile', companyProfile)
    await auth.loadMe()
    syncCompanyProfile()
    profileState.saved = 'Dados da empresa salvos.'
  } catch (requestError: any) {
    profileState.error = requestError.response?.data?.message || 'Não foi possível salvar os dados da empresa.'
  } finally {
    profileState.saving = false
  }
}

async function loadBillingSubscription() {
  billing.loading = true
  billing.error = ''

  try {
    const { data } = await api.get('/billing/subscription')
    billing.subscription = data.data?.subscription || data.data || null
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

function isPlaceholderCompanyName(name: string | undefined) {
  return Boolean(name?.startsWith('Empresa CNPJ '))
}

function priorityLabel(priority: NextAction['priority']) {
  return {
    high: 'Alta',
    medium: 'Média',
    low: 'Baixa',
  }[priority]
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

    <section v-if="showCompanyProfile" class="panel-main admin-form company-profile-panel">
      <div class="subsection-heading">
        <h2>Dados da empresa</h2>
        <span>Primeiro acesso</span>
      </div>

      <p v-if="profileState.error" class="form-error">{{ profileState.error }}</p>
      <p v-if="profileState.saved" class="form-success">{{ profileState.saved }}</p>

      <form class="admin-form" @submit.prevent="saveCompanyProfile">
        <div class="company-profile-grid">
          <label class="company-field-name">
            Empresa
            <input v-model="companyProfile.name" required />
          </label>
          <label class="company-field-legal">
            Razão social
            <input v-model="companyProfile.legal_name" required />
          </label>
          <label class="company-field-document">
            CNPJ
            <input :value="activeCompanyDocument" disabled />
          </label>
          <label class="company-field-platform">
            Plataforma
            <select v-model="companyProfile.platform" required>
              <option v-for="platform in platformOptions" :key="platform.value" :value="platform.value">
                {{ platform.label }}
              </option>
            </select>
          </label>
          <label class="company-field-domain">
            Domínio
            <input v-model="companyProfile.domain" placeholder="loja.com.br" required />
          </label>
          <label class="company-field-zip">
            CEP
            <input v-model="companyProfile.zip_code" inputmode="numeric" required @blur="lookupCompanyCep" />
            <small>{{ profileState.cepLoading ? 'Buscando endereço...' : ' ' }}</small>
          </label>
          <label class="company-field-street">
            Rua
            <input v-model="companyProfile.street" required />
          </label>
          <label class="company-field-number">
            Número
            <input v-model="companyProfile.number" required />
          </label>
          <label class="company-field-complement">
            Complemento
            <input v-model="companyProfile.complement" />
          </label>
          <label class="company-field-district">
            Bairro
            <input v-model="companyProfile.district" required />
          </label>
          <label class="company-field-city">
            Cidade
            <input v-model="companyProfile.city" required />
          </label>
          <label class="company-field-state">
            UF
            <input v-model="companyProfile.state" maxlength="2" required />
          </label>
        </div>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="profileState.saving">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            {{ profileState.saving ? 'Salvando...' : 'Salvar dados da empresa' }}
          </button>
        </div>
      </form>
    </section>

    <div class="overview-command-grid">
      <section class="panel-main coverage-panel" aria-label="Cobertura do catálogo">
        <div class="coverage-hero">
          <div class="coverage-ring" :style="coverageRingStyle">
            <span>
              <strong>{{ coverageRateLabel }}</strong>
              <small>cobertura</small>
            </span>
          </div>
          <div>
            <span class="eyebrow">Prontidão operacional</span>
            <h2>Cobertura do catálogo</h2>
            <p>
              Produtos cobertos têm status ativo, tabela vinculada, categoria, modelagem e nenhum erro de sincronização.
            </p>
          </div>
        </div>

        <div class="coverage-metrics-grid">
          <RouterLink
            v-for="card in coverageCards"
            :key="card.key"
            class="coverage-metric-card"
            :class="`tone-${card.tone}`"
            :to="card.to"
          >
            <i class="fa-solid" :class="card.icon" aria-hidden="true"></i>
            <span>
              <strong>{{ card.value }}</strong>
              <small>{{ card.label }}</small>
            </span>
            <em>{{ card.hint }}</em>
          </RouterLink>
        </div>
      </section>

      <section class="panel-main next-actions-panel" aria-label="Próximas ações">
        <div class="subsection-heading">
          <h2>Próximas ações</h2>
          <span>{{ nextActions.length }} prioridade{{ nextActions.length === 1 ? '' : 's' }}</span>
        </div>
        <div class="next-action-list">
          <RouterLink
            v-for="action in nextActions"
            :key="action.key"
            class="next-action-row"
            :class="`priority-${action.priority}`"
            :to="action.to"
          >
            <span>
              <strong>{{ action.title }}</strong>
              <small>{{ action.description }}</small>
            </span>
            <em>{{ priorityLabel(action.priority) }}</em>
          </RouterLink>
        </div>

        <div class="trend-strip">
          <div class="subsection-heading compact-heading">
            <h3>Evolução de cobertura</h3>
            <span>{{ coverageTrend.period_days }} dias</span>
          </div>
          <div v-if="coverageTrend.available" class="trend-bars" aria-label="Evolução dos últimos dias">
            <span v-for="point in trendBars" :key="point.date">
              <i :style="{ height: point.height }"></i>
              <small>{{ point.label }}</small>
            </span>
          </div>
          <p v-else>{{ coverageTrend.message || 'A evolução aparece quando houver histórico suficiente.' }}</p>
        </div>
      </section>
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

      <RouterLink class="btn btn-secondary btn-compact" to="/app/plano-e-cobranca">
        <i class="fa-solid fa-wallet" aria-hidden="true"></i>
        Ver plano e cobrança
      </RouterLink>

      <small v-if="billing.saved" class="billing-note ok">{{ billing.saved }}</small>
      <small v-else-if="billing.error" class="billing-note warning">{{ billing.error }}</small>
      <small v-else-if="billing.subscription.cancel_requested_at" class="billing-note">Renovação futura desativada.</small>
    </section>
  </section>
</template>
