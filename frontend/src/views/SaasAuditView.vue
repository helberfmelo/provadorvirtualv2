<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import type { AuditLogRow, LegalAcceptanceRow, SaasAuditPayload } from '../services/saasTypes'

const loading = ref(false)
const exporting = ref(false)
const error = ref('')
const payload = ref<SaasAuditPayload | null>(null)
const filters = reactive({
  merchant_company_id: '',
  category: '',
  module: '',
  event: '',
  document_type: '',
  date_from: '',
  date_to: '',
})

const summary = computed(() => payload.value?.summary ?? {
  logs: 0,
  critical_logs: 0,
  acceptances: 0,
  companies: 0,
})
const logs = computed<AuditLogRow[]>(() => payload.value?.logs ?? [])
const acceptances = computed<LegalAcceptanceRow[]>(() => payload.value?.acceptances ?? [])
const filterOptions = computed(() => payload.value?.filters ?? {
  companies: [],
  categories: [],
  modules: [],
  events: [],
  document_types: [],
})

onMounted(() => {
  loadAudit()
})

async function loadAudit() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/audit-logs', { params: requestParams() })
    payload.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar a auditoria.'
  } finally {
    loading.value = false
  }
}

async function exportAudit() {
  exporting.value = true
  error.value = ''

  try {
    const response = await api.get('/saas/audit-logs/export', {
      params: requestParams(),
      responseType: 'blob',
    })
    const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `auditoria-saas-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.csv`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível exportar a auditoria.'
  } finally {
    exporting.value = false
  }
}

function requestParams() {
  return {
    merchant_company_id: filters.merchant_company_id || undefined,
    category: filters.category || undefined,
    module: filters.module || undefined,
    event: filters.event || undefined,
    document_type: filters.document_type || undefined,
    date_from: filters.date_from || undefined,
    date_to: filters.date_to || undefined,
  }
}

function dateTime(value: string | null) {
  return value ? new Date(value).toLocaleString('pt-BR') : '-'
}

function summarize(value: Record<string, unknown> | null) {
  if (!value) {
    return '-'
  }

  return Object.entries(value)
    .slice(0, 4)
    .map(([key, entryValue]) => `${labelFor(key)}: ${formatValue(entryValue)}`)
    .join(' | ')
}

function formatContext(value: Record<string, unknown>) {
  const entries = Object.entries(value || {})

  if (!entries.length) {
    return '-'
  }

  return entries
    .slice(0, 5)
    .map(([key, entryValue]) => `${labelFor(key)}: ${formatValue(entryValue)}`)
    .join(' | ')
}

function labelFor(key: string) {
  return {
    platform: 'Plataforma',
    status: 'Status',
    is_active: 'Ativo',
    button_style: 'Botão',
    allowed_domains_count: 'Domínios',
    measurement_table_id: 'Tabela',
    measurement_table_name: 'Nome da tabela',
    rows_count: 'Linhas',
    imported_rows: 'Linhas importadas',
    source_format: 'Formato',
    secret_rotation_count: 'Segredos trocados',
    context: 'Origem',
    document_type: 'Documento',
    terms_version: 'Versão',
    accepted_at: 'Aceito em',
  }[key] || key
}

function formatValue(value: unknown): string {
  if (Array.isArray(value)) {
    return value.join(', ')
  }

  if (value && typeof value === 'object') {
    return Object.entries(value as Record<string, unknown>)
      .slice(0, 2)
      .map(([key, nestedValue]) => `${labelFor(key)}=${String(nestedValue)}`)
      .join(', ')
  }

  if (typeof value === 'boolean') {
    return value ? 'Sim' : 'Não'
  }

  return value == null || value === '' ? '-' : String(value)
}

