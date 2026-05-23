import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, setAuthToken } from '../services/api'

type AuthUser = {
  id: number
  name: string
  email: string
  cpf?: string | null
  role: string
  status?: string
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
type CompanyOption = AuthCompany & {
  merchant: AuthMerchant | null
}

const storedToken = localStorage.getItem('pv_token')
type PermissionMap = Record<string, { view: boolean; edit: boolean }>

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(storedToken)
  const user = ref<AuthUser | null>(null)
  const activeMerchant = ref<AuthMerchant | null>(null)
  const activeCompany = ref<AuthCompany | null>(null)
  const companyOptions = ref<CompanyOption[]>([])
  const permissions = ref<PermissionMap | null>(null)
  const saasPermissions = ref<PermissionMap | null>(null)
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
    companyOptions.value = data.company_options || []
    permissions.value = data.permissions || null
    saasPermissions.value = data.saas_permissions || null
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
    companyOptions.value = data.company_options || []
    permissions.value = data.permissions || null
    saasPermissions.value = data.saas_permissions || null
  }

  async function selectCompany(companyId: number) {
    const { data } = await api.post('/auth/select-company', {
      company_id: companyId,
    })

    token.value = data.token
    user.value = data.user
    activeMerchant.value = data.active_merchant || null
    activeCompany.value = data.active_company || null
    companyOptions.value = data.company_options || []
    permissions.value = data.permissions || null
    saasPermissions.value = data.saas_permissions || null
    localStorage.setItem('pv_token', data.token)
    setAuthToken(data.token)
  }

  async function logout() {
    if (token.value) {
      await api.post('/auth/logout').catch(() => undefined)
    }

    token.value = null
    user.value = null
    activeMerchant.value = null
    activeCompany.value = null
    companyOptions.value = []
    permissions.value = null
    saasPermissions.value = null
    localStorage.removeItem('pv_token')
    setAuthToken(null)
  }

  function canView(module: string) {
    if (['admin', 'support'].includes(user.value?.role || '')) {
      return true
    }

    return permissions.value ? Boolean(permissions.value[module]?.view) : true
  }

  function canEdit(module: string) {
    if (['admin', 'support'].includes(user.value?.role || '')) {
      return true
    }

    return permissions.value ? Boolean(permissions.value[module]?.edit) : true
  }

  function canSaasView(module: string) {
    if (!['admin', 'support'].includes(user.value?.role || '')) {
      return false
    }

    return saasPermissions.value ? Boolean(saasPermissions.value[module]?.view) : true
  }

  function canSaasEdit(module: string) {
    if (!['admin', 'support'].includes(user.value?.role || '')) {
      return false
    }

    return saasPermissions.value ? Boolean(saasPermissions.value[module]?.edit) : true
  }

  return {
    token,
    user,
    activeMerchant,
    activeCompany,
    companyOptions,
    permissions,
    saasPermissions,
    isAuthenticated,
    login,
    loadMe,
    selectCompany,
    logout,
    canView,
    canEdit,
    canSaasView,
    canSaasEdit,
  }
})
