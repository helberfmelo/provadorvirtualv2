<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

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

type Platform = {
  key: string
  name: string
  priority: boolean
  icon: string
  install_mode: string
  status: string
  has_connection: boolean
  connection: PlatformConnection | null
}

const platforms = ref<Platform[]>([])
const selectedKey = ref('bigshop')
const loading = ref(false)
const saving = ref(false)
const notice = ref('')

const form = reactive({
  external_store_id: '',
  api_base_url: '',
  status: 'draft',
  access_token: '',
  webhook_secret: '',
})

const selected = computed(() => platforms.value.find((platform) => platform.key === selectedKey.value) || platforms.value[0] || null)

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
  } finally {
    loading.value = false
  }
}

function selectPlatform(platform: Platform) {
  selectedKey.value = platform.key
  fillForm(platform)
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
  } finally {
    saving.value = false
  }
}

function statusLabel(status: string) {
  return {
    draft: 'Rascunho',
    configured: 'Configurada',
    connected: 'Conectada',
    disabled: 'Pausada',
    error: 'Erro',
  }[status] || status
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

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="saving">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            Salvar integracao
          </button>
        </div>
      </form>
    </div>
  </section>
</template>
