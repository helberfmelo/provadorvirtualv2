<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import OperationalStateCard from '../components/OperationalStateCard.vue'
import { api } from '../services/api'
import { showFeedback } from '../services/saveFeedback'
import type { IntegrationChangeRequest } from '../services/saasTypes'
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
  access_token_hint?: string | null
  webhook_secret_hint?: string | null
  last_error: string | null
  updated_at?: string | null
}

type ApiExample = {
  label: string
  method: string
  path: string
  description: string
}

type WebhookGuide = {
  enabled: boolean
  test_endpoint: string
  signature_header: string
  signature_algorithm: string
  secret_storage: string
  events: string[]
  notes: string
}

type GtmGuide = {
  default: boolean
  recommended: boolean
  trigger: string
  required_data: string[]
  when_to_use: string
  validation: string
}

type PlatformGuide = {
  steps: string[]
  snippet: string
  checklist: Array<{ key: string; label: string }>
  data_support: Record<string, string>
  api_examples?: ApiExample[]
  webhook?: WebhookGuide
  gtm?: GtmGuide
}

type ConnectionFieldKey = 'external_store_id' | 'api_base_url' | 'feed_url' | 'access_token' | 'webhook_secret'

type PlatformField = {
  label: string
  help: string
  placeholder?: string
  secret?: boolean
  required?: boolean
}

type PlatformSetup = {
  connection_fields: string[]
  catalog_flow: string
  product_page: string
  tracking: string
  fields?: Partial<Record<ConnectionFieldKey, PlatformField>>
}

type Platform = {
  key: string
  name: string
  priority: boolean
  icon: string
  install_mode: string
  status: string
  summary: string
  setup: PlatformSetup
  guide: PlatformGuide
  has_connection: boolean
  connection: PlatformConnection | null
  diagnostics?: {
    last_install_validation?: ValidationResult | null
    recent_webhook_logs?: WebhookLog[]
  }
}

type ValidationCheck = {
  key: string
  label: string
  status: 'passed' | 'warning' | 'failed'
  action: string | null
}

type InstallationDiagnostics = {
  container?: { found: boolean; selector: string | null }
  script?: { found: boolean; src: string | null }
  platform?: { found: boolean; value: string | null; expected: string }
  product_id?: { found: boolean; value: string | null }
  variant_id?: { found: boolean; value: string | null }
  sku?: { found: boolean; value: string | null }
  buttons?: { found: boolean; labels: string[] }
  gtm?: { detected: boolean }
}

type ValidationResult = {
  status: 'passed' | 'warning' | 'failed'
  url: string
  http_status: number | null
  checks: ValidationCheck[] | Record<string, ValidationCheck['status']>
  diagnostics?: InstallationDiagnostics
  checked_at?: string | null
}

type WebhookLog = {
  id: number
  status: string
  event_type: string
  signature_masked: string | null
  store_id: string | null
  payload_keys: string[]
  occurred_at: string | null
}

