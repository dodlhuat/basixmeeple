import type { MaybeRefOrGetter } from 'vue'
import { db } from '~/utils/db'
import type { CollectionGame, Game } from '~/types/models'

export interface CollectionGameRow {
  game: Game
  pivot: CollectionGame
}

export function useCollectionGames(collectionId: MaybeRefOrGetter<string>) {
  return useLiveQuery(async () => {
    const id = toValue(collectionId)
    const pivots = await db.collection_games.where('collection_id').equals(id).toArray()
    const games = await db.games.bulkGet(pivots.map((pivot) => pivot.game_id))

    return pivots
      .map((pivot, index) => {
        const game = games[index]
        return game ? { game, pivot } : null
      })
      .filter((row): row is CollectionGameRow => row !== null)
      .sort((a, b) => a.game.title.localeCompare(b.game.title))
  }, [] as CollectionGameRow[])
}
