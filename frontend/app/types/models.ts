// Mirrors the backend's JSON shape 1:1 (snake_case, ISO date strings) so
// records can move between Dexie and the API without a mapping layer.

export type Uuid = string
export type IsoDateTime = string
export type IsoDate = string

export type CollectionRole = 'owner' | 'editor' | 'viewer'
export type PlaySessionOutcome = 'win' | 'loss' | 'draw'

export interface User {
  id: Uuid
  name: string
  email: string
}

export interface Game {
  id: Uuid
  title: string
  bgg_id: number | null
  publisher: string | null
  min_players: number | null
  max_players: number | null
  play_time_min: number | null
  play_time_max: number | null
  min_age: number | null
  weight_complexity: string | null
  description: string | null
  cover_url: string | null
  rulebook_path: string | null
  edition: string | null
  language: string | null
  condition_notes: string | null
  purchase_price: string | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface Expansion {
  id: Uuid
  base_game_id: Uuid
  title: string
  bgg_id: number | null
  cover_url: string | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface Category {
  id: Uuid
  name: string
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface GameCategory {
  game_id: Uuid
  category_id: Uuid
}

export interface Collection {
  id: Uuid
  name: string
  owner_id: Uuid
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface CollectionGame {
  id: Uuid
  collection_id: Uuid
  game_id: Uuid
  location: string | null
  condition: string | null
  notes: string | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface CollectionUser {
  collection_id: Uuid
  user_id: Uuid
  role: CollectionRole
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface PlaySession {
  id: Uuid
  game_id: Uuid
  collection_id: Uuid
  played_at: IsoDateTime
  duration_min: number | null
  outcome: PlaySessionOutcome | null
  notes: string | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface SessionPlayer {
  id: Uuid
  session_id: Uuid
  user_id: Uuid | null
  player_name: string
  is_winner: boolean
  score: number | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface Loan {
  id: Uuid
  game_id: Uuid
  borrower_name: string
  borrower_user_id: Uuid | null
  loaned_at: IsoDateTime
  due_date: IsoDate | null
  returned_at: IsoDateTime | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface WishlistItem {
  id: Uuid
  collection_id: Uuid
  title: string
  bgg_id: number | null
  priority: number
  price_estimate: string | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

/**
 * Entities writable through the generic sync queue. `collections` and
 * `collection_user` are deliberately not part of this union — renaming a
 * collection or changing membership/roles has side effects (invite mails,
 * owner invariants, see CollectionController/CollectionMemberController)
 * that a blind last-write-wins upsert would bypass, so those stay
 * online-only via their dedicated REST endpoints. Keep this list in sync
 * with `SyncRequest::ENTITIES` on the backend.
 */
export type SyncEntity =
  | 'games'
  | 'expansions'
  | 'categories'
  | 'collection_games'
  | 'play_sessions'
  | 'session_players'
  | 'loans'
  | 'wishlist_items'

export type SyncOperation = 'create' | 'update' | 'delete'

/**
 * One pending offline mutation, queued in creation order. `local_id` is a
 * Dexie auto-increment key purely for local ordering/dequeueing — it never
 * leaves the device. `entity_id` is the record's real (client-generated)
 * UUID, so the same id already used in e.g. `games` is reused here.
 */
export interface SyncQueueEntry {
  local_id?: number
  entity: SyncEntity
  entity_id: Uuid
  operation: SyncOperation
  payload: Record<string, unknown> | null
  queued_at: IsoDateTime
}

export type SyncOperationStatus = 'applied' | 'skipped' | 'rejected'

export interface SyncOperationResult {
  entity: SyncEntity
  entity_id: Uuid
  status: SyncOperationStatus
  reason?: string
}

/**
 * Full current-state snapshot of everything the user can see, returned by
 * every `/api/sync` call and used to wholesale-replace the corresponding
 * Dexie tables (see `applySnapshot` in `~/utils/sync`) — chosen over
 * incremental/tombstone sync since it's simple and the expected data volume
 * (private board game collections) is small.
 */
export interface SyncSnapshot {
  users: User[]
  collections: Collection[]
  collection_user: CollectionUser[]
  games: Game[]
  expansions: Expansion[]
  categories: Category[]
  game_category: GameCategory[]
  collection_games: CollectionGame[]
  play_sessions: PlaySession[]
  session_players: SessionPlayer[]
  loans: Loan[]
  wishlist_items: WishlistItem[]
}

export interface SyncResponse {
  results: SyncOperationResult[]
  snapshot: SyncSnapshot
}
