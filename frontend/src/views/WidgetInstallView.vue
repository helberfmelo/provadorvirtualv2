<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

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
}

const install = ref<WidgetInstall | null>(null)
const loading = ref(false)
const saving = ref(false)
const copied = ref(false)
const notice = ref('')

const form = reactive({
  platform: 'custom',
  allowed_domains: '',
  is_active: true,
  theme: {
    primary: '#0f172a',
    secondary: '#ff4d5e',
    accent: '#ff7a1a',
  },
})

const domains = computed(() => form.allowed_domains
  .split('\n')
  .map((domain) => domain.trim())
  .filter(Boolean))

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
  form.platform = data.platform || 'custom'
  form.allowed_domains = (data.allowed_domains || []).join('\n')
  form.is_active = data.is_active
  form.theme.primary = data.theme?.primary || '#0f172a'
  form.theme.secondary = data.theme?.secondary || '#ff4d5e'
  form.theme.accent = data.theme?.accent || '#ff7a1a'
}

async function saveInstall() {
  saving.value = true
  notice.value = ''

  try {
    const { data } = await api.patch('/widget-install', {
      platform: form.platform,
      allowed_domains: domains.value,
      is_active: form.is_active,
      theme: form.theme,
    })

    install.value = data.data
    fillForm(data.data)
    notice.value = 'Widget atualizado.'
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
        <h1>Instalacao</h1>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="!install" @click="copySnippet">
        <i class="fa-solid fa-copy" aria-hidden="true"></i>
        {{ copied ? 'Copiado' : 'Copiar codigo' }}
      </button>
    </div>

    <p v-if="notice" class="success-message">{{ notice }}</p>

    <div v-if="loading" class="empty-state">Carregando widget...</div>

    <div v-else class="install-grid">
      <form class="panel-main admin-form" @submit.prevent="saveInstall">
        <div class="form-grid">
          <label>
            Plataforma
            <select v-model="form.platform">
              <option value="bigshop">BigShop</option>
              <option value="shopify">Shopify</option>
              <option value="woocommerce">WooCommerce</option>
              <option value="nuvemshop">Nuvemshop</option>
              <option value="vtex">VTEX</option>
              <option value="tray">Tray</option>
              <option value="custom">Personalizada</option>
            </select>
          </label>
          <label>
            Chave publica
            <input :value="install?.public_key" readonly />
          </label>
          <label class="toggle-line">
            Ativo
            <input v-model="form.is_active" type="checkbox" />
          </label>
        </div>

        <label>
          Dominios liberados
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
          <h2>Codigo</h2>
          <span>{{ install?.sample_product?.sku || 'produto' }}</span>
        </div>
        <pre><code>{{ install?.snippet }}</code></pre>

        <div class="check-list">
          <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Produto ativo</span>
          <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Tabela vinculada</span>
          <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Widget publico</span>
        </div>
      </aside>
    </div>
  </section>
</template>
