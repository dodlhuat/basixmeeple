<script setup lang="ts">
import { ApiError } from '~/utils/apiClient'
import type { BggSearchResult } from '~/utils/mutations'

const route = useRoute()
const collectionId = computed(() => route.params.id as string)

const collection = useCollection(collectionId)
const role = useCollectionRole(collectionId)
const canEdit = computed(() => role.value === 'owner' || role.value === 'editor')
const games = useCollectionGames(collectionId)
const toast = useToast()

useHead({ title: () => `${collection.value?.name ?? 'Sammlung'} – BasixMeeple` })

const { setActiveCollectionId } = useActiveCollectionId()
watchEffect(() => setActiveCollectionId(collectionId.value))

function openGame(gameId: string): void {
  navigateTo(`/collections/${collectionId.value}/games/${gameId}`)
}

async function onDetach(gameId: string, title: string): Promise<void> {
  if (!confirm(`"${title}" aus dieser Sammlung entfernen?`)) return
  await detachGameFromCollection(collectionId.value, gameId)
  toast.success('Spiel entfernt.')
}

// --- Add game modal ---
const showAddModal = ref(false)
const addMode = ref<'manual' | 'bgg'>('manual')

const manualTitle = ref('')
const manualMinPlayers = ref<number | null>(null)
const manualMaxPlayers = ref<number | null>(null)
const manualLocation = ref('')
const addingManual = ref(false)

async function onAddManual(): Promise<void> {
  if (!manualTitle.value.trim()) return
  addingManual.value = true

  try {
    await createGameInCollection(
      collectionId.value,
      { title: manualTitle.value.trim(), min_players: manualMinPlayers.value, max_players: manualMaxPlayers.value },
      { location: manualLocation.value.trim() || null },
    )
    toast.success('Spiel hinzugefügt.')
    resetAddForm()
    showAddModal.value = false
  } finally {
    addingManual.value = false
  }
}

const bggQuery = ref('')
const bggResults = ref<BggSearchResult[]>([])
const bggSearching = ref(false)
const bggImportingId = ref<number | null>(null)

async function onBggSearch(): Promise<void> {
  if (!bggQuery.value.trim()) return
  bggSearching.value = true

  try {
    bggResults.value = await bggSearch(bggQuery.value.trim())
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'BGG-Suche fehlgeschlagen.')
  } finally {
    bggSearching.value = false
  }
}

async function onBggImport(result: BggSearchResult): Promise<void> {
  bggImportingId.value = result.bgg_id
  try {
    await bggImportGame(collectionId.value, result.bgg_id)
    toast.success(`"${result.title}" importiert.`)
    bggResults.value = []
    bggQuery.value = ''
    showAddModal.value = false
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Import fehlgeschlagen.')
  } finally {
    bggImportingId.value = null
  }
}

function resetAddForm(): void {
  manualTitle.value = ''
  manualMinPlayers.value = null
  manualMaxPlayers.value = null
  manualLocation.value = ''
  bggQuery.value = ''
  bggResults.value = []
  addMode.value = 'manual'
}
</script>

