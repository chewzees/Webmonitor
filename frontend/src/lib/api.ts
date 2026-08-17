import type { ApiErrorBody } from '@/types/api'

/** `/api` in Vite dev; `/Webmonitor/api` when served from XAMPP. */
export function apiBase(): string {
  const base = import.meta.env.BASE_URL.replace(/\/$/, '')
  return `${base}/api`
}

export class ApiError extends Error {
  code: string
  status: number
  details?: unknown

  constructor(status: number, body: ApiErrorBody | null, fallback = 'Request failed') {
    super(body?.error?.message ?? fallback)
    this.name = 'ApiError'
    this.status = status
    this.code = body?.error?.code ?? 'UNKNOWN'
    this.details = body?.error?.details
  }
}

let csrfToken: string | null = null

export function getCsrfToken() {
  return csrfToken
}

export function setCsrfToken(token: string | null) {
  csrfToken = token
}

function buildUrl(path: string, params?: Record<string, string | number | boolean | undefined | null>) {
  const normalized = path.startsWith('/api')
    ? `${apiBase()}${path.slice(4)}`
    : `${apiBase()}${path.startsWith('/') ? path : `/${path}`}`
  if (!params) return normalized
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    search.set(key, String(value))
  }
  const qs = search.toString()
  return qs ? `${normalized}?${qs}` : normalized
}

async function parseJsonSafe(res: Response) {
  const text = await res.text()
  if (!text) return null
  try {
    return JSON.parse(text)
  } catch {
    return null
  }
}

export async function ensureCsrf(): Promise<string> {
  if (csrfToken) return csrfToken
  const res = await fetch(`${apiBase()}/auth/csrf`, {
    credentials: 'include',
    headers: { 'X-Requested-With': 'fetch' },
  })
  const data = (await parseJsonSafe(res)) as { csrfToken?: string } | null
  if (!res.ok || !data?.csrfToken) {
    throw new ApiError(res.status, data as ApiErrorBody | null, 'Failed to fetch CSRF token')
  }
  csrfToken = data.csrfToken
  return csrfToken
}

type RequestOptions = Omit<RequestInit, 'body'> & {
  body?: unknown
  params?: Record<string, string | number | boolean | undefined | null>
  skipCsrf?: boolean
}

export async function api<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = (options.method ?? 'GET').toUpperCase()
  const isMutation = !['GET', 'HEAD', 'OPTIONS'].includes(method)
  const headers = new Headers(options.headers)

  if (!headers.has('X-Requested-With')) {
    headers.set('X-Requested-With', 'fetch')
  }

  if (isMutation && !options.skipCsrf) {
    const token = await ensureCsrf()
    headers.set('X-CSRF-Token', token)
  }

  let body: BodyInit | undefined
  if (options.body !== undefined) {
    if (options.body instanceof FormData || typeof options.body === 'string') {
      body = options.body as BodyInit
    } else {
      headers.set('Content-Type', 'application/json')
      body = JSON.stringify(options.body)
    }
  }

  const res = await fetch(buildUrl(path, options.params), {
    ...options,
    method,
    credentials: 'include',
    headers,
    body,
  })

  if (res.status === 204) return undefined as T

  const data = await parseJsonSafe(res)
  if (!res.ok) {
    if (res.status === 403 && isMutation) {
      csrfToken = null
    }
    throw new ApiError(res.status, data as ApiErrorBody | null)
  }
  return data as T
}

export async function apiBlob(
  path: string,
  params?: Record<string, string | number | boolean | undefined | null>,
): Promise<Blob> {
  const res = await fetch(buildUrl(path, params), {
    credentials: 'include',
    headers: { 'X-Requested-With': 'fetch' },
  })
  if (!res.ok) {
    const data = await parseJsonSafe(res)
    throw new ApiError(res.status, data as ApiErrorBody | null, 'Export failed')
  }
  return res.blob()
}
