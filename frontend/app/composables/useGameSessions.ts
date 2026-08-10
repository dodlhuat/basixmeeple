import type { MaybeRefOrGetter } from 'vue'
import { db } from '~/utils/db'
import type { PlaySession, SessionPlayer } from '~/types/models'

export interface SessionWithPlayers {
  session: PlaySession
  players: SessionPlayer[]
}

/**
 * Sessions for one game, scoped to the collection they were logged in from
 * (a game can technically belong to more than one collection — see
 * [[basixmeeple-project]] memory on shared games).
 */
export function useGameSessions(collectionId: MaybeRefOrGetter<string>, gameId: MaybeRefOrGetter<string>) {
  return useLiveQuery(async () => {
    const cId = toValue(collectionId)
    const gId = toValue(gameId)

    const sessions = (await db.play_sessions.where('game_id').equals(gId).toArray())
      .filter((session) => session.collection_id === cId)
      .sort((a, b) => b.played_at.localeCompare(a.played_at))

    const players = await db.session_players.where('session_id').anyOf(sessions.map((s) => s.id)).toArray()

    return sessions.map((session) => ({
      session,
      players: players.filter((player) => player.session_id === session.id),
    }))
  }, [] as SessionWithPlayers[])
}
