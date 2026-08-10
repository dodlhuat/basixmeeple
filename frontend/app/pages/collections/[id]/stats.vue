<script setup lang="ts">
import { singleSeriesColor } from '~/utils/chartPalette'
import type { ChartSeries } from '@dodlhuat/basix/js/chart.js'

const route = useRoute()
const collectionId = computed(() => route.params.id as string)

const collection = useCollection(collectionId)
const stats = useCollectionStats(collectionId)

useHead({ title: () => `Statistik – ${collection.value?.name ?? 'Sammlung'} – BasixMeeple` })

const { setActiveCollectionId } = useActiveCollectionId()
watchEffect(() => setActiveCollectionId(collectionId.value))

function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes} min`
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`
}

const topGamesSeries = computed<ChartSeries[]>(() => [
  {
    name: 'Sitzungen',
    color: singleSeriesColor(),
    data: stats.value.topGames.map((g) => ({ label: g.title, value: g.sessionCount })),
  },
])

const monthlyActivitySeries = computed<ChartSeries[]>(() => [
  {
    name: 'Sitzungen',
    color: singleSeriesColor(),
    data: stats.value.monthlyActivity.map((m) => ({ label: m.label, value: m.count })),
  },
])
</script>

<template>
  <section>
    <NuxtLink :to="`/collections/${collectionId}`" class="back-link">
      <svg class="icon-svg"><use :href="iconHref('arrow_back')" /></svg>
      Zurück zur Sammlung
    </NuxtLink>

    <div class="page-header">
      <h1>Statistik – {{ collection?.name }}</h1>
      <CollectionSubnav :collection-id="collectionId" />
    </div>

    <p v-if="stats.totalSessions === 0" class="secondary-text">
      Noch keine Sitzungen protokolliert — sobald ihr Spiele festhaltet, erscheinen hier Auswertungen.
    </p>

    <template v-else>
      <div class="row kpi-row">
        <div class="column card kpi-tile">
          <span class="kpi-value">{{ stats.totalSessions }}</span>
          <span class="secondary-text">Sitzungen gesamt</span>
        </div>
        <div class="column card kpi-tile">
          <span class="kpi-value">{{ formatDuration(stats.totalPlaytimeMinutes) }}</span>
          <span class="secondary-text">Spielzeit gesamt</span>
        </div>
        <div class="column card kpi-tile">
          <span class="kpi-value">{{ stats.distinctGamesPlayed }}</span>
          <span class="secondary-text">Verschiedene Spiele</span>
        </div>
        <div class="column card kpi-tile">
          <span class="kpi-value">{{ formatDuration(stats.avgDurationMinutes) }}</span>
          <span class="secondary-text">Ø Dauer je Sitzung</span>
        </div>
      </div>

      <div class="card chart-card">
        <h2>Meistgespielte Spiele</h2>
        <AppChart type="bar" :series="topGamesSeries" :show-legend="false" :height="Math.max(160, stats.topGames.length * 40)" />
      </div>

      <div class="card chart-card">
        <h2>Aktivität der letzten 12 Monate</h2>
        <AppChart type="column" :series="monthlyActivitySeries" :show-legend="false" />
      </div>

      <div class="card">
        <h2>Rangliste</h2>
        <table class="leaderboard-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Sitzungen</th>
              <th>Siege</th>
              <th>Sieg-Quote</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="player in stats.leaderboard" :key="player.name">
              <td>{{ player.name }}</td>
              <td>{{ player.sessions }}</td>
              <td>{{ player.wins }}</td>
              <td>{{ player.winRate }}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin-bottom: 1rem;
  color: var(--secondary-text);
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  gap: 1rem;
}

.kpi-row {
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.kpi-tile {
  min-width: 160px;
  flex: 1 1 200px;
  align-items: flex-start;
  gap: 0.25rem;
}

.kpi-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--primary-text);
}

.chart-card {
  margin-bottom: 1rem;
}

.card {
  margin-bottom: 1rem;
}

.leaderboard-table {
  width: 100%;
  border-collapse: collapse;

  th,
  td {
    text-align: left;
    padding: 0.5rem 0.75rem 0.5rem 0;
    border-bottom: 1px solid var(--divider);
  }

  th {
    color: var(--secondary-text);
    font-size: 0.8125rem;
    font-weight: 600;
  }
}
</style>
