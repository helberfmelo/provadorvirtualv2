<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const identifier = ref('demo@provadorvirtual.online')
const companyAccess = ref('')
const password = ref('provador123')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true

  try {
    await auth.login(identifier.value, password.value, companyAccess.value)
    await router.push(['admin', 'support'].includes(auth.user?.role || '') && !companyAccess.value ? '/saas' : '/app')
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message
      || requestError.response?.data?.errors?.company_access?.[0]
      || requestError.response?.data?.errors?.login?.[0]
      || 'Nao foi possivel entrar com esses dados.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="auth-page">
    <div class="auth-panel">
      <span class="eyebrow">Acesso do lojista</span>
      <h1>Entrar no painel</h1>
      <form class="form" @submit.prevent="submit">
        <label>
          E-mail ou CPF
          <input v-model="identifier" autocomplete="username" required />
        </label>
        <label>
          Codigo da loja ou CNPJ
          <input v-model="companyAccess" inputmode="numeric" autocomplete="organization" />
          <small>Obrigatorio para o portal da empresa. Admin SaaS pode deixar vazio.</small>
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
