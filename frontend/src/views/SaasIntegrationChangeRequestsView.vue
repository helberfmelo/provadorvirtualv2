<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { CompanyRow, IntegrationChangeRequest } from '../services/saasTypes'

const loading = ref(false)
const savingId = ref<number | null>(null)
const error = ref('')
const requests = ref<IntegrationChangeRequest[]>([])
const companies = ref<CompanyRow[]>([])
const filters = reactive({
  status: '',
  company_id: '',
})
const forms = reactive<Record<number, {
  status: string
  payment_link: string
  admin_notes: string
  apply_change: boolean
}>>({})

const statusOptions = [
  { value: '', label: 'Todas' },
  { value: 'pending', label: 'Pendentes' },
  { value: 'payment_requested', label: 'Pagamento' },
  { value: 'approved', label: 'Aprovadas' },
  { value: 'completed', label: 'Concluídas' },
  { value: 'cancelled', label: 'Canceladas' },
]
const editableStatusOptions = statusOptions.filter((option) => option.value)
const openRequests = computed(() => requests.value.filter((request) => ['pending', 'payment_requested', 'approved'].includes(request.status)).length)
const completedRequests = computed(() => requests.value.filter((request) => request.status === 'completed').length)

onMounted(() => {
  loadData()
})

async function loadData() {
  loading.value = true
  error.value = ''

  try {
    const params = {
      status: filters.status || undefined,
      company_id: filters.company_id || undefined,
    }
    const [requestsResponse, companiesResponse] = await Promise.all([
      api.get('/saas/integration-change-requests', { params }),
      api.get('/saas/companies'),
    ])
    requests.value = requestsResponse.data.data || []
    companies.value = companiesResponse.data.data || []
    resetForms()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar solicitações de troca.'
  } finally {
    loading.value = false
  }
}

function resetForms() {
  for (const key of Object.keys(forms)) {
    delete forms[Number(key)]
  }

  for (const request of requests.value) {
    forms[request.id] = {
      status: request.status,
      payment_link: request.payment_link || '',
      admin_notes: request.admin_notes || '',
      apply_change: false,
    }
  }
}

async function saveRequest(request: IntegrationChangeRequest) {
  const form = forms[request.id]

  if (!form) {
    return
  }

  savingId.value = request.id
  error.value = ''

  try {
    const { data } = await api.patch(`/saas/integration-change-requests/${request.id}`, {
      status: form.status,
      payment_link: form.payment_link || null,
      admin_notes: form.admin_notes || null,
      apply_change: form.apply_change,
    })
    const updated = data.data as IntegrationChangeRequest
    const index = requests.value.findIndex((item) => item.id === updated.id)

    if (index >= 0) {
      requests.value.splice(index, 1, updated)
    }

    resetForms()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar a solicitação.'
  } finally {
    savingId.value = null
  }
}

function money(cents: number | undefined) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format((cents || 0) / 100)
}

