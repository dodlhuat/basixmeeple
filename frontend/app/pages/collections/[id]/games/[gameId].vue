<script setup lang="ts">
import { apiFetch, ApiError } from '~/utils/apiClient'
import type { Category, PlaySessionOutcome } from '~/types/models'
import type { PlaySessionPlayerInput } from '~/utils/mutations'

const route = useRoute()
const collectionId = computed(() => route.params.id as string)
const gameId = computed(() => route.params.gameId as string)

const detail = useGameDetail(gameId)
const role = useCollectionRole(collectionId)
const canEdit = computed(() => role.value === 'owner' || role.value === 'editor')
const sessions = useGameSessions(collectionId, gameId)
const toast = useToast()

useHead({ title: () => `${detail.value?.game.title ?? 'Spiel'} – BasixMeeple` })

const { setActiveCollectionId } = useActiveCollectionId()
watchEffect(() => setActiveCollectionId(collectionId.value))

// --- Edit form ---
const title = ref('')
const publisher = ref('')
const minPlayers = ref<number | null>(null)
const maxPlayers = ref<number | null>(null)
const edition = ref('')
const description = ref('')
const saving = ref(false)

watchEffect(() => {
  const game = detail.value?.game
  if (!game) return
  title.value = game.title
  publisher.value = game.publisher ?? ''
  minPlayers.value = game.min_players
  maxPlayers.value = game.max_players
  edition.value = game.edition ?? ''
  description.value = game.description ?? ''
})

async function onSave(): Promise<void> {
  saving.value = true
  try {
    await updateGame(gameId.value, {
      title: title.value.trim(),
      publisher: publisher.value.trim() || null,
      min_players: minPlayers.value,
      max_players: maxPlayers.value,
      edition: edition.value.trim() || null,
      description: description.value.trim() || null,
    })
    toast.success('Gespeichert.')
  } finally {
    saving.value = false
  }
}

async function onDelete(): Promise<void> {
  if (!detail.value || !confirm(`"${detail.value.game.title}" wirklich endgültig löschen?`)) return
  await deleteGame(gameId.value)
  toast.success('Spiel gelöscht.')
  navigateTo(`/collections/${collectionId.value}`)
}

// --- Cover upload (REST-only, see mutations.ts note on uploadGameCover/deleteGameCover) ---
const ACCEPTED_COVER_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const MAX_COVER_BYTES = 5 * 1024 * 1024

const coverInput = ref<HTMLInputElement | null>(null)
const uploadingCover = ref(false)
const removingCover = ref(false)
const isCoverDragOver = ref(false)

function triggerCoverSelect(): void {
  coverInput.value?.click()
}

async function handleCoverFile(file: File): Promise<void> {
  if (!ACCEPTED_COVER_TYPES.includes(file.type)) {
    toast.error('Bitte ein JPEG-, PNG- oder WebP-Bild wählen.')
    return
  }
  if (file.size > MAX_COVER_BYTES) {
    toast.error('Die Datei ist zu groß (max. 5 MB).')
    return
  }

  uploadingCover.value = true
  try {
    await uploadGameCover(gameId.value, file)
    toast.success('Cover aktualisiert.')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Cover-Upload fehlgeschlagen.')
  } finally {
    uploadingCover.value = false
  }
}

function onCoverFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (file) void handleCoverFile(file)
}

function onCoverDragOver(event: DragEvent): void {
  if (!canEdit.value) return
  event.preventDefault()
  isCoverDragOver.value = true
}

function onCoverDragLeave(): void {
  isCoverDragOver.value = false
}

function onCoverDrop(event: DragEvent): void {
  event.preventDefault()
  isCoverDragOver.value = false
  if (!canEdit.value) return
  const file = event.dataTransfer?.files?.[0]
  if (file) void handleCoverFile(file)
}

async function onRemoveCover(): Promise<void> {
  if (!confirm('Cover wirklich entfernen?')) return
  removingCover.value = true
  try {
    await deleteGameCover(gameId.value)
    toast.success('Cover entfernt.')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Cover konnte nicht entfernt werden.')
  } finally {
    removingCover.value = false
  }
}

// --- Categories (REST-only, see mutations.ts note on game_category being pull-only in sync) ---
const allCategories = ref<Category[]>([])
const selectedCategoryIds = ref<Set<string>>(new Set())
const savingCategories = ref(false)

onMounted(async () => {
  try {
    allCategories.value = await apiFetch<Category[]>('/api/categories')
  } catch {
    // Categories are a nice-to-have here; silently skip if offline.
  }
})

