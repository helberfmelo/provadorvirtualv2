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

type BigShopDryRunIssue = {
  severity: 'error' | 'warning'
  code: string
  product_id: string | null
  product_name: string | null
  grid_id: string | null
  message: string
}

type BigShopDryRunProduct = {
  external_product_id: string
  name: string | null
  sku: string | null
  brand: string | null
  category: string | null
  gender: string | null
  mapped?: {
    category?: string | null
    brand?: string | null
    gender?: string | null
    age_group?: string | null
    status?: string | null
    fit_profile?: string | null
  }
  grid_count: number
  sizes: string[]
}

type BigShopDryRunResult = {
  dry_run: boolean
  status: 'ready' | 'warning'
  products_read: number
  products_valid: number
  products_with_grids: number
  products_without_grids: number
  grids_read: number
  grids_joined: number
  grids_without_product: number
  grids_without_size: number
  variants_detected: number
  sizes_detected: number
  errors_count: number
  warnings_count: number
  sample_products: BigShopDryRunProduct[]
  issues: BigShopDryRunIssue[]
  limited: {
    sample_products: boolean
    issues: boolean
  }
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
const bigShopDryRun = ref<BigShopDryRunResult | null>(null)

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
const installationPlacementSteps = computed(() => {
  const platformName = selected.value?.name || 'plataforma'
  const steps = [
    'Instale o snippet na página de produto, no template que renderiza todos os produtos da loja.',
    'O container deve ficar onde os botões Descubra seu tamanho e Tabela de Medidas precisam aparecer, normalmente perto da escolha de tamanho/grade e antes ou próximo ao botão Comprar.',
    'O script pode carregar com defer no head ou no fim do body, desde que o container exista antes da inicialização do widget.',
    'Produto, variação e SKU precisam refletir a opção atual do comprador. Quando tamanho/cor/grade mudar, atualize os atributos e recarregue o widget com window.ProvadorVirtual.reload(...).',
  ]

  if (selected.value?.key === 'bigshop') {
    steps.push('Na BigShop, a instalação automática será feita depois no produto.vue da model3 plano pro, no repositório BigShop correto.')
  } else if (selected.value?.key === 'custom') {
    steps.push('Em site próprio, use o template oficial da PDP e mantenha o mesmo contrato de IDs para todos os produtos de moda.')
  } else {
    steps.push(`Em ${platformName}, publique primeiro em tema, app ou ambiente de homologação quando a plataforma oferecer esse fluxo.`)
  }

  return steps
})
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
  bigShopDryRun.value = null
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
  bigShopDryRun.value = null

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
  bigShopDryRun.value = null

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

async function dryRunBigShop() {
  if (!canRunBigShopApiAction()) {
    return
  }

  running.value = true
  integrationReport.value = null
  bigShopDryRun.value = null

  try {
    const { data } = await api.post('/integrations/bigshop/dry-run')
    bigShopDryRun.value = data.data
    showFeedback({
      status: bigShopDryRun.value?.errors_count ? 'info' : 'success',
      title: bigShopDryRun.value?.errors_count ? 'Prévia concluída com alertas' : 'Prévia BigShop pronta',
      message: 'Nenhum produto, variação ou tabela foi alterado. Revise os contadores e erros antes de sincronizar.',
      duration: bigShopDryRun.value?.errors_count ? 0 : undefined,
    })
    await loadPlatforms()
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Falha no dry-run BigShop',
      message: friendlyRequestMessage(requestError, 'Não foi possível executar a prévia segura da BigShop.'),
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
  bigShopDryRun.value = null

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

function dryRunStatusLabel(status: string) {
  return status === 'ready' ? 'Pronto' : 'Com alertas'
}

function friendlyRequestMessage(requestError: any, fallback: string) {
  return requestError.response?.data?.message
    || requestError.response?.data?.errors?.feed_url?.[0]
    || requestError.response?.data?.errors?.url?.[0]
    || requestError.response?.data?.errors?.bigshop?.[0]
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

    <div v-else class="integrations-stack">
      <form class="admin-form integrations-form" @submit.prevent="savePlatform">
        <section class="panel-main integration-section integration-platform-section">
          <div class="subsection-heading">
            <h2>Plataforma</h2>
            <span>{{ selected?.install_mode === 'one_click' ? 'Um clique' : 'Manual' }}</span>
          </div>

          <div class="integration-platform-summary">
            <span class="platform-icon">
              <i class="fa-solid" :class="selected?.icon" aria-hidden="true"></i>
            </span>
            <span>
              <strong>{{ selected?.name }}</strong>
              <small>{{ selected?.summary }}</small>
            </span>
            <em>{{ statusLabel(selected?.status || form.status) }}</em>
          </div>

          <div v-if="platforms.length > 1 && !isBigShopContract" class="integration-platform-picker" role="list">
            <button
              v-for="platform in platforms"
              :key="platform.key"
              class="platform-card"
              :class="{ active: selected?.key === platform.key }"
              type="button"
              role="listitem"
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
          </div>
        </section>

        <section class="panel-main integration-section">
          <div class="subsection-heading">
            <h2>Conexão</h2>
            <span>Credenciais e catálogo</span>
          </div>

          <div class="form-grid integration-connection-grid">
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
        </section>

        <section class="panel-main integration-section">
          <div class="subsection-heading">
            <h2>Validação da instalação</h2>
            <span>{{ validation?.status ? statusLabel(validation.status) : 'Pendente' }}</span>
          </div>

          <div class="form-grid integration-validation-grid">
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
        </section>

        <section class="panel-main integration-section">
          <div class="subsection-heading">
            <h2>Instalação no produto</h2>
            <span>Página de produto</span>
          </div>

          <div class="integration-guide-columns">
            <div>
              <h3>Passo a passo</h3>
              <ol class="guide-steps">
                <li v-for="step in selected?.guide.steps" :key="step">{{ step }}</li>
              </ol>
            </div>
            <div>
              <h3>Onde instalar o widget</h3>
              <ul class="placement-steps">
                <li v-for="step in installationPlacementSteps" :key="step">{{ step }}</li>
              </ul>
            </div>
          </div>

          <pre class="guide-snippet compact-snippet"><code>window.ProvadorVirtual?.reload({
  productId: 'ID_DO_PRODUTO',
  variantId: 'ID_DA_GRADE',
  sku: 'SKU_DA_GRADE'
})</code></pre>
        </section>

        <section class="panel-main integration-section">
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
        </section>

        <section class="panel-main integration-section">
          <div class="subsection-heading">
            <h2>Snippet</h2>
            <button class="icon-link" type="button" :title="copied ? 'Copiado' : 'Copiar snippet'" @click="copyGuideSnippet">
              <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
            </button>
          </div>
          <pre class="guide-snippet"><code>{{ selected?.guide.snippet }}</code></pre>
        </section>

        <section class="panel-main integration-section">
          <div class="subsection-heading">
            <h2>Ações</h2>
            <span>Salvar e sincronizar</span>
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
                  @click="dryRunBigShop"
                >
                  <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                  Prévia segura
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
        </section>

        <section v-if="integrationReport" class="panel-main integration-section">
          <div class="subsection-heading">
            <h2>Resultado da sincronização</h2>
            <span>Última ação</span>
          </div>
          <div class="integration-report">
            <span v-for="(value, key) in integrationReport" :key="key">
              <strong>{{ value }}</strong>
              <small>{{ key }}</small>
            </span>
          </div>
        </section>

        <section v-if="bigShopDryRun" class="panel-main integration-section bigshop-dry-run-panel">
          <div class="subsection-heading">
            <h2>Prévia BigShop</h2>
            <span>{{ dryRunStatusLabel(bigShopDryRun.status) }}</span>
          </div>

          <div class="summary-strip">
            <span>
              <strong>{{ bigShopDryRun.products_read }}</strong>
              <small>produtos lidos</small>
            </span>
            <span>
              <strong>{{ bigShopDryRun.grids_read }}</strong>
              <small>grades lidas</small>
            </span>
            <span>
              <strong>{{ bigShopDryRun.grids_joined }}</strong>
              <small>grades cruzadas</small>
            </span>
            <span>
              <strong>{{ bigShopDryRun.sizes_detected }}</strong>
              <small>tamanhos</small>
            </span>
            <span>
              <strong>{{ bigShopDryRun.errors_count }}</strong>
              <small>erros</small>
            </span>
            <span>
              <strong>{{ bigShopDryRun.warnings_count }}</strong>
              <small>alertas</small>
            </span>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>SKU</th>
                  <th>Categoria</th>
                  <th>Regras</th>
                  <th>Grades</th>
                  <th>Tamanhos</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product in bigShopDryRun.sample_products" :key="product.external_product_id">
                  <td>
                    <strong>{{ product.name || product.external_product_id }}</strong>
                    <small>{{ product.external_product_id }}</small>
                  </td>
                  <td>{{ product.sku || '-' }}</td>
                  <td>{{ product.category || '-' }}</td>
                  <td>
                    <span class="dry-run-rule-tags">
                      <em>{{ product.mapped?.brand || product.brand || 'marca' }}</em>
                      <em>{{ product.mapped?.gender || product.gender || 'gênero' }}</em>
                      <em>{{ product.mapped?.fit_profile || 'modelagem' }}</em>
                    </span>
                  </td>
                  <td>{{ product.grid_count }}</td>
                  <td>{{ product.sizes.length ? product.sizes.join(', ') : '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="bigShopDryRun.issues.length" class="dry-run-issues">
            <article v-for="issue in bigShopDryRun.issues" :key="`${issue.code}-${issue.product_id || issue.grid_id || issue.message}`">
              <i class="fa-solid" :class="issue.severity === 'error' ? 'fa-circle-xmark' : 'fa-circle-exclamation'" aria-hidden="true"></i>
              <span>
                <strong>{{ issue.product_name || issue.product_id || issue.grid_id || issue.code }}</strong>
                <small>{{ issue.message }}</small>
              </span>
              <em :class="issue.severity">{{ issue.severity === 'error' ? 'Erro' : 'Alerta' }}</em>
            </article>
          </div>
        </section>

        <section v-if="selected?.key === 'bigshop'" class="panel-main integration-section">
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
        </section>
      </form>
    </div>
  </section>
</template>