function dateTime(value: string | null | undefined) {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function historyTail(event: IntegrationChangeRequest['history'][number]) {
  const value = event.metadata.status_to || event.metadata.to_platform || event.severity

  return String(value || '-')
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Trocas BigShop</h1>
        <p>Fila operacional para revisar benefício, pagamento, aceite e aplicação de nova integração.</p>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadData">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <div class="metric-grid">
      <article class="metric-card">
        <i class="fa-solid fa-inbox" aria-hidden="true"></i>
        <strong>{{ requests.length }}</strong>
        <span>solicitações carregadas</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
        <strong>{{ openRequests }}</strong>
        <span>em revisão ou pagamento</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <strong>{{ completedRequests }}</strong>
        <span>trocas concluídas</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-building" aria-hidden="true"></i>
        <strong>{{ companies.length }}</strong>
        <span>empresas disponíveis no filtro</span>
      </article>
    </div>

    <section class="panel-main subsection bigshop-change-filters">
      <div class="subsection-heading">
        <h2>Filtros</h2>
        <span>{{ loading ? 'Carregando' : 'Pronto' }}</span>
      </div>
      <div class="sync-filters">
        <label>
          Status
          <select v-model="filters.status" @change="loadData">
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </label>
        <label>
          Empresa
          <select v-model="filters.company_id" @change="loadData">
            <option value="">Todas</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">
              {{ company.name }} · {{ company.access_code }}
            </option>
          </select>
        </label>
      </div>
    </section>

    <div v-if="loading" class="empty-state">Carregando solicitações...</div>
    <div v-else-if="!requests.length" class="empty-state">Nenhuma solicitação encontrada para os filtros atuais.</div>

    <section v-else class="bigshop-change-board">
      <article v-for="request in requests" :key="request.id" class="panel-main bigshop-change-request-card">
        <header>
          <span>
            <strong>{{ request.company.name }}</strong>
            <small>{{ request.from_platform_label }} para {{ request.to_platform_label }} · {{ request.merchant.name }}</small>
          </span>
          <em class="status-pill warning">{{ request.status_label }}</em>
        </header>

        <div class="bigshop-change-request-grid">
          <span>
            <small>Solicitado por</small>
            <strong>{{ request.user.name || request.user.email || 'usuário do portal' }}</strong>
          </span>
          <span>
            <small>Aceite</small>
            <strong>{{ request.terms_version || '-' }}</strong>
          </span>
          <span>
            <small>Solicitação</small>
            <strong>{{ dateTime(request.requested_at) }}</strong>
          </span>
          <span>
            <small>Conclusão</small>
            <strong>{{ dateTime(request.resolved_at) }}</strong>
          </span>
        </div>

        <div v-if="request.financial_summary" class="bigshop-change-money">
          <span>
            <small>BigShop anual</small>
            <strong>{{ money(request.financial_summary.annual_from_monthly_cents) }}/mês</strong>
          </span>
          <span>
            <small>Nova plataforma anual</small>
            <strong>{{ money(request.financial_summary.annual_to_monthly_cents) }}/mês</strong>
          </span>
          <span>
            <small>Diferença estimada</small>
            <strong>{{ money(request.financial_summary.annual_monthly_difference_cents) }}/mês</strong>
          </span>
          <span>
            <small>Total anual estimado</small>
            <strong>{{ money(request.financial_summary.annual_total_difference_cents) }}</strong>
          </span>
        </div>

        <div class="form-grid bigshop-change-edit-grid">
          <label>
            Status
            <select v-model="forms[request.id].status">
              <option v-for="option in editableStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>
          <label>
            Link de pagamento
            <input v-model="forms[request.id].payment_link" type="url" placeholder="https://..." />
          </label>
        </div>

        <label class="bigshop-change-notes">
          Observações internas
          <textarea v-model="forms[request.id].admin_notes" rows="3"></textarea>
        </label>

        <div class="bigshop-change-history">
          <h2>Histórico</h2>
          <div v-if="!request.history.length" class="empty-inline">Sem eventos de auditoria.</div>
          <ol v-else>
            <li v-for="event in request.history" :key="event.id">
              <span>
                <strong>{{ event.label }}</strong>
                <small>{{ event.actor_name || 'sistema' }} · {{ dateTime(event.occurred_at) }}</small>
              </span>
              <em>{{ historyTail(event) }}</em>
            </li>
          </ol>
        </div>

        <div class="action-row compact">
          <RouterLink class="btn btn-secondary" :to="`/saas/empresas/${request.company.id}/editar`">
            <i class="fa-solid fa-building" aria-hidden="true"></i>
            Abrir empresa
          </RouterLink>
          <label class="settings-check">
            <input
              v-model="forms[request.id].apply_change"
              type="checkbox"
              :disabled="forms[request.id].status !== 'completed'"
            />
            <span>Aplicar troca ao concluir</span>
          </label>
          <button class="btn btn-primary" type="button" :disabled="savingId === request.id" @click="saveRequest(request)">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            {{ savingId === request.id ? 'Salvando...' : 'Salvar' }}
          </button>
        </div>
      </article>
    </section>
  </section>
</template>
