import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import MeasurementTablesView from '../views/MeasurementTablesView.vue'
import ProductsView from '../views/ProductsView.vue'
import ProductTestView from '../views/ProductTestView.vue'

const base = import.meta.env.VITE_APP_BASE_PATH || '/'

const router = createRouter({
  history: createWebHistory(base),
  routes: [
    { path: '/', component: HomeView },
    { path: '/login', component: LoginView },
    { path: '/cadastro', component: LoginView },
    { path: '/produto-teste', component: ProductTestView },
    { path: '/app', component: DashboardView },
    { path: '/app/produtos', component: ProductsView },
    { path: '/app/tabelas-de-medidas', component: MeasurementTablesView },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach((to) => {
  if (to.path.startsWith('/app') && !localStorage.getItem('pv_token')) {
    return '/login'
  }

  return true
})

export default router