<template>
  <section>
    <div class="page-header">
      <h1>{{ collection?.name ?? 'Sammlung' }}</h1>
      <div class="buttons">
        <CollectionSubnav :collection-id="collectionId" />
        <button v-if="canEdit" type="button" class="button button-primary" @click="showAddModal = true">
          <svg class="icon-svg"><use :href="iconHref('add')" /></svg>
          Spiel hinzufügen
        </button>
      </div>
    </div>

    <p v-if="games.length === 0" class="secondary-text">Noch keine Spiele in dieser Sammlung.</p>

    <div class="row game-grid">
      <div v-for="row in games" :key="row.game.id" class="column card game-card">
        <button type="button" class="game-card-main" @click="openGame(row.game.id)">
          <img v-if="row.game.cover_url" :src="row.game.cover_url" :alt="row.game.title" class="game-cover" />
          <div v-else class="game-cover game-cover-placeholder">
            <svg class="icon-svg"><use :href="iconHref('casino')" /></svg>
          </div>
          <h3>{{ row.game.title }}</h3>
          <p v-if="row.game.min_players || row.game.max_players" class="secondary-text">
            {{ row.game.min_players ?? '?' }}–{{ row.game.max_players ?? '?' }} Spieler:innen
          </p>
          <p v-if="row.pivot.location" class="secondary-text">📍 {{ row.pivot.location }}</p>
        </button>
        <button v-if="canEdit" type="button" class="button button-sm button-outline" @click="onDetach(row.game.id, row.game.title)">
          Entfernen
        </button>
      </div>
    </div>

    <AppModal v-model="showAddModal" header="Spiel hinzufügen" @update:model-value="!$event && resetAddForm()">
      <div class="button-group add-mode-toggle">
        <button
          type="button"
          class="button button-sm"
          :class="{ 'button-primary': addMode === 'manual' }"
          @click="addMode = 'manual'"
        >
          Manuell
        </button>
        <button
          type="button"
          class="button button-sm"
          :class="{ 'button-primary': addMode === 'bgg' }"
          @click="addMode = 'bgg'"
        >
          BoardGameGeek-Suche
        </button>
      </div>

      <form v-if="addMode === 'manual'" class="manual-add-form" @submit.prevent="onAddManual">
        <div class="form-group">
          <label for="manual-title">Titel</label>
          <input id="manual-title" v-model="manualTitle" type="text" required autofocus />
        </div>
        <div class="row">
          <div class="column form-group">
            <label for="manual-min">Min. Spieler:innen</label>
            <input id="manual-min" v-model.number="manualMinPlayers" type="number" min="1" />
          </div>
          <div class="column form-group">
            <label for="manual-max">Max. Spieler:innen</label>
            <input id="manual-max" v-model.number="manualMaxPlayers" type="number" min="1" />
          </div>
        </div>
        <div class="form-group">
          <label for="manual-location">Standort</label>
          <input id="manual-location" v-model="manualLocation" type="text" placeholder="z.B. Regal A" />
        </div>
      </form>

      <div v-else class="bgg-search">
        <form class="bgg-search-form" @submit.prevent="onBggSearch">
          <input v-model="bggQuery" type="text" placeholder="Spieltitel suchen …" autofocus />
          <button type="submit" class="button" :disabled="bggSearching">
            <svg class="icon-svg"><use :href="iconHref('search')" /></svg>
          </button>
        </form>

        <ul class="bgg-results">
          <li v-for="result in bggResults" :key="result.bgg_id" class="bgg-result">
            <span>{{ result.title }} <span class="secondary-text">{{ result.year_published }}</span></span>
            <button
              type="button"
              class="button button-sm button-primary"
              :disabled="bggImportingId === result.bgg_id"
              @click="onBggImport(result)"
            >
              {{ bggImportingId === result.bgg_id ? 'Importiere …' : 'Importieren' }}
            </button>
          </li>
        </ul>
      </div>

      <template #footer>
        <div class="buttons">
          <button type="button" class="button button-outline" @click="showAddModal = false">Schließen</button>
          <button v-if="addMode === 'manual'" type="button" class="button button-primary" :disabled="addingManual" @click="onAddManual">
            {{ addingManual ? 'Wird hinzugefügt …' : 'Hinzufügen' }}
          </button>
        </div>
      </template>
    </AppModal>
  </section>
</template>

<style lang="scss" scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  gap: 1rem;
}

.game-grid {
  flex-wrap: wrap;
}

.game-card {
  min-width: 180px;
  flex: 1 1 200px;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.game-card-main {
  background: none;
  border: none;
  padding: 0;
  text-align: left;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.game-cover {
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: var(--radius-md, 8px);
}

.game-cover-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--secondary-background);
  color: var(--secondary-text);

  .icon-svg {
    width: 2.5rem;
    height: 2.5rem;
  }
}

.add-mode-toggle {
  margin-bottom: 1rem;
}

.manual-add-form,
.bgg-search {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.bgg-search-form {
  display: flex;
  gap: 0.5rem;
}

.bgg-results {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.bgg-result {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--divider);
}
</style>
