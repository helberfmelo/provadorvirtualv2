<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { showFeedback } from '../services/saveFeedback'
import { useAuthStore } from '../stores/auth'

type PlatformConnection = {
  id: number
  platform: string
  external_store_id: string | null
  api_base_url: string | null
  feed_url: string | null
  feed_format: string | null
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
const integrationReport = ref<Record<string, number | string> | null>(null)
const validation = ref<ValidationResult | null>(null)
const bigShopActivations = ref<BigShopActivation[]>([])

const form = reactive({
  external_store_id: '',
  api_base_url: '',
  feed_url: '',
  feed_format: 'google_xml',
  status: 'draft',
  access_token: '',
  webhook_secret: '',
  validation_url: '',
})

const selected = computed(() => platforms.value.find((platform) => platform.key === selectedKey.value) || platforms.value[0] || null)
const feedPlaceholder = computed(() => selected.value?.key === 'bigshop'
  ? 'https://domínio-da-loja.com.br/feed.xml'
  : 'https://loja.com.br/feed.xml')
const isBigShopContract = computed(() => {
  return auth.activeCompany?.platform === 'bigshop'
    || (platforms.value.length === 1 && platforms.value[0]?.key === 'bigshop')
})
const fieldHelp = {
  external_store_id: 'Informe o identificador da loja na plataforma. Na BigShop, use o store_id da loja.',
  api_base_url: 'Informe a URL base da API quando a plataforma tiver API autenticada. Na BigShop, use a API V3.',
  feed_url: 'Informe a URL pública do catálogo XML/Google Merchant. Na BigShop, normalmente é o domínio da loja seguido de /feed.xml.',
  status: 'Controle o estado operacional desta integração no Provador Virtual.',
  access_token: 'Cole o token ou chave de API da plataforma. O valor fica criptografado e não volta a aparecer no formulário.',
  webhook_secret: 'Informe o segredo usado para validar webhooks assinados, quando a plataforma enviar eventos ao Provador Virtual.',
  validation_url: 'Informe uma página pública de produto para confirmar se o container, script e identificadores do widget foram instalados.',
  validation_action: 'Rode a validação da página informada e veja o checklist técnico de instalação.',
}

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
  fillForm(platform)
  loadBigShopActivations()
}

function fillForm(platform = selected.value) {
  form.external_store_id = platform?.connection?.external_store_id || ''
  form.api_base_url = platform?.connection?.api_base_url || ''
  form.feed_url = platform?.connection?.feed_url || ''
  form.feed_format = platform?.connection?.feed_format || 'google_xml'
  form.status = platform?.connection?.status || platform?.status || 'draft'
  form.access_token = ''
  form.webhook_secret = ''
}

async function savePlatform() {
  if (!selected.value) {
    return
  }

  saving.value = true

  try {
    await api.patch(`/integrations/${selected.value.key}`, {
      external_store_id: form.external_store_id || null,
      api_base_url: form.api_base_url || null,
      feed_url: form.feed_url || null,
      feed_format: form.feed_format || 'google_xml',
      status: form.status,
      access_token: form.access_token || undefined,
      webhook_secret: form.webhook_secret || undefined,
    })
    await loadPlatforms()
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Não foi possível salvar',
      message: friendlyRequestMessage(requestError, 'Não foi possível salvar a integração.'),
    })
  } finally {
    saving.value = false
  }
}

async function probeBigShop() {
  if (!canRunBigShopApiAction()) {
    return
  }

  running.value = true
  integrationReport.value = null

  try {
    const { data } = await api.post('/integrations/bigshop/probe')
    integrationReport.value = data.data
    showFeedback({
      status: 'success',
      title: 'Conexão validada',
      message: 'A API da BigShop respondeu com sucesso para esta loja.',
    })
    await loadPlatforms()
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Falha ao testar a conexão',
      message: friendlyRequestMessage(requestError, 'Não foi possível validar a API da BigShop.'),
    })
  } finally {
    running.value = false
  }
}

async function syncBigShop() {
  if (!canRunBigShopApiAction()) {
    return
  }

  running.value = true
  integrationReport.value = null

  try {
    const { data } = await api.post('/integrations/bigshop/sync')
    integrationReport.value = data.data
    showFeedback({
      status: 'success',
      title: 'API BigShop sincronizada',
      message: 'Produtos e variações foram sincronizados. Acesse a página de produtos para revisar o catálogo importado.',
      actionLabel: 'Ver produtos',
      actionTo: '/app/produtos',
      duration: 0,
    })
    await loadPlatforms()
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Falha ao sincronizar API',
      message: friendlyRequestMessage(requestError, 'Não foi possível sincronizar produtos pela API da BigShop.'),
    })
  } finally {
    running.value = false
  }
}

