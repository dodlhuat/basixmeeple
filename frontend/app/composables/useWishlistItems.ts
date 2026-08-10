import type { MaybeRefOrGetter } from 'vue'
import { db } from '~/utils/db'
import type { WishlistItem } from '~/types/models'

export function useWishlistItems(collectionId: MaybeRefOrGetter<string>) {
  // `title` isn't an indexed field (see db.ts) — sort in-memory rather than
  // orderBy() (see [[basixmeeple-project]] memory: the useCollections() bug).
  return useLiveQuery(async () => {
    const items = await db.wishlist_items.where('collection_id').equals(toValue(collectionId)).toArray()
    return items.sort((a, b) => b.priority - a.priority || a.title.localeCompare(b.title))
  }, [] as WishlistItem[])
}