watchEffect(() => {
  selectedCategoryIds.value = new Set(detail.value?.categories.map((c) => c.id) ?? [])
})

async function onSaveCategories(): Promise<void> {
  savingCategories.value = true
  try {
    await apiFetch(`/api/games/${gameId.value}/categories`, {
      method: 'PUT',
      body: { category_ids: [...selectedCategoryIds.value] },
    })
    await useSyncStatus().syncNow()
    toast.success('Kategorien gespeichert.')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Kategorien konnten nicht gespeichert werden.')
  } finally {
    savingCategories.value = false
  }
}

function toggleCategory(categoryId: string): void {
  const next = new Set(selectedCategoryIds.value)
  if (next.has(categoryId)) next.delete(categoryId)
  else next.add(categoryId)
  selectedCategoryIds.value = next
}

// --- Play sessions ---
function toDatetimeLocal(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const showSessionModal = ref(false)
const playedAt = ref(toDatetimeLocal(new Date()))
const durationMin = ref<number | null>(null)
const outcome = ref<PlaySessionOutcome | ''>('')
const sessionNotes = ref('')
const players = ref<PlaySessionPlayerInput[]>([{ player_name: '', is_winner: false, score: null }])
const loggingSession = ref(false)

function addPlayerRow(): void {
  players.value.push({ player_name: '', is_winner: false, score: null })
}

function removePlayerRow(index: number): void {
  players.value.splice(index, 1)
}

function resetSessionForm(): void {
  playedAt.value = toDatetimeLocal(new Date())
  durationMin.value = null
  outcome.value = ''
  sessionNotes.value = ''
  players.value = [{ player_name: '', is_winner: false, score: null }]
}

async function onLogSession(): Promise<void> {
  const validPlayers = players.value.filter((p) => p.player_name.trim() !== '')
  if (validPlayers.length === 0) return

  loggingSession.value = true

  try {
    await logPlaySession(
      collectionId.value,
      {
        game_id: gameId.value,
        played_at: new Date(playedAt.value).toISOString(),
        duration_min: durationMin.value,
        outcome: outcome.value || null,
        notes: sessionNotes.value.trim() || null,
      },
      validPlayers,
    )
    toast.success('Sitzung protokolliert.')
    resetSessionForm()
    showSessionModal.value = false
  } finally {
    loggingSession.value = false
  }
}

async function onDeleteSession(sessionId: string): Promise<void> {
  if (!confirm('Diese Sitzung wirklich löschen?')) return
  await deletePlaySession(sessionId)
  toast.success('Sitzung gelöscht.')
}

const outcomeLabels: Record<PlaySessionOutcome, string> = { win: 'Sieg', loss: 'Niederlage', draw: 'Unentschieden' }
</script>

<template>
  <section v-if="detail">
    <NuxtLink :to="`/collections/${collectionId}`" class="back-link">
      <svg class="icon-svg"><use :href="iconHref('arrow_back')" /></svg>
      Zurück zur Sammlung
    </NuxtLink>

    <div class="game-detail-header">
      <div
        class="cover-tile"
        :class="{ 'is-drag-over': isCoverDragOver, 'is-editable': canEdit }"
        @dragover="onCoverDragOver"
        @dragleave="onCoverDragLeave"
        @drop="onCoverDrop"
      >
        <img v-if="detail.game.cover_url" :src="detail.game.cover_url" :alt="detail.game.title" class="cover-img" />
        <div v-else class="cover-img cover-placeholder">
          <svg class="icon-svg"><use :href="iconHref('casino')" /></svg>
        </div>

        <div v-if="uploadingCover || removingCover" class="cover-busy">
          <span class="spinner small" />
        </div>

        <template v-else-if="canEdit">
          <button
            type="button"
            class="button button-icon button-primary cover-action cover-action-upload"
            :title="detail.game.cover_url ? 'Cover ersetzen' : 'Cover hochladen'"
            @click="triggerCoverSelect"
          >
            <svg class="icon-svg"><use :href="iconHref('add_a_photo')" /></svg>
          </button>
          <button
            v-if="detail.game.cover_url"
            type="button"
            class="button button-icon button-outline cover-action cover-action-remove"
            title="Cover entfernen"
            @click="onRemoveCover"
          >
            <svg class="icon-svg"><use :href="iconHref('delete')" /></svg>
          </button>
        </template>

        <input
          ref="coverInput"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="visually-hidden"
          @change="onCoverFileChange"
        />
      </div>

      <div class="header-meta">
        <h1>{{ detail.game.title }}</h1>
        <p v-if="canEdit" class="text-secondary cover-hint">
          {{ detail.game.cover_url ? 'Neues Bild hochladen oder hierher ziehen' : 'Cover hochladen oder hierher ziehen' }}
        </p>
      </div>
    </div>

    <div class="card">
      <h2>Details</h2>
      <fieldset :disabled="!canEdit" class="detail-form">
        <div class="form-group">
          <label for="title">Titel</label>
          <input id="title" v-model="title" type="text" required />
        </div>
        <div class="row">
          <div class="column form-group">
            <label for="min-players">Min. Spieler:innen</label>
            <input id="min-players" v-model.number="minPlayers" type="number" min="1" />
          </div>
          <div class="column form-group">
            <label for="max-players">Max. Spieler:innen</label>
            <input id="max-players" v-model.number="maxPlayers" type="number" min="1" />
          </div>
        </div>
        <div class="form-group">
          <label for="publisher">Verlag</label>
          <input id="publisher" v-model="publisher" type="text" />
        </div>
        <div class="form-group">
          <label for="edition">Edition</label>
          <input id="edition" v-model="edition" type="text" />
        </div>
        <div class="form-group">
          <label for="description">Beschreibung</label>
          <textarea id="description" v-model="description" rows="4" />
        </div>
      </fieldset>
      <div v-if="canEdit" class="buttons">
        <button type="button" class="button button-primary" :disabled="saving" @click="onSave">
          {{ saving ? 'Speichert …' : 'Speichern' }}
        </button>
        <button type="button" class="button button-error button-outline" @click="onDelete">Spiel löschen</button>
      </div>
    </div>

    <div v-if="detail.expansions.length > 0" class="card">
      <h2>Erweiterungen</h2>
      <ul>
        <li v-for="expansion in detail.expansions" :key="expansion.id">{{ expansion.title }}</li>
      </ul>
    </div>

    <div v-if="canEdit && allCategories.length > 0" class="card">
      <h2>Kategorien</h2>
      <div class="category-list">
        <span v-for="category in allCategories" :key="category.id" class="category-checkbox">
          <input
            :id="`category-${category.id}`"
            type="checkbox"
            class="styled-checkbox"
            :checked="selectedCategoryIds.has(category.id)"
            @change="toggleCategory(category.id)"
          />
          <label :for="`category-${category.id}`">{{ category.name }}</label>
        </span>
      </div>
      <div class="buttons">
        <button type="button" class="button button-primary button-sm" :disabled="savingCategories" @click="onSaveCategories">
          {{ savingCategories ? 'Speichert …' : 'Kategorien speichern' }}
        </button>
      </div>
    </div>

    <div class="card">
      <div class="page-header">
        <h2>Spielverlauf</h2>
        <button v-if="canEdit" type="button" class="button button-primary button-sm" @click="showSessionModal = true">
          <svg class="icon-svg"><use :href="iconHref('add')" /></svg>
          Sitzung protokollieren
        </button>
      </div>

      <p v-if="sessions.length === 0" class="text-secondary">Noch keine Sitzungen protokolliert.</p>

      <ul class="session-list">
        <li v-for="row in sessions" :key="row.session.id" class="session-item">
          <div>
            <strong>{{ new Date(row.session.played_at).toLocaleString('de-AT') }}</strong>
            <span v-if="row.session.outcome" class="badge">{{ outcomeLabels[row.session.outcome] }}</span>
            <p class="text-secondary">
              {{ row.players.map((p) => `${p.player_name}${p.is_winner ? ' 🏆' : ''}`).join(', ') }}
            </p>
          </div>
          <button v-if="canEdit" type="button" class="button button-sm button-outline" @click="onDeleteSession(row.session.id)">
            <svg class="icon-svg"><use :href="iconHref('delete')" /></svg>
          </button>
        </li>
      </ul>
    </div>

    <AppModal v-model="showSessionModal" header="Sitzung protokollieren">
      <form class="session-form" @submit.prevent="onLogSession">
        <div class="form-group">
          <label for="played-at">Gespielt am</label>
          <input id="played-at" v-model="playedAt" type="datetime-local" required />
        </div>
        <div class="row">
          <div class="column form-group">
            <label for="duration">Dauer (Minuten)</label>
            <input id="duration" v-model.number="durationMin" type="number" min="1" />
          </div>
          <div class="column form-group">
            <label for="outcome">Ergebnis</label>
            <select id="outcome" v-model="outcome">
              <option value="">–</option>
              <option value="win">Sieg</option>
              <option value="loss">Niederlage</option>
              <option value="draw">Unentschieden</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label for="notes">Notizen</label>
          <textarea id="notes" v-model="sessionNotes" rows="2" />
        </div>

        <div class="form-group">
          <label>Spieler:innen</label>
          <div v-for="(player, index) in players" :key="index" class="player-row">
            <input v-model="player.player_name" type="text" placeholder="Name" required />
            <input v-model.number="player.score" type="number" placeholder="Punkte" />
            <div class="switch">
              <input :id="`winner-${index}`" v-model="player.is_winner" type="checkbox" />
              <label :for="`winner-${index}`">Sieg</label>
            </div>
            <button type="button" class="button button-sm button-outline" @click="removePlayerRow(index)">
              <svg class="icon-svg"><use :href="iconHref('close')" /></svg>
            </button>
          </div>
          <button type="button" class="button button-sm" @click="addPlayerRow">
            <svg class="icon-svg"><use :href="iconHref('add')" /></svg>
            Spieler:in hinzufügen
          </button>
        </div>
      </form>

      <template #footer>
        <div class="buttons">
          <button type="button" class="button button-outline" @click="showSessionModal = false">Abbrechen</button>
          <button type="button" class="button button-primary" :disabled="loggingSession" @click="onLogSession">
            {{ loggingSession ? 'Speichert …' : 'Speichern' }}
          </button>
        </div>
      </template>
    </AppModal>
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

.game-detail-header {
  display: flex;
  align-items: flex-end;
  gap: 1.25rem;
  margin-bottom: 1.5rem;
  animation: cover-header-rise-in 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
}

// The cover sits slightly proud of the baseline the meta text sits on —
// a small deliberate asymmetry instead of a dead-center vertical align.
.cover-tile {
  position: relative;
  flex-shrink: 0;
  width: clamp(6.5rem, 22vw, 9rem);
  aspect-ratio: 4 / 3;
  border-radius: var(--radius-md, 8px);
  overflow: visible;
  box-shadow: 0 10px 24px -12px rgba(0, 0, 0, 0.35), 0 1px 2px rgba(0, 0, 0, 0.08);

  &.is-editable {
    // Rounded clip target for the drag-over affordance, applied to a
    // pseudo layer so the overlapping action buttons stay un-clipped.
    &::before {
      content: '';
      position: absolute;
      inset: -6px;
      border-radius: calc(var(--radius-md, 8px) + 6px);
      border: 2px dashed transparent;
      transition: border-color 0.2s ease, background-color 0.2s ease;
      pointer-events: none;
    }
  }

  &.is-drag-over::before {
    border-color: var(--accent-color);
    background-color: color-mix(in srgb, var(--accent-color) 8%, transparent);
  }
}

.cover-img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: var(--radius-md, 8px);
}

