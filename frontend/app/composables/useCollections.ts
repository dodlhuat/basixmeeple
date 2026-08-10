import type { MaybeRefOrGetter } from 'vue'
import { db } from '~/utils/db'
import type { Collection, CollectionRole } from '~/types/models'

export function useCollections() {
  // `name` isn't an indexed field on this table (see db.ts) — the list is
  // small enough per user that an in-memory sort is simpler than a schema
  // version bump just to support orderBy().
  return useLiveQuery(
    async () => (await db.collections.toArray()).sort((a, b) => a.name.localeCompare(b.name)),
    [] as Collection[],
  )
}

export function useCollection(collectionId: MaybeRefOrGetter<string>) {
  return useLiveQuery(() => db.collections.get(toValue(collectionId)), undefined as Collection | undefined)
}

/** The current user's role in a collection, or `null` if not a member (or not loaded yet). */
export function useCollectionRole(collectionId: MaybeRefOrGetter<string>) {
  const { user } = useAuth()

  return useLiveQuery(async () => {
    if (!user.value) return null
    const membership = await db.collection_user
      .where('[collection_id+user_id]')
      .equals([toValue(collectionId), user.value.id])
      .first()
    return membership?.role ?? null
  }, null as CollectionRole | null)
}

const ACTIVE_COLLECTION_STORAGE_KEY = 'basixmeeple.active_collection_id'

/**
 * The collection shown by default in the nav switcher — separate from
 * whatever `[id]` happens to be in the current route, so the choice
 * persists when navigating to non-collection-scoped pages.
 */
export function useActiveCollectionId() {
  const id = useState<string | null>('active-collection-id', () => {
    if (import.meta.server) return null
    return localStorage.getItem(ACTIVE_COLLECTION_STORAGE_KEY)
  })

  function setActiveCollectionId(newId: string | null): void {
    id.value = newId

    if (import.meta.client) {
      if (newId) localStorage.setItem(ACTIVE_COLLECTION_STORAGE_KEY, newId)
      else localStorage.removeItem(ACTIVE_COLLECTION_STORAGE_KEY)
    }
  }

  return { id, setActiveCollectionId }
}
