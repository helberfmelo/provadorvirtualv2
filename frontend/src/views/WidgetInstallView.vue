<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

type PlacementMode = 'inside' | 'after' | 'before'

type PlacementSuggestion = {
  selector: string
  mode: PlacementMode
  label: string
}

type PlacementValidation = {
  status: 'untested' | 'passed' | 'warning' | 'failed'
  url?: string | null
  checked_at?: string | null
  message?: string | null
}

type WidgetPlacement = {
  mode: PlacementMode
  selector: string
  container_id: string
  validation?: PlacementValidation
}

type PlacementPreview = {
  status: 'passed' | 'warning' | 'failed'
  url: string
  http_status: number | null
  platform: string
  placement: {
    mode: PlacementMode
    selector: string
    container_id: string
    label: string
  }
  checks: Array<{
    key: string
    label: string
    status: 'passed' | 'warning' | 'failed'
    action: string | null
  }>
  diagnostics: {
    anchor: { selector: string; matches: number }
    container: { selector: string; matches: number; before_script: boolean | null }
    script: { found: boolean }
    duplicates: { container: boolean }
  }
  checked_at: string
}

type PlatformGuide = {
  key: string
  name: string
  icon: string
  summary: string
  install_mode: string
  guide: {
    steps: string[]
    data_support: Record<string, string>
    placement_label: string
    placement_suggestions: PlacementSuggestion[]
    snippet: string
    reload_snippet: string
  }
}

type WidgetInstall = {
  id: number
  merchant_id: number
  merchant_company_id: number | null
  public_key: string
  platform: string
  allowed_domains: string[]
  theme: {
    primary?: string
    secondary?: string
    accent?: string
    background?: string
    text?: string
    font_family?: string
    font_size?: string
    font_weight?: string
    button_radius?: string
    button_style?: string
    button_background?: string
    button_text?: string
    button_primary_icon?: string
    button_secondary_icon?: string
    button_icon_animation?: boolean | string
    confetti_enabled?: boolean | string
    presentation_mode?: 'drawer' | 'modal' | string
    placement?: WidgetPlacement
  }
  draft?: {
    platform: string
    allowed_domains: string[]
    theme: WidgetInstall['theme']
    is_active: boolean
    has_unpublished_changes: boolean
  }
  is_active: boolean
  published_at?: string | null
  script_url: string
  css_url: string
  snippet: string
  platform_guide?: PlatformGuide
  platform_guides?: PlatformGuide[]
  sample_product?: {
    id: number
    name: string
    sku: string | null
    external_product_id: string | null
  } | null
  company?: {
    id: number
    name: string
    domain: string | null
    platform: string
    external_store_id: string | null
  } | null
}

type WidgetForm = {
  platform: string
  allowed_domains: string
  is_active: boolean
  theme: {
    primary: string
    secondary: string
    accent: string
    background: string
    text: string
    font_family: string
    font_size: string
    font_weight: string
    button_radius: string
    button_style: string
    button_background: string
    button_text: string
    button_primary_icon: string
    button_secondary_icon: string
    button_icon_animation: boolean
    confetti_enabled: boolean
    presentation_mode: 'drawer' | 'modal'
    placement: WidgetPlacement
  }
}

const auth = useAuthStore()
const install = ref<WidgetInstall | null>(null)
const loading = ref(false)
const saving = ref(false)
const copied = ref(false)
const previewDevice = ref<'desktop' | 'mobile'>('desktop')
const previewModalOpen = ref(false)
const savedFormSnapshot = ref('')
const placementTesting = ref(false)
const placementPreview = ref<PlacementPreview | null>(null)
const placementPreviewUrl = ref('')
let confettiPreviewTimeout: number | null = null

const form = reactive<WidgetForm>({
  platform: 'custom',
  allowed_domains: '',
  is_active: true,
  theme: {
    primary: '#0f172a',
    secondary: '#ff4d5e',
    accent: '#ff7a1a',
    background: '#ffffff',
    text: '#111827',
    font_family: 'Manrope, Inter, Arial, sans-serif',
    font_size: '14',
    font_weight: '800',
    button_radius: '8',
    button_style: 'gallery_1_text_icons',
    button_background: '#ff4d5e',
    button_text: '#ffffff',
    button_primary_icon: 'hanger',
    button_secondary_icon: 'ruler',
    button_icon_animation: true,
    confetti_enabled: true,
    presentation_mode: 'drawer',
    placement: {
      mode: 'inside' as PlacementMode,
      selector: '#provador-virtual-container',
      container_id: 'provador-virtual-container',
      validation: {
        status: 'untested' as const,
      },
    },
  },
})

const domains = computed(() => form.allowed_domains
  .split('\n')
  .map((domain) => domain.trim())
  .filter(Boolean))

const platformOptions = computed(() => {
  const guides = install.value?.platform_guides?.length
    ? install.value.platform_guides
    : [
        { key: 'bigshop', name: 'BigShop', icon: 'fa-bolt', summary: 'Integração nativa BigShop.' },
        { key: 'shopify', name: 'Shopify', icon: 'fa-bag-shopping', summary: 'Template Liquid de produto.' },
        { key: 'woocommerce', name: 'WooCommerce', icon: 'fa-cart-shopping', summary: 'Hook ou template WooCommerce.' },
        { key: 'nuvemshop', name: 'Nuvemshop', icon: 'fa-cloud', summary: 'Layout da página de produto.' },
        { key: 'vtex', name: 'VTEX', icon: 'fa-layer-group', summary: 'Bloco ou app de storefront.' },
        { key: 'tray', name: 'Tray', icon: 'fa-store', summary: 'Template de produto Tray.' },
        { key: 'loja_integrada', name: 'Loja Integrada', icon: 'fa-shop', summary: 'HTML/JS do tema.' },
        { key: 'magento', name: 'Magento', icon: 'fa-cubes', summary: 'Bloco catalog_product_view.' },
        { key: 'opencart', name: 'OpenCart', icon: 'fa-box-open', summary: 'Template product.twig.' },
        { key: 'xml_feed', name: 'XML/feed', icon: 'fa-file-code', summary: 'Catálogo por XML ou feed público.' },
        { key: 'api', name: 'API', icon: 'fa-code-branch', summary: 'Conector por API autorizada.' },
        { key: 'custom', name: 'Personalizada', icon: 'fa-code', summary: 'Snippet universal.' },
      ]

  if (isBigShopContract.value) {
    return guides
      .filter((guide) => guide.key === 'bigshop')
      .map((guide) => ({
        value: guide.key,
        label: guide.name,
        icon: guide.icon,
        summary: guide.summary,
      }))
  }

  return guides.map((guide) => ({
    value: guide.key,
    label: guide.name,
    icon: guide.icon,
    summary: guide.summary,
  }))
})

