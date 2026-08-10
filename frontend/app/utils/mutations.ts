import { db } from './db'
import { enqueueSyncOperation } from './sync'
import { apiFetch } from './apiClient'
import type { Collection, CollectionGame, Game, PlaySession, PlaySessionOutcome, SessionPlayer, Uuid } from '~/types/models'

function nowIso(): string {
  return new Date().toISOString()
}

/** Fires the debounced sync after a local write — safe to call repeatedly. */
function afterMutation(): void {
  useSyncStatus().triggerSync()
}

type PivotInput = { location?: string | null; condition?: string | null; notes?: string | null }

export type GameInput = Partial<Omit<Game, 'id' | 'created_at' | 'updated_at'>> & { title: string }

/**
 * Creates a new catalog game and attaches it to the collection in one local
 * transaction — the offline-first equivalent of the combined
 * POST /collections/{id}/games REST endpoint (see [[basixmeeple-project]]
 * memory on Schritt 5's "combined create+attach" design).
 */
export async function createGameInCollection(collectionId: Uuid, input: GameInput, pivot: PivotInput = {}): Promise<Uuid> {
  const gameId = crypto.randomUUID()
  const pivotId = crypto.randomUUID()
  const timestamp = nowIso()

  const game: Game = {
    id: gameId,
    bgg_id: null,
    publisher: null,
    min_players: null,
    max_players: null,
    play_time_min: null,
    play_time_max: null,
    min_age: null,
    weight_complexity: null,
    description: null,
    cover_url: null,
    rulebook_path: null,
    edition: null,
    language: null,
    condition_notes: null,
    purchase_price: null,
    ...input,
    created_at: timestamp,
    updated_at: timestamp,
  }

  const pivotRow: CollectionGame = {
    id: pivotId,
    collection_id: collectionId,
    game_id: gameId,
    location: pivot.location ?? null,
    condition: pivot.condition ?? null,
    notes: pivot.notes ?? null,
    created_at: timestamp,
    updated_at: timestamp,
  }

  await db.transaction('rw', db.games, db.collection_games, async () => {
    await db.games.put(game)
    await db.collection_games.put(pivotRow)
  })

  await enqueueSyncOperation('games', gameId, 'create', { ...input, updated_at: timestamp })
  await enqueueSyncOperation('collection_games', pivotId, 'create', {
    collection_id: collectionId,
    game_id: gameId,
    location: pivotRow.location,
    condition: pivotRow.condition,
    notes: pivotRow.notes,
    updated_at: timestamp,
  })

  afterMutation()

  return gameId
}

/** Attaches a game already known locally (e.g. shared with another collection) to this one. */
export async function attachExistingGame(collectionId: Uuid, gameId: Uuid, pivot: PivotInput = {}): Promise<void> {
  const pivotId = crypto.randomUUID()
  const timestamp = nowIso()

  const pivotRow: CollectionGame = {
    id: pivotId,
    collection_id: collectionId,
    game_id: gameId,
    location: pivot.location ?? null,
    condition: pivot.condition ?? null,
    notes: pivot.notes ?? null,
    created_at: timestamp,
    updated_at: timestamp,
  }

  await db.collection_games.put(pivotRow)
  await enqueueSyncOperation('collection_games', pivotId, 'create', {
    collection_id: collectionId,
    game_id: gameId,
    location: pivotRow.location,
    condition: pivotRow.condition,
    notes: pivotRow.notes,
    updated_at: timestamp,
  })

  afterMutation()
}

export async function updateGame(gameId: Uuid, changes: Partial<Omit<Game, 'id' | 'created_at' | 'updated_at'>>): Promise<void> {
  const timestamp = nowIso()

  await db.games.update(gameId, { ...changes, updated_at: timestamp })
  await enqueueSyncOperation('games', gameId, 'update', { ...changes, updated_at: timestamp })

  afterMutation()
}

/** Removes a game and everything hanging off it locally, mirroring the backend's FK cascade. */
export async function deleteGame(gameId: Uuid): Promise<void> {
  await db.transaction(
    'rw',
    [db.games, db.collection_games, db.expansions, db.game_category, db.play_sessions, db.session_players, db.loans],
    async () => {
      const sessionIds = (await db.play_sessions.where('game_id').equals(gameId).toArray()).map((s) => s.id)
      await db.session_players.where('session_id').anyOf(sessionIds).delete()
      await db.play_sessions.where('game_id').equals(gameId).delete()
      await db.loans.where('game_id').equals(gameId).delete()
      await db.expansions.where('base_game_id').equals(gameId).delete()
      await db.game_category.where('game_id').equals(gameId).delete()
      await db.collection_games.where('game_id').equals(gameId).delete()
      await db.games.delete(gameId)
    },
  )

  await enqueueSyncOperation('games', gameId, 'delete', null)

  afterMutation()
}

