import { createRouter, createWebHistory } from 'vue-router'
import AiAssistantView from '../views/AiAssistantView.vue'
import AnalyticsView from '../views/AnalyticsView.vue'
import BigShopChangeTermsView from '../views/BigShopChangeTermsView.vue'
import CheckoutSuccessView from '../views/CheckoutSuccessView.vue'
import CheckoutView from '../views/CheckoutView.vue'
import DashboardView from '../views/DashboardView.vue'
import HomeView from '../views/HomeView.vue'
import FitProfilesView from '../views/FitProfilesView.vue'
import GoLiveView from '../views/GoLiveView.vue'
import ImportRulesView from '../views/ImportRulesView.vue'
import ImportsView from '../views/ImportsView.vue'
import IntegrationsView from '../views/IntegrationsView.vue'
import LoginView from '../views/LoginView.vue'
import MeasurementTableFormView from '../views/MeasurementTableFormView.vue'
import MeasurementTablesView from '../views/MeasurementTablesView.vue'
import ProductFormView from '../views/ProductFormView.vue'
import PrivacyView from '../views/PrivacyView.vue'
import ProductsView from '../views/ProductsView.vue'
import ProductTestView from '../views/ProductTestView.vue'
import SaasAdminView from '../views/SaasAdminView.vue'
import SaasCompaniesView from '../views/SaasCompaniesView.vue'
import SaasCompanyFormView from '../views/SaasCompanyFormView.vue'
import SaasCompanyUserFormView from '../views/SaasCompanyUserFormView.vue'
import SaasCompanyUsersView from '../views/SaasCompanyUsersView.vue'
import SaasCheckoutOrderDetailView from '../views/SaasCheckoutOrderDetailView.vue'
import SaasCheckoutOrdersView from '../views/SaasCheckoutOrdersView.vue'
import SaasCheckoutSettingsView from '../views/SaasCheckoutSettingsView.vue'
import SaasEmailFormView from '../views/SaasEmailFormView.vue'
import SaasEmailsView from '../views/SaasEmailsView.vue'
import SaasEmailSettingsView from '../views/SaasEmailSettingsView.vue'
import SaasUserFormView from '../views/SaasUserFormView.vue'
import SaasUsersView from '../views/SaasUsersView.vue'
import SyncStatusView from '../views/SyncStatusView.vue'
import TermsView from '../views/TermsView.vue'
import UserFormView from '../views/UserFormView.vue'
import UsersView from '../views/UsersView.vue'
import WidgetInstallView from '../views/WidgetInstallView.vue'

const base = import.meta.env.VITE_APP_BASE_PATH || '/'

const router = createRouter({
  history: createWebHistory(base),
  routes: [
    { path: '/', component: HomeView },
    { path: '/login', component: LoginView },
    { path: '/saas/login', component: LoginView },
    { path: '/cadastro', component: LoginView },
    { path: '/produto-teste', component: ProductTestView },
    { path: '/produto-teste/:slug', component: ProductTestView },
    { path: '/checkout', component: CheckoutView },
    { path: '/checkout/sucesso', component: CheckoutSuccessView },
    { path: '/privacidade', component: PrivacyView },
    { path: '/termos', component: TermsView },
    { path: '/termos/troca-bigshop', component: BigShopChangeTermsView },
    { path: '/app', component: DashboardView },
    { path: '/app/produtos', component: ProductsView },
    { path: '/app/produtos/novo', component: ProductFormView },
    { path: '/app/produtos/:id/editar', component: ProductFormView },
    { path: '/app/tabelas-de-medidas', component: MeasurementTablesView },
    { path: '/app/tabelas-de-medidas/nova', component: MeasurementTableFormView },
    { path: '/app/tabelas-de-medidas/:id/editar', component: MeasurementTableFormView },
    { path: '/app/modelagens', component: FitProfilesView },
    { path: '/app/assistente', component: AiAssistantView },
    { path: '/app/analytics', component: AnalyticsView },
    { path: '/app/go-live', component: GoLiveView },
    { path: '/app/importacoes', component: ImportsView },
    { path: '/app/regras-de-importacao', component: ImportRulesView },
    { path: '/app/widget', component: WidgetInstallView },
    { path: '/app/integracoes', component: IntegrationsView },
    { path: '/app/sincronizacao', component: SyncStatusView },
    { path: '/app/usuarios', component: UsersView },
    { path: '/app/usuarios/novo', component: UserFormView },
    { path: '/app/usuarios/:id/editar', component: UserFormView },
    { path: '/saas', component: SaasAdminView },
    { path: '/saas/empresas', component: SaasCompaniesView },
    { path: '/saas/empresas/nova', component: SaasCompanyFormView },
    { path: '/saas/empresas/:id/editar', component: SaasCompanyFormView },
    { path: '/saas/usuarios', component: SaasUsersView },
    { path: '/saas/usuarios/novo', component: SaasUserFormView },
    { path: '/saas/usuarios/:id/editar', component: SaasUserFormView },
    { path: '/saas/usuarios-empresas', component: SaasCompanyUsersView },
    { path: '/saas/usuarios-empresas/novo', component: SaasCompanyUserFormView },
    { path: '/saas/usuarios-empresas/:id/editar', component: SaasCompanyUserFormView },
    { path: '/saas/checkout', component: SaasCheckoutSettingsView },
    { path: '/saas/pedidos', component: SaasCheckoutOrdersView },
    { path: '/saas/pedidos/:id', component: SaasCheckoutOrderDetailView },
    { path: '/saas/emails', component: SaasEmailsView },
    { path: '/saas/emails/configuracoes', component: SaasEmailSettingsView },
    { path: '/saas/emails/novo', component: SaasEmailFormView },
    { path: '/saas/emails/:id/editar', component: SaasEmailFormView },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach((to) => {
  const needsCompanyAuth = to.path === '/app' || to.path.startsWith('/app/')
  const needsSaasAuth = (to.path === '/saas' || to.path.startsWith('/saas/')) && to.path !== '/saas/login'

  if ((needsCompanyAuth || needsSaasAuth) && !localStorage.getItem('pv_token')) {
    return {
      path: needsSaasAuth ? '/saas/login' : '/login',
      query: { redirect: to.fullPath },
    }
  }

  return true
})

export default router
