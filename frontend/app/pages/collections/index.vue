<script setup lang="ts">
import { db } from '~/utils/db'
import { ApiError } from '~/utils/apiClient'
import type { Collection, CollectionGame, CollectionRole, CollectionUser } from '~/types/models'

useHead({ title: 'Sammlungen – BasixMeeple' })

const { user } = useAuth()
const { setActiveCollectionId } = useActiveCollectionId()
const collections = useCollections()
const toast = useToast()

// Both queried whole-table rather than per-collection: `collection_games`
// and `collection_user` are already fully synced locally for every
// collection this user can see (see utils/sync.ts's snapshot), and the
// per-collection counts below are cheap in-memory grouping over that —
// no extra Dexie round-trip per card, no backend call invented for this.
const allCollectionGames = useLiveQuery(() => db.collection_games.toArray(), [] as CollectionGame[])
const allCollectionUsers = useLiveQuery(() => db.collection_user.toArray(), [] as CollectionUser[])

const roleLabels: Record<CollectionRole, string> = {
  owner: 'Eigentümer:in',
  editor: 'Bearbeiter:in',
  viewer: 'Betrachter:in',
}

interface CollectionRow {
  collection: Collection
  role: CollectionRole | null
  roleLabel: string
  gameCount: number
  memberCount: number
}

const collectionRows = computed<CollectionRow[]>(() => {
  const gameCounts = new Map<string, number>()
  for (const row of allCollectionGames.value) {
    gameCounts.set(row.collection_id, (gameCounts.get(row.collection_id) ?? 0) + 1)
  }

  const memberCounts = new Map<string, number>()
  const roleByCollection = new Map<string, CollectionRole>()
  for (const row of allCollectionUsers.value) {
    memberCounts.set(row.collection_id, (memberCounts.get(row.collection_id) ?? 0) + 1)
    if (user.value && row.user_id === user.value.id) roleByCollection.set(row.collection_id, row.role)
  }

  return collections.value.map((collection) => {
    const role = roleByCollection.get(collection.id) ?? null
    return {
      collection,
      role,
      roleLabel: role ? roleLabels[role] : '',
      gameCount: gameCounts.get(collection.id) ?? 0,
      memberCount: memberCounts.get(collection.id) ?? 0,
    }
  })
})

// Presentational only — gives the owner role a branded accent to scan for,
// same idea as the priority/status colour-coding on wishlist.vue/loans.vue.
function roleBadgeClass(role: CollectionRole | null): string {
  if (role === 'owner') return 'badge badge-info'
  if (role === 'editor') return 'badge'
  return 'badge badge-outline'
}

function open(collectionId: string): void {
  setActiveCollectionId(collectionId)
  navigateTo(`/collections/${collectionId}`)
}

const showCreateModal = ref(false)
const newCollectionName = ref('')
const creating = ref(false)

async function onCreate(): Promise<void> {
  if (!newCollectionName.value.trim()) return

  creating.value = true

  try {
    const collection = await createCollection(newCollectionName.value.trim())
    showCreateModal.value = false
    newCollectionName.value = ''
    toast.success('Sammlung erstellt.')
    open(collection.id)
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Sammlung konnte nicht erstellt werden.')
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <section>
    <div class="page-header">
      <div class="page-heading">
        <h1>Sammlungen</h1>
        <p v-if="collectionRows.length > 0" class="page-subtitle">
          {{ collectionRows.length }} {{ collectionRows.length === 1 ? 'Sammlung' : 'Sammlungen' }}
        </p>
      </div>
      <button type="button" class="button button-primary" @click="showCreateModal = true">
        <svg class="icon-svg"><use :href="iconHref('add')" /></svg>
        Neue Sammlung
      </button>
    </div>

    <!-- Unlike stats.vue/wishlist.vue's bare icon+text empty states, this one
         carries its own CTA — this is the very first screen a brand-new user
         with zero data lands on, so "what do I do now" deserves a direct
         answer here rather than relying on the header button alone. -->
    <div v-if="collectionRows.length === 0" class="empty-state card">
      <svg class="icon-svg empty-state-icon"><use :href="iconHref('folder')" /></svg>
      <h2>Noch keine Sammlung</h2>
      <p class="text-secondary">Lege deine erste Sammlung an, um eure Spiele zu erfassen und gemeinsam zu verwalten.</p>
      <button type="button" class="button button-primary" @click="showCreateModal = true">
        <svg class="icon-svg"><use :href="iconHref('add')" /></svg>
        Sammlung erstellen
      </button>
    </div>

    <TransitionGroup v-else tag="ul" name="collection-list" class="collection-list" appear>
      <li v-for="row in collectionRows" :key="row.collection.id" class="collection-item">
        <button type="button" class="collection-card" @click="open(row.collection.id)">
          <span class="collection-icon" aria-hidden="true">
            <svg class="icon-svg"><use :href="iconHref('folder')" /></svg>
          </span>

          <span class="collection-main">
            <span class="collection-title-row">
              <span class="collection-title">{{ row.collection.name }}</span>
              <span v-if="row.roleLabel" :class="roleBadgeClass(row.role)">{{ row.roleLabel }}</span>
            </span>

            <span class="collection-meta">
              <span class="meta-chip">
                <svg class="icon-svg"><use :href="iconHref('casino')" /></svg>
                {{ row.gameCount }} {{ row.gameCount === 1 ? 'Spiel' : 'Spiele' }}
              </span>
              <span class="meta-chip">
                <svg class="icon-svg"><use :href="iconHref('group')" /></svg>
                {{ row.memberCount }} {{ row.memberCount === 1 ? 'Mitglied' : 'Mitglieder' }}
              </span>
            </span>
          </span>

          <svg class="icon-svg collection-chevron" aria-hidden="true"><use :href="iconHref('chevron_right')" /></svg>
        </button>
      </li>
    </TransitionGroup>

    <AppModal v-model="showCreateModal" header="Neue Sammlung">
      <form class="form-group" @submit.prevent="onCreate">
        <label for="collection-name">Name</label>
        <input id="collection-name" v-model="newCollectionName" type="text" required autofocus />
      </form>

      <template #footer>
        <div class="buttons">
          <button type="button" class="button button-outline" @click="showCreateModal = false">Abbrechen</button>
          <button type="button" class="button button-primary" :disabled="creating" @click="onCreate">
            {{ creating ? 'Wird erstellt …' : 'Erstellen' }}
          </button>
        </div>
      </template>
    </AppModal>
  </section>
</template>

<style lang="scss" scoped>
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.page-heading {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;

  h1 {
    margin: 0;
  }
}

.page-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--secondary-text);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.75rem;
  padding: 3rem 1.5rem;

  h2 {
    margin: 0;
  }
}

