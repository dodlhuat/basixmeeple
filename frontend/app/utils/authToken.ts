// Minimal Sanctum bearer-token storage. No login UI/composable yet (that's
// a later step) — this is just the primitive the API client and sync engine
// need to attach `Authorization: Bearer …` to requests.
const STORAGE_KEY = 'basixmeeple.auth_token'

export function getAuthToken(): string | null {
  if (import.meta.server) return null
  return localStorage.getItem(STORAGE_KEY)
}

export function setAuthToken(token: string): void {
  if (import.meta.server) return
  localStorage.setItem(STORAGE_KEY, token)
}

export function clearAuthToken(): void {
  if (import.meta.server) return
  localStorage.removeItem(STORAGE_KEY)
}
