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
})

const statusLabel = computed(() => {
  if (readiness.summary.status === 'ready') {
    return 'Pronto'
  }

  if (readiness.summary.status === 'ready_with_warnings') {
    return 'Pronto com pendencias'
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
  } catch {
    error.value = 'Nao foi possivel carregar o checklist de go-live.'
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
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Go-live assistido</span>
        <h1>Prontidao de producao</h1>
        <p>
          Use este painel para conferir se produto teste, widget, seguranca e integracoes estao
          prontos antes de mover trafego real.
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
          <strong>{{ readiness.summary.warnings }} pendencia{{ readiness.summary.warnings === 1 ? '' : 's' }}</strong>
          <span>Itens que nao impedem operacao assistida, mas precisam de dono.</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-ban" aria-hidden="true"></i>
          <strong>{{ readiness.summary.blockers }} bloqueio{{ readiness.summary.blockers === 1 ? '' : 's' }}</strong>
          <span>Devem estar zerados antes de campanha publica.</span>
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
                {{ check.status === 'passed' ? 'Ok' : check.status === 'warning' ? 'Atencao' : 'Bloqueio' }}
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
            <h2>URLs de producao</h2>
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
    </template>
  </section>
</template>
