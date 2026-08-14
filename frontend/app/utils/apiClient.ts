import { getAuthToken } from './authToken'

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export interface ApiFetchOptions {
  method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE'
  body?: unknown
}

/**
 * Thin authenticated fetch wrapper around the Laravel API (Bearer-token
 * Sanctum auth, see [[basixmeeple-project]] memory — no SPA cookie flow).
 */
export async function apiFetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
  const config = useRuntimeConfig()
  const token = getAuthToken()

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${config.public.apiBase}${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
  })

  if (!response.ok) {
    const body: unknown = await response.json().catch(() => null)
    const message =
      body && typeof body === 'object' && 'message' in body && typeof body.message === 'string'
        ? body.message
        : `Anfrage fehlgeschlagen (${response.status})`

    throw new ApiError(response.status, message)
  }

  if (response.status === 204) {
    return undefined as T
  }

  return (await response.json()) as T
}

/**
 * Same auth/error handling as `apiFetch`, but for multipart file uploads —
 * `apiFetch` always JSON-encodes its body, which can't carry a `File`.
 */
export async function apiFetchMultipart<T>(path: string, formData: FormData, method: 'POST' | 'PATCH' = 'POST'): Promise<T> {
  const config = useRuntimeConfig()
  const token = getAuthToken()

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${config.public.apiBase}${path}`, {
    method,
    headers,
    body: formData,
  })

  if (!response.ok) {
    const body: unknown = await response.json().catch(() => null)
    const message =
      body && typeof body === 'object' && 'message' in body && typeof body.message === 'string'
        ? body.message
        : `Anfrage fehlgeschlagen (${response.status})`

    throw new ApiError(response.status, message)
  }

  return (await response.json()) as T
}
