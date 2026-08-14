<script setup lang="ts">
import type { WishlistItem } from '~/types/models'

const route = useRoute()
const collectionId = computed(() => route.params.id as string)

const collection = useCollection(collectionId)
const role = useCollectionRole(collectionId)
const canEdit = computed(() => role.value === 'owner' || role.value === 'editor')
const items = useWishlistItems(collectionId)
const toast = useToast()

useHead({ title: () => `Wunschliste – ${collection.value?.name ?? 'Sammlung'} – BasixMeeple` })

const { setActiveCollectionId } = useActiveCollectionId()
watchEffect(() => setActiveCollectionId(collectionId.value))

const showModal = ref(false)
const editingId = ref<string | null>(null)
const title = ref('')
const priority = ref(3)
const priceEstimate = ref<number | null>(null)
const saving = ref(false)

function openCreate(): void {
  editingId.value = null
  title.value = ''
  priority.value = 3
  priceEstimate.value = null
  showModal.value = true
}

function openEdit(item: WishlistItem): void {
  editingId.value = item.id
  title.value = item.title
  priority.value = item.priority
  priceEstimate.value = item.price_estimate ? Number(item.price_estimate) : null
  showModal.value = true
}

async function onSave(): Promise<void> {
  if (!title.value.trim()) return
  saving.value = true

  try {
    if (editingId.value) {
      await updateWishlistItem(editingId.value, {
        title: title.value.trim(),
        priority: priority.value,
        price_estimate: priceEstimate.value !== null ? String(priceEstimate.value) : null,
      })
      toast.success('Gespeichert.')
    } else {
      await createWishlistItem(collectionId.value, {
        title: title.value.trim(),
        priority: priority.value,
        price_estimate: priceEstimate.value !== null ? String(priceEstimate.value) : null,
      })
      toast.success('Zur Wunschliste hinzugefügt.')
    }
    showModal.value = false
  } finally {
    saving.value = false
  }
}

async function onDelete(itemId: string): Promise<void> {
  if (!confirm('Diesen Wunsch wirklich löschen?')) return
  await deleteWishlistItem(itemId)
}

// Presentational only — priority is already the sort key from
// useWishlistItems(); these just turn that same number into a colour tier
// and badge variant so the priority-order the list already has becomes
// visible at a glance, not just readable in the badge text.
function priorityTierClass(value: number): string {
  if (value >= 5) return 'tier-critical'
  if (value === 4) return 'tier-high'
  if (value === 3) return 'tier-medium'
  return 'tier-low'
}

function priorityBadgeClass(value: number): string {
  if (value >= 5) return 'badge badge-error'
  if (value === 4) return 'badge badge-warning'
  if (value === 3) return 'badge badge-info'
  return 'badge'
}
</script>

