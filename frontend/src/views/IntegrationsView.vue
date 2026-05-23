<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

type PlatformConnection = {
  id: number
  platform: string
  external_store_id: string | null
  api_base_url: string | null
  status: string
  has_access_token: boolean
  has_webhook_secret: boolean
  last_error: string | null
}

type PlatformGuide = {
  steps: string[]
  snippet: string
  checklist: Array<{ key: string; label: string }>
  data_support: Record<string, string>
}

type Platform = {
  key: string
  name: string
  priority: boolean
  icon: string
  install_mode: string
  status: string
  summary: string
  guide: PlatformGuide
  has_connection: boolean
  connection: PlatformConnection | null
}

type ValidationCheck = {
  key: string
  label: string
  status: 'passed' | 'warning' | 'failed'
  action: string | null
}

type ValidationResult = {
  status: 'passed' | 'warning' | 'failed'
  url: string
  http_status: number | null
  checks: ValidationCheck[]
}

type BigShopActivation = {
  id: number
  status: string
  store_id: string | null
  store_domain: string | null
  has_access_token: boolean
  widget_public_key: string | null
  contract_version: string | null
  company: { name: string; access_code: string; domain: string | null } | null
  occurred_at: string | null
}

const auth = useAuthStore()
const platforms = ref<Platform[]>([])
const selectedKey = ref('bigshop')
const loading = ref(false)
const saving = ref(false)
const running = ref(false)
const validating = ref(false)
const copied = ref(false)
const notice = ref('')
const error = ref('')
const integrationReport = ref<Record<string, number | string> | null>(null)
const validation = ref<ValidationResult | null>(null)
const bigShopActivations = ref<BigShopActivation[]>([])

const form = reactive({
  external_store_id: '',
  api_base_url: '',
  status: 'draft',
  access_token: '',
  webhook_secret: '',
  validation_url: '',
})

const selected = computed(() => platforms.value.find((platform) => platform.key === selectedKey.value) || platforms.value[0] || null)
const isBigShopContract = computed(() => {
  return auth.activeCompany?.platform === 'bigshop'
    || (platforms.value.length === 1 && platforms.value[0]?.key === 'bigshop')
})

onMounted(() => {
  loadPlatforms()
})

async function loadPlatforms() {
  loading.value = true

  try {
    const { data } = await api.get('/integrations')
    platforms.value = data.data

    if (!platforms.value.find((platform) => platform.key === selectedKey.value)) {
      selectedKey.value = platforms.value[0]?.key || 'bigshop'
    }

    fillForm()
    await loadBigShopActivations()
  } finally {
    loading.value = false
  }
}

function selectPlatform(platform: Platform) {
  selectedKey.value = platform.key
  integrationReport.value = null
  validation.value = null
  error.value = ''
  fillForm(platform)
  loadBigShopActivations()
}

function fillForm(platform = selected.value) {
  form.external_store_id = platform?.connection?.external_store_id || ''
  form.api_base_url = platform?.connection?.api_base_url || ''
  form.status = platform?.connection?.status || platform?.status || 'draft'
  form.access_token = ''
  form.webhook_secret = ''
}

async function savePlatform() {
  if (!selected.value) {
    return
  }

  saving.value = true
  notice.value = ''
  error.value = ''

  try {
    await api.patch(`/integrations/${selected.value.key}`, {
      external_store_id: form.external_store_id || null,
      api_base_url: form.api_base_url || null,
      status: form.status,
      access_token: form.access_token || undefined,
      webhook_secret: form.webhook_secret || undefined,
    })

    notice.value = 'Integracao atualizada.'
    await loadPlatforms()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel salvar.'
  } finally {
    saving.value = false
  }
}

async function probeBigShop() {
  running.value = true
  notice.value = ''
  error.value = ''
  integrationReport.value = null

  try {
    const { data } = await api.post('/integrations/bigshop/probe')
    integrationReport.value = data.data
    notice.value = 'Conexao BigShop validada.'
    await loadPlatforms()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel validar a BigShop.'
  } finally {
    running.value = false
  }
}

async function syncBigShop() {
  running.value = true
  notice.value = ''
  error.value = ''
  integrationReport.value = null

  try {
    const { data } = await api.post('/integrations/bigshop/sync')
    integrationReport.value = data.data
    notice.value = 'Produtos BigShop sincronizados.'
    await loadPlatforms()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel sincronizar a BigShop.'
  } finally {
    running.value = false
  }
}

