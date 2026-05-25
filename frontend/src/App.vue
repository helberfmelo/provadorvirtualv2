<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import SaveFeedbackModal from './components/SaveFeedbackModal.vue'
import { useAuthStore } from './stores/auth'

type NavLink = {
  to: string
  label: string
  icon: string
  show: boolean
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const navOpen = ref(false)
const brandLogoUrl = `${import.meta.env.BASE_URL}images/brand/logo_provador_virtual.png`

const canSeeSaas = computed(() => ['admin', 'support'].includes(auth.user?.role || ''))
const isCompanyRoute = computed(() => route.path === '/app' || route.path.startsWith('/app/'))
const isSaasRoute = computed(() => (route.path === '/saas' || route.path.startsWith('/saas/')) && route.path !== '/saas/login')
const isWorkRoute = computed(() => isCompanyRoute.value || isSaasRoute.value)
const contextLabel = computed(() => isSaasRoute.value ? 'SaaS admin' : 'Portal da empresa')
const workNavTitle = computed(() => isSaasRoute.value ? 'Operação SaaS' : 'Operação da loja')
const activeCompanyName = computed(() => auth.activeCompany?.name || 'Sem empresa ativa')
const workViewKey = computed(() => {
  if (!isCompanyRoute.value) {
    return `${route.fullPath}:saas`
  }

  return `${route.fullPath}:company:${auth.activeCompany?.id || 'none'}`
})

const publicLinks = computed<NavLink[]>(() => [
  { to: '/produto-teste', label: 'Teste o widget', icon: 'fa-wand-magic-sparkles', show: true },
  { to: '/checkout', label: 'Contratar', icon: 'fa-credit-card', show: !auth.isAuthenticated },
  { to: '/login', label: 'Entrar', icon: 'fa-right-to-bracket', show: !auth.isAuthenticated },
])

const companyLinks = computed<NavLink[]>(() => [
  { to: '/app', label: 'Painel', icon: 'fa-gauge-high', show: true },
  { to: '/app/produtos', label: 'Produtos', icon: 'fa-shirt', show: auth.canView('products') },
  { to: '/app/tabelas-de-medidas', label: 'Tabelas', icon: 'fa-ruler-combined', show: auth.canView('measurement_tables') },
  { to: '/app/assistente', label: 'Assistente IA', icon: 'fa-wand-magic-sparkles', show: auth.canView('ai_assistant') },
  { to: '/app/importacoes', label: 'Importações', icon: 'fa-file-arrow-up', show: auth.canView('imports') },
  { to: '/app/widget', label: 'Widget', icon: 'fa-code', show: auth.canView('widget') },
  { to: '/app/integracoes', label: 'Integrações', icon: 'fa-plug', show: auth.canView('integrations') },
  { to: '/app/analytics', label: 'Analytics', icon: 'fa-chart-line', show: auth.canView('analytics') },
  { to: '/app/go-live', label: 'Go-live', icon: 'fa-rocket', show: auth.canView('go_live') },
  { to: '/app/usuarios', label: 'Usuários', icon: 'fa-users-gear', show: auth.canView('users') },
])

const saasLinks = computed<NavLink[]>(() => [
  { to: '/saas', label: 'Visão geral', icon: 'fa-gauge-high', show: auth.canSaasView('saas_dashboard') },
  { to: '/saas/empresas', label: 'Empresas', icon: 'fa-building', show: auth.canSaasView('saas_companies') },
  { to: '/saas/usuarios', label: 'Usuários SaaS', icon: 'fa-user-shield', show: auth.canSaasView('saas_users') },
  { to: '/saas/usuarios-empresas', label: 'Usuários das empresas', icon: 'fa-users-gear', show: auth.canSaasView('saas_company_users') },
  { to: '/saas/emails', label: 'E-mails', icon: 'fa-envelope-open-text', show: auth.canSaasView('saas_emails') },
])

const visibleWorkLinks = computed(() => (
  isSaasRoute.value ? saasLinks.value : companyLinks.value
).filter((link) => link.show))

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
  <div class="shell" :class="{ 'shell-work': isWorkRoute, 'shell-company': isCompanyRoute, 'shell-saas': isSaasRoute }">
    <header class="topbar" :class="{ 'topbar-work': isWorkRoute }">
      <RouterLink to="/" class="brand" aria-label="Provador Virtual">
        <img class="brand-logo" :src="brandLogoUrl" alt="Provador Virtual" />
        <small v-if="isWorkRoute" class="brand-context">{{ contextLabel }}</small>
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

      <nav v-if="!isWorkRoute" id="main-navigation" class="nav public-nav" :class="{ open: navOpen }" aria-label="Principal">
        <div class="public-nav-header">
          <img :src="brandLogoUrl" alt="Provador Virtual" />
          <span>Menu</span>
        </div>
        <RouterLink
          v-for="link in publicLinks.filter((item) => item.show)"
          :key="link.to"
          :to="link.to"
          @click="navOpen = false"
        >
          <i class="fa-solid" :class="link.icon" aria-hidden="true"></i>
          <span>{{ link.label }}</span>
        </RouterLink>
        <button v-if="auth.isAuthenticated" class="nav-button" type="button" title="Sair" @click="logout">
          <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
        </button>
      </nav>

      <div v-else class="work-top-actions">
        <RouterLink
          v-if="isCompanyRoute && canSeeSaas && auth.canSaasView('saas_dashboard')"
          to="/saas"
          class="context-link"
        >
          <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
          SaaS
        </RouterLink>
        <RouterLink v-if="isSaasRoute && auth.activeCompany" to="/app" class="context-link">
          <i class="fa-solid fa-store" aria-hidden="true"></i>
          Portal da empresa
        </RouterLink>
        <span class="user-chip">
          <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
          {{ auth.user?.name || 'Usuário' }}
        </span>
        <button class="nav-button" type="button" title="Sair" @click="logout">
          <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
        </button>
      </div>
    </header>

    <div v-if="isWorkRoute" class="work-layout">
      <aside id="main-navigation" class="work-sidebar" :class="{ open: navOpen }" aria-label="Menu operacional">
        <div class="work-sidebar-header">
          <span>{{ workNavTitle }}</span>
          <strong>{{ isSaasRoute ? 'Provador Virtual' : activeCompanyName }}</strong>
        </div>

        <label v-if="isCompanyRoute && auth.isAuthenticated && auth.companyOptions.length > 1" class="company-switcher">
          <span>Empresa</span>
          <select :value="auth.activeCompany?.id || ''" @change="switchCompany">
            <option v-for="company in auth.companyOptions" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
        </label>

        <nav class="work-nav" aria-label="Navegacao do portal">
          <RouterLink
            v-for="link in visibleWorkLinks"
            :key="link.to"
            :to="link.to"
            @click="navOpen = false"
          >
            <i class="fa-solid" :class="link.icon" aria-hidden="true"></i>
            <span>{{ link.label }}</span>
          </RouterLink>
        </nav>
      </aside>

      <main class="work-main">
        <RouterView :key="workViewKey" />
      </main>
    </div>

    <main v-else>
      <RouterView />
    </main>

    <SaveFeedbackModal />
  </div>
</template>