.cover-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--secondary-background);
  color: var(--secondary-text);

  .icon-svg {
    width: 2.25rem;
    height: 2.25rem;
    opacity: 0.6;
  }
}

.cover-busy {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md, 8px);
  background: color-mix(in srgb, var(--background) 55%, transparent);
  backdrop-filter: blur(2px);
}

.cover-action {
  position: absolute;
  width: 2.75rem;
  height: 2.75rem;
  padding: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
  transition: transform 0.15s cubic-bezier(0.25, 0.46, 0.45, 0.94), box-shadow 0.15s ease;

  &:hover:not(:disabled) {
    transform: scale(1.08);
  }

  &:active:not(:disabled) {
    transform: scale(0.95);
  }

  .icon-svg {
    width: 1.15rem;
    height: 1.15rem;
  }
}

.cover-action-upload {
  right: -0.6rem;
  bottom: -0.6rem;
}

.cover-action-remove {
  right: -0.6rem;
  top: -0.6rem;
  background: var(--background);
}

.header-meta {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding-bottom: 0.15rem;

  h1 {
    margin: 0;
  }
}

.cover-hint {
  font-size: 0.8125rem;
  margin: 0;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@keyframes cover-header-rise-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .game-detail-header {
    animation: none;
  }
}

.card {
  margin-bottom: 1rem;
}

.detail-form {
  border: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.category-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.category-checkbox {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.session-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.session-item {
  // basix's `ul li { list-style-type: disc }` (typography.scss:150) targets
  // the <li> directly, overriding the parent <ul>'s inherited `list-style:
  // none` — the override has to live here, not just on `.session-list`.
  list-style-type: none;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--divider);
}

.session-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.player-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}
</style>