async function loadBigShopActivations() {
  if (selected.value?.key !== 'bigshop') {
    bigShopActivations.value = []
    return
  }

  const { data } = await api.get('/integrations/bigshop/activations').catch(() => ({ data: { data: [] } }))
  bigShopActivations.value = data.data || []
}

async function validateInstall() {
  if (!selected.value) {
    return
  }

  validating.value = true
  notice.value = ''
  error.value = ''
  validation.value = null

  try {
    const { data } = await api.post(`/integrations/${selected.value.key}/validate-install`, {
      url: form.validation_url || undefined,
    })
    validation.value = data.data
    notice.value = validation.value?.status === 'passed'
      ? 'Instalacao validada.'
      : 'Validacao concluida com pendencias.'
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message
      || requestError.response?.data?.errors?.url?.[0]
      || 'Nao foi possivel validar a instalacao.'
  } finally {
    validating.value = false
  }
}

async function copyGuideSnippet() {
  if (!selected.value?.guide?.snippet) {
    return
  }

  await navigator.clipboard.writeText(selected.value.guide.snippet)
  copied.value = true
  window.setTimeout(() => {
    copied.value = false
  }, 1800)
}

function statusLabel(status: string) {
  return {
    draft: 'Rascunho',
    configured: 'Configurada',
    connected: 'Conectada',
    disabled: 'Pausada',
    error: 'Erro',
    passed: 'Validado',
    warning: 'Atencao',
    failed: 'Falhou',
  }[status] || status
}

function checkStatus(key: string) {
  return validation.value?.checks.find((check) => check.key === key)?.status || 'pending'
}

