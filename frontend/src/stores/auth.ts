import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, setAuthToken } from '../services/api'

type AuthUser = {
  id: number
  name: string
  email: string
  role: string
}

const storedToken = localStorage.getItem('pv_token')

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(storedToken)
  const user = ref<AuthUser | null>(null)
  const isAuthenticated = computed(() => Boolean(token.value))

  setAuthToken(storedToken)

  async function login(email: string, password: string) {
    const { data } = await api.post('/auth/login', { email, password })
    token.value = data.token
    user.value = data.user
    localStorage.setItem('pv_token', data.token)
    setAuthToken(data.token)
  }

  async function loadMe() {
    if (!token.value) {
      return
    }

    const { data } = await api.get('/me')
    user.value = data.user
  }

  async function logout() {
    if (token.value) {
      await api.post('/auth/logout').catch(() => undefined)
    }

    token.value = null
    user.value = null
    localStorage.removeItem('pv_token')
    setAuthToken(null)
  }

  return { token, user, isAuthenticated, login, loadMe, logout }
})
