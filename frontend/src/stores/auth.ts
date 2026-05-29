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
  legal_name?: string | null
  document: string | null
  zip_code?: string | null
  street?: string | null
  number?: string | null
  complement?: string | null
  district?: string | null
  city?: string | null
  state?: string | null
  country?: string | null
  domain?: string | null
  platform: string
  bigshop_discount_active?: boolean
  external_store_id?: string | null
  status: string
  profile_completed?: boolean
}
type CompanyOption = AuthCompany & {
  merchant: AuthMerchant | null
}

const tokenStorageKey = 'pv_token'
const companyStorageKey = 'pv_active_company_id'
const storedToken = localStorage.getItem(tokenStorageKey)
type PermissionMap = Record<string, { view: boolean; edit: boolean }>

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(storedToken)
  const user = ref<AuthUser | null>(null)
  const activeMerchant = ref<AuthMerchant | null>(null)
  const activeCompany = ref<AuthCompany | null>(null)
  const companyOptions = ref<CompanyOption[]>([])
  const permissions = ref<PermissionMap | null>(null)
  const saasPermissions = ref<PermissionMap | null>(null)
  const loadingMe = ref(false)
  const initialized = ref(!storedToken)
  const isAuthenticated = computed(() => Boolean(token.value))
  let loadMePromise: Promise<void> | null = null

  setAuthToken(storedToken)

  async function login(identifier: string, password: string, companyAccess = '') {
    const { data } = await api.post('/auth/login', {
      login: identifier,
      password,
      company_access: companyAccess || undefined,
    })
    applySessionPayload(data)
    initialized.value = true
  }

  async function loadMe() {
    if (loadMePromise) {
      return loadMePromise
    }

    loadMePromise = fetchMe()

    try {
      await loadMePromise
    } finally {
      loadMePromise = null
    }
  }

  async function ensureLoaded() {
    if (initialized.value) {
      return
    }

    await loadMe()
  }

  async function fetchMe() {
    if (!token.value) {
      initialized.value = true
      return
    }

    loadingMe.value = true

    try {
      const rememberedCompanyId = rememberedActiveCompanyId()
      const { data } = await api.get('/me')
      applySessionPayload(data, false, false)

      if (
        rememberedCompanyId
        && rememberedCompanyId !== activeCompany.value?.id
        && companyOptions.value.some((company) => company.id === rememberedCompanyId)
      ) {
        await selectCompany(rememberedCompanyId)
        return
      }

      rememberActiveCompany(activeCompany.value)
    } finally {
      loadingMe.value = false
      initialized.value = true
    }
  }

  function applySessionPayload(data: any, persistToken = true, rememberCompany = true) {
    if (data.token) {
      token.value = data.token
      if (persistToken) {
        localStorage.setItem(tokenStorageKey, data.token)
      }
      setAuthToken(data.token)
    }

    user.value = data.user
    activeMerchant.value = data.active_merchant || null
    activeCompany.value = data.active_company || null
    companyOptions.value = data.company_options || []
    permissions.value = data.permissions || null
    saasPermissions.value = data.saas_permissions || null

    if (rememberCompany) {
      rememberActiveCompany(activeCompany.value)
    }
  }

  function rememberActiveCompany(company: AuthCompany | null) {
    if (company?.id) {
      localStorage.setItem(companyStorageKey, String(company.id))
      return
    }

    localStorage.removeItem(companyStorageKey)
  }

  function rememberedActiveCompanyId() {
    const value = Number(localStorage.getItem(companyStorageKey) || 0)

    return Number.isFinite(value) && value > 0 ? value : null
  }

  async function selectCompany(companyId: number) {
    const { data } = await api.post('/auth/select-company', {
      company_id: companyId,
    })

    applySessionPayload(data)
    initialized.value = true
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
    loadingMe.value = false
    initialized.value = true
    localStorage.removeItem(tokenStorageKey)
    localStorage.removeItem(companyStorageKey)
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
    loadingMe,
    initialized,
    isAuthenticated,
    login,
    loadMe,
    ensureLoaded,
    selectCompany,
    logout,
    canView,
    canEdit,
    canSaasView,
    canSaasEdit,
  }
})
