<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

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
    button_style?: 'gradient' | 'clean' | 'outline' | 'soft' | string
    button_background?: string
    button_text?: string
    confetti_enabled?: boolean | string
    presentation_mode?: 'drawer' | 'modal' | string
  }
  is_active: boolean
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

const auth = useAuthStore()
const install = ref<WidgetInstall | null>(null)
const loading = ref(false)
const saving = ref(false)
const copied = ref(false)
let confettiPreviewTimeout: number | null = null

const form = reactive({
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
    button_style: 'gradient',
    button_background: '#ff4d5e',
    button_text: '#ffffff',
    confetti_enabled: true,
    presentation_mode: 'drawer',
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

const presentationModeOptions = [
  { value: 'drawer', label: 'Drawer lateral', icon: 'fa-table-columns' },
  { value: 'modal', label: 'Modal central', icon: 'fa-window-maximize' },
]

const buttonStyleOptions = [
  {
    value: 'gradient',
    label: 'Destaque com brilho',
    icon: 'fa-wand-magic-sparkles',
    description: 'Botão principal preenchido e tabela em contorno.',
  },
  {
    value: 'clean',
    label: 'Minimal com ícones',
    icon: 'fa-ruler-combined',
    description: 'Texto em caixa alta, ícones curtos e sublinhado animado.',
  },
  {
    value: 'outline',
    label: 'Contorno leve',
    icon: 'fa-square',
    description: 'Dois botões equivalentes com preenchimento no hover.',
  },
  {
    value: 'soft',
    label: 'Pílulas suaves',
    icon: 'fa-capsules',
    description: 'Bordas arredondadas, toque macio e elevação discreta.',
  },
]

const selectedButtonStyle = computed(() => {
  return buttonStyleOptions.find((option) => option.value === form.theme.button_style)
    || buttonStyleOptions[0]
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
  form.platform = isBigShopContract.value ? 'bigshop' : data.platform || 'custom'
  form.allowed_domains = (data.allowed_domains || []).join('\n')
  form.is_active = data.is_active
  form.theme.primary = data.theme?.primary || '#0f172a'
  form.theme.secondary = data.theme?.secondary || '#ff4d5e'
  form.theme.accent = data.theme?.accent || '#ff7a1a'
  form.theme.background = data.theme?.background || '#ffffff'
  form.theme.text = data.theme?.text || '#111827'
  form.theme.font_family = data.theme?.font_family || 'Manrope, Inter, Arial, sans-serif'
  form.theme.font_size = data.theme?.font_size || '14'
  form.theme.font_weight = data.theme?.font_weight || '800'
  form.theme.button_radius = data.theme?.button_radius || '8'
  form.theme.button_style = ['gradient', 'clean', 'outline', 'soft'].includes(String(data.theme?.button_style))
    ? String(data.theme?.button_style)
    : 'gradient'
  form.theme.button_background = data.theme?.button_background || data.theme?.secondary || '#ff4d5e'
  form.theme.button_text = data.theme?.button_text || '#ffffff'
  form.theme.presentation_mode = data.theme?.presentation_mode === 'modal' ? 'modal' : 'drawer'
  form.theme.confetti_enabled = data.theme?.confetti_enabled === undefined
    || data.theme?.confetti_enabled === null
    || data.theme?.confetti_enabled === true
    || data.theme?.confetti_enabled === 'true'
    || data.theme?.confetti_enabled === '1'
}

async function saveInstall() {
  saving.value = true

  try {
    const { data } = await api.patch('/widget-install', {
      platform: form.platform,
      allowed_domains: domains.value,
      is_active: form.is_active,
      theme: form.theme,
    })

    install.value = data.data
    fillForm(data.data)
  } finally {
    saving.value = false
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
      <button class="btn btn-secondary" type="button" :disabled="!currentSnippet" @click="copySnippet">
        <i class="fa-solid fa-copy" aria-hidden="true"></i>
        {{ copied ? 'Copiado' : 'Copiar código' }}
      </button>
    </div>

    <div v-if="loading" class="empty-state">Carregando provador...</div>

    <div v-else class="install-grid widget-install-layout">
      <form class="panel-main admin-form widget-config-form" @submit.prevent="saveInstall">
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
              <span>{{ selectedButtonStyle.label }}</span>
            </div>

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
                  <span>PV Descubra</span>
                  <span>cm Tabela</span>
                </span>
              </button>
            </div>

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

              <div :class="['button-live-preview', `preview-button-style-${form.theme.button_style}`]">
                <button type="button"><span>PV</span>Descubra seu tamanho</button>
                <button type="button"><span>cm</span>Tabela de Medidas</button>
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
            Salvar provador
          </button>
        </div>
      </form>

      <aside class="widget-install-aside">
        <section class="panel-main widget-preview-panel">
          <div class="subsection-heading">
            <h2>Visualizador</h2>
            <span>{{ form.theme.presentation_mode === 'modal' ? 'Modal central' : 'Drawer lateral' }}</span>
          </div>
          <div class="widget-style-preview" :style="previewStyle">
            <div class="preview-product-line">
              <strong>Vestido Midi Aurora</strong>
              <span>Selecione seu tamanho</span>
            </div>
            <div :class="['preview-widget-buttons', `preview-button-style-${form.theme.button_style}`]">
              <button type="button">Descubra seu tamanho</button>
              <button type="button">Tabela de Medidas</button>
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
  </section>
</template>
