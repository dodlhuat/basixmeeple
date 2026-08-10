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

    <p v-if="items.length === 0" class="secondary-text">Die Wunschliste ist leer.</p>

    <ul class="wishlist">
      <li v-for="item in items" :key="item.id" class="card wishlist-item">
        <div>
          <strong>{{ item.title }}</strong>
          <span class="badge badge-info">Priorität {{ item.priority }}</span>
          <p v-if="item.price_estimate" class="secondary-text">ca. {{ Number(item.price_estimate).toFixed(2) }} €</p>
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
    </ul>

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
  margin-bottom: 1rem;
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

.wishlist-item {
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.wishlist-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
</style>