const presentationModeOptions: Array<{ value: 'drawer' | 'modal'; label: string; icon: string }> = [
  { value: 'drawer', label: 'Drawer lateral', icon: 'fa-table-columns' },
  { value: 'modal', label: 'Modal central', icon: 'fa-window-maximize' },
]

const placementModeOptions: Array<{ value: PlacementMode; label: string; icon: string }> = [
  { value: 'inside', label: 'Dentro', icon: 'fa-arrows-to-dot' },
  { value: 'after', label: 'Depois', icon: 'fa-arrow-down' },
  { value: 'before', label: 'Antes', icon: 'fa-arrow-up' },
]

const buttonStyleOptions = [
  {
    value: 'gallery_1_text_icons',
    label: '#1 Texto com ícones',
    icon: 'fa-ruler-combined',
    description: 'Links horizontais limpos com ícones antes do texto.',
  },
  {
    value: 'gallery_2_side_icons',
    label: '#2 Cartão com ícone lateral',
    icon: 'fa-table-list',
    description: 'Cartões verticais com faixa lateral e texto em destaque.',
  },
  {
    value: 'gallery_3_dark_outline',
    icon: 'fa-square',
    label: '#3 Bloco escuro',
    description: 'Blocos sólidos com contraste alto e hover invertido.',
  },
  {
    value: 'gallery_4_underlined_icons',
    label: '#4 Sublinhado com ícones',
    icon: 'fa-underline',
    description: 'Links sublinhados com ícone de apoio antes do texto.',
  },
  {
    value: 'gallery_5_pills',
    label: '#5 Pílulas animadas',
    icon: 'fa-capsules',
    description: 'Pílulas verticais com ícone em movimento.',
  },
  {
    value: 'gallery_6_split_line',
    label: '#6 Linha central',
    icon: 'fa-minus',
    description: 'Links compactos separados por uma linha central.',
  },
  {
    value: 'gallery_7_editorial_links',
    label: '#7 Editorial sublinhado',
    icon: 'fa-link',
    description: 'Texto editorial, leve e sublinhado.',
  },
  {
    value: 'gallery_8_dotted_stack',
    label: '#8 Contorno pontilhado',
    icon: 'fa-grip-lines',
    description: 'Botões verticais com borda pontilhada e hover preenchido.',
  },
  {
    value: 'gallery_9_light_block',
    label: '#9 Bloco claro',
    icon: 'fa-square-full',
    description: 'Blocos claros que ganham preenchimento no hover.',
  },
  {
    value: 'gallery_10_badge_tooltip',
    label: '#10 Selo novo',
    icon: 'fa-certificate',
    description: 'Links com selo novo e tooltip para o VFR.',
  },
]

const buttonStyleCompatibilityOptions = [
  {
    value: 'gallery_11_icon_chips',
    label: 'Compatível: chips com ícone',
    icon: 'fa-tags',
    description: 'Mantido para instalações legadas e testes internos.',
  },
  {
    value: 'gallery_12_dual_cards',
    label: 'Compatível: cartões duplos',
    icon: 'fa-grip',
    description: 'Mantido para instalações legadas e testes internos.',
  },
]

const allButtonStyleOptions = [...buttonStyleOptions, ...buttonStyleCompatibilityOptions]
const buttonStyleValues = allButtonStyleOptions.map((option) => option.value)
const compatibilityButtonStyleValues = buttonStyleCompatibilityOptions.map((option) => option.value)
const legacyButtonStyleMap: Record<string, string> = {
  gradient: 'gallery_3_dark_outline',
  clean: 'gallery_1_text_icons',
  outline: 'gallery_3_dark_outline',
  soft: 'gallery_5_pills',
}

