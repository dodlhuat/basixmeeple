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

// Presentational-only helpers for the leaderboard's rank badge — purely a
// display concern, doesn't touch how stats.leaderboard itself is computed/sorted.
function rankClass(index: number): string {
  if (index === 0) return 'rank-badge rank-first'
  if (index === 1) return 'rank-badge rank-second'
  if (index === 2) return 'rank-badge rank-third'
  return 'rank-badge'
}
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

    <div v-if="stats.totalSessions === 0" class="empty-state card">
      <svg class="icon-svg empty-state-icon"><use :href="iconHref('bar_chart')" /></svg>
      <p class="text-secondary">
        Noch keine Sitzungen protokolliert — sobald ihr Spiele festhaltet, erscheinen hier Auswertungen.
      </p>
    </div>

    <div v-else class="stats-sections">
      <div class="kpi-row">
        <div class="card kpi-hero">
          <div class="kpi-hero-icon">
            <svg class="icon-svg"><use :href="iconHref('casino')" /></svg>
          </div>
          <div class="kpi-hero-body">
            <span class="kpi-hero-label">Sitzungen gesamt</span>
            <span class="kpi-hero-value">{{ stats.totalSessions }}</span>
          </div>
        </div>

        <div class="card kpi-secondary">
          <div class="kpi-mini">
            <svg class="icon-svg"><use :href="iconHref('schedule')" /></svg>
            <div class="kpi-mini-text">
              <span class="kpi-mini-value">{{ formatDuration(stats.totalPlaytimeMinutes) }}</span>
              <span class="kpi-mini-label">Spielzeit gesamt</span>
            </div>
          </div>
          <div class="kpi-mini">
            <svg class="icon-svg"><use :href="iconHref('extension')" /></svg>
            <div class="kpi-mini-text">
              <span class="kpi-mini-value">{{ stats.distinctGamesPlayed }}</span>
              <span class="kpi-mini-label">Verschiedene Spiele</span>
            </div>
          </div>
          <div class="kpi-mini">
            <svg class="icon-svg"><use :href="iconHref('timer')" /></svg>
            <div class="kpi-mini-text">
              <span class="kpi-mini-value">{{ formatDuration(stats.avgDurationMinutes) }}</span>
              <span class="kpi-mini-label">Ø Dauer je Sitzung</span>
            </div>
          </div>
        </div>
      </div>

      <div class="card chart-card">
        <div class="card-section-header">
          <svg class="icon-svg"><use :href="iconHref('leaderboard')" /></svg>
          <h2>Meistgespielte Spiele</h2>
          <span class="text-caption">{{ stats.topGames.length }} Spiele</span>
        </div>
        <AppChart type="bar" :series="topGamesSeries" :show-legend="false" :height="Math.max(160, stats.topGames.length * 40)" />
      </div>

      <div class="card chart-card">
        <div class="card-section-header">
          <svg class="icon-svg"><use :href="iconHref('trending_up')" /></svg>
          <h2>Aktivität der letzten 12 Monate</h2>
          <span class="text-caption">12 Monate</span>
        </div>
        <AppChart type="column" :series="monthlyActivitySeries" :show-legend="false" />
      </div>

      <div class="card">
        <div class="card-section-header">
          <svg class="icon-svg"><use :href="iconHref('military_tech')" /></svg>
          <h2>Rangliste</h2>
        </div>
        <div class="table-wrapper">
          <table class="leaderboard-table">
            <thead>
              <tr>
                <th>Rang</th>
                <th>Name</th>
                <th>Sitzungen</th>
                <th>Siege</th>
                <th>Sieg-Quote</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(player, index) in stats.leaderboard" :key="player.name">
                <td data-label="Rang">
                  <span :class="rankClass(index)">{{ index + 1 }}</span>
                </td>
                <td data-label="Name">{{ player.name }}</td>
                <td data-label="Sitzungen">{{ player.sessions }}</td>
                <td data-label="Siege">{{ player.wins }}</td>
                <td data-label="Sieg-Quote">
                  <div class="winrate-cell">
                    <div class="progress">
                      <div class="progress-bar accent" :style="{ width: `${player.winRate}%` }" />
                    </div>
                    <span class="winrate-value">{{ player.winRate }}%</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
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
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
  gap: 1rem;
}