.empty-state-icon {
  width: 2.5rem;
  height: 2.5rem;
  color: var(--secondary-text);
  opacity: 0.5;
}

.collection-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

// The actual bug fix lives on `.collection-title` below, but the whole flex
// chain from here down needs `min-width: 0` too — a flex item's default
// `min-width: auto` refuses to shrink below its content's intrinsic width,
// which is the other half of the classic "long text breaks a flex row" bug
// (the previous `.collection-card { min-width: 200px; flex: 1 1 220px }`
// never had this, which is why long names could distort the row).
.collection-item {
  // basix's `ul li { list-style-type: disc }` (typography.scss:150) targets
  // the <li> directly, overriding the parent <ul>'s inherited `list-style:
  // none` — the override has to live here, not just on `.collection-list`.
  list-style-type: none;
  min-width: 0;
}

// Deliberately not basix's `.card`/`.button` classes — both carry baggage
// that caused the original bug: `.card` sets `overflow: hidden`
// (card.scss) and `.button` sets `white-space: nowrap` (button.scss:46),
// and since the whole tile *was* a `<button class="card">`, both rules
// cascaded onto the `<h3>` title inside with no wrap/ellipsis handling —
// long names were silently clipped mid-word. Hand-rolled here instead,
// against basix's tokens, with explicit wrap/clamp handling on the title.
.collection-card {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  width: 100%;
  min-width: 0;
  padding: 0.9rem 1.1rem;
  border: 1px solid var(--divider);
  border-radius: 0.75rem;
  background: var(--secondary-background);
  color: inherit;
  text-align: left;
  cursor: pointer;
  font: inherit;
  transition: transform 0.15s ease, box-shadow 0.2s ease, border-color 0.2s ease;

  &:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--accent-color) 30%, var(--divider));
    box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.2);

    .collection-chevron {
      transform: translateX(3px);
      color: var(--accent-color);
    }
  }

  &:active {
    transform: translateY(0) scale(0.99);
  }

  &:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color) 35%, transparent);
  }
}

.collection-icon {
  flex-shrink: 0;
  width: 2.75rem;
  height: 2.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--accent-color-tint);
  color: var(--accent-color);

  .icon-svg {
    width: 1.375rem;
    height: 1.375rem;
  }
}

.collection-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.collection-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  min-width: 0;
}

.collection-title {
  min-width: 0;
  flex: 1 1 auto;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--primary-text);
  letter-spacing: -0.01em;
  line-height: 1.3;

  // Long-title fix proper: explicitly override the `white-space: nowrap`
  // this element would otherwise inherit from the `.button`-like ancestor,
  // clamp to two lines with a real ellipsis instead of a hard cutoff, and
  // let mid-word breaks happen only as a last resort.
  white-space: normal;
  overflow: hidden;
  overflow-wrap: anywhere;
  word-break: break-word;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.collection-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--secondary-text);

  .icon-svg {
    width: 1rem;
    height: 1rem;
    flex-shrink: 0;
  }
}

.collection-chevron {
  flex-shrink: 0;
  width: 1.25rem;
  height: 1.25rem;
  color: var(--divider);
  transition: transform 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94), color 0.2s ease;
}

// TransitionGroup hooks: same rise-in shape as stats.vue/login.vue's
// composed entrance, but applied per row (via nth-child stagger below)
// since this list — unlike those pages' fixed handful of sections — is
// variable-length. Also covers future add/remove once a collection is
// created, echoing loan-list/wishlist's mount+reorder transitions.
.collection-list-enter-active {
  transition: opacity 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94), transform 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.collection-list-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.collection-list-move {
  transition: transform 0.3s ease;
}
.collection-list-enter-from {
  opacity: 0;
  transform: translateY(14px);
}
.collection-list-leave-to {
  opacity: 0;
  transform: translateX(16px);
}

@for $i from 1 through 10 {
  .collection-item.collection-list-enter-active:nth-child(#{$i}) {
    transition-delay: #{55 * ($i - 1)}ms;
  }
}

@media (prefers-reduced-motion: reduce) {
  .collection-list-enter-active,
  .collection-list-leave-active,
  .collection-list-move,
  .collection-card {
    transition: none;
  }

  .collection-item.collection-list-enter-active {
    transition-delay: 0ms;
  }
}
</style>