function severityClass(severity: string) {
  return {
    ok: severity === 'info',
    warning: ['warning', 'error'].includes(severity),
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Auditoria</h1>
        <p>Central de rastreabilidade por empresa, com aceites de termos, ações críticas e exportação.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadAudit">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <button class="btn btn-primary" type="button" :disabled="exporting" @click="exportAudit">
          <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
          {{ exporting ? 'Exportando...' : 'Exportar CSV' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <div class="metric-grid">
      <article class="metric-card">
        <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
        <strong>{{ summary.logs }}</strong>
        <span>eventos carregados</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <strong>{{ summary.critical_logs }}</strong>
        <span>eventos críticos</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
        <strong>{{ summary.acceptances }}</strong>
        <span>aceites centralizados</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-building" aria-hidden="true"></i>
        <strong>{{ summary.companies }}</strong>
        <span>empresas no SaaS</span>
      </article>
    </div>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Filtros</h2>
        <span>{{ loading ? 'Carregando' : 'Pronto' }}</span>
      </div>
      <div class="sync-filters">
        <label>
          Empresa
          <select v-model="filters.merchant_company_id">
            <option value="">Todas</option>
            <option v-for="company in filterOptions.companies" :key="company.id" :value="company.id">
              {{ company.name }} · {{ company.access_code || '-' }}
            </option>
          </select>
        </label>
        <label>
          Categoria
          <select v-model="filters.category">
            <option value="">Todas</option>
            <option v-for="category in filterOptions.categories" :key="category" :value="category">
              {{ category }}
            </option>
          </select>
        </label>
        <label>
          Módulo
          <select v-model="filters.module">
            <option value="">Todos</option>
            <option v-for="module in filterOptions.modules" :key="module" :value="module">
              {{ module }}
            </option>
          </select>
        </label>
        <label>
          Evento
          <select v-model="filters.event">
            <option value="">Todos</option>
            <option v-for="event in filterOptions.events" :key="event" :value="event">
              {{ event }}
            </option>
          </select>
        </label>
        <label>
          Documento
          <select v-model="filters.document_type">
            <option value="">Todos</option>
            <option v-for="documentType in filterOptions.document_types" :key="documentType" :value="documentType">
              {{ documentType }}
            </option>
          </select>
        </label>
        <label>
          De
          <input v-model="filters.date_from" type="date" />
        </label>
        <label>
          Até
          <input v-model="filters.date_to" type="date" />
        </label>
      </div>
      <div class="action-row compact">
        <button class="btn btn-primary" type="button" :disabled="loading" @click="loadAudit">
          Aplicar filtros
        </button>
      </div>
    </section>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Ações críticas</h2>
        <span>{{ logs.length }} registros</span>
      </div>
      <div class="table-wrap saas-audit-table">
        <table>
          <thead>
            <tr>
              <th>Quando</th>
              <th>Empresa</th>
              <th>Evento</th>
              <th>Ator</th>
              <th>Antes</th>
              <th>Depois</th>
              <th>Contexto</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!logs.length">
              <td colspan="7">Nenhum evento para os filtros atuais.</td>
            </tr>
            <tr v-for="log in logs" :key="log.id">
              <td>
                <strong>{{ dateTime(log.created_at) }}</strong>
                <small>{{ log.module }}</small>
              </td>
              <td>
                <strong>{{ log.company?.name || 'Sem empresa' }}</strong>
                <small>{{ log.company?.access_code || '-' }}</small>
              </td>
              <td>
                <strong>{{ log.event_label }}</strong>
                <small>{{ log.event }}</small>
              </td>
              <td>
                <strong>{{ log.user?.name || 'Sistema' }}</strong>
                <small>{{ log.user?.email || '-' }}</small>
              </td>
              <td class="saas-audit-cell">{{ summarize(log.before) }}</td>
              <td class="saas-audit-cell">{{ summarize(log.after) }}</td>
              <td>
                <span class="status-pill" :class="severityClass(log.severity)">
                  {{ log.severity }}
                </span>
                <small>{{ formatContext(log.context) }}</small>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Aceites de termos</h2>
        <span>{{ acceptances.length }} registros</span>
      </div>
      <div class="table-wrap saas-audit-table">
        <table>
          <thead>
            <tr>
              <th>Quando</th>
              <th>Empresa</th>
              <th>Documento</th>
              <th>Origem</th>
              <th>Usuário</th>
              <th>IP</th>
              <th>Versões</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!acceptances.length">
              <td colspan="7">Nenhum aceite para os filtros atuais.</td>
            </tr>
            <tr v-for="acceptance in acceptances" :key="acceptance.id">
              <td>
                <strong>{{ dateTime(acceptance.accepted_at) }}</strong>
                <small>{{ acceptance.context_label }}</small>
              </td>
              <td>
                <strong>{{ acceptance.company?.name || 'Sem empresa' }}</strong>
                <small>{{ acceptance.company?.access_code || '-' }}</small>
              </td>
              <td>
                <strong>{{ acceptance.document_label }}</strong>
                <small>{{ acceptance.document_type }}</small>
              </td>
              <td>{{ acceptance.source_label }}</td>
              <td>
                <strong>{{ acceptance.user?.name || 'Sem vínculo' }}</strong>
                <small>{{ acceptance.user?.email || '-' }}</small>
              </td>
              <td>{{ acceptance.ip_masked || '-' }}</td>
              <td>
                <strong>{{ acceptance.terms_version }}</strong>
                <small>{{ acceptance.privacy_version || 'sem privacidade separada' }}</small>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