async function syncXmlFeed() {
  if (!selected.value) {
    return
  }

  if (!selected.value.connection?.feed_url) {
    showFeedback({
      status: 'info',
      title: 'Salve o XML/feed primeiro',
      message: form.feed_url
        ? 'A URL do XML foi preenchida, mas ainda precisa ser salva antes da sincronização.'
        : 'Informe e salve a URL pública do XML/feed antes de sincronizar o catálogo.',
    })
    return
  }

  running.value = true
  integrationReport.value = null

  try {
    const { data } = await api.post(`/integrations/${selected.value.key}/sync-xml`)
    const job = data.data
    integrationReport.value = {
      status: job.status,
      total_rows: job.total_rows,
      imported_rows: job.imported_rows,
      failed_rows: job.failed_rows,
      products: job.summary?.products || 0,
      variants: job.summary?.variants || 0,
    }
    showFeedback({
      status: job.status === 'completed' ? 'success' : 'info',
      title: job.status === 'completed' ? 'XML/feed sincronizado' : 'XML/feed sincronizado com avisos',
      message: 'O catálogo foi processado. Acesse a página de produtos para visualizar os produtos sincronizados e conferir as variações.',
      actionLabel: 'Ver produtos',
      actionTo: '/app/produtos',
      duration: 0,
    })
    await loadPlatforms()
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Falha ao sincronizar XML/feed',
      message: friendlyRequestMessage(requestError, 'Não foi possível sincronizar o XML/feed.'),
    })
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
  validation.value = null

  try {
    const { data } = await api.post(`/integrations/${selected.value.key}/validate-install`, {
      url: form.validation_url || undefined,
    })
    validation.value = data.data
    showFeedback({
      status: validation.value?.status === 'passed' ? 'success' : 'info',
      title: validation.value?.status === 'passed' ? 'Instalação validada' : 'Validação concluída com pendências',
      message: validation.value?.status === 'passed'
        ? 'Container, script e identificadores do widget foram encontrados na página informada.'
        : 'Revise o checklist da instalação para corrigir os pontos pendentes.',
    })
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Falha ao validar instalação',
      message: friendlyRequestMessage(requestError, 'Não foi possível validar a instalação.'),
    })
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
    warning: 'Atenção',
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

function friendlyRequestMessage(requestError: any, fallback: string) {
  return requestError.response?.data?.message
    || requestError.response?.data?.errors?.feed_url?.[0]
    || requestError.response?.data?.errors?.url?.[0]
    || fallback
}

