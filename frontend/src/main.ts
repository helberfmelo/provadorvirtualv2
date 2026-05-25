import '@fortawesome/fontawesome-free/css/all.min.css'
import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './style.css'

function redirectLegacyFrontendUrl() {
  const legacyBasePath = '/provadorvirtual_v2'
  const cleanRoutePrefixes = new Set([
    'app',
    'cadastro',
    'checkout',
    'login',
    'privacidade',
    'produto-teste',
    'saas',
    'termos',
  ])

  if (window.location.pathname !== legacyBasePath && !window.location.pathname.startsWith(`${legacyBasePath}/`)) {
    return false
  }

  const cleanPath = window.location.pathname.slice(legacyBasePath.length) || '/'
  const firstSegment = cleanPath.split('/').filter(Boolean)[0] || ''

  if (cleanPath !== '/' && !cleanRoutePrefixes.has(firstSegment)) {
    return false
  }

  window.location.replace(`${window.location.origin}${cleanPath}${window.location.search}${window.location.hash}`)

  return true
}

if (!redirectLegacyFrontendUrl()) {
  createApp(App).use(createPinia()).use(router).mount('#app')
}
