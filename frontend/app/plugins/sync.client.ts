import { getAuthToken } from '~/utils/authToken'

const BACKGROUND_SYNC_INTERVAL_MS = 60_000

/**
 * Boots the sync engine once per app load: validates a stored token,
 * triggers an initial sync, and keeps syncing on a background interval and
 * whenever the device regains connectivity. Individual mutations trigger
 * their own (debounced) sync via `useSyncStatus().triggerSync()` — this
 * plugin only covers the "nothing changed locally, but time/connectivity
 * did" cases.
 */
export default defineNuxtPlugin(() => {
  const { isOnline, syncNow } = useSyncStatus()
  const { fetchMe } = useAuth()

  isOnline.value = navigator.onLine

  window.addEventListener('online', () => {
    isOnline.value = true
    void syncNow()
  })

  window.addEventListener('offline', () => {
    isOnline.value = false
  })

  if (getAuthToken()) {
    void fetchMe().then((ok) => {
      if (ok) void syncNow()
    })
  }

  setInterval(() => {
    if (getAuthToken() && navigator.onLine) void syncNow()
  }, BACKGROUND_SYNC_INTERVAL_MS)
})
