import { apiFetch } from '~/utils/apiClient'
import { clearAuthToken, getAuthToken, setAuthToken } from '~/utils/authToken'
import { clearLocalData } from '~/utils/sync'
import type { User } from '~/types/models'

interface AuthResponse {
  user: User
  token: string
}

/**
 * Current authenticated user, shared app-wide. `null` means "not logged in
 * (yet)" — checked by the `auth` middleware. There is no session refresh
 * endpoint; the user snapshot is only (re-)populated on login/register or by
 * `fetchMe()`.
 */
function useAuthUser() {
  return useState<User | null>('auth-user', () => null)
}

export function useAuth() {
  const user = useAuthUser()

  async function login(email: string, password: string): Promise<void> {
    const response = await apiFetch<AuthResponse>('/api/login', {
      method: 'POST',
      body: { email, password },
    })

    setAuthToken(response.token)
    user.value = response.user
    // The sync.client.ts plugin's boot-time sync already missed this login
    // (it only fires if a token exists when the app first loads) — without
    // this, the app would stay empty until the next periodic/manual sync.
    await useSyncStatus().syncNow()
  }

  async function register(payload: {
    token: string
    name: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    const response = await apiFetch<AuthResponse>('/api/register', {
      method: 'POST',
      body: payload,
    })

    setAuthToken(response.token)
    user.value = response.user
    await useSyncStatus().syncNow()
  }

  async function logout(): Promise<void> {
    try {
      await apiFetch('/api/logout', { method: 'POST' })
    } finally {
      clearAuthToken()
      user.value = null
      // Avoid leaking the previous account's offline mirror to whoever logs
      // in next on this device/browser.
      await clearLocalData()
    }
  }

  /**
   * Restores the user snapshot from a stored token (e.g. after a page
   * reload). Returns false and clears the stale token if it no longer works.
   */
  async function fetchMe(): Promise<boolean> {
    if (!getAuthToken()) return false

    try {
      user.value = await apiFetch<User>('/api/me')
      return true
    } catch {
      clearAuthToken()
      user.value = null
      return false
    }
  }

  return { user, login, register, logout, fetchMe }
}
