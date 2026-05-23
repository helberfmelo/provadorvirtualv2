import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, setAuthToken } from '../services/api'

type AuthUser = {
  id: number
  name: string
  email: string
  cpf?: string | null
  role: string
}
type AuthMerchant = {
  id: number
  name: string
  slug: string
  billing_status: string
}
type AuthCompany = {
  id: number
  name: string
  access_code: string
  document: string | null
  platform: string
  status: string
}

const storedToken = localStorage.getItem('pv_token')

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(storedToken)
  const user = ref<AuthUser | null>(null)
  const activeMerchant = ref<AuthMerchant | null>(null)
  const activeCompany = ref<AuthCompany | null>(null)
  const isAuthenticated = computed(() => Boolean(token.value))

  setAuthToken(storedToken)

  async function login(identifier: string, password: string, companyAccess = '') {
    const { data } = await api.post('/auth/login', {
      login: identifier,
      password,
      company_access: companyAccess || undefined,
    })
    token.value = data.token
    user.value = data.user
    activeMerchant.value = data.active_merchant || null
    activeCompany.value = data.active_company || null
    localStorage.setItem('pv_token', data.token)
    setAuthToken(data.token)
  }

  async function loadMe() {
    if (!token.value) {
      return
    }

    const { data } = await api.get('/me')
    user.value = data.user
    activeMerchant.value = data.active_merchant || null
    activeCompany.value = data.active_company || null
  }

  async function logout() {
    if (token.value) {
      await api.post('/auth/logout').catch(() => undefined)
    }

    token.value = null
    user.value = null
    activeMerchant.value = null
    activeCompany.value = null
    localStorage.removeItem('pv_token')
    setAuthToken(null)
  }

  return { token, user, activeMerchant, activeCompany, isAuthenticated, login, loadMe, logout }
})