type WebhookTestResult = {
  status: string
  platform: string
  has_webhook_secret: boolean
  signature_header: string
  signature_masked: string
  payload: Record<string, string | null>
  log: WebhookLog
  recent_logs: WebhookLog[]
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
const platforms = ref<Platform[]>([])
const selectedKey = ref('bigshop')
const loading = ref(false)
const saving = ref(false)
const savingCompanyPlatform = ref(false)
const requestingPlatformChange = ref(false)
const changeRequestModalOpen = ref(false)
const openingChangeRequestLink = ref(false)
const running = ref(false)
const validating = ref(false)
const testingWebhook = ref(false)
const copied = ref(false)
const loadError = ref('')
const integrationReport = ref<Record<string, number | string> | null>(null)
const validation = ref<ValidationResult | null>(null)
const webhookTest = ref<WebhookTestResult | null>(null)
const bigShopActivations = ref<BigShopActivation[]>([])
const bigShopDryRun = ref<BigShopDryRunResult | null>(null)
const currentChangeRequest = ref<IntegrationChangeRequest | null>(null)

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
const companyPlatformForm = reactive({
  platform: auth.activeCompany?.platform || 'custom',
})
const changeRequestForm = reactive({
  to_platform: 'shopify',
  accepted_terms: false,
})

const selected = computed(() => platforms.value.find((platform) => platform.key === selectedKey.value) || platforms.value[0] || null)
const selectedPlatformName = computed(() => selected.value?.name || (selected.value?.key === 'bigshop' ? 'BigShop' : 'Plataforma'))
const selectedPlatformSummary = computed(() => selected.value?.summary || (selected.value?.key === 'bigshop'
  ? 'Integração BigShop para API, XML/feed, validação de instalação e sincronização de catálogo.'
  : 'Configure credenciais, validação e sincronização desta plataforma.'))
const selectedPlatformIcon = computed(() => selected.value?.icon || (selected.value?.key === 'bigshop' ? 'fa-bolt' : 'fa-plug'))
const selectedStatus = computed(() => selected.value?.status || form.status || 'draft')
const platformModeLabel = computed(() => selected.value?.install_mode === 'one_click' ? 'Um clique' : 'Manual')
const platformSetupItems = computed(() => {
  const setup = selected.value?.setup

  if (!setup) {
    return []
  }

  return [
    {
      icon: 'fa-key',
      label: 'Conexão',
      text: setup.connection_fields.join(', '),
    },
    {
      icon: 'fa-database',
      label: 'Catálogo',
      text: setup.catalog_flow,
    },
    {
      icon: 'fa-window-maximize',
      label: 'Página de produto',
      text: setup.product_page,
    },
    {
      icon: 'fa-chart-line',
      label: 'Aprendizado',
      text: setup.tracking,
    },
  ]
})
const platformNextStep = computed(() => {
  if (selectedStatus.value === 'connected') {
    return 'Conexão validada. Próximo passo: sincronizar catálogo, revisar erros e manter o widget publicado nos produtos com tabela.'
  }

  if (selectedStatus.value === 'configured') {
    return selected.value?.key === 'bigshop'
      ? 'Credenciais mínimas salvas. Próximo passo: testar conexão, rodar Prévia segura e revisar product_grids antes da sincronização real.'
      : 'Configuração mínima salva. Próximo passo: validar a página de produto e sincronizar XML/feed ou API.'
  }

  if (selectedStatus.value === 'disabled') {
    return 'Integração pausada. Reative o status quando a loja estiver pronta para validar e sincronizar.'
  }

  if (selectedStatus.value === 'error') {
    return 'Existe falha registrada. Revise credenciais, domínio, feed e último erro antes de tentar novamente.'
  }

  return 'Informe os dados mínimos da plataforma para liberar validação, prévia e sincronização.'
})
const guideSteps = computed(() => {
  if (selected.value?.guide?.steps?.length) {
    return selected.value.guide.steps
  }

  return [
    'Salve a conexão da plataforma com loja, API, XML/feed e credenciais.',
    'Valide uma URL pública de produto para confirmar container, script e identificadores.',
    selected.value?.key === 'bigshop'
      ? 'Execute a prévia segura BigShop antes de sincronizar produtos pela API.'
      : 'Sincronize o XML/feed e revise os produtos importados.',
  ]
})
const dataSupportEntries = computed<[string, string][]>(() => Object.entries(selected.value?.guide?.data_support || {}))
const guideSnippet = computed(() => selected.value?.guide?.snippet || '')
const apiExamples = computed(() => selected.value?.guide?.api_examples || [])
const webhookGuide = computed(() => selected.value?.guide?.webhook || null)
const gtmGuide = computed(() => selected.value?.guide?.gtm || null)
const activeValidation = computed(() => validation.value || selected.value?.diagnostics?.last_install_validation || null)
const activeValidationChecks = computed<ValidationCheck[]>(() => {
  const checks = activeValidation.value?.checks

  if (Array.isArray(checks)) {
    return checks
  }

  if (!checks || !selected.value?.guide?.checklist) {
    return []
  }

  return selected.value.guide.checklist.map((item) => ({
    key: item.key,
    label: item.label,
    status: checks[item.key] || 'warning',
    action: null,
  }))
})
const validationDiagnostics = computed(() => activeValidation.value?.diagnostics || null)
const validationDiagnosticItems = computed(() => {
  const diagnostics = validationDiagnostics.value

  if (!diagnostics) {
    return []
  }

  return [
    { key: 'container', label: 'Container', value: diagnostics.container?.selector || '-', ok: Boolean(diagnostics.container?.found) },
    { key: 'script', label: 'Script', value: diagnostics.script?.src || '-', ok: Boolean(diagnostics.script?.found) },
    { key: 'product', label: 'Produto', value: diagnostics.product_id?.value || '-', ok: Boolean(diagnostics.product_id?.found) },
    { key: 'variant', label: 'Variação', value: diagnostics.variant_id?.value || '-', ok: Boolean(diagnostics.variant_id?.found) },
    { key: 'sku', label: 'SKU', value: diagnostics.sku?.value || '-', ok: Boolean(diagnostics.sku?.found) },
    { key: 'buttons', label: 'Botões', value: diagnostics.buttons?.labels?.join(', ') || '-', ok: Boolean(diagnostics.buttons?.found) },
  ]
})
const recentWebhookLogs = computed(() => webhookTest.value?.recent_logs || selected.value?.diagnostics?.recent_webhook_logs || [])
const canTestWebhook = computed(() => Boolean(showConnectionField('webhook_secret') && selected.value?.connection?.has_webhook_secret))
const showXmlFeedAction = computed(() => showConnectionField('feed_url'))
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
const isBigShopPlatform = computed(() => {
  return auth.activeCompany?.platform === 'bigshop'
    || (platforms.value.length === 1 && platforms.value[0]?.key === 'bigshop')
})
const hasBigShopDiscount = computed(() => Boolean(auth.activeCompany?.bigshop_discount_active))
const companyPlatformLabel = computed(() => platformLabel(auth.activeCompany?.platform || companyPlatformForm.platform))
const companyPlatformOptions = computed(() => {
  return platformOptions
})
const changeRequestPlatformOptions = computed(() => platformOptions.filter((platform) => platform.value !== 'bigshop'))
const hasOpenChangeRequest = computed(() => Boolean(
  currentChangeRequest.value
    && ['pending', 'payment_requested', 'approved'].includes(currentChangeRequest.value.status),
))
const changeRequestActionLabel = computed(() => hasOpenChangeRequest.value ? 'Acompanhar troca' : 'Mudar integração')
const changeFinancialSummary = computed(() => currentChangeRequest.value?.financial_summary || {
  currency: 'BRL',
  from_label: 'Cliente BigShop',
  to_label: 'Qualquer plataforma',
  annual_from_monthly_cents: 34990,
  annual_to_monthly_cents: 44980,
  annual_monthly_difference_cents: 9990,
  annual_total_difference_cents: 119880,
  monthly_from_cents: 38980,
  monthly_to_cents: 48980,
  monthly_difference_cents: 10000,
  short_text: 'Referência anual: R$ 349,90/mês para R$ 449,80/mês. Diferença estimada: R$ 99,90/mês, sujeita ao ciclo e pagamento.',
})
const changeRequestSteps = computed(() => {
  if (!currentChangeRequest.value) {
    return [
      'Você escolhe a nova plataforma e aceita os termos de troca.',
      'O SaaS revisa o impacto comercial e envia link de pagamento quando houver diferença.',
      'Depois da confirmação, a nova integração é liberada e o benefício BigShop é encerrado para esta empresa.',
    ]
  }

  if (currentChangeRequest.value.status === 'payment_requested') {
    return [
      'O SaaS enviou a etapa comercial da troca.',
      'Após a confirmação do pagamento, a equipe conclui a solicitação.',
      'A nova plataforma aparece no painel de integrações quando a troca for aplicada.',
    ]
  }

  if (currentChangeRequest.value.status === 'completed') {
    return [
      'A troca já foi concluída pelo SaaS.',
      'Configure a nova plataforma no painel de integrações.',
      'O benefício comercial BigShop não fica ativo para esta empresa após a troca.',
    ]
  }

  if (currentChangeRequest.value.status === 'cancelled') {
    return [
      'A solicitação anterior foi cancelada.',
      'Você pode abrir uma nova solicitação se ainda precisar sair do benefício BigShop.',
      'O contrato BigShop continua limitado à integração BigShop enquanto o benefício estiver ativo.',
    ]
  }

  return [
    'A solicitação está na fila de revisão SaaS.',
    'A equipe pode enviar um link de pagamento ou aprovar a troca diretamente.',
    'A plataforma só muda quando o SaaS concluir e aplicar a solicitação.',
  ]
})
const canEditCompanyPlatform = computed(() => !hasBigShopDiscount.value && auth.canEdit('integrations'))
const showReadOnlyIntegrationNote = computed(() => !hasBigShopDiscount.value && !auth.canEdit('integrations'))
const companyPlatformHelp = computed(() => {
  if (hasBigShopDiscount.value) {
    return 'Esta loja usa BigShop com benefício comercial ativo. Para trocar por outra plataforma, solicite a mudança e o SaaS fará a revisão de diferença de plano.'
  }

  if (isBigShopPlatform.value) {
    return 'A loja usa BigShop como plataforma operacional. Como não há benefício comercial travado aqui, a troca pode ser feita diretamente.'
  }

  return 'O lojista informa aqui quando precisa trocar a plataforma operacional. O SaaS também pode alterar em Empresas > editar.'
})
const fieldHelp: Record<string, string> = {
  external_store_id: 'Informe o identificador da loja na plataforma. Na BigShop, use o store_id da loja.',
  api_base_url: 'Informe a URL base da API quando a plataforma tiver API autenticada. Na BigShop, use a API V3.',
  feed_url: 'Informe a URL pública do catálogo XML/Google Merchant. Na BigShop, normalmente é o domínio da loja seguido de /feed.xml.',
  status: 'Controle o estado operacional desta integração no Provador Virtual.',
  access_token: 'Cole o token ou chave de API da plataforma. O valor fica criptografado e não volta a aparecer no formulário.',
  webhook_secret: 'Informe o segredo usado para validar webhooks assinados, quando a plataforma enviar eventos ao Provador Virtual.',
  validation_url: 'Informe uma página pública de produto para confirmar se o container, script e identificadores do widget foram instalados.',
  validation_action: 'Rode a validação da página informada e veja o checklist técnico de instalação.',
}

const defaultConnectionFields: Record<ConnectionFieldKey, PlatformField> = {
  external_store_id: {
    label: 'Loja',
    help: fieldHelp.external_store_id,
    placeholder: 'loja-ou-dominio',
  },
  api_base_url: {
    label: 'URL da API',
    help: fieldHelp.api_base_url,
    placeholder: 'https://api.loja.com.br',
  },
  feed_url: {
    label: 'URL do XML/feed',
    help: fieldHelp.feed_url,
    placeholder: 'https://loja.com.br/feed.xml',
  },
  access_token: {
    label: 'Token',
    help: fieldHelp.access_token,
    secret: true,
  },
  webhook_secret: {
    label: 'Webhook secret',
    help: fieldHelp.webhook_secret,
    secret: true,
  },
}

onMounted(() => {
  loadPlatforms()
})

watch(() => auth.activeCompany?.platform, (platform) => {
  companyPlatformForm.platform = platform || 'custom'
}, { immediate: true })

watch(() => auth.activeCompany?.id, (companyId, previousCompanyId) => {
  if (!companyId || companyId === previousCompanyId) {
    return
  }

  loadPlatforms()
})

async function loadPlatforms() {
  loading.value = true
  loadError.value = ''

  try {
    const { data } = await api.get('/integrations')
    platforms.value = Array.isArray(data.data) ? data.data : []

    if (!platforms.value.length) {
      selectedKey.value = ''
      fillForm(null)
      return
    }

    const preferredKey = preferredPlatformKey()
    if (
      !platforms.value.find((platform) => platform.key === selectedKey.value)
      || (selectedKey.value === 'bigshop' && preferredKey !== 'bigshop')
    ) {
      selectedKey.value = preferredKey
    }

    fillForm()
    await loadBigShopActivations()
    await loadCurrentChangeRequest()
  } catch (requestError: any) {
    platforms.value = []
    selectedKey.value = ''
    fillForm(null)
    loadError.value = friendlyRequestMessage(requestError, 'Não foi possível carregar as integrações da empresa ativa.')
  } finally {
    loading.value = false
  }
}

function selectPlatform(platform: Platform) {
  selectedKey.value = platform.key
  integrationReport.value = null
  validation.value = null
  webhookTest.value = null
  bigShopDryRun.value = null
  fillForm(platform)
  loadBigShopActivations()
}

function fillForm(platform: Platform | null = selected.value) {
  form.external_store_id = platform?.connection?.external_store_id || ''
  form.api_base_url = platform?.connection?.api_base_url || ''
  form.feed_url = platform?.connection?.feed_url || ''
  form.feed_format = platform?.connection?.feed_format || 'google_xml'
  form.status = platform?.status || platform?.connection?.status || 'draft'
  form.access_token = ''
  form.webhook_secret = ''
}

async function saveCompanyPlatform() {
  if (!canEditCompanyPlatform.value || companyPlatformForm.platform === auth.activeCompany?.platform) {
    return
  }

  savingCompanyPlatform.value = true

  try {
    await api.patch('/merchant/company-platform', {
      platform: companyPlatformForm.platform,
    })
    selectedKey.value = companyPlatformForm.platform
    await auth.loadMe()
    await loadPlatforms()
    showFeedback({
      status: 'success',
      title: 'Plataforma atualizada',
      message: 'As instruções e opções de integração foram atualizadas para a plataforma da loja.',
    })
  } catch (requestError: any) {
    companyPlatformForm.platform = auth.activeCompany?.platform || 'custom'
    showFeedback({
      status: 'error',
      title: 'Não foi possível salvar a plataforma',
      message: friendlyRequestMessage(requestError, 'Não foi possível alterar a plataforma da loja.'),
    })
  } finally {
    savingCompanyPlatform.value = false
  }
}

function openChangeRequestModal() {
  changeRequestForm.to_platform = currentChangeRequest.value?.to_platform
    || changeRequestPlatformOptions.value[0]?.value
    || 'custom'
  changeRequestForm.accepted_terms = false
  changeRequestModalOpen.value = true
}

async function requestPlatformChange() {
  if (!changeRequestForm.accepted_terms) {
    return
  }

  requestingPlatformChange.value = true

  try {
    const { data } = await api.post('/merchant/integration-change-requests', {
      to_platform: changeRequestForm.to_platform,
      accepted_terms: changeRequestForm.accepted_terms,
    })
    currentChangeRequest.value = data.data || null
    changeRequestModalOpen.value = false
    showFeedback({
      status: 'success',
      title: 'Solicitação enviada',
      message: 'O SaaS recebeu o pedido de troca e vai revisar a diferença comercial antes de liberar a nova integração.',
    })
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Não foi possível solicitar',
      message: friendlyRequestMessage(requestError, 'Não foi possível registrar a solicitação de troca.'),
    })
  } finally {
    requestingPlatformChange.value = false
  }
}

