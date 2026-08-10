import { db } from './db'
import type { Expansion, Game, Uuid } from '~/types/models'

export interface CollectionExportGame {
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
  edition: string | null
  language: string | null
  condition: string | null
  categories: string[]
  expansions: { title: string; bgg_id: number | null; cover_url: string | null }[]
}

export interface CollectionExport {
  format: 'basixmeeple.collection-export'
  format_version: '1.0'
  exported_at: string
  collection: { name: string }
  games: CollectionExportGame[]
}

const FORMAT_VERSION = '1.0'

/**
 * One-way BasixMeeple → AUA catalog export (see [[basixmeeple-project]]
 * memory — AUA has no import to target yet, so this format is ours to
 * define; a future AUA import should treat it as the reference shape).
 * Computed entirely from the already-synced Dexie mirror, same approach as
 * the Schritt 9 stats dashboard, so it also works offline.
 *
 * Deliberately excludes play_sessions/loans/wishlist_items (private
 * activity data, not catalog data) and the collection_games pivot's
 * `location`/`notes` (personal organizational details an external system
 * has no use for) — only the pivot's `condition` carries over, since a
 * lending platform cares about a physical copy's condition.
 */
export async function buildCollectionExport(collectionId: Uuid): Promise<CollectionExport> {
  const collection = await db.collections.get(collectionId)
  const pivots = await db.collection_games.where('collection_id').equals(collectionId).toArray()
  const gameIds = pivots.map((pivot) => pivot.game_id)

  const games = (await db.games.bulkGet(gameIds)).filter((game): game is Game => game !== undefined)
  const gameById = new Map(games.map((game) => [game.id, game]))
  const pivotByGameId = new Map(pivots.map((pivot) => [pivot.game_id, pivot]))

  const expansions = await db.expansions.where('base_game_id').anyOf(gameIds).toArray()
  const expansionsByGameId = new Map<Uuid, Expansion[]>()
  for (const expansion of expansions) {
    const list = expansionsByGameId.get(expansion.base_game_id) ?? []
    list.push(expansion)
    expansionsByGameId.set(expansion.base_game_id, list)
  }

  const gameCategoryRows = await db.game_category.where('game_id').anyOf(gameIds).toArray()
  const allCategories = await db.categories.toArray()
  const categoryNameById = new Map(allCategories.map((category) => [category.id, category.name]))
  const categoryNamesByGameId = new Map<Uuid, string[]>()
  for (const row of gameCategoryRows) {
    const name = categoryNameById.get(row.category_id)
    if (!name) continue
    const list = categoryNamesByGameId.get(row.game_id) ?? []
    list.push(name)
    categoryNamesByGameId.set(row.game_id, list)
  }

  const exportGames: CollectionExportGame[] = gameIds
    .map((gameId) => gameById.get(gameId))
    .filter((game): game is Game => game !== undefined)
    .map((game) => ({
      title: game.title,
      bgg_id: game.bgg_id,
      publisher: game.publisher,
      min_players: game.min_players,
      max_players: game.max_players,
      play_time_min: game.play_time_min,
      play_time_max: game.play_time_max,
      min_age: game.min_age,
      weight_complexity: game.weight_complexity,
      description: game.description,
      cover_url: game.cover_url,
      edition: game.edition,
      language: game.language,
      condition: pivotByGameId.get(game.id)?.condition ?? null,
      categories: (categoryNamesByGameId.get(game.id) ?? []).sort(),
      expansions: (expansionsByGameId.get(game.id) ?? []).map((expansion) => ({
        title: expansion.title,
        bgg_id: expansion.bgg_id,
        cover_url: expansion.cover_url,
      })),
    }))
    .sort((a, b) => a.title.localeCompare(b.title))

  return {
    format: 'basixmeeple.collection-export',
    format_version: FORMAT_VERSION,
    exported_at: new Date().toISOString(),
    collection: { name: collection?.name ?? 'Sammlung' },
    games: exportGames,
  }
}
