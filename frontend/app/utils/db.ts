import Dexie, { type EntityTable } from 'dexie'
import type {
  Category,
  Collection,
  CollectionGame,
  CollectionUser,
  Expansion,
  Game,
  GameCategory,
  Loan,
  PlaySession,
  SessionPlayer,
  SyncQueueEntry,
  User,
  WishlistItem,
} from '~/types/models'

// Local offline mirror of the backend data model (see backend/database/migrations).
// Records share their UUID primary key with the server (see [[basixmeeple-project]]
// memory: client-generated UUIDs, no id remapping after sync). Sync itself
// (flushing sync_queue against the API, last-write-wins via updated_at) lands
// in a later step — this only defines the offline storage shape.
export class BasixMeepleDB extends Dexie {
  users!: EntityTable<User, 'id'>
  games!: EntityTable<Game, 'id'>
  expansions!: EntityTable<Expansion, 'id'>
  categories!: EntityTable<Category, 'id'>
  game_category!: EntityTable<GameCategory, 'game_id'>
  collections!: EntityTable<Collection, 'id'>
  collection_games!: EntityTable<CollectionGame, 'id'>
  collection_user!: EntityTable<CollectionUser, 'collection_id'>
  play_sessions!: EntityTable<PlaySession, 'id'>
  session_players!: EntityTable<SessionPlayer, 'id'>
  loans!: EntityTable<Loan, 'id'>
  wishlist_items!: EntityTable<WishlistItem, 'id'>
  sync_queue!: EntityTable<SyncQueueEntry, 'local_id'>

  constructor() {
    super('basixmeeple')

    this.version(1).stores({
      users: 'id, email',
      games: 'id, bgg_id, title, updated_at',
      expansions: 'id, base_game_id, updated_at',
      categories: 'id, name',
      game_category: '[game_id+category_id], game_id, category_id',
      collections: 'id, owner_id, updated_at',
      collection_games: 'id, [collection_id+game_id], collection_id, game_id, updated_at',
      collection_user: '[collection_id+user_id], collection_id, user_id',
      play_sessions: 'id, collection_id, game_id, played_at, updated_at',
      session_players: 'id, session_id, user_id, updated_at',
      loans: 'id, game_id, borrower_user_id, returned_at, updated_at',
      wishlist_items: 'id, collection_id, priority, updated_at',
      sync_queue: '++local_id, entity, entity_id, queued_at',
    })
  }
}

export const db = new BasixMeepleDB()
