import { runSync } from '~/utils/sync'

let inFlight: Promise<void> | null = null
let debounceTimer: ReturnType<typeof setTimeout> | null = null

function useSyncState() {
  return {
    isOnline: useState('sync-is-online', () => true),
    isSyncing: useState('sync-is-syncing', () => false),
    lastSyncedAt: useState<string | null>('sync-last-synced-at', () => null),
    lastError: useState<string | null>('sync-last-error', () => null),
  }
}

/**
 * Shared sync status + trigger, used both by the automatic wiring
 * (`plugins/sync.client.ts`) and the manual "Jetzt synchronisieren" button.
 * A missing/expired auth token or being offline are expected, silent
 * conditions here (not surfaced as `lastError`) — actual server-side
 * rejections are.
 */
export function useSyncStatus() {
  const { isOnline, isSyncing, lastSyncedAt, lastError } = useSyncState()

  async function syncNow(): Promise<void> {
    if (inFlight) return inFlight

    isSyncing.value = true
    inFlight = (async () => {
      try {
        await runSync()
        lastSyncedAt.value = new Date().toISOString()
        lastError.value = null
        isOnline.value = true
      } catch (e) {
        isOnline.value = typeof navigator === 'undefined' ? isOnline.value : navigator.onLine
        lastError.value = isOnline.value && e instanceof Error ? e.message : null
      } finally {
        isSyncing.value = false
        inFlight = null
      }
    })()

    return inFlight
  }

  /** Debounced trigger, safe to call after every local mutation. */
  function triggerSync(): void {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      debounceTimer = null
      void syncNow()
    }, 800)
  }

  return { isOnline, isSyncing, lastSyncedAt, lastError, triggerSync, syncNow }
}