function checkIcon(key: string) {
  const status = checkStatus(key)

  if (status === 'passed') {
    return 'fa-circle-check'
  }

  if (status === 'failed') {
    return 'fa-circle-xmark'
  }

  return 'fa-circle-exclamation'
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Integracoes</span>
        <h1>Plataformas</h1>
      </div>
    </div>

    <p v-if="notice" class="success-message">{{ notice }}</p>
    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="isBigShopContract" class="info-message">
      Plano BigShop ativo: este painel exibe somente a integracao BigShop.
    </p>

    <div v-if="loading" class="empty-state">Carregando integracoes...</div>

    <div v-else class="integrations-grid">
      <aside class="platform-list">
        <button
          v-for="platform in platforms"
          :key="platform.key"
          class="platform-card"
          :class="{ active: selected?.key === platform.key }"
          type="button"
          @click="selectPlatform(platform)"
        >
          <span class="platform-icon">
            <i class="fa-solid" :class="platform.icon" aria-hidden="true"></i>
          </span>
          <span>
            <strong>{{ platform.name }}</strong>
            <small>{{ statusLabel(platform.status) }}</small>
          </span>
          <em v-if="platform.priority">Prioridade</em>
        </button>
      </aside>

      <form class="panel-main admin-form" @submit.prevent="savePlatform">
        <div class="subsection-heading">
          <h2>{{ selected?.name }}</h2>
          <span>{{ selected?.install_mode === 'one_click' ? 'Um clique' : 'Manual' }}</span>
        </div>
        <p class="guide-summary">{{ selected?.summary }}</p>

        <div class="form-grid">
          <label>
            Loja
            <input v-model="form.external_store_id" maxlength="120" />
          </label>
          <label>
            URL da API
            <input v-model="form.api_base_url" type="url" maxlength="255" />
          </label>
          <label>
            Status
            <select v-model="form.status">
              <option value="draft">Rascunho</option>
              <option value="configured">Configurada</option>
              <option value="connected">Conectada</option>
              <option value="disabled">Pausada</option>
            </select>
          </label>
          <label>
            Token
            <input v-model="form.access_token" autocomplete="off" />
          </label>
          <label>
            Webhook secret
            <input v-model="form.webhook_secret" autocomplete="off" />
          </label>
        </div>

        <div class="connection-flags">
          <span :class="{ on: selected?.connection?.has_access_token }">
            <i class="fa-solid fa-key" aria-hidden="true"></i>
            Token
          </span>
          <span :class="{ on: selected?.connection?.has_webhook_secret }">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            Webhook
          </span>
          <span :class="{ on: selected?.status === 'connected' }">
            <i class="fa-solid fa-link" aria-hidden="true"></i>
            Conexao
          </span>
        </div>

        <div class="guide-panel">
          <div class="subsection-heading">
            <h2>Checklist</h2>
            <span>{{ validation?.status ? statusLabel(validation.status) : 'Pendente' }}</span>
          </div>
          <div class="check-list install-checklist">
            <span
              v-for="item in selected?.guide.checklist"
              :key="item.key"
              :class="checkStatus(item.key)"
            >
              <i class="fa-solid" :class="checkIcon(item.key)" aria-hidden="true"></i>
              {{ item.label }}
            </span>
          </div>

          <div v-if="validation" class="validation-result">
            <strong>{{ validation.url }}</strong>
            <small>HTTP {{ validation.http_status || '-' }}</small>
            <p v-for="check in validation.checks.filter((item) => item.action)" :key="check.key">
              {{ check.action }}
            </p>
          </div>
        </div>

        <div class="form-grid">
          <label>
            URL para validar
            <input v-model="form.validation_url" type="url" placeholder="https://loja.com.br/produto" />
          </label>
          <label class="inline-action-label">
            Validacao
            <button class="btn btn-secondary" type="button" :disabled="validating" @click="validateInstall">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
              {{ validating ? 'Validando...' : 'Validar instalacao' }}
            </button>
          </label>
        </div>

        <div class="guide-panel">
          <div class="subsection-heading">
            <h2>Passo a passo</h2>
            <span>{{ selected?.name }}</span>
          </div>
          <ol class="guide-steps">
            <li v-for="step in selected?.guide.steps" :key="step">{{ step }}</li>
          </ol>
        </div>

        <div class="guide-panel">
          <div class="subsection-heading">
            <h2>Dados suportados</h2>
            <span>Matriz</span>
          </div>
          <div class="data-support-grid">
            <span v-for="(value, key) in selected?.guide.data_support" :key="key">
              <small>{{ key }}</small>
              <strong>{{ value }}</strong>
            </span>
          </div>
        </div>

        <div class="guide-panel">
          <div class="subsection-heading">
            <h2>Snippet</h2>
            <button class="icon-link" type="button" :title="copied ? 'Copiado' : 'Copiar snippet'" @click="copyGuideSnippet">
              <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
            </button>
          </div>
          <pre class="guide-snippet"><code>{{ selected?.guide.snippet }}</code></pre>
        </div>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="saving">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            Salvar integracao
          </button>
          <button
            v-if="selected?.key === 'bigshop'"
            class="btn btn-secondary"
            type="button"
            :disabled="running"
            @click="probeBigShop"
          >
            <i class="fa-solid fa-signal" aria-hidden="true"></i>
            Testar
          </button>
          <button
            v-if="selected?.key === 'bigshop'"
            class="btn btn-secondary"
            type="button"
            :disabled="running"
            @click="syncBigShop"
          >
            <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
            Sincronizar
          </button>
        </div>

        <div v-if="integrationReport" class="integration-report">
          <span v-for="(value, key) in integrationReport" :key="key">
            <strong>{{ value }}</strong>
            <small>{{ key }}</small>
          </span>
        </div>

        <div v-if="selected?.key === 'bigshop'" class="guide-panel">
          <div class="subsection-heading">
            <h2>Ativacoes um clique</h2>
            <span>{{ bigShopActivations.length }} recentes</span>
          </div>
          <div v-if="!bigShopActivations.length" class="empty-inline">Nenhuma ativacao BigShop registrada para esta empresa.</div>
          <div v-else class="activation-list">
            <article v-for="activation in bigShopActivations" :key="activation.id">
              <i class="fa-solid fa-bolt" aria-hidden="true"></i>
              <span>
                <strong>{{ activation.company?.name || activation.store_id || 'Loja BigShop' }}</strong>
                <small>{{ activation.store_domain || activation.company?.domain || 'dominio pendente' }}</small>
              </span>
              <em :class="{ ok: activation.status === 'success' }">{{ activation.status }}</em>
              <small>{{ activation.contract_version || 'contrato atual' }}</small>
            </article>
          </div>
        </div>
      </form>
    </div>
  </section>
</template>