async function openCommercialPaymentLink() {
  if (!currentChangeRequest.value?.payment_link_access || openingChangeRequestLink.value) {
    return
  }

  openingChangeRequestLink.value = true

  try {
    const { data } = await api.post('/billing/payment-links/resolve', currentChangeRequest.value.payment_link_access)
    const url = data.data?.url

    if (!url) {
      throw new Error('Link comercial indisponível.')
    }

    window.open(url, '_blank', 'noopener')
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Não foi possível abrir o link',
      message: friendlyRequestMessage(requestError, 'Não foi possível abrir o link comercial agora.'),
    })
  } finally {
    openingChangeRequestLink.value = false
  }
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

async function loadCurrentChangeRequest() {
  if (!hasBigShopDiscount.value) {
    currentChangeRequest.value = null
    return
  }

  const { data } = await api.get('/merchant/integration-change-requests/current').catch(() => ({ data: { data: null } }))
  currentChangeRequest.value = data.data || null
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

async function testWebhook() {
  if (!selected.value || !canTestWebhook.value) {
    showFeedback({
      status: 'info',
      title: 'Webhook secret pendente',
      message: 'Salve um segredo de webhook antes de rodar o teste. O campo é write-only: para rotacionar, cole um novo valor e salve.',
    })
    return
  }

  testingWebhook.value = true
  webhookTest.value = null

  try {
    const { data } = await api.post(`/integrations/${selected.value.key}/test-webhook`)
    webhookTest.value = data.data
    showFeedback({
      status: 'success',
      title: 'Webhook testado',
      message: 'O teste assinou um payload de exemplo com o segredo armazenado e registrou log sem expor o segredo.',
    })
    await loadPlatforms()
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Falha ao testar webhook',
      message: friendlyRequestMessage(requestError, 'Não foi possível testar o webhook desta plataforma.'),
    })
  } finally {
    testingWebhook.value = false
  }
}

