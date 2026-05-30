<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'

type ReadinessStatus = 'passed' | 'warning' | 'blocked'
type SummaryStatus = 'ready' | 'ready_with_warnings' | 'blocked'

type ReadinessMetric = {
  label: string
  value: number | string
}

type ReadinessCheck = {
  key: string
  label: string
  status: ReadinessStatus
  detail: string
  action: string
  link: string | null
  group: string
  impact: string | null
}

type ConnectedBlock = {
  status: ReadinessStatus
  label: string
  summary: string
  detail: string
  link: string
  metrics: ReadinessMetric[]
  coverage_rate?: number
  published_at?: string | null
  last_run_at?: string | null
}

type PublicationReport = {
  title: string
  generated_at: string | null
  status_label: string
  headline: string
  summary: string
  blockers: string[]
  warnings: string[]
  recommendations: Array<{
    label: string
    description: string
    link: string | null
  }>
  text: string
}

type ReadinessPayload = {
  summary: {
    status: SummaryStatus
    status_label: string
    passed: number
    warnings: number
    blockers: number
    total: number
    generated_at: string | null
  }
  checks: ReadinessCheck[]
  connected_data: {
    coverage: ConnectedBlock
    widget: ConnectedBlock
    sync: ConnectedBlock
  }
  production_urls: Record<string, string>
  missing_credentials: Record<string, boolean>
  pilot_package: {
    status: 'commercial_ready' | 'assisted_demo_ready'
    sales_assets: { label: string; url: string }[]
    onboarding_steps: string[]
    automation_commands: Record<string, string>
    pending_real_world_tests: string[]
  }
  report: PublicationReport
}

const loading = ref(true)
const copying = ref(false)
const copyFeedback = ref('')
const error = ref('')
const readiness = reactive<ReadinessPayload>({
  summary: {
    status: 'blocked',
    status_label: 'Bloqueado',
    passed: 0,
    warnings: 0,
    blockers: 0,
    total: 0,
    generated_at: null,
  },
  connected_data: {
    coverage: {
      status: 'blocked',
      label: 'Catálogo',
      summary: '',
      detail: '',
      link: '/app/produtos',
      metrics: [],
      coverage_rate: 0,
    },
    widget: {
      status: 'blocked',
      label: 'Widget',
      summary: '',
      detail: '',
      link: '/app/widget',
      metrics: [],
      published_at: null,
    },
    sync: {
      status: 'warning',
      label: 'Sincronização',
      summary: '',
      detail: '',
      link: '/app/sincronizacao',
      metrics: [],
      last_run_at: null,
    },
  },
  checks: [],
  production_urls: {},
  missing_credentials: {},
  pilot_package: {
    status: 'assisted_demo_ready',
    sales_assets: [],
    onboarding_steps: [],
    automation_commands: {},
    pending_real_world_tests: [],
  },
  report: {
    title: 'Relatório de publicação',
    generated_at: null,
    status_label: 'Bloqueado',
    headline: '',
    summary: '',
    blockers: [],
    warnings: [],
    recommendations: [],
    text: '',
  },
})

const heroStatusLabel = computed(() => readiness.summary.status_label)
const generatedAtLabel = computed(() => formatDateTime(readiness.summary.generated_at))
const coverageRateLabel = computed(() => {
  const value = readiness.connected_data.coverage.coverage_rate

  return typeof value === 'number' ? `${Math.round(value)}%` : '0%'
})
const connectedCards = computed(() => [
  readiness.connected_data.coverage,
  readiness.connected_data.widget,
  readiness.connected_data.sync,
])
const groupedChecks = computed(() => [
  {
    key: 'catalogo',
    label: 'Catálogo',
    items: readiness.checks.filter((check) => check.group === 'catalogo'),
  },
  {
    key: 'widget',
    label: 'Widget',
    items: readiness.checks.filter((check) => check.group === 'widget'),
  },
  {
    key: 'sincronizacao',
    label: 'Sincronização',
    items: readiness.checks.filter((check) => check.group === 'sincronizacao'),
  },
  {
    key: 'financeiro',
    label: 'Comercial',
    items: readiness.checks.filter((check) => check.group === 'financeiro'),
  },
  {
    key: 'operacao',
    label: 'Operação',
    items: readiness.checks.filter((check) => check.group === 'operacao'),
  },
  {
    key: 'seguranca',
    label: 'Segurança',
    items: readiness.checks.filter((check) => check.group === 'seguranca'),
  },
].filter((group) => group.items.length > 0))

onMounted(loadReadiness)

async function loadReadiness() {
  loading.value = true
  error.value = ''
  copyFeedback.value = ''

  try {
    const { data } = await api.get('/go-live/readiness')
    Object.assign(readiness.summary, data.summary)
    readiness.checks = data.checks || []
    Object.assign(readiness.connected_data.coverage, data.connected_data?.coverage || {})
    Object.assign(readiness.connected_data.widget, data.connected_data?.widget || {})
    Object.assign(readiness.connected_data.sync, data.connected_data?.sync || {})
    readiness.production_urls = data.production_urls || {}
    readiness.missing_credentials = data.missing_credentials || {}
    readiness.pilot_package = data.pilot_package || readiness.pilot_package
    readiness.report = data.report || readiness.report
  } catch {
    error.value = 'Não foi possível carregar o diagnóstico de publicação.'
  } finally {
    loading.value = false
  }
}

