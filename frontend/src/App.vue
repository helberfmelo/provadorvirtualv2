<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'

const route = useRoute()
const auth = useAuthStore()

const isAppRoute = computed(() => route.path.startsWith('/app'))
const canSeeSaas = computed(() => ['admin', 'support'].includes(auth.user?.role || ''))

onMounted(() => {
  auth.loadMe().catch(() => undefined)
})

async function logout() {
  await auth.logout()
}
</script>

<template>
  <div class="shell" :class="{ 'shell-app': isAppRoute }">
    <header class="topbar">
      <RouterLink to="/" class="brand" aria-label="Provador Virtual">
        <span class="brand-mark">PV</span>
        <span>Provador Virtual</span>
      </RouterLink>

      <nav class="nav" aria-label="Principal">
        <RouterLink to="/produto-teste">Produto teste</RouterLink>
        <RouterLink v-if="!auth.isAuthenticated" to="/checkout">Contratar</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app/produtos">Produtos</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app/tabelas-de-medidas">Tabelas</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app/assistente">Assistente</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app/analytics">Analytics</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app/go-live">Go-live</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app/importacoes">Importacoes</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app/widget">Widget</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app/integracoes">Integracoes</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && canSeeSaas" to="/saas">SaaS</RouterLink>
        <RouterLink v-if="!auth.isAuthenticated" to="/login">Entrar</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app">Painel</RouterLink>
        <button v-if="auth.isAuthenticated" class="nav-button" type="button" title="Sair" @click="logout">
          <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
        </button>
      </nav>
    </header>

    <main>
      <RouterView />
    </main>
  </div>
</template>