const measureIconOptions = [
  {
    value: 'hanger',
    label: 'Cabide',
    description: 'Ícone principal para encontrar tamanho.',
    svg: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7.5a2.6 2.6 0 1 0-2.55-3.08"/><path d="M12 7.5v3.1"/><path d="M4.2 19.3 12 10.6l7.8 8.7"/><path d="M5.6 19.3h12.8"/></svg>',
  },
  {
    value: 'ruler',
    label: 'Régua',
    description: 'Boa para tabela de medidas.',
    svg: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.8 16.2 16.2 3.8l4 4L7.8 20.2z"/><path d="m8 17-1.5-1.5"/><path d="m11 14-1.5-1.5"/><path d="m14 11-1.5-1.5"/><path d="m17 8-1.5-1.5"/></svg>',
  },
  {
    value: 'tape',
    label: 'Fita métrica',
    description: 'Remete a medidas corporais e de peça.',
    svg: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.4 10.6a6.2 6.2 0 1 1 11.1 3.8"/><path d="M10.6 10.6a2.2 2.2 0 1 1 4.4 0 2.2 2.2 0 0 1-4.4 0Z"/><path d="M14.8 14.8h4.4c1.2 0 2 .8 2 2v2.4H9.4v-2.4c0-1.2.8-2 2-2h1.2"/><path d="M12.8 18.4v-1.8"/><path d="M16 18.4v-1.8"/><path d="M19.2 18.4v-1.8"/></svg>',
  },
  {
    value: 'ruler_combined',
    label: 'Esquadro',
    description: 'Visual técnico para guias de tamanho.',
    svg: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 20V4.5L20 20z"/><path d="M8 13v3h3"/><path d="M4.5 8h3"/><path d="M4.5 11h2"/><path d="M4.5 14h3"/><path d="M4.5 17h2"/></svg>',
  },
  {
    value: 'shirt',
    label: 'Camiseta',
    description: 'Indicado para moda casual.',
    svg: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4.5 12 6l4-1.5 4 4-3 3V20H7v-8.5l-3-3z"/><path d="M9.5 5.2c.7 1.5 1.5 2.2 2.5 2.2s1.8-.7 2.5-2.2"/></svg>',
  },
  {
    value: 'body',
    label: 'Corpo',
    description: 'Representa medidas do cliente.',
    svg: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6.8a2.4 2.4 0 1 0 0-4.8 2.4 2.4 0 0 0 0 4.8Z"/><path d="M7.2 21.5 9 9.2h6l1.8 12.3"/><path d="M5.3 12.2 9 9.2"/><path d="m15 9.2 3.7 3"/><path d="M9 14h6"/></svg>',
  },
  {
    value: 'chart',
    label: 'Tabela',
    description: 'Útil para o botão de tabela.',
    svg: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M4 10h16"/><path d="M4 15h16"/><path d="M9 5v14"/><path d="M15 5v14"/></svg>',
  },
  {
    value: 'size_tag',
    label: 'Etiqueta',
    description: 'Comunica tamanho de produto.',
    svg: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 12.2V5h7.2l7.8 7.8-7.2 7.2z"/><path d="M8.2 8.2h.1"/><path d="M10 15.2h4.2"/></svg>',
  },
]

const measureIconValues = measureIconOptions.map((option) => option.value)

const selectedButtonStyle = computed(() => {
  return allButtonStyleOptions.find((option) => option.value === form.theme.button_style)
    || buttonStyleOptions[0]
})

const selectedButtonStyleCompatibility = computed(() => {
  return buttonStyleCompatibilityOptions.find((option) => option.value === form.theme.button_style) || null
})

const isCompatibilityButtonStyle = computed(() => compatibilityButtonStyleValues.includes(form.theme.button_style))

const selectedPrimaryIcon = computed(() => selectedMeasureIcon(form.theme.button_primary_icon, 'hanger'))
const selectedSecondaryIcon = computed(() => selectedMeasureIcon(form.theme.button_secondary_icon, 'ruler'))
const isHangerIconSelected = computed(() => selectedPrimaryIcon.value.value === 'hanger')
const shouldAnimateButtonIcon = computed(() => isHangerIconSelected.value && Boolean(form.theme.button_icon_animation))

const hasUnpublishedChanges = computed(() => Boolean(install.value?.draft?.has_unpublished_changes))
const hasLocalChanges = computed(() => savedFormSnapshot.value !== JSON.stringify(formState()))
const hasPendingChanges = computed(() => hasUnpublishedChanges.value || hasLocalChanges.value)
const publicationStatusLabel = computed(() => {
  if (hasLocalChanges.value) {
    return 'Alterações locais'
  }

  return hasUnpublishedChanges.value ? 'Rascunho salvo' : 'Publicado'
})

const isBigShopContract = computed(() => {
  return auth.activeCompany?.platform === 'bigshop'
    || install.value?.company?.platform === 'bigshop'
})

const installationSteps = computed(() => {
  const steps = [
    'Instale na página de produto, no template que renderiza a vitrine de cada item.',
    'Coloque o container no ponto exato em que os botões devem aparecer, perto do seletor de tamanho/grade e antes ou próximo ao botão Comprar.',
    'Carregue o script com defer no template da página, no head ou no fim do body, garantindo que o container exista quando o provador iniciar.',
    'Preencha produto, variação e SKU com os dados reais do item atual; quando a grade mudar, atualize esses dados e recarregue o provador.',
  ]

  if (isBigShopContract.value || form.platform === 'bigshop') {
    steps.push('Na BigShop, a instalação automática será preparada no produto.vue da model3 plano pro, no repositório BigShop correto.')
  }

  return steps
})

const currentPlatformGuide = computed(() => {
  return install.value?.platform_guides?.find((guide) => guide.key === form.platform)
    || install.value?.platform_guide
    || null
})

const currentSnippet = computed(() => currentPlatformGuide.value?.guide.snippet || install.value?.snippet || '')

const currentInstallationSteps = computed(() => {
  return currentPlatformGuide.value?.guide.steps?.length
    ? currentPlatformGuide.value.guide.steps
    : installationSteps.value
})

const currentReloadSnippet = computed(() => {
  return currentPlatformGuide.value?.guide.reload_snippet
    || `window.ProvadorVirtual?.reload({
  productId: 'ID_DO_PRODUTO',
  variantId: 'ID_DA_VARIACAO',
  sku: 'SKU_DA_VARIACAO'
})`
})

const currentDataSupport = computed(() => {
  return Object.entries(currentPlatformGuide.value?.guide.data_support || {})
    .map(([field, description]) => ({ field, description }))
})

const placementSuggestions = computed(() => {
  return currentPlatformGuide.value?.guide.placement_suggestions?.length
    ? currentPlatformGuide.value.guide.placement_suggestions
    : [
        { selector: '#provador-virtual-container', mode: 'inside' as PlacementMode, label: 'Container padrão' },
        { selector: '.product-form', mode: 'after' as PlacementMode, label: 'Depois do formulário' },
        { selector: 'button[type="submit"]', mode: 'before' as PlacementMode, label: 'Antes do comprar' },
      ]
})

const placementValidation = computed<PlacementValidation>(() => form.theme.placement.validation || { status: 'untested' })

const placementStatusLabel = computed(() => {
  const status = placementValidation.value.status

  if (status === 'passed') {
    return 'Validado'
  }

  if (status === 'warning') {
    return 'Com aviso'
  }

  if (status === 'failed') {
    return 'Falhou'
  }

  return 'Não testado'
})

