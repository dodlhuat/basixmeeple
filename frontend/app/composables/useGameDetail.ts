import type { MaybeRefOrGetter } from 'vue'
import { db } from '~/utils/db'
import type { Category, Expansion, Game } from '~/types/models'

export interface GameDetail {
  game: Game
  categories: Category[]
  expansions: Expansion[]
}

export function useGameDetail(gameId: MaybeRefOrGetter<string>) {
  return useLiveQuery(async () => {
    const id = toValue(gameId)
    const game = await db.games.get(id)
    if (!game) return undefined

    const gameCategoryRows = await db.game_category.where('game_id').equals(id).toArray()
    const categories = (await db.categories.bulkGet(gameCategoryRows.map((row) => row.category_id))).filter(
      (category): category is Category => category !== undefined,
    )
    const expansions = await db.expansions.where('base_game_id').equals(id).toArray()

    return { game, categories, expansions } satisfies GameDetail
  }, undefined as GameDetail | undefined)
}
