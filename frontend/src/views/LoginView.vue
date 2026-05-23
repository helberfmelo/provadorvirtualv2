<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const email = ref('demo@provadorvirtual.online')
const password = ref('provador123')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true

  try {
    await auth.login(email.value, password.value)
    await router.push('/app')
  } catch {
    error.value = 'Nao foi possivel entrar com esses dados.'
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
          E-mail
          <input v-model="email" type="email" autocomplete="email" required />
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