const platformInstallMode = computed(() => {
  if (currentPlatformGuide.value?.install_mode === 'one_click') {
    return 'Instalação assistida'
  }

  return 'Instalação por tema'
})

const previewStyle = computed(() => ({
  '--pv-preview-primary': form.theme.primary,
  '--pv-preview-secondary': form.theme.secondary,
  '--pv-preview-accent': form.theme.accent,
  '--pv-preview-bg': form.theme.background,
  '--pv-preview-text': form.theme.text,
  '--pv-preview-button-bg': form.theme.button_background,
  '--pv-preview-button-text': form.theme.button_text,
  '--pv-preview-radius': `${form.theme.button_radius}px`,
  fontFamily: form.theme.font_family,
  fontSize: `${form.theme.font_size}px`,
}))

onMounted(() => {
  loadInstall()
})

onBeforeUnmount(() => {
  removeConfettiPreview()
})

async function loadInstall() {
  loading.value = true

  try {
    const { data } = await api.get('/widget-install')
    install.value = data.data
    fillForm(data.data)
  } finally {
    loading.value = false
  }
}

function fillForm(data: WidgetInstall) {
  const draft = data.draft || {
    platform: data.platform,
    allowed_domains: data.allowed_domains,
    theme: data.theme,
    is_active: data.is_active,
    has_unpublished_changes: false,
  }
  const theme = draft.theme || data.theme || {}

  form.platform = isBigShopContract.value ? 'bigshop' : draft.platform || data.platform || 'custom'
  form.allowed_domains = (draft.allowed_domains || data.allowed_domains || []).join('\n')
  form.is_active = draft.is_active
  form.theme.primary = theme.primary || '#0f172a'
  form.theme.secondary = theme.secondary || '#ff4d5e'
  form.theme.accent = theme.accent || '#ff7a1a'
  form.theme.background = theme.background || '#ffffff'
  form.theme.text = theme.text || '#111827'
  form.theme.font_family = theme.font_family || 'Manrope, Inter, Arial, sans-serif'
  form.theme.font_size = theme.font_size || '14'
  form.theme.font_weight = theme.font_weight || '800'
  form.theme.button_radius = theme.button_radius || '8'
  const buttonStyle = String(theme.button_style || '')
  form.theme.button_style = buttonStyleValues.includes(buttonStyle)
    ? buttonStyle
    : legacyButtonStyleMap[buttonStyle] || 'gallery_1_text_icons'
  form.theme.button_background = theme.button_background || theme.secondary || '#ff4d5e'
  form.theme.button_text = theme.button_text || '#ffffff'
  form.theme.button_primary_icon = measureIconValues.includes(String(theme.button_primary_icon || ''))
    ? String(theme.button_primary_icon)
    : 'hanger'
  form.theme.button_secondary_icon = measureIconValues.includes(String(theme.button_secondary_icon || ''))
    ? String(theme.button_secondary_icon)
    : 'ruler'
  form.theme.button_icon_animation = form.theme.button_primary_icon === 'hanger' && (
    theme.button_icon_animation === undefined
    || theme.button_icon_animation === null
    || theme.button_icon_animation === true
    || theme.button_icon_animation === 'true'
    || theme.button_icon_animation === '1'
  )
  form.theme.presentation_mode = theme.presentation_mode === 'modal' ? 'modal' : 'drawer'
  form.theme.confetti_enabled = theme.confetti_enabled === undefined
    || theme.confetti_enabled === null
    || theme.confetti_enabled === true
    || theme.confetti_enabled === 'true'
    || theme.confetti_enabled === '1'
  form.theme.placement = normalizePlacement(theme.placement)
  placementPreview.value = null
  placementPreviewUrl.value = form.theme.placement.validation?.url || data.company?.domain || ''
  savedFormSnapshot.value = JSON.stringify(formState())
}

function normalizePlacement(value: WidgetPlacement | undefined): WidgetPlacement {
  const mode = value?.mode && placementModeOptions.some((option) => option.value === value.mode)
    ? value.mode
    : 'inside'

  return {
    mode,
    selector: value?.selector || '#provador-virtual-container',
    container_id: value?.container_id || 'provador-virtual-container',
    validation: {
      status: value?.validation?.status || 'untested',
      url: value?.validation?.url || null,
      checked_at: value?.validation?.checked_at || null,
      message: value?.validation?.message || null,
    },
  }
}

function selectedMeasureIcon(value: string | undefined, fallback: string) {
  return measureIconOptions.find((option) => option.value === value)
    || measureIconOptions.find((option) => option.value === fallback)
    || measureIconOptions[0]
}

function buttonIconHtml(kind: 'primary' | 'secondary') {
  return kind === 'primary' ? selectedPrimaryIcon.value.svg : selectedSecondaryIcon.value.svg
}

function buttonIconClass(kind: 'primary' | 'secondary') {
  return [
    'pv-measure-icon',
    {
      'is-swinging': kind === 'primary' && shouldAnimateButtonIcon.value,
    },
  ]
}

function chooseButtonIcon(field: 'button_primary_icon' | 'button_secondary_icon', value: string) {
  if (!measureIconValues.includes(value)) {
    return
  }

  form.theme[field] = value

  if (field === 'button_primary_icon' && value !== 'hanger') {
    form.theme.button_icon_animation = false
  }
}

function formState() {
  return {
    platform: form.platform,
    allowed_domains: domains.value,
    is_active: form.is_active,
    theme: {
      ...form.theme,
      placement: {
        ...form.theme.placement,
        validation: { ...form.theme.placement.validation },
      },
    },
  }
}

function widgetPayload(mode: 'draft' | 'publish' | 'discard') {
  if (mode === 'discard') {
    return { mode }
  }

  return {
    mode,
    platform: form.platform,
    allowed_domains: domains.value,
    is_active: form.is_active,
    theme: form.theme,
  }
}