function canRunBigShopApiAction() {
  if (!selected.value?.connection) {
    showFeedback({
      status: 'info',
      title: 'Salve a conexão BigShop primeiro',
      message: 'Informe store_id, URL da API e token da BigShop, salve a integração e depois execute esta ação.',
    })
    return false
  }

  if (!selected.value.connection.external_store_id) {
    showFeedback({
      status: 'info',
      title: 'Store ID pendente',
      message: 'Informe e salve o ID da loja BigShop antes de testar ou sincronizar pela API.',
    })
    return false
  }

  if (!selected.value.connection.has_access_token) {
    showFeedback({
      status: 'info',
      title: 'Token pendente',
      message: form.access_token
        ? 'O token foi preenchido, mas precisa ser salvo antes de testar ou sincronizar pela API.'
        : 'Informe e salve o token da API BigShop antes de testar ou sincronizar pela API.',
    })
    return false
  }

  return true
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Integrações</span>
        <h1>Plataformas</h1>
      </div>
    </div>

    <p v-if="isBigShopContract" class="info-message">
      Plano BigShop ativo: este painel exibe somente a integração BigShop.
    </p>

    <div v-if="loading" class="empty-state">Carregando integrações...</div>

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
            <span class="field-label">
              <span class="info-tooltip" tabindex="0" role="button" :aria-label="fieldHelp.external_store_id" :data-tooltip="fieldHelp.external_store_id">i</span>
              Loja
            </span>
            <input v-model="form.external_store_id" maxlength="120" />
          </label>
          <label>
            <span class="field-label">
              <span class="info-tooltip" tabindex="0" role="button" :aria-label="fieldHelp.api_base_url" :data-tooltip="fieldHelp.api_base_url">i</span>
              URL da API
            </span>
            <input v-model="form.api_base_url" type="url" maxlength="255" />
          </label>
          <label>
            <span class="field-label">
              <span class="info-tooltip" tabindex="0" role="button" :aria-label="fieldHelp.feed_url" :data-tooltip="fieldHelp.feed_url">i</span>
              URL do XML/feed
            </span>
            <input v-model="form.feed_url" type="text" inputmode="url" maxlength="255" :placeholder="feedPlaceholder" />
          </label>
          <label>
            <span class="field-label">
              <span class="info-tooltip" tabindex="0" role="button" :aria-label="fieldHelp.status" :data-tooltip="fieldHelp.status">i</span>
              Status
            </span>
            <select v-model="form.status">
              <option value="draft">Rascunho</option>
              <option value="configured">Configurada</option>
              <option value="connected">Conectada</option>
              <option value="disabled">Pausada</option>
            </select>
          </label>
          <label>
            <span class="field-label">
              <span class="info-tooltip" tabindex="0" role="button" :aria-label="fieldHelp.access_token" :data-tooltip="fieldHelp.access_token">i</span>
              Token
            </span>
            <input v-model="form.access_token" autocomplete="off" />
          </label>
          <label>
            <span class="field-label">
              <span class="info-tooltip" tabindex="0" role="button" :aria-label="fieldHelp.webhook_secret" :data-tooltip="fieldHelp.webhook_secret">i</span>
              Webhook secret
            </span>
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
            Conexão
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
            <span class="field-label">
              <span class="info-tooltip" tabindex="0" role="button" :aria-label="fieldHelp.validation_url" :data-tooltip="fieldHelp.validation_url">i</span>
              URL para validar
            </span>
            <input v-model="form.validation_url" type="url" placeholder="https://loja.com.br/produto" />
          </label>
          <label class="inline-action-label">
            <span class="field-label">
              <span class="info-tooltip" tabindex="0" role="button" :aria-label="fieldHelp.validation_action" :data-tooltip="fieldHelp.validation_action">i</span>
              Validação
            </span>
            <button class="btn btn-secondary" type="button" :disabled="validating" @click="validateInstall">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
              {{ validating ? 'Validando...' : 'Validar instalação' }}
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

        <div class="integration-actions">
          <div class="integration-action-card">
            <strong>Configuração</strong>
            <button class="btn btn-primary" type="submit" :disabled="saving">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
              Salvar integração
            </button>
          </div>
          <div class="integration-action-card">
            <strong>Catálogo XML/feed</strong>
            <button
              class="btn btn-secondary"
              type="button"
              :disabled="running"
              @click="syncXmlFeed"
            >
              <i class="fa-solid fa-file-code" aria-hidden="true"></i>
              Sincronizar XML/feed
            </button>
          </div>
          <div v-if="selected?.key === 'bigshop'" class="integration-action-card integration-action-card-wide">
            <strong>API BigShop</strong>
            <div class="action-row compact">
              <button
                class="btn btn-secondary"
                type="button"
                :disabled="running"
                @click="probeBigShop"
              >
                <i class="fa-solid fa-signal" aria-hidden="true"></i>
                Testar conexão
              </button>
              <button
                class="btn btn-secondary"
                type="button"
                :disabled="running"
                @click="syncBigShop"
              >
                <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                Sincronizar API
              </button>
            </div>
          </div>
        </div>

        <div v-if="integrationReport" class="integration-report">
          <span v-for="(value, key) in integrationReport" :key="key">
            <strong>{{ value }}</strong>
            <small>{{ key }}</small>
          </span>
        </div>

        <div v-if="selected?.key === 'bigshop'" class="guide-panel">
          <div class="subsection-heading">
            <h2>Ativações um clique</h2>
            <span>{{ bigShopActivations.length }} recentes</span>
          </div>
          <div v-if="!bigShopActivations.length" class="empty-inline">Nenhuma ativação BigShop registrada para esta empresa.</div>
          <div v-else class="activation-list">
            <article v-for="activation in bigShopActivations" :key="activation.id">
              <i class="fa-solid fa-bolt" aria-hidden="true"></i>
              <span>
                <strong>{{ activation.company?.name || activation.store_id || 'Loja BigShop' }}</strong>
                <small>{{ activation.store_domain || activation.company?.domain || 'domínio pendente' }}</small>
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
