<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

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
    confetti_enabled?: boolean | string
  }
  is_active: boolean
  script_url: string
  css_url: string
  snippet: string
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
    confetti_enabled: true,
  },
})

const domains = computed(() => form.allowed_domains
  .split('\n')
  .map((domain) => domain.trim())
  .filter(Boolean))

const platformOptions = computed(() => {
  if (isBigShopContract.value) {
    return [{ value: 'bigshop', label: 'BigShop' }]
  }

  return [
    { value: 'bigshop', label: 'BigShop' },
    { value: 'shopify', label: 'Shopify' },
    { value: 'woocommerce', label: 'WooCommerce' },
    { value: 'nuvemshop', label: 'Nuvemshop' },
    { value: 'vtex', label: 'VTEX' },
    { value: 'tray', label: 'Tray' },
    { value: 'loja_integrada', label: 'Loja Integrada' },
    { value: 'magento', label: 'Magento' },
    { value: 'opencart', label: 'OpenCart' },
    { value: 'custom', label: 'Personalizada' },
  ]
})

const isBigShopContract = computed(() => {
  return auth.activeCompany?.platform === 'bigshop'
    || install.value?.company?.platform === 'bigshop'
})

const installationSteps = computed(() => {
  const steps = [
    'Instale na página de produto, no template que renderiza a vitrine de cada item.',
    'Coloque o container no ponto exato em que os botões devem aparecer, perto do seletor de tamanho/grade e antes ou próximo ao botão Comprar.',
    'Carregue o script com defer no template da página, no head ou no fim do body, garantindo que o container exista quando o widget iniciar.',
    'Preencha produto, variação e SKU com os dados reais do item atual; quando a grade mudar, atualize esses dados e recarregue o widget.',
  ]

  if (isBigShopContract.value || form.platform === 'bigshop') {
    steps.push('Na BigShop, a instalação automática será preparada no produto.vue da model3 plano pro, no repositório BigShop correto.')
  }

  return steps
})

const previewStyle = computed(() => ({
  '--pv-preview-primary': form.theme.primary,
  '--pv-preview-secondary': form.theme.secondary,
  '--pv-preview-accent': form.theme.accent,
  '--pv-preview-bg': form.theme.background,
  '--pv-preview-text': form.theme.text,
  '--pv-preview-radius': `${form.theme.button_radius}px`,
  fontFamily: form.theme.font_family,
  fontSize: `${form.theme.font_size}px`,
}))

onMounted(() => {
  loadInstall()
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
  if (!install.value) {
    return
  }

  await navigator.clipboard.writeText(install.value.snippet)
  copied.value = true
  window.setTimeout(() => {
    copied.value = false
  }, 1800)
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Widget</span>
        <h1>Instalação</h1>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="!install" @click="copySnippet">
        <i class="fa-solid fa-copy" aria-hidden="true"></i>
        {{ copied ? 'Copiado' : 'Copiar código' }}
      </button>
    </div>

    <div v-if="loading" class="empty-state">Carregando widget...</div>

    <div v-else class="install-grid">
      <form class="panel-main admin-form" @submit.prevent="saveInstall">
        <div class="form-grid">
          <label>
            Plataforma
            <select v-model="form.platform" :disabled="isBigShopContract">
              <option v-for="platform in platformOptions" :key="platform.value" :value="platform.value">
                {{ platform.label }}
              </option>
            </select>
            <small v-if="isBigShopContract">Plano BigShop permite instalação somente na BigShop.</small>
          </label>
          <label>
            Chave pública
            <input :value="install?.public_key" readonly />
          </label>
          <label class="toggle-line">
            Ativo
            <input v-model="form.is_active" type="checkbox" />
          </label>
        </div>

        <label>
          Domínios liberados
          <textarea v-model="form.allowed_domains" rows="5"></textarea>
        </label>

        <div class="color-grid">
          <label>
            Primaria
            <span class="swatch-field">
              <input v-model="form.theme.primary" type="color" />
              <input v-model="form.theme.primary" maxlength="7" />
            </span>
          </label>
          <label>
            Secundaria
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

        <div class="form-grid">
          <label>
            Fonte
            <select v-model="form.theme.font_family">
              <option value="Manrope, Inter, Arial, sans-serif">Manrope</option>
              <option value="Inter, Arial, sans-serif">Inter</option>
              <option value="Arial, sans-serif">Arial</option>
              <option value="Georgia, serif">Georgia</option>
            </select>
          </label>
          <label>
            Tamanho da fonte
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
            Raio dos botões
            <input v-model="form.theme.button_radius" type="number" min="0" max="24" />
          </label>
          <label class="toggle-line">
            Confete
            <input v-model="form.theme.confetti_enabled" type="checkbox" />
          </label>
        </div>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="saving">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            Salvar widget
          </button>
        </div>
      </form>

      <aside class="install-preview">
        <div class="subsection-heading">
          <h2>Visualizador</h2>
          <span>Widget e tabela</span>
        </div>
        <div class="widget-style-preview" :style="previewStyle">
          <div class="preview-product-line">
            <strong>Vestido Midi Aurora</strong>
            <span>Selecione seu tamanho</span>
          </div>
          <div class="preview-widget-buttons">
            <button type="button">Descubra seu tamanho</button>
            <button type="button">Tabela de Medidas</button>
          </div>
          <div class="preview-size-table">
            <div><strong>P</strong><span>84 - 90</span><span>66 - 72</span></div>
            <div><strong>M</strong><span>90 - 96</span><span>72 - 78</span></div>
            <div><strong>G</strong><span>96 - 104</span><span>78 - 86</span></div>
          </div>
          <a href="https://provadorvirtual.online/" target="_blank" rel="noopener">desenvolvido por provadorvirtual.online</a>
        </div>

        <div class="subsection-heading">
          <h2>Código</h2>
          <span>{{ install?.sample_product?.sku || 'produto' }}</span>
        </div>
        <pre><code>{{ install?.snippet }}</code></pre>

        <div class="subsection-heading">
          <h2>Onde instalar</h2>
          <span>Página de produto</span>
        </div>
        <ul class="placement-steps">
          <li v-for="step in installationSteps" :key="step">{{ step }}</li>
        </ul>
        <pre class="guide-snippet compact-snippet"><code>window.ProvadorVirtual?.reload({
  productId: 'ID_DO_PRODUTO',
  variantId: 'ID_DA_GRADE',
  sku: 'SKU_DA_GRADE'
})</code></pre>

        <div class="check-list">
          <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Produto ativo</span>
          <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Tabela vinculada</span>
          <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Widget público</span>
        </div>
      </aside>
    </div>
  </section>
</template>
