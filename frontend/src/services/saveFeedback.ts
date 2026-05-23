import type { AxiosError, InternalAxiosRequestConfig } from 'axios'
import { reactive } from 'vue'

type FeedbackStatus = 'saving' | 'success' | 'error'

type TrackedRequest = InternalAxiosRequestConfig & {
  pvTrackSave?: boolean
}

export const saveFeedback = reactive({
  open: false,
  status: 'saving' as FeedbackStatus,
  title: 'Salvando',
  message: 'Aguarde um instante.',
  pending: 0,
  timer: 0,
})

const mutationMethods = new Set(['post', 'put', 'patch', 'delete'])
const ignoredPrefixes = [
  '/auth/login',
  '/auth/logout',
  '/auth/select-company',
  '/public/',
  '/ai/measurement-table-suggestions',
  '/imports/preview',
]
const ignoredFragments = [
  '/probe',
  '/sync',
  '/validate-install',
  '/recommendations',
]

export function shouldTrackSave(config: InternalAxiosRequestConfig) {
  const method = String(config.method || 'get').toLowerCase()
  const url = String(config.url || '')

  if (!mutationMethods.has(method)) {
    return false
  }

  if (!localStorage.getItem('pv_token')) {
    return false
  }

  if (ignoredPrefixes.some((prefix) => url.startsWith(prefix))) {
    return false
  }

  if (ignoredFragments.some((fragment) => url.includes(fragment))) {
    return false
  }

  return true
}

export function startSaveFeedback(config: InternalAxiosRequestConfig) {
  const tracked = config as TrackedRequest
  tracked.pvTrackSave = true
  window.clearTimeout(saveFeedback.timer)
  saveFeedback.pending += 1
  saveFeedback.open = true
  saveFeedback.status = 'saving'
  saveFeedback.title = 'Salvando'
  saveFeedback.message = 'Aguarde um instante.'
}

export function finishSaveFeedback(config?: InternalAxiosRequestConfig) {
  const tracked = config as TrackedRequest | undefined

  if (!tracked?.pvTrackSave) {
    return
  }

  saveFeedback.pending = Math.max(0, saveFeedback.pending - 1)

  if (saveFeedback.pending > 0) {
    return
  }

  window.clearTimeout(saveFeedback.timer)
  saveFeedback.open = true
  saveFeedback.status = 'success'
  saveFeedback.title = 'Salvo'
  saveFeedback.message = 'Alteracao salva com sucesso.'
  saveFeedback.timer = window.setTimeout(() => {
    closeSaveFeedback()
  }, 4000)
}

export function failSaveFeedback(error: AxiosError) {
  const tracked = error.config as TrackedRequest | undefined

  if (!tracked?.pvTrackSave) {
    return
  }

  window.clearTimeout(saveFeedback.timer)
  saveFeedback.pending = 0
  saveFeedback.open = true
  saveFeedback.status = 'error'
  saveFeedback.title = 'Não foi possível salvar'
  saveFeedback.message = friendlyErrorMessage(error)
}

export function closeSaveFeedback() {
  window.clearTimeout(saveFeedback.timer)
  saveFeedback.open = false
  saveFeedback.pending = 0
}

function friendlyErrorMessage(error: AxiosError) {
  const status = error.response?.status
  const data = error.response?.data as {
    message?: string
    errors?: Record<string, string[] | string>
  } | undefined
  const validationMessage = firstValidationMessage(data?.errors)
  const message = validationMessage || data?.message || ''

  if (message) {
    return translateKnownMessage(message, status)
  }

  if (status === 403) {
    return 'Seu usuário não tem permissão para salvar esta alteração.'
  }

  if (status === 404) {
    return 'O registro não foi encontrado. Atualize a página e tente novamente.'
  }

  if (status === 422) {
    return 'Revise os campos destacados e tente salvar novamente.'
  }

  if (!error.response) {
    return 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.'
  }

  return 'Ocorreu um erro ao salvar. Tente novamente em alguns instantes.'
}

function firstValidationMessage(errors?: Record<string, string[] | string>) {
  if (!errors) {
    return ''
  }

  const first = Object.values(errors)[0]

  if (Array.isArray(first)) {
    return first[0] || ''
  }

  return first || ''
}

function translateKnownMessage(message: string, status?: number) {
  const normalized = message.trim()

  if (normalized === 'The given data was invalid.') {
    return 'Revise os campos e tente salvar novamente.'
  }

  if (normalized.includes('selected merchant company id')) {
    return 'A empresa selecionada não foi encontrada. Atualize a página e selecione a empresa novamente.'
  }

  if (normalized.includes('email field must be a valid email')) {
    return 'Informe um e-mail válido.'
  }

  if (normalized.includes('cpf field must be 11')) {
    return 'Informe um CPF com 11 digitos.'
  }

  if (normalized.includes('password field must be at least')) {
    return 'A senha precisa ter pelo menos 8 caracteres.'
  }

  if (status === 422 && normalized.includes('already been taken')) {
    return 'Ja existe um usuário com estes dados. Confira e tente novamente.'
  }

  return normalized
}
