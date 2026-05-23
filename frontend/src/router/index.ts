import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
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
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

export default router
