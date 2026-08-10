import type { MaybeRefOrGetter } from 'vue'
import { db } from '~/utils/db'
import type { Game } from '~/types/models'

export interface GameStat {
  gameId: string
  title: string
  sessionCount: number
}

export interface MonthlyActivity {
  label: string
  count: number
}

export interface PlayerStat {
  name: string
  sessions: number
  wins: number
  winRate: number
}

export interface CollectionStats {
  totalSessions: number
  totalPlaytimeMinutes: number
  distinctGamesPlayed: number
  avgDurationMinutes: number
  topGames: GameStat[]
  monthlyActivity: MonthlyActivity[]
  leaderboard: PlayerStat[]
}

const EMPTY_STATS: CollectionStats = {
  totalSessions: 0,
  totalPlaytimeMinutes: 0,
  distinctGamesPlayed: 0,
  avgDurationMinutes: 0,
  topGames: [],
  monthlyActivity: [],
  leaderboard: [],
}

const MONTH_FORMATTER = new Intl.DateTimeFormat('de-AT', { month: 'short', year: '2-digit' })

/** Trailing 12 calendar months (oldest first), each keyed "YYYY-MM". */
function last12Months(): { key: string; label: string }[] {
  const now = new Date()
  const months: { key: string; label: string }[] = []

  for (let i = 11; i >= 0; i--) {
    const date = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
    months.push({ key, label: MONTH_FORMATTER.format(date) })
  }

  return months
}

/**
 * Play-session stats for one collection, computed entirely client-side from
 * the already-synced Dexie mirror — no dedicated backend endpoint, since the
 * source data (play_sessions/session_players/games) is small per collection
 * and already local, and this keeps the dashboard usable offline like the
 * rest of the app.
 */
export function useCollectionStats(collectionId: MaybeRefOrGetter<string>) {
  return useLiveQuery(async () => {
    const id = toValue(collectionId)
    const sessions = await db.play_sessions.where('collection_id').equals(id).toArray()

    if (sessions.length === 0) return EMPTY_STATS

    const sessionIds = sessions.map((s) => s.id)
    const players = await db.session_players.where('session_id').anyOf(sessionIds).toArray()

    const gameIds = [...new Set(sessions.map((s) => s.game_id))]
    const games = (await db.games.bulkGet(gameIds)).filter((game): game is Game => game !== undefined)
    const gameById = new Map(games.map((game) => [game.id, game]))

    const totalSessions = sessions.length
    const totalPlaytimeMinutes = sessions.reduce((sum, s) => sum + (s.duration_min ?? 0), 0)
    const distinctGamesPlayed = gameIds.length
    const avgDurationMinutes = totalSessions > 0 ? Math.round(totalPlaytimeMinutes / totalSessions) : 0

    const sessionCountByGame = new Map<string, number>()
    for (const session of sessions) {
      sessionCountByGame.set(session.game_id, (sessionCountByGame.get(session.game_id) ?? 0) + 1)
    }
    const topGames: GameStat[] = [...sessionCountByGame.entries()]
      .map(([gameId, sessionCount]) => ({
        gameId,
        title: gameById.get(gameId)?.title ?? 'Unbekanntes Spiel',
        sessionCount,
      }))
      .sort((a, b) => b.sessionCount - a.sessionCount)
      .slice(0, 8)

    const monthBuckets = new Map(last12Months().map((m) => [m.key, 0]))
    for (const session of sessions) {
      const playedAt = new Date(session.played_at)
      const key = `${playedAt.getFullYear()}-${String(playedAt.getMonth() + 1).padStart(2, '0')}`
      if (monthBuckets.has(key)) monthBuckets.set(key, (monthBuckets.get(key) ?? 0) + 1)
    }
    const monthlyActivity: MonthlyActivity[] = last12Months().map((m) => ({
      label: m.label,
      count: monthBuckets.get(m.key) ?? 0,
    }))

    const byPlayer = new Map<string, { sessions: number; wins: number }>()
    for (const player of players) {
      const entry = byPlayer.get(player.player_name) ?? { sessions: 0, wins: 0 }
      entry.sessions += 1
      if (player.is_winner) entry.wins += 1
      byPlayer.set(player.player_name, entry)
    }
    const leaderboard: PlayerStat[] = [...byPlayer.entries()]
      .map(([name, stat]) => ({
        name,
        sessions: stat.sessions,
        wins: stat.wins,
        winRate: stat.sessions > 0 ? Math.round((stat.wins / stat.sessions) * 100) : 0,
      }))
      .sort((a, b) => b.sessions - a.sessions)

    return {
      totalSessions,
      totalPlaytimeMinutes,
      distinctGamesPlayed,
      avgDurationMinutes,
      topGames,
      monthlyActivity,
      leaderboard,
    } satisfies CollectionStats
  }, EMPTY_STATS)
}
