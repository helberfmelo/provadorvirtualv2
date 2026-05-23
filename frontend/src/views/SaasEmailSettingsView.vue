<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../services/api'
import { normalizeEmailSettings, type EmailSettings } from '../services/saasTypes'

const router = useRouter()
const emailSettings = reactive<EmailSettings>(normalizeEmailSettings({}))
const smtpPassword = ref('')
const loading = ref(false)
const saving = ref(false)
const error = ref('')

onMounted(() => {
  loadSettings()
})

async function loadSettings() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/email-settings')
    Object.assign(emailSettings, normalizeEmailSettings(data.data))
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar as credenciais de e-mail.'
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  error.value = ''

  try {
    const payload: Record<string, unknown> = {
      mailer: emailSettings.mailer,
      host: emailSettings.host.trim(),
      port: emailSettings.port,
      username: emailSettings.username.trim(),
      encryption: emailSettings.encryption || null,
      from_address: emailSettings.from_address.trim(),
      from_name: emailSettings.from_name.trim(),
      is_active: emailSettings.is_active,
    }

    if (smtpPassword.value.trim()) {
      payload.smtp_password = smtpPassword.value.trim()
    }

    await api.patch('/saas/email-settings', payload)
    await router.push('/saas/emails')
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar as credenciais de e-mail.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Credenciais SMTP</h1>
        <p>Configuração central de envio transacional.</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/saas/emails">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <form class="panel-main admin-form form-page" @submit.prevent="saveSettings">
      <div class="form-grid">
        <label>
          Mailer
          <select v-model="emailSettings.mailer">
            <option value="smtp">SMTP</option>
          </select>
        </label>
        <label>
          Host SMTP
          <input v-model="emailSettings.host" placeholder="mail.provadorvirtual.online" />
        </label>
        <label>
          Porta
          <input v-model.number="emailSettings.port" type="number" min="1" max="65535" />
        </label>
      </div>

      <div class="form-grid">
        <label>
          Usuário
          <input v-model="emailSettings.username" autocomplete="username" />
        </label>
        <label>
          Senha SMTP
          <input v-model="smtpPassword" type="password" autocomplete="new-password" placeholder="Manter senha atual" />
          <small>{{ emailSettings.has_smtp_password ? 'Senha já salva no cofre criptografado.' : 'Nenhuma senha salva ainda.' }}</small>
        </label>
        <label>
          Criptografia
          <select v-model="emailSettings.encryption">
            <option value="tls">TLS</option>
            <option value="ssl">SSL</option>
          </select>
        </label>
      </div>

      <div class="form-grid">
        <label>
          E-mail remetente
          <input v-model="emailSettings.from_address" type="email" />
        </label>
        <label>
          Nome remetente
          <input v-model="emailSettings.from_name" />
        </label>
        <label>
          Status
          <select v-model="emailSettings.is_active">
            <option :value="true">Ativo</option>
            <option :value="false">Inativo</option>
          </select>
        </label>
      </div>

      <div class="action-row compact">
        <button class="btn btn-primary" type="submit" :disabled="saving || loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar credenciais' }}
        </button>
      </div>
    </form>
  </section>
</template>
