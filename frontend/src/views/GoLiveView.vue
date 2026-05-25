<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

type ReadinessCheck = {
  key: string
  label: string
  status: 'passed' | 'warning' | 'blocked'
  detail: string
  action: string
}

type ReadinessPayload = {
  summary: {
    status: 'ready' | 'ready_with_warnings' | 'blocked'
    passed: number
    warnings: number
    blockers: number
    total: number
  }
  checks: ReadinessCheck[]
  production_urls: Record<string, string>
  missing_credentials: Record<string, boolean>
  pilot_package: {
    status: 'commercial_ready' | 'assisted_demo_ready'
    sales_assets: { label: string; url: string }[]
    onboarding_steps: string[]
    automation_commands: Record<string, string>
    pending_real_world_tests: string[]
  }
}

const loading = ref(true)
const error = ref('')
const readiness = reactive<ReadinessPayload>({
  summary: {
    status: 'blocked',
    passed: 0,
    warnings: 0,
    blockers: 0,
    total: 0,
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
})

const statusLabel = computed(() => {
  if (readiness.summary.status === 'ready') {
    return 'Pronto'
  }

  if (readiness.summary.status === 'ready_with_warnings') {
    return 'Pronto com pendências'
  }

  return 'Bloqueado'
})

onMounted(loadReadiness)

async function loadReadiness() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/go-live/readiness')
    Object.assign(readiness.summary, data.summary)
    readiness.checks = data.checks
    readiness.production_urls = data.production_urls
    readiness.missing_credentials = data.missing_credentials
    readiness.pilot_package = data.pilot_package
  } catch {
    error.value = 'Não foi possível carregar o checklist de go-live.'
  } finally {
    loading.value = false
  }
}

function pillClass(status: ReadinessCheck['status']) {
  return {
    'status-pill': true,
    ok: status === 'passed',
    warning: status === 'warning',
  }
}

function packageStatusLabel(status: ReadinessPayload['pilot_package']['status']) {
  return status === 'commercial_ready' ? 'Comercial pronto' : 'Demo assistida pronta'
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Go-live assistido</span>
        <h1>Prontidão de produção</h1>
        <p>
          Revise produto teste, segurança, integrações e publicação antes de levar tráfego real para a loja.
        </p>
      </div>
      <button class="btn btn-secondary" type="button" title="Atualizar checklist" @click="loadReadiness">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="loading" class="empty-state">Carregando checklist...</p>

    <template v-else>
      <div class="metric-grid">
        <article class="metric-card">
          <i class="fa-solid fa-rocket" aria-hidden="true"></i>
          <strong>{{ statusLabel }}</strong>
          <span>{{ readiness.summary.passed }} de {{ readiness.summary.total }} itens passaram.</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <strong>{{ readiness.summary.warnings }} pendência{{ readiness.summary.warnings === 1 ? '' : 's' }}</strong>
          <span>Itens que não impedem operação assistida, mas precisam de dono.</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-ban" aria-hidden="true"></i>
          <strong>{{ readiness.summary.blockers }} bloqueio{{ readiness.summary.blockers === 1 ? '' : 's' }}</strong>
          <span>Devem estar zerados antes de campanha pública.</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-link" aria-hidden="true"></i>
          <strong>Subpasta ativa</strong>
          <span>Cutover para a raiz depende de aceite e piloto BigShop.</span>
        </article>
      </div>

      <div class="analytics-grid">
        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Checklist</h2>
            <span>{{ readiness.summary.total }} itens</span>
          </div>

          <div class="readiness-list">
            <article v-for="check in readiness.checks" :key="check.key" class="readiness-row">
              <span :class="pillClass(check.status)">
                <i
                  :class="check.status === 'passed' ? 'fa-solid fa-check' : check.status === 'warning' ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-xmark'"
                  aria-hidden="true"
                ></i>
                {{ check.status === 'passed' ? 'Ok' : check.status === 'warning' ? 'Atenção' : 'Bloqueio' }}
              </span>
              <div>
                <strong>{{ check.label }}</strong>
                <small>{{ check.detail }}</small>
                <small>{{ check.action }}</small>
              </div>
            </article>
          </div>
        </section>

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
      </div>

      <div class="analytics-grid">
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
        </section>

        <section class="panel-main">
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