async function saveInstall(mode: 'draft' | 'publish' = 'draft') {
  saving.value = true

  try {
    const { data } = await api.patch('/widget-install', widgetPayload(mode))

    install.value = data.data
    fillForm(data.data)
  } finally {
    saving.value = false
  }
}

async function saveDraft() {
  await saveInstall('draft')
}

async function discardDraft() {
  saving.value = true

  try {
    const { data } = await api.patch('/widget-install', widgetPayload('discard'))
    install.value = data.data
    fillForm(data.data)
  } finally {
    saving.value = false
  }
}

function markPlacementUntested() {
  placementPreview.value = null
  form.theme.placement.validation = { status: 'untested' }
}

function setPlacementMode(mode: PlacementMode) {
  form.theme.placement.mode = mode
  markPlacementUntested()
}

function applyPlacementSuggestion(suggestion: PlacementSuggestion) {
  form.theme.placement.selector = suggestion.selector
  form.theme.placement.mode = suggestion.mode
  markPlacementUntested()
}

function placementCheckClass(status: PlacementPreview['checks'][number]['status']) {
  return {
    ok: status === 'passed',
    warning: status === 'warning',
    danger: status === 'failed',
  }
}

async function testPlacementSelector() {
  placementTesting.value = true

  try {
    const { data } = await api.post('/widget-install/placement-preview', {
      platform: form.platform,
      url: placementPreviewUrl.value,
      mode: form.theme.placement.mode,
      selector: form.theme.placement.selector,
      container_id: form.theme.placement.container_id,
    })

    placementPreview.value = data.data
    const firstIssue = placementPreview.value?.checks.find((check) => check.status !== 'passed')
    form.theme.placement.validation = {
      status: placementPreview.value?.status || 'untested',
      url: placementPreview.value?.url || placementPreviewUrl.value,
      checked_at: placementPreview.value?.checked_at || null,
      message: firstIssue?.action || null,
    }
  } finally {
    placementTesting.value = false
  }
}

async function copySnippet() {
  if (!currentSnippet.value) {
    return
  }

  await navigator.clipboard.writeText(currentSnippet.value)
  copied.value = true
  window.setTimeout(() => {
    copied.value = false
  }, 1800)
}

function handleConfettiChange(event: Event) {
  if ((event.target as HTMLInputElement).checked) {
    triggerConfettiPreview()
  }
}

function triggerConfettiPreview() {
  removeConfettiPreview()

  const layer = document.createElement('div')
  const colors = ['#ff4d5e', '#ff7a1a', '#0f172a', '#22c55e', '#38bdf8']

  layer.className = 'pv-confetti-layer portal-confetti-preview'
  layer.setAttribute('aria-hidden', 'true')

  for (let index = 0; index < 42; index += 1) {
    const piece = document.createElement('i')
    piece.style.left = `${Math.round(Math.random() * 100)}%`
    piece.style.background = colors[index % colors.length]
    piece.style.animationDelay = `${(Math.random() * 0.35).toFixed(2)}s`
    piece.style.transform = `rotate(${Math.round(Math.random() * 180)}deg)`
    layer.appendChild(piece)
  }

  document.body.appendChild(layer)

  confettiPreviewTimeout = window.setTimeout(() => {
    removeConfettiPreview()
  }, 2200)
}

