const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'
const TOKEN_KEY = 'qr_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}
export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

export class ApiError extends Error {
  constructor(message, status, errors) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

async function request(path, { method = 'GET', body, params } = {}) {
  let url = `${BASE_URL}${path}`
  if (params) {
    const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== ''))
    const s = qs.toString()
    if (s) url += `?${s}`
  }

  const headers = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const isFormData = body instanceof FormData
  // Let the browser set Content-Type for FormData (it needs the multipart
  // boundary token, which we can't set by hand).
  if (body !== undefined && !isFormData) headers['Content-Type'] = 'application/json'

  const res = await fetch(url, {
    method,
    headers,
    body: body === undefined ? undefined : isFormData ? body : JSON.stringify(body),
  })

  if (res.status === 204) return null
  const data = await res.json().catch(() => null)

  if (!res.ok) {
    throw new ApiError(data?.message || `Request failed (${res.status})`, res.status, data?.errors)
  }
  return data
}

export const api = {
  get: (path, params) => request(path, { method: 'GET', params }),
  post: (path, body) => request(path, { method: 'POST', body }),
  put: (path, body) => request(path, { method: 'PUT', body }),
  patch: (path, body) => request(path, { method: 'PATCH', body }),
  del: (path) => request(path, { method: 'DELETE' }),
}
