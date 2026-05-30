<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import SaveFeedbackModal from './components/SaveFeedbackModal.vue'
import { buildSupportUrl, findHelpArticleByRoute } from './content/helpCenter'
import { useAuthStore } from './stores/auth'

type NavLink = {
  to: string
  label: string
  icon: string
  show: boolean
  variant?: 'platform'
}

type NavSection = {
  label: string
  links: NavLink[]
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const navOpen = ref(false)
const cookieNoticeVisible = ref(false)
const brandLogoUrl = `${import.meta.env.BASE_URL}images/brand/logo_provador_virtual.png`

const canSeeSaas = computed(() => ['admin', 'support'].includes(auth.user?.role || ''))
const isCompanyRoute = computed(() => route.path === '/app' || route.path.startsWith('/app/'))
const isSaasRoute = computed(() => (route.path === '/saas' || route.path.startsWith('/saas/')) && route.path !== '/saas/login')
const isProductTestRoute = computed(() => route.path === '/produto-teste' || route.path.startsWith('/produto-teste/'))
const isWorkRoute = computed(() => isCompanyRoute.value || isSaasRoute.value)
const contextLabel = computed(() => isSaasRoute.value ? 'SaaS admin' : 'Portal da empresa')
const brandTarget = computed(() => {
  if (isSaasRoute.value) {
    return '/saas'
  }

  if (isCompanyRoute.value) {
    return '/app'
  }

  return '/'
})
const brandAriaLabel = computed(() => {
  if (isSaasRoute.value) {
    return 'Provador Virtual - página inicial do SaaS'
  }

  if (isCompanyRoute.value) {
    return 'Provador Virtual - página inicial do portal'
  }

  return 'Provador Virtual - página inicial do site'
})
const workNavTitle = computed(() => isSaasRoute.value ? 'Operação SaaS' : 'Operação da loja')
const activeCompanyName = computed(() => auth.activeCompany?.name || 'Sem empresa ativa')
const isSaasViewingCompanyPortal = computed(() => isCompanyRoute.value && canSeeSaas.value)
const saasPortalContextLabel = computed(() => {
  if (!isSaasViewingCompanyPortal.value || !auth.activeCompany) {
    return ''
  }

  const companyCode = auth.activeCompany.access_code || `Empresa ${auth.activeCompany.id}`
  const merchantName = auth.activeMerchant?.name || 'Lojista'

  return `${merchantName} · ${companyCode}`
})
const publicPlatformLink = computed<NavLink | null>(() => {
  if (!auth.isAuthenticated || !auth.user) {
    return null
  }

  const shouldReturnToSaas = canSeeSaas.value && auth.canSaasView('saas_dashboard')

  return {
    to: shouldReturnToSaas ? '/saas' : '/app',
    label: shouldReturnToSaas ? 'Voltar ao SaaS' : 'Voltar ao portal',
    icon: shouldReturnToSaas ? 'fa-user-shield' : 'fa-store',
    show: true,
    variant: 'platform',
  }
})
const workViewKey = computed(() => {
  if (!isCompanyRoute.value) {
    return `${route.fullPath}:saas`
  }

  return `${route.fullPath}:company:${auth.activeCompany?.id || 'none'}`
})
const workContextReady = computed(() => !isWorkRoute.value || !auth.isAuthenticated || auth.initialized)

const publicLinks = computed<NavLink[]>(() => [
  ...(publicPlatformLink.value ? [publicPlatformLink.value] : []),
  { to: '/produto-teste', label: 'Teste o provador', icon: 'fa-wand-magic-sparkles', show: !isProductTestRoute.value },
  { to: '/checkout', label: 'Contratar', icon: 'fa-credit-card', show: !auth.isAuthenticated },
  { to: '/login', label: 'Entrar', icon: 'fa-right-to-bracket', show: !auth.isAuthenticated },
])

const companyNavSections = computed<NavSection[]>(() => [
  {
    label: 'Operação',
    links: [
      { to: '/app', label: 'Painel', icon: 'fa-gauge-high', show: true },
      { to: '/app/importacoes', label: 'Importações', icon: 'fa-file-arrow-up', show: auth.canView('imports') },
      { to: '/app/sincronizacao', label: 'Sincronização', icon: 'fa-rotate', show: auth.canView('integrations') },
    ],
  },
  {
    label: 'Catálogo',
    links: [
      { to: '/app/produtos', label: 'Produtos', icon: 'fa-shirt', show: auth.canView('products') },
      { to: '/app/tabelas-de-medidas', label: 'Tabelas', icon: 'fa-ruler-combined', show: auth.canView('measurement_tables') },
      { to: '/app/modelagens', label: 'Modelagens', icon: 'fa-sliders', show: auth.canView('measurement_tables') },
      { to: '/app/categorias', label: 'Categorias', icon: 'fa-sitemap', show: auth.canView('products') },
      { to: '/app/marcas', label: 'Marcas', icon: 'fa-copyright', show: auth.canView('products') },
      { to: '/app/taxonomia', label: 'Taxonomia IA', icon: 'fa-brain', show: auth.canView('products') },
      { to: '/app/regras-de-importacao', label: 'Regras', icon: 'fa-filter', show: auth.canView('integrations') },
    ],
  },
  {
    label: 'Provador',
    links: [
      { to: '/app/widget', label: 'Instalação', icon: 'fa-code', show: auth.canView('widget') },
      { to: '/app/integracoes', label: 'Integrações', icon: 'fa-plug', show: auth.canView('integrations') },
      { to: '/app/go-live', label: 'Publicação', icon: 'fa-rocket', show: auth.canView('go_live') },
    ],
  },
  {
    label: 'Resultados',
    links: [
      { to: '/app/analytics', label: 'Relatórios', icon: 'fa-chart-line', show: auth.canView('analytics') },
      { to: '/app/pedidos', label: 'Pedidos', icon: 'fa-receipt', show: auth.canView('analytics') },
      { to: '/app/devolucoes', label: 'Devoluções', icon: 'fa-arrow-rotate-left', show: auth.canView('analytics') },
      { to: '/app/assistente', label: 'Assistente IA', icon: 'fa-wand-magic-sparkles', show: auth.canView('ai_assistant') },
    ],
  },
  {
    label: 'Conta',
    links: [
      { to: '/app/plano-e-cobranca', label: 'Plano e cobrança', icon: 'fa-wallet', show: auth.canView('dashboard') },
      { to: '/app/usuarios', label: 'Usuários', icon: 'fa-users-gear', show: auth.canView('users') },
      { to: '/app/ajuda', label: 'Ajuda', icon: 'fa-life-ring', show: auth.canView('dashboard') },
    ],
  },
])

const saasNavSections = computed<NavSection[]>(() => [
  {
    label: 'SaaS',
    links: [
      { to: '/saas', label: 'Visão geral', icon: 'fa-gauge-high', show: auth.canSaasView('saas_dashboard') },
      { to: '/saas/empresas', label: 'Empresas', icon: 'fa-building', show: auth.canSaasView('saas_companies') },
      { to: '/saas/usuarios', label: 'Usuários SaaS', icon: 'fa-user-shield', show: auth.canSaasView('saas_users') },
      { to: '/saas/usuarios-empresas', label: 'Usuários das empresas', icon: 'fa-users-gear', show: auth.canSaasView('saas_company_users') },
      { to: '/saas/auditoria', label: 'Auditoria', icon: 'fa-shield-halved', show: auth.canSaasView('saas_audit') },
    ],
  },
  {
    label: 'Operação',
    links: [
      { to: '/saas/checkout', label: 'Checkout', icon: 'fa-credit-card', show: auth.canSaasView('saas_checkout') },
      { to: '/saas/pedidos', label: 'Pedidos', icon: 'fa-receipt', show: auth.canSaasView('saas_checkout') },
      { to: '/saas/trocas-bigshop', label: 'Trocas BigShop', icon: 'fa-right-left', show: auth.canSaasView('saas_companies') },
      { to: '/saas/emails', label: 'E-mails', icon: 'fa-envelope-open-text', show: auth.canSaasView('saas_emails') },
    ],
  },
])

const visibleWorkSections = computed(() => (
  isSaasRoute.value ? saasNavSections.value : companyNavSections.value
).map((section) => ({
  ...section,
  links: section.links.filter((link) => link.show),
})).filter((section) => section.links.length > 0))

const contextHelp = computed(() => {
  if (!isCompanyRoute.value || route.path === '/app/ajuda') {
    return null
  }

  return findHelpArticleByRoute(route.path)
})

const contextualSupportUrl = computed(() => buildSupportUrl({
  article: contextHelp.value,
  routePath: route.path,
  companyName: auth.activeCompany?.name || null,
  companyCode: auth.activeCompany?.access_code || null,
  merchantName: auth.activeMerchant?.name || null,
  platform: auth.activeCompany?.platform || null,
  userName: auth.user?.name || null,
  userEmail: auth.user?.email || null,
}))

onMounted(() => {
  auth.ensureLoaded().catch(() => undefined)
  cookieNoticeVisible.value = !hasCookieNoticeAcceptance()
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

function hasCookieNoticeAcceptance() {
  return localStorage.getItem('pv_cookie_notice_ok') === '1'
    || document.cookie.split(';').some((item) => item.trim().startsWith('pv_cookie_notice_ok=1'))
}

function acceptCookieNotice() {
  localStorage.setItem('pv_cookie_notice_ok', '1')
  document.cookie = 'pv_cookie_notice_ok=1; Max-Age=31536000; Path=/; SameSite=Lax'
  cookieNoticeVisible.value = false
}

function handleBrandClick(event: MouseEvent) {
  navOpen.value = false

  if (brandTarget.value === '/' && route.path === '/') {
    event.preventDefault()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}
</script>

<template>
  <div class="shell" :class="{ 'shell-work': isWorkRoute, 'shell-company': isCompanyRoute, 'shell-saas': isSaasRoute, 'nav-open': navOpen }">
    <header class="topbar" :class="{ 'topbar-work': isWorkRoute }">
      <RouterLink :to="brandTarget" class="brand" :aria-label="brandAriaLabel" @click="handleBrandClick">
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
          <div class="drawer-header-row">
            <RouterLink :to="brandTarget" :aria-label="brandAriaLabel" @click="handleBrandClick">
              <img :src="brandLogoUrl" alt="Provador Virtual" />
            </RouterLink>
            <button class="drawer-close" type="button" aria-label="Fechar menu" @click="navOpen = false">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
          </div>
          <span>Menu</span>
        </div>
        <RouterLink
          v-for="link in publicLinks.filter((item) => item.show)"
          :key="link.to"
          :to="link.to"
          :class="{ 'platform-return-link': link.variant === 'platform' }"
          @click="navOpen = false"
        >
          <i class="fa-solid" :class="link.icon" aria-hidden="true"></i>
          <span>{{ link.label }}</span>
        </RouterLink>
        <button v-if="auth.isAuthenticated" class="nav-button" type="button" title="Sair" @click="logout">
          <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
          <span>Sair</span>
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
          <div class="drawer-header-row">
            <span>{{ workNavTitle }}</span>
            <button class="drawer-close" type="button" aria-label="Fechar menu" @click="navOpen = false">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
          </div>
          <strong>{{ isSaasRoute ? 'Provador Virtual' : activeCompanyName }}</strong>
        </div>

        <div v-if="isSaasViewingCompanyPortal" class="admin-context-note">
          <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
          <span>
            <strong>Acesso SaaS em empresa</strong>
            <small>{{ saasPortalContextLabel }}. Você está vendo o portal da empresa com credenciais administrativas.</small>
          </span>
        </div>

        <label v-if="isCompanyRoute && auth.isAuthenticated && auth.companyOptions.length > 1" class="company-switcher">
          <span>Empresa</span>
          <select :value="auth.activeCompany?.id || ''" :disabled="auth.loadingMe" @change="switchCompany">
            <option v-if="!auth.activeCompany" value="" disabled>Selecione</option>
            <option v-for="company in auth.companyOptions" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
        </label>

        <nav class="work-nav" aria-label="Navegação do portal">
          <section v-for="section in visibleWorkSections" :key="section.label" class="work-nav-section">
            <h2 class="work-nav-section-title">{{ section.label }}</h2>
            <RouterLink
              v-for="link in section.links"
              :key="link.to"
              :to="link.to"
              @click="navOpen = false"
            >
              <i class="fa-solid" :class="link.icon" aria-hidden="true"></i>
              <span>{{ link.label }}</span>
            </RouterLink>
          </section>
        </nav>

        <div class="work-sidebar-mobile-actions">
          <RouterLink
            v-if="isCompanyRoute && canSeeSaas && auth.canSaasView('saas_dashboard')"
            to="/saas"
            class="context-link"
            @click="navOpen = false"
          >
            <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
            SaaS
          </RouterLink>
          <RouterLink
            v-if="isSaasRoute && auth.activeCompany"
            to="/app"
            class="context-link"
            @click="navOpen = false"
          >
            <i class="fa-solid fa-store" aria-hidden="true"></i>
            Portal da empresa
          </RouterLink>
          <span class="user-chip">
            <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
            {{ auth.user?.name || 'Usuário' }}
          </span>
          <button class="nav-button" type="button" title="Sair" @click="logout">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span>Sair</span>
          </button>
        </div>
      </aside>

      <main class="work-main">
        <section v-if="contextHelp" class="context-help-bar" aria-label="Ajuda contextual">
          <div class="context-help-copy">
            <strong>
              <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
              {{ contextHelp.title }}
            </strong>
            <span>{{ contextHelp.summary }}</span>
          </div>
          <div class="context-help-actions">
            <RouterLink :to="{ path: '/app/ajuda', query: { topico: contextHelp.key } }">
              Manual
            </RouterLink>
            <RouterLink v-if="contextHelp.nextTo" :to="contextHelp.nextTo">
              {{ contextHelp.nextLabel || 'Próximo passo' }}
            </RouterLink>
            <a :href="contextualSupportUrl" target="_blank" rel="noopener noreferrer">
              Suporte
            </a>
          </div>
        </section>
        <div v-if="!workContextReady" class="empty-state work-context-loading">
          Carregando contexto da empresa...
        </div>
        <RouterView v-else :key="workViewKey" />
      </main>
    </div>

    <main v-else>
      <RouterView />
    </main>

    <SaveFeedbackModal />

    <div v-if="cookieNoticeVisible" class="cookie-notice" role="dialog" aria-live="polite" aria-label="Aviso de privacidade">
      <p>
        Usamos cookies técnicos, localStorage e registros operacionais para login, segurança, checkout,
        preferências do provador e melhoria do serviço. Ao continuar, você concorda com esse uso.
      </p>
      <button class="btn btn-secondary" type="button" @click="acceptCookieNotice">OK</button>
    </div>
  </div>
</template>
