<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const identifier = ref('')
const companyAccess = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const companyOptions = ref<Array<{
  id: number
  name: string
  access_code: string
  document: string | null
  platform: string
  merchant: { name: string } | null
}>>([])
const isSaasLogin = computed(() => {
  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''

  return route.path === '/saas/login' || redirect.startsWith('/saas')
})

const loginEyebrow = computed(() => isSaasLogin.value ? 'Acesso SaaS' : 'Acesso da empresa')
const loginTitle = computed(() => isSaasLogin.value ? 'Entrar no SaaS' : 'Entrar no portal da empresa')
const companyAccessHelp = computed(() => (
  companyOptions.value.length > 0
    ? 'Selecione a empresa para continuar no portal.'
    : 'Obrigatório para o portal da empresa. Use código da loja ou CNPJ.'
))

async function submit() {
  error.value = ''
  loading.value = true

  try {
    await auth.login(identifier.value, password.value, isSaasLogin.value ? '' : companyAccess.value)

    const isAdmin = ['admin', 'support'].includes(auth.user?.role || '')

    if (isSaasLogin.value && !isAdmin) {
      await auth.logout()
      error.value = 'Este acesso é exclusivo para administradores do SaaS.'

      return
    }

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
    const fallback = isAdmin && (isSaasLogin.value || !companyAccess.value) ? '/saas' : '/app'
    const target = redirect.startsWith(isSaasLogin.value ? '/saas' : '/app') ? redirect : fallback

    await router.push(target)
  } catch (requestError: any) {
    if (requestError.response?.status === 409 && Array.isArray(requestError.response?.data?.company_options)) {
      companyOptions.value = requestError.response.data.company_options
      companyAccess.value = companyOptions.value[0]?.access_code || ''
    }

    error.value = requestError.response?.data?.message
      || requestError.response?.data?.errors?.company_access?.[0]
      || requestError.response?.data?.errors?.login?.[0]
      || 'Não foi possível entrar com esses dados.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="auth-page">
    <div class="auth-panel">
      <span class="eyebrow">{{ loginEyebrow }}</span>
      <h1>{{ loginTitle }}</h1>
      <form class="form" @submit.prevent="submit">
        <label>
          E-mail ou CPF
          <input v-model="identifier" autocomplete="username" required />
        </label>
        <label v-if="!isSaasLogin || companyOptions.length > 0">
          Código da loja ou CNPJ
          <input
            v-if="companyOptions.length === 0"
            v-model="companyAccess"
            inputmode="numeric"
            autocomplete="organization"
          />
          <select v-else v-model="companyAccess" autocomplete="organization">
            <option v-for="company in companyOptions" :key="company.id" :value="company.access_code">
              {{ company.name }} - {{ company.access_code }}
            </option>
          </select>
          <small>{{ companyAccessHelp }}</small>
        </label>
        <label>
          Senha
          <input v-model="password" type="password" autocomplete="current-password" required />
        </label>
        <p v-if="error" class="form-error">{{ error }}</p>
        <button class="btn btn-primary" type="submit" :disabled="loading">
          <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>
    </div>
  </section>
</template>
