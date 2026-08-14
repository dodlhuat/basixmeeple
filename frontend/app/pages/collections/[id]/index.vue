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

    <div v-if="games.length === 0" class="empty-state card">
      <svg class="icon-svg empty-state-icon"><use :href="iconHref('casino')" /></svg>
      <p class="text-secondary">Noch keine Spiele in dieser Sammlung.</p>
    </div>

    <TransitionGroup v-else tag="ul" name="game-list" class="game-grid" appear>
      <li v-for="row in games" :key="row.game.id" class="game-item">
        <div class="game-card">
          <!-- Whole card is one interactive surface — the remove action below
               is a CSS-positioned sibling, not a nested <button>, so this stays
               valid, screen-reader-friendly markup (no button-in-button). -->
          <button type="button" class="game-card-link" @click="openGame(row.game.id)">
            <span class="game-cover-wrap">
              <img v-if="row.game.cover_url" :src="row.game.cover_url" :alt="row.game.title" class="game-cover" />
              <span v-else class="game-cover game-cover-placeholder">
                <svg class="icon-svg"><use :href="iconHref('casino')" /></svg>
              </span>
            </span>
            <span class="game-card-body">
              <span class="game-title">{{ row.game.title }}</span>
              <span v-if="row.game.min_players || row.game.max_players" class="game-meta">
                <svg class="icon-svg"><use :href="iconHref('group')" /></svg>
                {{ row.game.min_players ?? '?' }}–{{ row.game.max_players ?? '?' }}
              </span>
              <span v-if="row.pivot.location" class="game-meta">
                <svg class="icon-svg"><use :href="iconHref('location_on')" /></svg>
                {{ row.pivot.location }}
              </span>
            </span>
          </button>

          <button
            v-if="canEdit"
            type="button"
            class="game-card-remove"
            title="Entfernen"
            aria-label="Aus Sammlung entfernen"
            @click="onDetach(row.game.id, row.game.title)"
          >
            <svg class="icon-svg"><use :href="iconHref('delete')" /></svg>
          </button>
        </div>
      </li>
    </TransitionGroup>

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
            <span>{{ result.title }} <span class="text-secondary">{{ result.year_published }}</span></span>
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

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.5rem;
  padding: 3rem 1.5rem;
}

.empty-state-icon {
  width: 2.5rem;
  height: 2.5rem;
  color: var(--secondary-text);
  opacity: 0.5;
}

// CSS Grid rather than the old flex-wrap + min-width/flex-basis hack — an
// explicit `auto-fill`/`minmax` track gives every card an equal, predictable
// width and reflows cleanly from a 375px viewport (2 columns) up, without a
// per-card min-width fighting the flex algorithm for the last, short row.
.game-grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 1.1rem;
}

.game-item {
  // basix's `ul li { list-style-type: disc }` (typography.scss:150) targets
  // the <li> directly, overriding the parent <ul>'s inherited `list-style:
  // none` — the override has to live here, not just on `.game-grid`.
  list-style-type: none;
  min-width: 0;
}

// Deliberately hand-rolled rather than basix's `.card`/`.button` — same
// reasoning as collections/index.vue's `.collection-card`: `.card` sets
// `overflow: hidden` (would clip the corner-overlapping remove button below)
// and bare `.button` sets `white-space: nowrap` (button.scss:46), which is
// exactly what forced the old long-title overflow fix. Avoided at the root
// instead of patched after the fact.
.game-card {
  position: relative;
  border-radius: 0.75rem;
  background: var(--secondary-background);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
  border: 1px solid transparent;
  transition: transform 0.15s ease, box-shadow 0.2s ease, border-color 0.2s ease;

  &:hover,
  &:focus-within {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--accent-color) 30%, var(--divider));
    box-shadow: 0 10px 22px -10px rgba(0, 0, 0, 0.25);
  }
}

.game-card-link {
  display: flex;
  flex-direction: column;
  width: 100%;
  background: none;
  border: none;
  padding: 0;
  margin: 0;
  text-align: left;
  cursor: pointer;
  color: inherit;
  font: inherit;
  min-width: 0;
  white-space: normal;
  border-radius: inherit;

  // Neutralize basix's default bare-button hover fill and focus ring, both
  // sized for normal button padding — the `.game-card` hover/focus-within
  // above carries all visual feedback for the tile instead.
  &:hover:not(:disabled) {
    background: none;
  }

  &:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color) 35%, transparent);
  }
}