async function copyReport() {
  if (!readiness.report.text) {
    return
  }

  copying.value = true
  copyFeedback.value = ''

  try {
    await navigator.clipboard.writeText(readiness.report.text)
    copyFeedback.value = 'Relatório copiado.'
  } catch {
    copyFeedback.value = 'Não foi possível copiar o relatório.'
  } finally {
    copying.value = false
  }
}

function statusPillClass(status: ReadinessStatus) {
  return {
    'status-pill': true,
    ok: status === 'passed',
    warning: status === 'warning',
    danger: status === 'blocked',
  }
}

function statusIcon(status: ReadinessStatus) {
  return status === 'passed'
    ? 'fa-solid fa-check'
    : status === 'warning'
      ? 'fa-solid fa-triangle-exclamation'
      : 'fa-solid fa-xmark'
}

function statusLabel(status: ReadinessStatus) {
  return status === 'passed' ? 'Ok' : status === 'warning' ? 'Atenção' : 'Bloqueio'
}

function summaryTone(status: SummaryStatus) {
  return {
    'go-live-summary-card': true,
    'tone-good': status === 'ready',
    'tone-warning': status === 'ready_with_warnings',
    'tone-danger': status === 'blocked',
  }
}

function packageStatusLabel(status: ReadinessPayload['pilot_package']['status']) {
  return status === 'commercial_ready' ? 'Comercial pronto' : 'Demo assistida pronta'
}

