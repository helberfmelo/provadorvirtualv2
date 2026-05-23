import { createRouter, createWebHistory } from 'vue-router'
import AiAssistantView from '../views/AiAssistantView.vue'
import AnalyticsView from '../views/AnalyticsView.vue'
import CheckoutSuccessView from '../views/CheckoutSuccessView.vue'
import CheckoutView from '../views/CheckoutView.vue'
import DashboardView from '../views/DashboardView.vue'
import HomeView from '../views/HomeView.vue'
import GoLiveView from '../views/GoLiveView.vue'
import ImportsView from '../views/ImportsView.vue'
import IntegrationsView from '../views/IntegrationsView.vue'
import LoginView from '../views/LoginView.vue'
import MeasurementTablesView from '../views/MeasurementTablesView.vue'
import PrivacyView from '../views/PrivacyView.vue'
import ProductsView from '../views/ProductsView.vue'
import ProductTestView from '../views/ProductTestView.vue'
import SaasAdminView from '../views/SaasAdminView.vue'
import TermsView from '../views/TermsView.vue'
import WidgetInstallView from '../views/WidgetInstallView.vue'

const base = import.meta.env.VITE_APP_BASE_PATH || '/'

const router = createRouter({
  history: createWebHistory(base),
  routes: [
    { path: '/', component: HomeView },
    { path: '/login', component: LoginView },
    { path: '/cadastro', component: LoginView },
    { path: '/produto-teste', component: ProductTestView },
    { path: '/produto-teste/:slug', component: ProductTestView },
    { path: '/checkout', component: CheckoutView },
    { path: '/checkout/sucesso', component: CheckoutSuccessView },
    { path: '/privacidade', component: PrivacyView },
    { path: '/termos', component: TermsView },
    { path: '/app', component: DashboardView },
    { path: '/app/produtos', component: ProductsView },
    { path: '/app/tabelas-de-medidas', component: MeasurementTablesView },
    { path: '/app/assistente', component: AiAssistantView },
    { path: '/app/analytics', component: AnalyticsView },
    { path: '/app/go-live', component: GoLiveView },
    { path: '/app/importacoes', component: ImportsView },
    { path: '/app/widget', component: WidgetInstallView },
    { path: '/app/integracoes', component: IntegrationsView },
    { path: '/saas', component: SaasAdminView },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach((to) => {
  if ((to.path.startsWith('/app') || to.path.startsWith('/saas')) && !localStorage.getItem('pv_token')) {
    return '/login'
  }

  return true
})

export default router
