<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import { normalizeEmailSettings, type EmailSettings, type TransactionalEmail, type TransactionalEmailSend } from '../services/saasTypes'

const emailSettings = reactive<EmailSettings>(normalizeEmailSettings({}))
const transactionalEmails = ref<TransactionalEmail[]>([])
const emailSends = ref<TransactionalEmailSend[]>([])
const loading = ref(false)
const error = ref('')
const notice = ref('')

onMounted(() => {
  loadEmails()
})

async function loadEmails() {
  loading.value = true
  error.value = ''

  try {
    const [emailSettingsResponse, transactionalEmailsResponse, emailSendsResponse] = await Promise.all([
      api.get('/saas/email-settings'),
      api.get('/saas/transactional-emails'),
      api.get('/saas/transactional-email-sends'),
    ])

    Object.assign(emailSettings, normalizeEmailSettings(emailSettingsResponse.data.data))
    transactionalEmails.value = transactionalEmailsResponse.data.data
    emailSends.value = emailSendsResponse.data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar os e-mails.'
  } finally {
    loading.value = false
  }
}

async function toggleTemplate(template: TransactionalEmail) {
  notice.value = ''
  error.value = ''

  try {
    await api.patch(`/saas/transactional-emails/${template.id}`, {
      is_active: !template.is_active,
    })
    notice.value = template.is_active ? 'Template desativado.' : 'Template ativado.'
    await loadEmails()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel alterar o status do template.'
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>E-mails</h1>
        <p>Listagens e configuracoes de e-mails transacionais do SaaS.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadEmails">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink class="btn btn-secondary" to="/saas/emails/configuracoes">
          <i class="fa-solid fa-sliders" aria-hidden="true"></i>
          SMTP
        </RouterLink>
        <RouterLink class="btn btn-primary" to="/saas/emails/novo">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          Novo e-mail
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="notice" class="success-message">{{ notice }}</p>

    <div class="quick-grid">
      <RouterLink to="/saas/emails/configuracoes" class="quick-card">
        <i class="fa-solid fa-server" aria-hidden="true"></i>
        <strong>Credenciais SMTP</strong>
        <span>{{ emailSettings.is_active ? 'Envio ativo' : 'Envio inativo' }} em {{ emailSettings.host || 'host nao configurado' }}</span>
      </RouterLink>
      <article class="quick-card">
        <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i>
        <strong>{{ transactionalEmails.length }}</strong>
        <span>Templates transacionais cadastrados.</span>
      </article>
      <article class="quick-card">
        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
        <strong>{{ emailSends.length }}</strong>
        <span>Envios recentes no historico.</span>
      </article>
    </div>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>E-mails transacionais</h2>
        <span>{{ transactionalEmails.length }} templates</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Codigo</th>
              <th>Nome</th>
              <th>Assunto</th>
              <th>Status</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!transactionalEmails.length">
              <td colspan="5">Sem e-mails transacionais.</td>
            </tr>
            <tr v-for="template in transactionalEmails" :key="template.id">
              <td><strong>{{ template.code }}</strong></td>
              <td>
                <strong>{{ template.name }}</strong>
                <small>{{ template.description || 'sem descricao' }}</small>
              </td>
              <td>{{ template.subject }}</td>
              <td>
                <span class="status-pill" :class="{ ok: template.is_active, warning: !template.is_active }">
                  {{ template.is_active ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td class="row-actions">
                <RouterLink class="icon-link" :to="`/saas/emails/${template.id}/editar`" title="Editar">
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button type="button" :title="template.is_active ? 'Desativar' : 'Ativar'" @click="toggleTemplate(template)">
                  <i :class="template.is_active ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Historico de envios</h2>
        <span>{{ emailSends.length }} recentes</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Template</th>
              <th>Empresa</th>
              <th>Destinatario</th>
              <th>Status</th>
              <th>Data</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!emailSends.length">
              <td colspan="5">Nenhum envio registrado.</td>
            </tr>
            <tr v-for="send in emailSends" :key="send.id">
              <td>
                <strong>{{ send.template_name || send.code }}</strong>
                <small>{{ send.subject || 'sem assunto' }}</small>
              </td>
              <td>{{ send.company_name || '-' }}</td>
              <td>
                <strong>{{ send.recipient_name || '-' }}</strong>
                <small>{{ send.recipient_email || '-' }}</small>
              </td>
              <td>
                <span class="status-pill" :class="{ ok: send.status === 'sent', warning: send.status === 'skipped' }">
                  {{ send.status }}
                </span>
                <small v-if="send.error">{{ send.error }}</small>
              </td>
              <td>{{ send.sent_at || send.created_at || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