async function copyGuideSnippet() {
  if (!guideSnippet.value) {
    return
  }

  await navigator.clipboard.writeText(guideSnippet.value)
  copied.value = true
  window.setTimeout(() => {
    copied.value = false
  }, 1800)
}

function statusLabel(status: string) {
  return {
    draft: 'Pendente',
    configured: 'Configurada',
    connected: 'Conectada',
    disabled: 'Pausada',
    error: 'Erro',
    passed: 'Validado',
    warning: 'Atenção',
    failed: 'Falhou',
  }[status] || status
}

function platformLabel(platform: string) {
  return platformOptions.find((option) => option.value === platform)?.label || 'Personalizada'
}

function connectionField(key: ConnectionFieldKey): PlatformField {
  return selected.value?.setup?.fields?.[key] || defaultConnectionFields[key]
}

function showConnectionField(key: ConnectionFieldKey) {
  return Boolean((selected.value?.setup?.fields || defaultConnectionFields)[key])
}

function connectionFieldHelp(key: ConnectionFieldKey) {
  return connectionField(key).help || fieldHelp[key]
}

function dataSupportLabel(key: string) {
  const labels: Record<string, string> = {
    product_id: 'Produto',
    variant_id: 'Variação',
    sku: 'SKU',
    size_change: 'Troca de tamanho',
    xml_feed: 'XML/feed',
    feed_api: 'API/feed',
    orders_returns: 'Pedidos/devoluções',
  }

  return labels[key] || key
}