function formatDateTime(value: string | null | undefined) {
  if (!value) {
    return 'agora'
  }

  return new Date(value).toLocaleString('pt-BR')
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Publicação conectada</span>
        <h1>Checklist real de publicação</h1>
        <p>
          Esta tela junta catálogo, widget, sincronização e operação para mostrar se a loja está pronta,
          pronta com avisos ou bloqueada.
        </p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" title="Revalidar tudo" @click="loadReadiness">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Revalidar tudo
        </button>
        <button class="btn btn-secondary" type="button" :disabled="copying || !readiness.report.text" @click="copyReport">
          <i class="fa-solid fa-copy" aria-hidden="true"></i>
          {{ copying ? 'Copiando...' : 'Copiar relatório' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="copyFeedback" class="form-success">{{ copyFeedback }}</p>
    <p v-if="loading" class="empty-state">Carregando diagnóstico de publicação...</p>

    <template v-else>
      <div class="metric-grid go-live-summary-grid">
        <article :class="summaryTone(readiness.summary.status)">
          <i class="fa-solid fa-rocket" aria-hidden="true"></i>
          <strong>{{ heroStatusLabel }}</strong>
          <span>{{ readiness.summary.passed }} de {{ readiness.summary.total }} itens passaram.</span>
          <small>Atualizado em {{ generatedAtLabel }}</small>
        </article>
        <article class="go-live-summary-card tone-warning">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <strong>{{ readiness.summary.warnings }}</strong>
          <span>Aviso{{ readiness.summary.warnings === 1 ? '' : 's' }} com impacto explicado.</span>
          <small>Não impedem demo assistida, mas pedem dono.</small>
        </article>
        <article class="go-live-summary-card tone-danger">
          <i class="fa-solid fa-ban" aria-hidden="true"></i>
          <strong>{{ readiness.summary.blockers }}</strong>
          <span>Bloqueio{{ readiness.summary.blockers === 1 ? '' : 's' }} antes de campanha pública.</span>
          <small>Publicação não deve seguir com este número maior que zero.</small>
        </article>
        <article class="go-live-summary-card tone-neutral">
          <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
          <strong>{{ coverageRateLabel }}</strong>
          <span>Cobertura atual do catálogo ativo.</span>
          <small>Base usada no diagnóstico desta publicação.</small>
        </article>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Dados conectados</h2>
            <span>tempo real</span>
          </div>

          <div class="go-live-connected-grid">
            <article v-for="card in connectedCards" :key="card.label" class="go-live-connected-card">
              <div class="go-live-connected-head">
                <span :class="statusPillClass(card.status)">
                  <i :class="statusIcon(card.status)" aria-hidden="true"></i>
                  {{ statusLabel(card.status) }}
                </span>
                <strong>{{ card.label }}</strong>
              </div>
              <p>{{ card.summary }}</p>
              <small>{{ card.detail }}</small>
              <div class="go-live-connected-metrics">
                <span v-for="metric in card.metrics" :key="`${card.label}-${metric.label}`">
                  <strong>{{ metric.value }}</strong>
                  <small>{{ metric.label }}</small>
                </span>
              </div>
              <RouterLink class="btn btn-secondary btn-sm" :to="card.link">
                Abrir {{ card.label.toLowerCase() }}
              </RouterLink>
            </article>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Relatório para o lojista</h2>
            <span>{{ readiness.report.status_label }}</span>
          </div>

          <div class="go-live-report-panel">
            <strong>{{ readiness.report.headline }}</strong>
            <p>{{ readiness.report.summary }}</p>
            <small>Gerado em {{ formatDateTime(readiness.report.generated_at) }}</small>

            <div v-if="readiness.report.blockers.length" class="impact-warning-list">
              <article v-for="blocker in readiness.report.blockers" :key="blocker" class="error">
                <strong>Bloqueio</strong>
                <small>{{ blocker }}</small>
              </article>
            </div>

            <div v-if="readiness.report.warnings.length" class="impact-warning-list compact">
              <article v-for="warning in readiness.report.warnings" :key="warning" class="warning">
                <strong>Aviso</strong>
                <small>{{ warning }}</small>
              </article>
            </div>

            <div v-if="readiness.report.recommendations.length" class="job-list">
              <RouterLink
                v-for="recommendation in readiness.report.recommendations"
                :key="recommendation.label"
                class="job-row"
                :to="recommendation.link || '/app/go-live'"
              >
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                <span>
                  <strong>{{ recommendation.label }}</strong>
                  <small>{{ recommendation.description }}</small>
                </span>
              </RouterLink>
            </div>

            <pre class="go-live-report-text">{{ readiness.report.text }}</pre>
          </div>
        </section>
      </div>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Checklist com ações</h2>
          <span>{{ readiness.summary.total }} itens</span>
        </div>

        <div class="go-live-group-list">
          <section v-for="group in groupedChecks" :key="group.key" class="go-live-group">
            <div class="subsection-heading compact-heading">
              <h3>{{ group.label }}</h3>
              <span>{{ group.items.length }} item{{ group.items.length === 1 ? '' : 's' }}</span>
            </div>

            <div class="readiness-list">
              <article v-for="check in group.items" :key="check.key" class="readiness-row go-live-check-row">
                <span :class="statusPillClass(check.status)">
                  <i :class="statusIcon(check.status)" aria-hidden="true"></i>
                  {{ statusLabel(check.status) }}
                </span>
                <div class="go-live-check-copy">
                  <strong>{{ check.label }}</strong>
                  <small>{{ check.detail }}</small>
                  <small>{{ check.action }}</small>
                  <small v-if="check.impact">{{ check.impact }}</small>
                  <RouterLink v-if="check.link" class="btn btn-secondary btn-sm" :to="check.link">
                    Resolver agora
                  </RouterLink>
                </div>
              </article>
            </div>
          </section>
        </div>
      </section>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>URLs de produção</h2>
            <span>smoke</span>
          </div>

          <div class="job-list">
            <a
              v-for="(url, key) in readiness.production_urls"
              :key="key"
              class="job-row"
              :href="url"
              target="_blank"
              rel="noreferrer"
            >
              <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
              <span>
                <strong>{{ key }}</strong>
                <small>{{ url }}</small>
              </span>
            </a>
          </div>

          <div class="subsection">
            <div class="subsection-heading">
              <h2>Credenciais pendentes</h2>
              <span>externas</span>
            </div>
            <div class="check-list">
              <span v-for="(missing, key) in readiness.missing_credentials" :key="key">
                <i :class="missing ? 'fa-solid fa-clock' : 'fa-solid fa-check'" aria-hidden="true"></i>
                {{ key }}
              </span>
            </div>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Pacote de piloto</h2>
            <span>{{ packageStatusLabel(readiness.pilot_package.status) }}</span>
          </div>

          <div class="job-list">
            <a
              v-for="asset in readiness.pilot_package.sales_assets"
              :key="asset.label"
              class="job-row"
              :href="asset.url"
              target="_blank"
              rel="noreferrer"
            >
              <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
              <span>
                <strong>{{ asset.label }}</strong>
                <small>{{ asset.url }}</small>
              </span>
            </a>
          </div>

          <div class="subsection">
            <div class="subsection-heading">
              <h2>Onboarding</h2>
              <span>{{ readiness.pilot_package.onboarding_steps.length }} passos</span>
            </div>

            <div class="check-list stacked">
              <span v-for="step in readiness.pilot_package.onboarding_steps" :key="step">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                {{ step }}
              </span>
            </div>
          </div>
        </section>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Automações</h2>
            <span>cPanel</span>
          </div>
          <div class="command-list">
            <article v-for="(command, key) in readiness.pilot_package.automation_commands" :key="key">
              <strong>{{ key }}</strong>
              <code>{{ command }}</code>
            </article>
          </div>
        </section>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Pendências reais</h2>
            <span>{{ readiness.pilot_package.pending_real_world_tests.length }} testes</span>
          </div>
          <div class="check-list stacked">
            <span v-for="test in readiness.pilot_package.pending_real_world_tests" :key="test">
              <i class="fa-solid fa-clock" aria-hidden="true"></i>
              {{ test }}
            </span>
          </div>
        </section>
      </div>
    </template>
  </section>
</template>