<template>
  <section>
    <NuxtLink :to="`/collections/${collectionId}`" class="back-link">
      <svg class="icon-svg"><use :href="iconHref('arrow_back')" /></svg>
      Zurück zur Sammlung
    </NuxtLink>

    <div class="page-header">
      <h1>Wunschliste</h1>
      <div class="buttons">
        <CollectionSubnav :collection-id="collectionId" />
        <button v-if="canEdit" type="button" class="button button-primary" @click="openCreate">
          <svg class="icon-svg"><use :href="iconHref('add')" /></svg>
          Hinzufügen
        </button>
      </div>
    </div>

    <div v-if="items.length === 0" class="empty-state card">
      <svg class="icon-svg empty-state-icon"><use :href="iconHref('favorite')" /></svg>
      <p class="text-secondary">Die Wunschliste ist leer.</p>
    </div>

    <TransitionGroup v-else tag="ul" name="wish-list" class="wishlist" appear>
      <li
        v-for="item in items"
        :key="item.id"
        class="card wishlist-item"
        :class="priorityTierClass(item.priority)"
      >
        <div class="priority-meter" :aria-label="`Priorität ${item.priority} von 5`">
          <span v-for="n in 5" :key="n" class="priority-dot" :class="{ filled: n <= item.priority }" />
        </div>
        <div class="wishlist-main">
          <strong>{{ item.title }}</strong>
          <div class="wishlist-meta">
            <span :class="priorityBadgeClass(item.priority)">Priorität {{ item.priority }}</span>
            <span v-if="item.price_estimate" class="price-chip">
              <svg class="icon-svg"><use :href="iconHref('sell')" /></svg>
              ca. {{ Number(item.price_estimate).toFixed(2) }} €
            </span>
          </div>
        </div>
        <div v-if="canEdit" class="buttons">
          <button type="button" class="button button-sm button-outline" @click="openEdit(item)">
            <svg class="icon-svg"><use :href="iconHref('edit')" /></svg>
          </button>
          <button type="button" class="button button-sm button-outline" @click="onDelete(item.id)">
            <svg class="icon-svg"><use :href="iconHref('delete')" /></svg>
          </button>
        </div>
      </li>
    </TransitionGroup>

    <AppModal v-model="showModal" :header="editingId ? 'Wunsch bearbeiten' : 'Zur Wunschliste hinzufügen'">
      <form class="wishlist-form" @submit.prevent="onSave">
        <div class="form-group">
          <label for="wish-title">Titel</label>
          <input id="wish-title" v-model="title" type="text" required autofocus />
        </div>
        <div class="form-group">
          <label for="wish-priority">Priorität</label>
          <select id="wish-priority" v-model.number="priority">
            <option :value="5">5 – sehr hoch</option>
            <option :value="4">4 – hoch</option>
            <option :value="3">3 – mittel</option>
            <option :value="2">2 – niedrig</option>
            <option :value="1">1 – sehr niedrig</option>
          </select>
        </div>
        <div class="form-group">
          <label for="wish-price">Geschätzter Preis (€)</label>
          <input id="wish-price" v-model.number="priceEstimate" type="number" min="0" step="0.01" />
        </div>
      </form>

      <template #footer>
        <div class="buttons">
          <button type="button" class="button button-outline" @click="showModal = false">Abbrechen</button>
          <button type="button" class="button button-primary" :disabled="saving" @click="onSave">
            {{ saving ? 'Speichert …' : 'Speichern' }}
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

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
  gap: 1rem;
}

.wishlist {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

// Left-edge colour tier echoes the priority the list is already sorted
// by — the sort order becomes something you can see, not just infer.
.wishlist-item {
  // basix's `ul li { list-style-type: disc }` (typography.scss:150) targets
  // the <li> directly, overriding the parent <ul>'s inherited `list-style:
  // none` — the override has to live here, not just on `.wishlist`.
  list-style-type: none;
  flex-direction: row;
  align-items: center;
  gap: 0.875rem;
  border-left: 3px solid var(--divider);
  transition: transform 0.15s ease, box-shadow 0.2s ease, border-color 0.2s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
  }

  &.tier-critical {
    border-left-color: var(--error);
  }
  &.tier-high {
    border-left-color: var(--warning);
  }
  &.tier-medium {
    border-left-color: var(--accent-color);
  }
  &.tier-low {
    border-left-color: var(--divider);
  }
}

.priority-meter {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

// Edit/delete buttons keep their natural size — only the title/badge column
// (which has min-width: 0) gives up space to a long title.
.wishlist-item > .buttons {
  flex-shrink: 0;
}

.priority-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--divider);

  &.filled {
    background: var(--accent-color);
  }
}

.wishlist-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.wishlist-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.price-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  background: var(--secondary-background);
  color: var(--secondary-text);
  font-size: 0.75rem;
  font-weight: 600;

  .icon-svg {
    width: 0.8rem;
    height: 0.8rem;
  }
}

.wishlist-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.wish-list-enter-active {
  transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.wish-list-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.wish-list-move {
  transition: transform 0.3s ease;
}
.wish-list-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}
.wish-list-leave-to {
  opacity: 0;
  transform: translateX(16px);
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
  .wish-list-enter-active,
  .wish-list-leave-active,
  .wish-list-move,
  .wishlist-item {
    transition: none;
  }
}
</style>
