import axios from 'axios'

/**
 * Base API URL. VITE_API_URL usually points at `.../api/v1`; we normalise so
 * this client always builds URLs as `${base}/v1/whatever`.
 */
function resolveBase() {
  const raw = import.meta.env.VITE_API_URL || '/api'
  return raw.replace(/\/v1\/?$/, '').replace(/\/$/, '')
}

const baseURL = resolveBase()

export const api = axios.create({
  baseURL,
  timeout: 30_000,
  headers: { 'Content-Type': 'application/json' },
})

const TOKEN_KEY = 'build_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || ''
}

export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

api.interceptors.request.use((config) => {
  const t = getToken()
  if (t) {
    if (typeof config.headers?.set === 'function') {
      config.headers.set('Authorization', `Bearer ${t}`)
    } else {
      config.headers.Authorization = `Bearer ${t}`
    }
  }
  return config
})

api.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err.response?.status === 401) {
      const hadToken = getToken()
      setToken('')
      const onLogin = window.location.pathname.endsWith('/login')
      if (hadToken && !onLogin) {
        window.location.assign('/login')
      }
    }
    return Promise.reject(err)
  },
)

export const v1 = (path) => `/v1${path.startsWith('/') ? path : `/${path}`}`

/**
 * Convenience wrappers that unwrap the `{ success, message, data, errors }`
 * envelope Flow / Console / Build all share. On failure, throws an Error
 * with the server-provided `message`.
 */
async function unwrap(promise) {
  const res = await promise
  const body = res?.data ?? {}
  if (body && body.success === false) {
    const err = new Error(body.message || 'Request failed')
    err.errors = body.errors || {}
    err.status = res?.status
    throw err
  }
  return body?.data ?? body
}

export const http = {
  get:    (path, config)     => unwrap(api.get(v1(path), config)),
  post:   (path, body, cfg)  => unwrap(api.post(v1(path), body ?? {}, cfg)),
  put:    (path, body, cfg)  => unwrap(api.put(v1(path), body ?? {}, cfg)),
  patch:  (path, body, cfg)  => unwrap(api.patch(v1(path), body ?? {}, cfg)),
  delete: (path, config)     => unwrap(api.delete(v1(path), config)),
}

export function apiBaseUrl() {
  return baseURL
}

export function v1Absolute(path) {
  return `${apiBaseUrl()}${v1(path)}`
}