// One composed "the page just arrived" moment rather than scattered
// micro-animations: each section rises in with a short stagger.
.stats-sections > * {
  animation: rise-in 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
}

.stats-sections > *:nth-child(1) {
  animation-delay: 0ms;
}
.stats-sections > *:nth-child(2) {
  animation-delay: 70ms;
}
.stats-sections > *:nth-child(3) {
  animation-delay: 140ms;
}
.stats-sections > *:nth-child(4) {
  animation-delay: 210ms;
}

@keyframes rise-in {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.kpi-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

@media (min-width: 640px) {
  .kpi-row {
    grid-template-columns: 1.15fr 1fr;
    align-items: stretch;
  }
}

// Hero tile: one headline number instead of four equal-weight boxes — the
// thing people actually open this page to check.
.kpi-hero {
  position: relative;
  overflow: hidden;
  flex-direction: row;
  align-items: center;
  gap: 1rem;
  background: linear-gradient(135deg, var(--secondary-background), var(--background));

  &::before {
    content: '';
    position: absolute;
    top: -35%;
    right: -15%;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, color-mix(in srgb, var(--accent-color) 20%, transparent), transparent 70%);
    pointer-events: none;
  }
}

.kpi-hero-icon {
  position: relative;
  flex-shrink: 0;
  width: 3.25rem;
  height: 3.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--accent-color-tint);
  color: var(--accent-color);

  .icon-svg {
    width: 1.625rem;
    height: 1.625rem;
  }
}

.kpi-hero-body {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.kpi-hero-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--secondary-text);
}

.kpi-hero-value {
  font-size: clamp(2.25rem, 7vw, 3rem);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1;
  color: var(--primary-text);
}

// Secondary stats: a stacked, divided list rather than three more identical
// boxes — deliberate asymmetry against the hero tile.
.kpi-secondary {
  gap: 0;
  padding: 0.25rem 1rem;
}

.kpi-mini {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0;

  & + & {
    border-top: 1px solid var(--divider);
  }

  .icon-svg {
    flex-shrink: 0;
    width: 1.25rem;
    height: 1.25rem;
    color: var(--accent-color);
  }
}

.kpi-mini-text {
  display: flex;
  flex-direction: column;
  gap: 0.05rem;
}

.kpi-mini-value {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--primary-text);
}

.kpi-mini-label {
  font-size: 0.75rem;
  color: var(--secondary-text);
}

.chart-card {
  margin-bottom: 1rem;
}

.card {
  margin-bottom: 1rem;
}

.card-section-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;

  h2 {
    margin: 0;
    flex: 1;
    min-width: 0;
  }

  .icon-svg {
    width: 1.25rem;
    height: 1.25rem;
    color: var(--accent-color);
    flex-shrink: 0;
  }
}

.leaderboard-table {
  width: 100%;
}

.rank-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 50%;
  background: var(--secondary-background);
  color: var(--secondary-text);
  font-size: 0.8125rem;
  font-weight: 700;
}

.rank-first {
  background: var(--accent-color-tint);
  color: var(--accent-color);
}

.rank-second {
  background: color-mix(in srgb, var(--secondary-text) 16%, transparent);
  color: var(--primary-text);
}

.rank-third {
  background: var(--warning-tint);
  color: var(--warning);
}

.winrate-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 96px;

  .progress {
    flex: 1;
    min-width: 48px;
  }
}

.winrate-value {
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  font-size: 0.8125rem;
  color: var(--secondary-text);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.75rem;
  padding: 2.5rem 1.5rem;
}

.empty-state-icon {
  width: 2.5rem;
  height: 2.5rem;
  color: var(--secondary-text);
  opacity: 0.5;
}

@media (prefers-reduced-motion: reduce) {
  .stats-sections > * {
    animation: none;
  }
}
</style>