function removeConfettiPreview() {
  if (confettiPreviewTimeout) {
    window.clearTimeout(confettiPreviewTimeout)
    confettiPreviewTimeout = null
  }

  document.querySelectorAll('.portal-confetti-preview').forEach((layer) => {
    layer.remove()
  })
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">
          Widget
          <span
            class="info-tooltip title-info-tooltip"
            tabindex="0"
            role="button"
            aria-label="O widget do Provador Virtual é o provador que aparece na página do produto da loja."
            data-tooltip="É o provador que aparece na página do produto da loja. Ele mostra os botões Descubra seu tamanho e Tabela de Medidas, abre a recomendação por IA e ajuda o cliente a escolher o tamanho certo sem sair da compra."
          >i</span>
        </span>
        <h1>Instalação e visual</h1>
        <p class="page-heading-help">
          Ajuste botões, cores, domínios e código para exibir o provador na página de produto.
        </p>
      </div>
      <div class="action-row compact">
        <span class="status-pill" :class="{ ok: !hasPendingChanges, warning: hasPendingChanges }">
          {{ publicationStatusLabel }}
        </span>
        <button class="btn btn-secondary" type="button" @click="previewModalOpen = true">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
          Visualizar
        </button>
        <button class="btn btn-secondary" type="button" :disabled="!currentSnippet" @click="copySnippet">
          <i class="fa-solid fa-copy" aria-hidden="true"></i>
          {{ copied ? 'Copiado' : 'Copiar código' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="empty-state">Carregando provador...</div>

    <div v-else class="install-grid widget-install-layout">
      <form class="panel-main admin-form widget-config-form" @submit.prevent="saveDraft">
        <section class="widget-config-section">
          <div class="subsection-heading">
            <h2>Instalação</h2>
            <span>{{ platformInstallMode }}</span>
          </div>

          <div class="widget-install-summary">
            <label class="widget-field-platform">
              Plataforma
              <select v-model="form.platform" :disabled="isBigShopContract">
                <option v-for="platform in platformOptions" :key="platform.value" :value="platform.value">
                  {{ platform.label }}
                </option>
              </select>
              <small v-if="isBigShopContract">Plano BigShop permite instalação somente na BigShop.</small>
            </label>
            <label class="widget-field-key">
              Chave pública
              <input :value="install?.public_key" readonly />
            </label>
            <label class="settings-check widget-inline-toggle">
              <input v-model="form.is_active" type="checkbox" />
              <span>
                <strong>Widget ativo</strong>
                <small>Controla a exibição pública na loja.</small>
              </span>
            </label>
          </div>

          <div v-if="currentPlatformGuide" class="widget-platform-guide">
            <i :class="['fa-solid', currentPlatformGuide.icon]" aria-hidden="true"></i>
            <div>
              <strong>{{ currentPlatformGuide.name }}</strong>
              <span>{{ currentPlatformGuide.summary }}</span>
            </div>
            <em>{{ currentPlatformGuide.guide.placement_label }}</em>
          </div>

          <div class="widget-placement-config">
            <div class="subsection-heading compact-heading">
              <h2>Posição na PDP</h2>
              <span :class="['status-pill', placementValidation.status]">{{ placementStatusLabel }}</span>
            </div>

            <fieldset class="mode-selector widget-placement-mode">
              <legend>Âncora do botão</legend>
              <div class="segmented-control">
                <button
                  v-for="mode in placementModeOptions"
                  :key="mode.value"
                  type="button"
                  :class="{ active: form.theme.placement.mode === mode.value }"
                  @click="setPlacementMode(mode.value)"
                >
                  <i :class="['fa-solid', mode.icon]" aria-hidden="true"></i>
                  {{ mode.label }}
                </button>
              </div>
            </fieldset>

            <div class="widget-placement-fields">
              <label>
                Seletor CSS
                <input
                  v-model="form.theme.placement.selector"
                  placeholder="#provador-virtual-container"
                  @input="markPlacementUntested"
                />
              </label>
              <label>
                URL da PDP
                <input
                  v-model="placementPreviewUrl"
                  placeholder="https://loja.com.br/produto"
                />
              </label>
              <button class="btn btn-secondary" type="button" :disabled="placementTesting || !form.theme.placement.selector" @click="testPlacementSelector">
                <i class="fa-solid fa-magnifying-glass-location" aria-hidden="true"></i>
                {{ placementTesting ? 'Testando...' : 'Testar seletor' }}
              </button>
            </div>

            <div class="widget-placement-suggestions">
              <button
                v-for="suggestion in placementSuggestions"
                :key="`${suggestion.mode}-${suggestion.selector}`"
                type="button"
                class="selector-chip"
                @click="applyPlacementSuggestion(suggestion)"
              >
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <span>{{ suggestion.label }}</span>
                <code>{{ suggestion.selector }}</code>
              </button>
            </div>

            <div class="widget-placement-preview">
              <div class="placement-product-preview" :class="`placement-${form.theme.placement.mode}`">
                <div class="placement-anchor">
                  <span>Seletor</span>
                  <strong>{{ form.theme.placement.selector }}</strong>
                </div>
                <div :class="['preview-widget-buttons', `preview-button-style-${form.theme.button_style}`]">
                  <button type="button">
                    <span :class="buttonIconClass('primary')" v-html="buttonIconHtml('primary')"></span>
                    <span class="button-label">Descubra seu tamanho</span>
                  </button>
                  <button type="button">
                    <span :class="buttonIconClass('secondary')" v-html="buttonIconHtml('secondary')"></span>
                    <span class="button-label">Tabela de Medidas</span>
                  </button>
                </div>
              </div>

              <div v-if="placementPreview" class="placement-checks">
                <span
                  v-for="check in placementPreview.checks"
                  :key="check.key"
                  :class="placementCheckClass(check.status)"
                >
                  <i
                    :class="[
                      'fa-solid',
                      check.status === 'passed' ? 'fa-circle-check' : check.status === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-xmark',
                    ]"
                    aria-hidden="true"
                  ></i>
                  {{ check.label }}
                </span>
              </div>
            </div>
          </div>
        </section>

        <section class="widget-config-section">
          <div class="subsection-heading">
            <h2>Domínios</h2>
            <span>{{ domains.length || 0 }} liberado{{ domains.length === 1 ? '' : 's' }}</span>
          </div>
          <label>
            <span class="field-label">
              Domínios liberados
              <span
                class="info-tooltip"
                tabindex="0"
                role="button"
                aria-label="Os domínios liberados protegem o provador contra uso não autorizado em outras lojas."
                data-tooltip="Informe os domínios onde o provador pode aparecer. Isso bloqueia chamadas feitas por lojas não autorizadas e evita uso indevido da sua chave pública."
              >i</span>
            </span>
            <textarea
              v-model="form.allowed_domains"
              rows="4"
              placeholder="loja.com.br&#10;www.loja.com.br"
            ></textarea>
            <small>Informe um domínio por linha, sem caminho da página.</small>
          </label>
        </section>

        <section class="widget-config-section">
          <div class="subsection-heading">
            <h2>Personalização</h2>
            <span>Botões, abertura e celebração</span>
          </div>

          <fieldset class="mode-selector widget-mode-selector">
            <legend>Abertura do provador</legend>
            <div class="segmented-control">
              <button
                v-for="mode in presentationModeOptions"
                :key="mode.value"
                type="button"
                :class="{ active: form.theme.presentation_mode === mode.value }"
                @click="form.theme.presentation_mode = mode.value"
              >
                <i :class="['fa-solid', mode.icon]" aria-hidden="true"></i>
                {{ mode.label }}
              </button>
            </div>
            <small>O modal central fica amplo no desktop e ocupa a tela toda no celular.</small>
          </fieldset>

          <section class="widget-button-customizer" :style="previewStyle" aria-labelledby="widget-button-style-title">
            <div class="subsection-heading compact-heading">
              <h2 id="widget-button-style-title">Botões personalizados</h2>
              <span>{{ selectedButtonStyle.label }}<template v-if="selectedButtonStyleCompatibility"> · legado</template></span>
            </div>

            <p class="widget-button-customizer-note">
              Os 10 modelos principais seguem a galeria pública da Sizebay. Valores legados continuam disponíveis em compatibilidade.
            </p>

            <div class="button-style-list" role="radiogroup" aria-label="Visual dos botões">
              <button
                v-for="option in buttonStyleOptions"
                :key="option.value"
                type="button"
                class="button-style-option"
                :class="{ active: form.theme.button_style === option.value }"
                role="radio"
                :aria-checked="form.theme.button_style === option.value"
                @click="form.theme.button_style = option.value"
              >
                <i :class="['fa-solid', option.icon]" aria-hidden="true"></i>
                <span class="button-style-copy">
                  <strong>{{ option.label }}</strong>
                  <small>{{ option.description }}</small>
                </span>
                <span :class="['button-option-preview', `preview-button-style-${option.value}`]" aria-hidden="true">
                  <span>
                    <i :class="buttonIconClass('primary')" v-html="buttonIconHtml('primary')"></i>
                    Descubra
                  </span>
                  <span>
                    <i :class="buttonIconClass('secondary')" v-html="buttonIconHtml('secondary')"></i>
                    Tabela
                  </span>
                </span>
              </button>
            </div>

            <details class="button-style-compatibility" :open="isCompatibilityButtonStyle">
              <summary>
                <div>
                  <strong>Modelos compatíveis</strong>
                  <span>Use apenas se precisar manter um estilo salvo em versões anteriores.</span>
                </div>
                <i class="fa-solid fa-angles-down" aria-hidden="true"></i>
              </summary>

              <div class="button-style-list button-style-compatibility-list" role="radiogroup" aria-label="Modelos compatíveis">
                <button
                  v-for="option in buttonStyleCompatibilityOptions"
                  :key="option.value"
                  type="button"
                  class="button-style-option button-style-option-compatibility"
                  :class="{ active: form.theme.button_style === option.value }"
                  role="radio"
                  :aria-checked="form.theme.button_style === option.value"
                  @click="form.theme.button_style = option.value"
                >
                  <i :class="['fa-solid', option.icon]" aria-hidden="true"></i>
                  <span class="button-style-copy">
                    <strong>{{ option.label }}</strong>
                    <small>{{ option.description }}</small>
                  </span>
                </button>
              </div>
            </details>

            <div class="button-color-box" :style="previewStyle">
              <div class="button-color-controls">
                <label>
                  Fundo do botão
                  <span class="swatch-field">
                    <input v-model="form.theme.button_background" type="color" />
                    <input v-model="form.theme.button_background" maxlength="7" />
                  </span>
                </label>
                <label>
                  Texto do botão
                  <span class="swatch-field">
                    <input v-model="form.theme.button_text" type="color" />
                    <input v-model="form.theme.button_text" maxlength="7" />
                  </span>
                </label>
              </div>

              <div class="button-icon-settings">
                <div>
                  <strong>Ícone do botão Descubra seu tamanho</strong>
                  <span>Escolha um símbolo de medida para substituir PV.</span>
                </div>
                <div class="measure-icon-catalog" role="radiogroup" aria-label="Ícone do botão Descubra seu tamanho">
                  <button
                    v-for="option in measureIconOptions"
                    :key="`primary-${option.value}`"
                    type="button"
                    class="measure-icon-option"
                    :class="{ active: form.theme.button_primary_icon === option.value }"
                    role="radio"
                    :aria-checked="form.theme.button_primary_icon === option.value"
                    @click="chooseButtonIcon('button_primary_icon', option.value)"
                  >
                    <i class="pv-measure-icon" v-html="option.svg"></i>
                    <span>
                      <strong>{{ option.label }}</strong>
                      <small>{{ option.description }}</small>
                    </span>
                  </button>
                </div>
              </div>

              <label v-if="isHangerIconSelected" class="settings-check widget-icon-animation-toggle">
                <input v-model="form.theme.button_icon_animation" type="checkbox" />
                <span>
                  <strong>Animar ícone do cabide</strong>
                  <small>O cabide fica pendurado e balançando no botão Descubra seu tamanho.</small>
                </span>
              </label>

              <div class="button-icon-settings">
                <div>
                  <strong>Ícone do botão Tabela de Medidas</strong>
                  <span>Escolha um símbolo para substituir cm.</span>
                </div>
                <div class="measure-icon-catalog compact-icons" role="radiogroup" aria-label="Ícone do botão Tabela de Medidas">
                  <button
                    v-for="option in measureIconOptions"
                    :key="`secondary-${option.value}`"
                    type="button"
                    class="measure-icon-option"
                    :class="{ active: form.theme.button_secondary_icon === option.value }"
                    role="radio"
                    :aria-checked="form.theme.button_secondary_icon === option.value"
                    @click="chooseButtonIcon('button_secondary_icon', option.value)"
                  >
                    <i class="pv-measure-icon" v-html="option.svg"></i>
                    <span>
                      <strong>{{ option.label }}</strong>
                    </span>
                  </button>
                </div>
              </div>

              <div :class="['button-live-preview', `preview-button-style-${form.theme.button_style}`]">
                <button type="button">
                  <span :class="buttonIconClass('primary')" v-html="buttonIconHtml('primary')"></span>
                  <span class="button-label">Descubra seu tamanho</span>
                </button>
                <button type="button">
                  <span :class="buttonIconClass('secondary')" v-html="buttonIconHtml('secondary')"></span>
                  <span class="button-label">Tabela de Medidas</span>
                </button>
              </div>
            </div>
          </section>

          <div class="widget-color-grid">
            <label>
              Primária
              <span class="swatch-field">
                <input v-model="form.theme.primary" type="color" />
                <input v-model="form.theme.primary" maxlength="7" />
              </span>
            </label>
            <label>
              Secundária
              <span class="swatch-field">
                <input v-model="form.theme.secondary" type="color" />
                <input v-model="form.theme.secondary" maxlength="7" />
              </span>
            </label>
            <label>
              Destaque
              <span class="swatch-field">
                <input v-model="form.theme.accent" type="color" />
                <input v-model="form.theme.accent" maxlength="7" />
              </span>
            </label>
            <label>
              Fundo
              <span class="swatch-field">
                <input v-model="form.theme.background" type="color" />
                <input v-model="form.theme.background" maxlength="7" />
              </span>
            </label>
            <label>
              Texto
              <span class="swatch-field">
                <input v-model="form.theme.text" type="color" />
                <input v-model="form.theme.text" maxlength="7" />
              </span>
            </label>
          </div>

          <div class="widget-type-grid">
            <label class="widget-field-font">
              Fonte
              <select v-model="form.theme.font_family">
                <option value="Manrope, Inter, Arial, sans-serif">Manrope</option>
                <option value="Inter, Arial, sans-serif">Inter</option>
                <option value="Arial, sans-serif">Arial</option>
                <option value="Georgia, serif">Georgia</option>
              </select>
            </label>
            <label>
              Tamanho
              <input v-model="form.theme.font_size" type="number" min="11" max="22" />
            </label>
            <label>
              Peso
              <select v-model="form.theme.font_weight">
                <option value="400">Regular</option>
                <option value="600">Semibold</option>
                <option value="700">Bold</option>
                <option value="800">Extra bold</option>
              </select>
            </label>
            <label>
              Raio
              <input v-model="form.theme.button_radius" type="number" min="0" max="24" />
            </label>
          </div>

          <label class="settings-check widget-confetti-toggle">
            <input
              v-model="form.theme.confetti_enabled"
              type="checkbox"
              @change="handleConfettiChange"
            />
            <span>
              <strong>Animação de confetes</strong>
              <small>Ao ativar, o cliente vê essa celebração quando chega ao resultado completo.</small>
            </span>
          </label>
        </section>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="saving">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            Salvar rascunho
          </button>
          <button class="btn btn-secondary" type="button" :disabled="saving" @click="saveInstall('publish')">
            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
            Publicar
          </button>
          <button class="btn btn-secondary" type="button" :disabled="saving || !hasPendingChanges" @click="discardDraft">
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
            Desfazer
          </button>
        </div>
      </form>

      <aside class="widget-install-aside">
        <section class="panel-main widget-code-panel">
          <div class="subsection-heading">
            <h2>Código</h2>
            <span>{{ currentPlatformGuide?.name || 'Plataforma' }}</span>
          </div>
          <pre class="widget-code-block"><code>{{ currentSnippet }}</code></pre>
          <button class="btn btn-secondary compact-copy" type="button" :disabled="!currentSnippet" @click="copySnippet">
            <i class="fa-solid fa-copy" aria-hidden="true"></i>
            {{ copied ? 'Copiado' : 'Copiar snippet' }}
          </button>
        </section>

        <section class="panel-main widget-guide-panel">
          <div class="subsection-heading">
            <h2>Onde instalar</h2>
            <span>{{ currentPlatformGuide?.guide.placement_label || 'Página de produto' }}</span>
          </div>
          <ol class="placement-steps">
            <li v-for="step in currentInstallationSteps" :key="step">{{ step }}</li>
          </ol>

          <div class="subsection-heading compact-heading">
            <h2>Atualização da variação</h2>
            <span>reload</span>
          </div>
          <pre class="guide-snippet compact-snippet"><code>{{ currentReloadSnippet }}</code></pre>

          <div v-if="currentDataSupport.length" class="widget-data-support">
            <span v-for="support in currentDataSupport" :key="support.field">
              <strong>{{ support.field }}</strong>
              {{ support.description }}
            </span>
          </div>

          <div class="check-list">
            <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Produto ativo</span>
            <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Tabela vinculada</span>
            <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Provador público</span>
          </div>
        </section>
      </aside>
    </div>

    <Teleport to="body">
      <div
        v-if="previewModalOpen"
        class="widget-preview-modal-layer"
        role="presentation"
        @click.self="previewModalOpen = false"
      >
        <section
          class="panel-main widget-preview-panel widget-preview-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="widget-preview-title"
        >
          <div class="subsection-heading">
            <h2 id="widget-preview-title">Visualizador</h2>
            <div class="widget-preview-modal-actions">
              <div class="segmented-control compact-segmented">
                <button
                  type="button"
                  :class="{ active: previewDevice === 'desktop' }"
                  @click="previewDevice = 'desktop'"
                >
                  <i class="fa-solid fa-display" aria-hidden="true"></i>
                  Desktop
                </button>
                <button
                  type="button"
                  :class="{ active: previewDevice === 'mobile' }"
                  @click="previewDevice = 'mobile'"
                >
                  <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                  Mobile
                </button>
              </div>
              <button class="drawer-close" type="button" aria-label="Fechar visualizador" @click="previewModalOpen = false">
                x
              </button>
            </div>
          </div>
          <div :class="['widget-style-preview', `preview-device-${previewDevice}`]" :style="previewStyle">
            <div class="preview-product-line">
              <strong>Vestido Midi Aurora</strong>
              <span>Selecione seu tamanho</span>
            </div>
            <div :class="['preview-widget-buttons', `preview-button-style-${form.theme.button_style}`]">
              <button type="button">
                <span :class="buttonIconClass('primary')" v-html="buttonIconHtml('primary')"></span>
                <span class="button-label">Descubra seu tamanho</span>
              </button>
              <button type="button">
                <span :class="buttonIconClass('secondary')" v-html="buttonIconHtml('secondary')"></span>
                <span class="button-label">Tabela de Medidas</span>
              </button>
            </div>
            <div :class="['preview-launch-frame', form.theme.presentation_mode === 'modal' ? 'modal' : 'drawer']">
              <span>{{ form.theme.presentation_mode === 'modal' ? 'Modal central' : 'Drawer lateral' }}</span>
              <div></div>
            </div>
            <div class="preview-size-table">
              <div><strong>P</strong><span>84 - 90</span><span>66 - 72</span></div>
              <div><strong>M</strong><span>90 - 96</span><span>72 - 78</span></div>
              <div><strong>G</strong><span>96 - 104</span><span>78 - 86</span></div>
            </div>
            <a href="https://provadorvirtual.online/" target="_blank" rel="noopener">desenvolvido por provadorvirtual.online</a>
          </div>
        </section>
      </div>
    </Teleport>
  </section>
</template>
