<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import type { TransactionalEmail } from '../services/saasTypes'

const route = useRoute()
const router = useRouter()
const templateId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(templateId.value))
const loading = ref(false)
const saving = ref(false)
const error = ref('')

const form = reactive({
  code: '',
  name: '',
  description: '',
  subject: '',
  body: '',
  variables: 'nome, empresa, codigo_empresa, link_login',
  is_active: true,
})

onMounted(() => {
  loadTemplate()
})

async function loadTemplate() {
  if (!editing.value) {
    return
  }

  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/transactional-emails')
    const template = (data.data as TransactionalEmail[]).find((item) => item.id === templateId.value)
    if (!template) {
      error.value = 'Template não encontrado.'
      return
    }

    Object.assign(form, {
      code: template.code,
      name: template.name,
      description: template.description || '',
      subject: template.subject,
      body: template.body,
      variables: (template.variables || []).join(', '),
      is_active: template.is_active,
    })
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o template.'
  } finally {
    loading.value = false
  }
}

async function saveTemplate() {
  saving.value = true
  error.value = ''

  try {
    const payload = {
      code: form.code.trim(),
      name: form.name.trim(),
      description: form.description.trim(),
      subject: form.subject.trim(),
      body: form.body.trim(),
      variables: form.variables
        .split(/[,\n]+/)
        .map((variable) => variable.trim())
        .filter(Boolean),
      is_active: form.is_active,
    }

    editing.value
      ? await api.patch(`/saas/transactional-emails/${templateId.value}`, payload)
      : await api.post('/saas/transactional-emails', payload)
    await router.push('/saas/emails')
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar o e-mail transacional.'
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
        <h1>{{ editing ? 'Editar e-mail' : 'Novo e-mail' }}</h1>
        <p>Defina assunto, variáveis e conteúdo usado nas mensagens automáticas.</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/saas/emails">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <form class="panel-main admin-form form-page" @submit.prevent="saveTemplate">
      <div class="form-grid">
        <label>
          Código
          <input v-model="form.code" placeholder="cadastro_realizado" required />
        </label>
        <label>
          Nome
          <input v-model="form.name" required />
        </label>
        <label>
          Status
          <select v-model="form.is_active">
            <option :value="true">Ativo</option>
            <option :value="false">Inativo</option>
          </select>
        </label>
      </div>

      <label>
        Assunto
        <input v-model="form.subject" required />
      </label>
      <label>
        Descrição interna
        <input v-model="form.description" />
      </label>
      <label>
        Variáveis
        <input v-model="form.variables" placeholder="nome, empresa, link_checkout" />
      </label>
      <label>
        Corpo do e-mail
        <textarea v-model="form.body" rows="12" required></textarea>
      </label>

      <div class="action-row compact">
        <button class="btn btn-primary" type="submit" :disabled="saving || loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar e-mail' }}
        </button>
      </div>
    </form>
  </section>
</template>
