import type { MaybeRefOrGetter } from 'vue'
import { db } from '~/utils/db'
import type { Game, Loan } from '~/types/models'

export interface LoanRow {
  loan: Loan
  game: Game
}

/**
 * Loans for games in this collection. `loans` has no `collection_id` of its
 * own (see [[basixmeeple-project]] memory on Schritt 5/7) — scoped here via
 * the collection's `collection_games` pivot, same join the backend's
 * LoanController/SyncController use.
 */
export function useLoans(collectionId: MaybeRefOrGetter<string>) {
  return useLiveQuery(async () => {
    const id = toValue(collectionId)
    const pivots = await db.collection_games.where('collection_id').equals(id).toArray()
    const gameIds = pivots.map((pivot) => pivot.game_id)
    const loans = await db.loans.where('game_id').anyOf(gameIds).toArray()
    const games = (await db.games.bulkGet(gameIds)).filter((game): game is Game => game !== undefined)
    const gameById = new Map(games.map((game) => [game.id, game]))

    return loans
      .map((loan) => {
        const game = gameById.get(loan.game_id)
        return game ? { loan, game } : null
      })
      .filter((row): row is LoanRow => row !== null)
      .sort((a, b) => b.loan.loaned_at.localeCompare(a.loan.loaned_at))
  }, [] as LoanRow[])
}