function preferredPlatformKey() {
  const platform = auth.activeCompany?.platform || companyPlatformForm.platform

  return platforms.value.find((item) => item.key === platform)?.key || platforms.value[0]?.key || ''
}

function checkStatus(key: string) {
  const checks = activeValidation.value?.checks

  if (Array.isArray(checks)) {
    return checks.find((check) => check.key === key)?.status || 'pending'
  }

  return checks?.[key] || 'pending'
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

function money(cents: number) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(cents / 100)
}

function shortDate(value: string | null | undefined) {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function friendlyRequestMessage(requestError: any, fallback: string) {
  return requestError.response?.data?.message
    || requestError.response?.data?.errors?.feed_url?.[0]
    || requestError.response?.data?.errors?.url?.[0]
    || requestError.response?.data?.errors?.bigshop?.[0]
    || requestError.response?.data?.errors?.platform?.[0]
    || requestError.response?.data?.errors?.to_platform?.[0]
    || requestError.response?.data?.errors?.accepted_terms?.[0]
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

    <p v-if="isBigShopPlatform" class="info-message">
      {{ hasBigShopDiscount
        ? 'Benefício BigShop ativo: este painel exibe a integração BigShop. Para mudar, solicite a troca no painel.'
        : 'Integração BigShop ativa: este painel exibe a configuração específica da BigShop.' }}
    </p>

    <OperationalStateCard
      v-if="showReadOnlyIntegrationNote"
      tone="permission"
      eyebrow="Modo leitura"
      title="Seu acesso pode revisar integrações, mas não pode salvar conexão ou validação."
      description="Status, instruções e histórico continuam visíveis. Para editar feed, token, plataforma ou instalação, entre com um perfil com permissão de edição."
      compact
    />

    <section v-if="hasBigShopDiscount" class="panel-main bigshop-governance-panel">
      <div>
        <span class="eyebrow">Governança BigShop</span>
        <h2>Integração protegida pelo benefício comercial</h2>
        <p>
          O preço BigShop é exclusivo para lojas operando na BigShop. A mudança para outra plataforma passa por revisão
          comercial, aceite eletrônico e aplicação pelo SaaS, sem expor token ou credencial no portal.
        </p>
      </div>
      <div class="bigshop-governance-status">
        <span v-if="currentChangeRequest" class="status-pill warning">{{ currentChangeRequest.status_label }}</span>
        <small v-if="currentChangeRequest">
          {{ currentChangeRequest.to_platform_label }} · {{ shortDate(currentChangeRequest.requested_at) }}
        </small>
        <button class="btn btn-secondary btn-compact" type="button" @click="openChangeRequestModal">
          <i class="fa-solid fa-right-left" aria-hidden="true"></i>
          {{ changeRequestActionLabel }}
        </button>
      </div>
    </section>

    <OperationalStateCard
      v-if="loading"
      tone="loading"
      eyebrow="Integrações"
      title="Carregando plataformas da empresa"
      description="Estamos trazendo conexão, catálogo, validação e guias da integração ativa."
      compact
    />
    <OperationalStateCard
      v-else-if="loadError"
      tone="error"
      eyebrow="Integrações"
      :title="loadError"
      description="Atualize a página ou revise a empresa ativa antes de salvar credenciais e validar a instalação."
      action-label="Tentar novamente"
      compact
      @action="loadPlatforms"
    />
    <OperationalStateCard
      v-else-if="!platforms.length"
      tone="empty"
      eyebrow="Integrações"
      title="Nenhuma plataforma disponível para esta empresa."
      description="Revise o cadastro da empresa no SaaS ou atualize a página para recarregar a configuração operacional."
      action-label="Atualizar página"
      compact
      @action="loadPlatforms"
    />

    <div v-else class="integrations-stack">
      <form class="admin-form integrations-form" @submit.prevent="savePlatform">
        <section class="panel-main integration-section integration-platform-section">
          <div class="subsection-heading">
            <h2>Plataforma</h2>
            <span>{{ platformModeLabel }}</span>
          </div>

          <div class="company-platform-source">
            <div>
              <small>Plataforma da loja</small>
              <strong>{{ companyPlatformLabel }}</strong>
              <span>{{ companyPlatformHelp }}</span>
            </div>
            <div class="company-platform-controls">
              <select
                v-model="companyPlatformForm.platform"
                :disabled="!canEditCompanyPlatform || savingCompanyPlatform"
              >
                <option v-for="platform in companyPlatformOptions" :key="platform.value" :value="platform.value">
                  {{ platform.label }}
                </option>
              </select>
              <button
                v-if="canEditCompanyPlatform"
                class="btn btn-secondary btn-compact"
                type="button"
                :disabled="savingCompanyPlatform || companyPlatformForm.platform === auth.activeCompany?.platform"
                @click="saveCompanyPlatform"
              >
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                {{ savingCompanyPlatform ? 'Salvando...' : 'Salvar plataforma' }}
              </button>
              <button
                v-else-if="hasBigShopDiscount"
                class="btn btn-secondary btn-compact"
                type="button"
                @click="openChangeRequestModal"
              >
                <i class="fa-solid fa-right-left" aria-hidden="true"></i>
                {{ changeRequestActionLabel }}
              </button>
            </div>
          </div>

          <div class="integration-platform-summary">
            <span class="platform-icon">
              <i class="fa-solid" :class="selectedPlatformIcon" aria-hidden="true"></i>
            </span>
            <span class="integration-platform-copy">
              <strong>{{ selectedPlatformName }}</strong>
              <small>{{ selectedPlatformSummary }}</small>
            </span>
            <em class="integration-status-pill" :class="selectedStatus">{{ statusLabel(selectedStatus) }}</em>
          </div>

          <div v-if="platformSetupItems.length" class="integration-platform-details">
            <article v-for="item in platformSetupItems" :key="item.label">
              <i class="fa-solid" :class="item.icon" aria-hidden="true"></i>
              <span>
                <small>{{ item.label }}</small>
                <strong>{{ item.text }}</strong>
              </span>
            </article>
          </div>

          <p class="platform-next-step">
            {{ platformNextStep }}
          </p>

          <p v-if="selected?.key === 'bigshop'" class="platform-reference-note">
            BigShop exige Store ID para API, token x-api para leitura autenticada e atenção especial às grades em product_grids antes de importar medidas.
          </p>

          <div v-if="selected?.key !== 'bigshop'" class="platform-reference-note">
            As instruções mudam conforme a plataforma: catálogo por API/feed, instalação na PDP e tracking de pedidos seguem contratos diferentes.
          </div>

          <div v-if="platforms.length > 1 && !isBigShopPlatform" class="integration-platform-picker" role="list">
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
            <label v-if="showConnectionField('external_store_id')">
              <span class="field-label">
                <span class="info-tooltip" tabindex="0" role="button" :aria-label="connectionFieldHelp('external_store_id')" :data-tooltip="connectionFieldHelp('external_store_id')">i</span>
                {{ connectionField('external_store_id').label }}
              </span>
              <input v-model="form.external_store_id" maxlength="120" :placeholder="connectionField('external_store_id').placeholder" :required="connectionField('external_store_id').required" />
            </label>
            <label v-if="showConnectionField('api_base_url')">
              <span class="field-label">
                <span class="info-tooltip" tabindex="0" role="button" :aria-label="connectionFieldHelp('api_base_url')" :data-tooltip="connectionFieldHelp('api_base_url')">i</span>
                {{ connectionField('api_base_url').label }}
              </span>
              <input v-model="form.api_base_url" type="url" maxlength="255" :placeholder="connectionField('api_base_url').placeholder" :required="connectionField('api_base_url').required" />
            </label>
            <label v-if="showConnectionField('feed_url')">
              <span class="field-label">
                <span class="info-tooltip" tabindex="0" role="button" :aria-label="connectionFieldHelp('feed_url')" :data-tooltip="connectionFieldHelp('feed_url')">i</span>
                {{ connectionField('feed_url').label }}
              </span>
              <input v-model="form.feed_url" type="text" inputmode="url" maxlength="255" :placeholder="connectionField('feed_url').placeholder" :required="connectionField('feed_url').required" />
            </label>
            <label>
              <span class="field-label">
                <span class="info-tooltip" tabindex="0" role="button" :aria-label="fieldHelp.status" :data-tooltip="fieldHelp.status">i</span>
                Status
              </span>
              <select v-model="form.status">
                <option value="draft">Pendente</option>
                <option value="configured">Configurada</option>
                <option value="connected">Conectada</option>
                <option value="disabled">Pausada</option>
              </select>
            </label>
            <label v-if="showConnectionField('access_token')">
              <span class="field-label">
                <span class="info-tooltip" tabindex="0" role="button" :aria-label="connectionFieldHelp('access_token')" :data-tooltip="connectionFieldHelp('access_token')">i</span>
                {{ connectionField('access_token').label }}
              </span>
              <input v-model="form.access_token" :type="connectionField('access_token').secret ? 'password' : 'text'" autocomplete="off" :placeholder="connectionField('access_token').placeholder" :required="connectionField('access_token').required" />
              <small v-if="selected?.connection?.has_access_token" class="secret-field-note">
                Salvo como {{ selected.connection.access_token_hint || '********' }}. Cole um novo valor para rotacionar.
              </small>
            </label>
            <label v-if="showConnectionField('webhook_secret')">
              <span class="field-label">
                <span class="info-tooltip" tabindex="0" role="button" :aria-label="connectionFieldHelp('webhook_secret')" :data-tooltip="connectionFieldHelp('webhook_secret')">i</span>
                {{ connectionField('webhook_secret').label }}
              </span>
              <input v-model="form.webhook_secret" :type="connectionField('webhook_secret').secret ? 'password' : 'text'" autocomplete="off" :placeholder="connectionField('webhook_secret').placeholder" :required="connectionField('webhook_secret').required" />
              <small v-if="selected?.connection?.has_webhook_secret" class="secret-field-note">
                Salvo como {{ selected.connection.webhook_secret_hint || '********' }}. Cole um novo segredo para rotacionar.
              </small>
            </label>
          </div>

          <div class="connection-flags">
            <span v-if="showConnectionField('access_token')" :class="{ on: selected?.connection?.has_access_token }">
              <i class="fa-solid fa-key" aria-hidden="true"></i>
              Token
            </span>
            <span v-if="showConnectionField('webhook_secret')" :class="{ on: selected?.connection?.has_webhook_secret }">
              <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
              Webhook
            </span>
            <span v-if="showConnectionField('feed_url')" :class="{ on: Boolean(selected?.connection?.feed_url) }">
              <i class="fa-solid fa-file-code" aria-hidden="true"></i>
              XML/feed
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
            <span>{{ activeValidation?.status ? statusLabel(activeValidation.status) : 'Pendente' }}</span>
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

          <div v-if="activeValidation" class="validation-result">
            <strong>{{ activeValidation.url }}</strong>
            <small>
              HTTP {{ activeValidation.http_status || '-' }}
              <template v-if="activeValidation.checked_at"> - {{ shortDate(activeValidation.checked_at) }}</template>
            </small>
            <p v-for="check in activeValidationChecks.filter((item) => item.action)" :key="check.key">
              {{ check.action }}
            </p>
          </div>

          <div v-if="validationDiagnosticItems.length" class="install-diagnostics-grid">
            <span v-for="item in validationDiagnosticItems" :key="item.key" :class="{ ok: item.ok }">
              <small>{{ item.label }}</small>
              <strong>{{ item.value }}</strong>
            </span>
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
                <li v-for="step in guideSteps" :key="step">{{ step }}</li>
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

          <div v-if="gtmGuide" class="gtm-guide">
            <div>
              <h3>Google Tag Manager</h3>
              <span>{{ gtmGuide.recommended ? 'Alternativa disponível' : 'Fallback assistido' }}</span>
              <p>{{ gtmGuide.when_to_use }}</p>
            </div>
            <ol class="guide-steps">
              <li>Gatilho: {{ gtmGuide.trigger }}.</li>
              <li>Dados obrigatórios: {{ gtmGuide.required_data.join(', ') }}.</li>
              <li>{{ gtmGuide.validation }}</li>
            </ol>
          </div>
        </section>

        <section v-if="apiExamples.length" class="panel-main integration-section">
          <div class="subsection-heading">
            <h2>API e webhook</h2>
            <span>{{ webhookGuide?.enabled ? 'Assinado' : 'Opcional' }}</span>
          </div>

          <div class="api-example-grid">
            <article v-for="example in apiExamples" :key="`${example.method}-${example.path}`">
              <small>{{ example.label }}</small>
              <strong><em>{{ example.method }}</em> {{ example.path }}</strong>
              <p>{{ example.description }}</p>
            </article>
          </div>

          <div v-if="webhookGuide" class="webhook-test-panel">
            <div>
              <strong>{{ webhookGuide.signature_algorithm }} via {{ webhookGuide.signature_header }}</strong>
              <small>{{ webhookGuide.notes }}</small>
              <small>Teste protegido: {{ webhookGuide.test_endpoint }}</small>
            </div>
            <button class="btn btn-secondary" type="button" :disabled="testingWebhook || !canTestWebhook" @click="testWebhook">
              <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
              {{ testingWebhook ? 'Testando...' : 'Testar webhook' }}
            </button>
          </div>

          <div v-if="webhookTest" class="validation-result">
            <strong>{{ webhookTest.signature_header }} {{ webhookTest.signature_masked }}</strong>
            <small>Payload: {{ Object.keys(webhookTest.payload).join(', ') }}</small>
          </div>

          <div v-if="recentWebhookLogs.length" class="webhook-log-list">
            <article v-for="log in recentWebhookLogs" :key="log.id">
              <span>
                <strong>{{ log.event_type }}</strong>
                <small>{{ log.signature_masked || 'assinatura mascarada' }}</small>
              </span>
              <em>{{ statusLabel(log.status) }}</em>
              <small>{{ log.occurred_at ? shortDate(log.occurred_at) : '-' }}</small>
            </article>
          </div>
        </section>

        <section v-if="dataSupportEntries.length" class="panel-main integration-section">
          <div class="subsection-heading">
            <h2>Dados suportados</h2>
            <span>Matriz</span>
          </div>
          <div class="data-support-grid">
            <span v-for="[key, value] in dataSupportEntries" :key="key">
              <small>{{ dataSupportLabel(key) }}</small>
              <strong>{{ value }}</strong>
            </span>
          </div>
        </section>

        <section v-if="guideSnippet" class="panel-main integration-section">
          <div class="subsection-heading">
            <h2>Snippet</h2>
            <button class="icon-link" type="button" :title="copied ? 'Copiado' : 'Copiar snippet'" @click="copyGuideSnippet">
              <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
            </button>
          </div>
          <pre class="guide-snippet"><code>{{ guideSnippet }}</code></pre>
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
            <div v-if="showXmlFeedAction" class="integration-action-card">
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

    <div
      v-if="changeRequestModalOpen"
      class="widget-preview-modal-layer"
      role="presentation"
      @click.self="changeRequestModalOpen = false"
    >
      <section class="panel-main integration-change-modal" role="dialog" aria-modal="true" aria-labelledby="integration-change-title">
        <div class="subsection-heading">
          <div>
            <h2 id="integration-change-title">Mudar integração</h2>
            <span>Benefício BigShop</span>
          </div>
          <button class="drawer-close" type="button" aria-label="Fechar solicitação" @click="changeRequestModalOpen = false">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>

        <div class="integration-change-copy">
          <p>
            Esta loja usa BigShop com benefício comercial. A troca para outra plataforma é possível, mas precisa de
            revisão do SaaS porque o desconto deixa de valer fora da BigShop.
          </p>
          <p>
            Nenhuma credencial é exibida nesse processo. A plataforma só muda depois do aceite, da revisão comercial e
            da aplicação feita pelo SaaS.
          </p>
        </div>

        <div v-if="currentChangeRequest" class="integration-change-current">
          <span>
            <small>Status</small>
            <strong>{{ currentChangeRequest.status_label }}</strong>
          </span>
          <span>
            <small>Destino</small>
            <strong>{{ currentChangeRequest.to_platform_label }}</strong>
          </span>
          <span>
            <small>Solicitada em</small>
            <strong>{{ shortDate(currentChangeRequest.requested_at) }}</strong>
          </span>
          <button
            v-if="currentChangeRequest.payment_link_available"
            class="btn btn-secondary btn-compact"
            type="button"
            :disabled="openingChangeRequestLink"
            @click="openCommercialPaymentLink"
          >
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
            {{ openingChangeRequestLink ? 'Abrindo...' : 'Abrir pagamento' }}
          </button>
        </div>

        <div class="integration-change-financial">
          <span>
            <small>BigShop anual</small>
            <strong>{{ money(changeFinancialSummary.annual_from_monthly_cents) }}/mês</strong>
          </span>
          <span>
            <small>Nova plataforma anual</small>
            <strong>{{ money(changeFinancialSummary.annual_to_monthly_cents) }}/mês</strong>
          </span>
          <span>
            <small>Diferença estimada</small>
            <strong>{{ money(changeFinancialSummary.annual_monthly_difference_cents) }}/mês</strong>
          </span>
        </div>

        <ol class="integration-change-steps">
          <li v-for="step in changeRequestSteps" :key="step">{{ step }}</li>
        </ol>

        <label>
          Nova integração
          <select v-model="changeRequestForm.to_platform" :disabled="hasOpenChangeRequest">
            <option v-for="platform in changeRequestPlatformOptions" :key="platform.value" :value="platform.value">
              {{ platform.label }}
            </option>
          </select>
        </label>

        <label v-if="!hasOpenChangeRequest" class="settings-check">
          <input v-model="changeRequestForm.accepted_terms" type="checkbox" />
          <span>
            <strong>Concordo com os termos de troca</strong>
            <small>
              Li e aceito os
              <RouterLink to="/termos/troca-bigshop" target="_blank">termos para troca do benefício BigShop</RouterLink>.
            </small>
          </span>
        </label>

        <div class="action-row compact">
          <button v-if="!hasOpenChangeRequest" class="btn btn-primary" type="button" :disabled="requestingPlatformChange || !changeRequestForm.accepted_terms" @click="requestPlatformChange">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
            {{ requestingPlatformChange ? 'Enviando...' : 'Confirmar solicitação' }}
          </button>
          <button class="btn btn-secondary" type="button" @click="changeRequestModalOpen = false">Cancelar</button>
        </div>
      </section>
    </div>
  </section>
</template>
