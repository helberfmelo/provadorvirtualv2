import axios from 'axios'
import {
  failSaveFeedback,
  finishSaveFeedback,
  shouldTrackSave,
  startSaveFeedback,
} from './saveFeedback'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1',
  headers: {
    Accept: 'application/json',
  },
})

export function setAuthToken(token: string | null) {
  if (token) {
    api.defaults.headers.common.Authorization = `Bearer ${token}`
    return
  }

  delete api.defaults.headers.common.Authorization
}

api.interceptors.request.use((config) => {
  if (shouldTrackSave(config)) {
    startSaveFeedback(config)
  }

  return config
})

api.interceptors.response.use(
  (response) => {
    finishSaveFeedback(response.config)

    return response
  },
  (error) => {
    failSaveFeedback(error)

    return Promise.reject(error)
  },
)