.game-cover-wrap {
  position: relative;
  overflow: hidden;
  border-radius: 0.75rem 0.75rem 0 0;
}

.game-cover {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
}

.game-cover-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--divider);
  color: var(--secondary-text);

  .icon-svg {
    width: 2.5rem;
    height: 2.5rem;
    opacity: 0.6;
  }
}

.game-card-body {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  padding: 0.75rem 0.85rem 0.9rem;
  min-width: 0;
}

// basix's `button, .button { white-space: nowrap }` rule (button.scss:46)
// targets every bare <button>, so this title — living inside the whole-card
// <button> above — inherited nowrap with no wrap handling and could overflow
// past the card. `min-width: 0` is needed alongside it: a flex item's default
// `min-width: auto` won't shrink below its content's intrinsic width, which
// is what let one long, unbroken title force the whole card wider than its
// grid track.
.game-title {
  min-width: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--primary-text);
  letter-spacing: -0.005em;
  line-height: 1.3;
  white-space: normal;
  overflow: hidden;
  overflow-wrap: anywhere;
  word-break: break-word;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.game-meta {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--secondary-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;

  .icon-svg {
    width: 0.95rem;
    height: 0.95rem;
    flex-shrink: 0;
  }
}

// Corner-overlapping icon action over the cover, echoing the established
// cover-action-upload/-remove language from games/[gameId].vue's cover tile
// (circular, box-shadow, scale-on-hover/active) — this page's redesign now
// speaks the same visual dialect as the detail page it sits next to. It's a
// sibling of `.game-card-link`, not a DOM child, positioned via the shared
// `.game-card` `position: relative` — never nested inside the card's own
// button (invalid HTML, breaks with screen readers).
.game-card-remove {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 50%;
  padding: 0;
  background: color-mix(in srgb, var(--background) 88%, transparent);
  color: var(--secondary-text);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(3px);
  cursor: pointer;
  opacity: 0;
  transform: scale(0.85);
  transition: opacity 0.15s ease, transform 0.15s cubic-bezier(0.25, 0.46, 0.45, 0.94),
    background 0.15s ease, color 0.15s ease;

  .icon-svg {
    width: 1.1rem;
    height: 1.1rem;
  }

  &:hover {
    background: var(--error);
    color: #fff;
    transform: scale(1.08);
  }

  &:active {
    transform: scale(0.95);
  }

  // Always reachable/visible on keyboard focus regardless of hover state —
  // opacity:0 alone would make a tabbed-to control invisible.
  &:focus-visible {
    outline: none;
    opacity: 1;
    transform: scale(1);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--error) 35%, transparent);
  }
}

// Hover-reveal only where hover is a real signal — on touch, there is no
// hover event to reveal it with, so the action stays always-visible there
// instead of becoming undiscoverable.
@media (hover: hover) {
  .game-card-remove {
    opacity: 0;
  }

  .game-card:hover .game-card-remove,
  .game-card:focus-within .game-card-remove {
    opacity: 1;
    transform: scale(1);
  }
}

@media (hover: none) {
  .game-card-remove {
    opacity: 1;
    transform: scale(1);
  }
}

// TransitionGroup hooks: same rise-in shape as collections/index.vue's list
// and stats.vue's section reveal — one composed "the grid just arrived"
// entrance with a short per-card stagger, not per-element fade-ins.
.game-list-enter-active {
  transition: opacity 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94), transform 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.game-list-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
  position: absolute;
}
.game-list-move {
  transition: transform 0.3s ease;
}
.game-list-enter-from {
  opacity: 0;
  transform: translateY(14px);
}
.game-list-leave-to {
  opacity: 0;
  transform: scale(0.96);
}

@for $i from 1 through 12 {
  .game-item.game-list-enter-active:nth-child(#{$i}) {
    transition-delay: #{45 * ($i - 1)}ms;
  }
}

@media (prefers-reduced-motion: reduce) {
  .game-list-enter-active,
  .game-list-leave-active,
  .game-list-move,
  .game-card,
  .game-card-remove {
    transition: none;
  }

  .game-item.game-list-enter-active {
    transition-delay: 0ms;
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
  // Same fix as `.game-item` above: `ul li { list-style-type: disc }` in
  // basix overrides `.bgg-results`'s inherited `list-style: none`.
  list-style-type: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--divider);
}
</style>
