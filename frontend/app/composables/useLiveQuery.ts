import { liveQuery } from 'dexie'
import type { Ref } from 'vue'

/**
 * Reactive wrapper around Dexie's `liveQuery()` — re-runs `querier` whenever
 * any Dexie table it reads changes, keeping the returned ref in sync with
 * the local offline mirror. `initial` is shown until the first query result
 * arrives (relevant since Dexie reads are always async).
 */
export function useLiveQuery<T>(querier: () => T | Promise<T>, initial: T): Ref<T> {
  const value = ref(initial) as Ref<T>

  const subscription = liveQuery(querier).subscribe({
    next: (result) => {
      value.value = result
    },
    error: (error) => {
      console.error('useLiveQuery failed', error)
    },
  })

  onScopeDispose(() => subscription.unsubscribe())

  return value
}
