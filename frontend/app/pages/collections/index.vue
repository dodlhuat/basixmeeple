<script setup lang="ts">
import { db } from '~/utils/db'
import { ApiError } from '~/utils/apiClient'
import type { CollectionUser } from '~/types/models'

useHead({ title: 'Sammlungen – BasixMeeple' })

const { user } = useAuth()
const { setActiveCollectionId } = useActiveCollectionId()
const collections = useCollections()
const toast = useToast()

const memberships = useLiveQuery(async () => {
  if (!user.value) return []
  return db.collection_user.where('user_id').equals(user.value.id).toArray()
}, [] as CollectionUser[])

const roleLabels: Record<string, string> = {
  owner: 'Eigentümer:in',
  editor: 'Bearbeiter:in',
  viewer: 'Betrachter:in',
}

function roleLabelFor(collectionId: string): string {
  const role = memberships.value.find((m) => m.collection_id === collectionId)?.role
  return (role && roleLabels[role]) || ''
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
      <h1>Sammlungen</h1>
      <button type="button" class="button button-primary" @click="showCreateModal = true">
        <svg class="icon-svg"><use :href="iconHref('add')" /></svg>
        Neue Sammlung
      </button>
    </div>

    <p v-if="collections.length === 0" class="secondary-text">
      Noch keine Sammlung vorhanden. Lege deine erste Sammlung an.
    </p>

    <div class="row collection-grid">
      <button
        v-for="collection in collections"
        :key="collection.id"
        type="button"
        class="column card collection-card"
        @click="open(collection.id)"
      >
        <h3>{{ collection.name }}</h3>
        <span class="badge">{{ roleLabelFor(collection.id) }}</span>
      </button>
    </div>

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
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  gap: 1rem;
}

.collection-grid {
  flex-wrap: wrap;
}

.collection-card {
  min-width: 200px;
  flex: 1 1 220px;
  text-align: left;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  align-items: flex-start;
}
</style>
