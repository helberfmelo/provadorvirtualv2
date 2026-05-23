<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const navOpen = ref(false)

const isAppRoute = computed(() => route.path.startsWith('/app'))
const canSeeSaas = computed(() => ['admin', 'support'].includes(auth.user?.role || ''))

onMounted(() => {
  auth.loadMe().catch(() => undefined)
})

watch(() => route.fullPath, () => {
  navOpen.value = false
})

async function logout() {
  await auth.logout()
  navOpen.value = false
}

async function switchCompany(event: Event) {
  const companyId = Number((event.target as HTMLSelectElement).value)

  if (!companyId || companyId === auth.activeCompany?.id) {
    return
  }

  await auth.selectCompany(companyId)

  if (route.path.startsWith('/saas')) {
    await router.push('/app')
  }

  navOpen.value = false
}
</script>

<template>
  <div class="shell" :class="{ 'shell-app': isAppRoute }">
    <header class="topbar">
      <RouterLink to="/" class="brand" aria-label="Provador Virtual">
        <span class="brand-mark">PV</span>
        <span>Provador Virtual</span>
      </RouterLink>

      <button
        class="menu-toggle"
        type="button"
        :aria-expanded="navOpen"
        aria-controls="main-navigation"
        :aria-label="navOpen ? 'Fechar menu' : 'Abrir menu'"
        @click="navOpen = !navOpen"
      >
        <i class="fa-solid" :class="navOpen ? 'fa-xmark' : 'fa-bars'" aria-hidden="true"></i>
      </button>

      <div v-if="navOpen" class="nav-scrim" @click="navOpen = false"></div>

      <nav id="main-navigation" class="nav" :class="{ open: navOpen }" aria-label="Principal">
        <label v-if="auth.isAuthenticated && auth.companyOptions.length > 1" class="company-switcher">
          <span>Empresa</span>
          <select :value="auth.activeCompany?.id || ''" @change="switchCompany">
            <option v-for="company in auth.companyOptions" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
        </label>
        <RouterLink to="/produto-teste">Produto teste</RouterLink>
        <RouterLink v-if="!auth.isAuthenticated" to="/checkout">Contratar</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && auth.canView('products')" to="/app/produtos">Produtos</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && auth.canView('measurement_tables')" to="/app/tabelas-de-medidas">Tabelas</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && auth.canView('ai_assistant')" to="/app/assistente">Assistente</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && auth.canView('analytics')" to="/app/analytics">Analytics</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && auth.canView('go_live')" to="/app/go-live">Go-live</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && auth.canView('imports')" to="/app/importacoes">Importacoes</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && auth.canView('widget')" to="/app/widget">Widget</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && auth.canView('integrations')" to="/app/integracoes">Integracoes</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && auth.canView('users')" to="/app/usuarios">Usuarios</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && canSeeSaas && auth.canSaasView('saas_dashboard')" to="/saas">SaaS</RouterLink>
        <RouterLink v-if="auth.isAuthenticated && canSeeSaas && auth.canSaasView('saas_users')" to="/saas/usuarios">Usuarios SaaS</RouterLink>
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