/** Detaches a game from one collection only; the game (and its presence elsewhere) is untouched. */
export async function detachGameFromCollection(collectionId: Uuid, gameId: Uuid): Promise<void> {
  const pivot = await db.collection_games.where('[collection_id+game_id]').equals([collectionId, gameId]).first()
  if (!pivot) return

  await db.collection_games.delete(pivot.id)
  await enqueueSyncOperation('collection_games', pivot.id, 'delete', null)

  afterMutation()
}

export interface PlaySessionPlayerInput {
  user_id?: Uuid | null
  player_name: string
  is_winner?: boolean
  score?: number | null
}

export interface PlaySessionInput {
  game_id: Uuid
  played_at: string
  duration_min?: number | null
  outcome?: PlaySessionOutcome | null
  notes?: string | null
}

export async function logPlaySession(
  collectionId: Uuid,
  input: PlaySessionInput,
  players: PlaySessionPlayerInput[],
): Promise<Uuid> {
  const sessionId = crypto.randomUUID()
  const timestamp = nowIso()

  const session: PlaySession = {
    id: sessionId,
    collection_id: collectionId,
    game_id: input.game_id,
    played_at: input.played_at,
    duration_min: input.duration_min ?? null,
    outcome: input.outcome ?? null,
    notes: input.notes ?? null,
    created_at: timestamp,
    updated_at: timestamp,
  }

  const playerRows: SessionPlayer[] = players.map((player) => ({
    id: crypto.randomUUID(),
    session_id: sessionId,
    user_id: player.user_id ?? null,
    player_name: player.player_name,
    is_winner: player.is_winner ?? false,
    score: player.score ?? null,
    created_at: timestamp,
    updated_at: timestamp,
  }))

  await db.transaction('rw', db.play_sessions, db.session_players, async () => {
    await db.play_sessions.put(session)
    await db.session_players.bulkPut(playerRows)
  })

  await enqueueSyncOperation('play_sessions', sessionId, 'create', {
    game_id: session.game_id,
    collection_id: session.collection_id,
    played_at: session.played_at,
    duration_min: session.duration_min,
    outcome: session.outcome,
    notes: session.notes,
    updated_at: timestamp,
  })

  for (const player of playerRows) {
    await enqueueSyncOperation('session_players', player.id, 'create', {
      session_id: player.session_id,
      user_id: player.user_id,
      player_name: player.player_name,
      is_winner: player.is_winner,
      score: player.score,
      updated_at: timestamp,
    })
  }

  afterMutation()

  return sessionId
}

/** Server cascades session_players via FK once the play_session row is gone — no separate delete ops needed for them. */
export async function deletePlaySession(sessionId: Uuid): Promise<void> {
  await db.transaction('rw', db.play_sessions, db.session_players, async () => {
    await db.session_players.where('session_id').equals(sessionId).delete()
    await db.play_sessions.delete(sessionId)
  })

  await enqueueSyncOperation('play_sessions', sessionId, 'delete', null)

  afterMutation()
}

// --- REST-only actions (require connectivity; not offline-queueable) ---

/**
 * Renaming/creating a `collections` row and BGG import both go straight to
 * the REST API rather than the sync queue (see [[basixmeeple-project]]
 * memory: `collections` is pull-only in the sync engine, and BGG import
 * inherently needs a live call to BoardGameGeek). Both await a full sync
 * immediately after so the local Dexie mirror reflects the change before
 * the caller navigates away.
 */
export async function createCollection(name: string): Promise<Collection> {
  const collection = await apiFetch<Collection>('/api/collections', {
    method: 'POST',
    body: { name },
  })

  await useSyncStatus().syncNow()

  return collection
}

export interface BggSearchResult {
  bgg_id: number
  title: string
  year_published: number | null
}

export async function bggSearch(query: string): Promise<BggSearchResult[]> {
  return apiFetch<BggSearchResult[]>(`/api/bgg/search?q=${encodeURIComponent(query)}`)
}

export async function bggImportGame(collectionId: Uuid, bggId: number, pivot: PivotInput = {}): Promise<void> {
  await apiFetch(`/api/collections/${collectionId}/games/import-bgg`, {
    method: 'POST',
    body: { bgg_id: bggId, ...pivot },
  })

  await useSyncStatus().syncNow()
}
