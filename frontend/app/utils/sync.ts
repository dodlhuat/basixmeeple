import { db } from './db'
import { apiFetch } from './apiClient'
import type { SyncEntity, SyncOperation, SyncOperationResult, SyncResponse, SyncSnapshot, Uuid } from '~/types/models'

/**
 * Queues one offline mutation for the next `runSync()` call. `payload` should
 * include `updated_at` set to the current local time — the server uses it
 * for last-write-wins conflict resolution against its own copy.
 */
export async function enqueueSyncOperation(
  entity: SyncEntity,
  entityId: Uuid,
  operation: SyncOperation,
  payload: Record<string, unknown> | null,
): Promise<void> {
  await db.sync_queue.add({
    entity,
    entity_id: entityId,
    operation,
    payload,
    queued_at: new Date().toISOString(),
  })
}

const SYNCED_TABLES = [
  db.users,
  db.games,
  db.expansions,
  db.categories,
  db.game_category,
  db.collections,
  db.collection_games,
  db.collection_user,
  db.play_sessions,
  db.session_players,
  db.loans,
  db.wishlist_items,
] as const

export interface SyncRunResult {
  results: SyncOperationResult[]
}

/** Wipes every synced table plus the local sync_queue — used on logout so the next account on this device starts clean. */
export async function clearLocalData(): Promise<void> {
  await db.transaction('rw', [...SYNCED_TABLES, db.sync_queue], async () => {
    for (const table of SYNCED_TABLES) await table.clear()
    await db.sync_queue.clear()
  })
}

/**
 * Flushes the local sync_queue to the backend and reconciles the local Dexie
 * mirror with the authoritative snapshot the server sends back. Safe to call
 * with an empty queue (e.g. a periodic background refresh) — the push is
 * then just an empty operations array and the pull still happens.
 */
export async function runSync(): Promise<SyncRunResult> {
  const queueEntries = await db.sync_queue.orderBy('queued_at').toArray()

  const operations = queueEntries.map(({ local_id: _local_id, ...operation }) => operation)

  const response = await apiFetch<SyncResponse>('/api/sync', {
    method: 'POST',
    body: { operations },
  })

  await applySnapshot(response.snapshot)

  const localIds = queueEntries
    .map((entry) => entry.local_id)
    .filter((id): id is number => id !== undefined)
  await db.sync_queue.bulkDelete(localIds)

  return { results: response.results }
}

/**
 * Wholesale-replaces every synced Dexie table with the server's snapshot, in
 * one transaction. Deliberately clear-then-bulkPut per table rather than a
 * diff — the snapshot is the complete, authoritative state, so this also
 * removes anything the client only knew about because it (or a queued edit)
 * has since been deleted server-side.
 */
async function applySnapshot(snapshot: SyncSnapshot): Promise<void> {
  await db.transaction('rw', SYNCED_TABLES, async () => {
    await db.users.clear()
    await db.users.bulkPut(snapshot.users)

    await db.games.clear()
    await db.games.bulkPut(snapshot.games)

    await db.expansions.clear()
    await db.expansions.bulkPut(snapshot.expansions)

    await db.categories.clear()
    await db.categories.bulkPut(snapshot.categories)

    await db.game_category.clear()
    await db.game_category.bulkPut(snapshot.game_category)

    await db.collections.clear()
    await db.collections.bulkPut(snapshot.collections)

    await db.collection_games.clear()
    await db.collection_games.bulkPut(snapshot.collection_games)

    await db.collection_user.clear()
    await db.collection_user.bulkPut(snapshot.collection_user)

    await db.play_sessions.clear()
    await db.play_sessions.bulkPut(snapshot.play_sessions)

    await db.session_players.clear()
    await db.session_players.bulkPut(snapshot.session_players)

    await db.loans.clear()
    await db.loans.bulkPut(snapshot.loans)

    await db.wishlist_items.clear()
    await db.wishlist_items.bulkPut(snapshot.wishlist_items)
  })
}
