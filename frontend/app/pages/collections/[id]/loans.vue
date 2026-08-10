<script setup lang="ts">
const route = useRoute()
const collectionId = computed(() => route.params.id as string)

const collection = useCollection(collectionId)
const role = useCollectionRole(collectionId)
const canEdit = computed(() => role.value === 'owner' || role.value === 'editor')
const games = useCollectionGames(collectionId)
const loans = useLoans(collectionId)
const toast = useToast()

useHead({ title: () => `Ausleihen – ${collection.value?.name ?? 'Sammlung'} – BasixMeeple` })

const { setActiveCollectionId } = useActiveCollectionId()
watchEffect(() => setActiveCollectionId(collectionId.value))

const activeLoans = computed(() => loans.value.filter((row) => row.loan.returned_at === null))
const returnedLoans = computed(() => loans.value.filter((row) => row.loan.returned_at !== null))

function isOverdue(dueDate: string | null): boolean {
  if (!dueDate) return false
  return new Date(dueDate) < new Date()
}

// --- Lend modal ---
const showLendModal = ref(false)
const selectedGameId = ref('')
const borrowerName = ref('')
const loanedAt = ref(new Date().toISOString().slice(0, 10))
const dueDate = ref('')
const lending = ref(false)

async function onLend(): Promise<void> {
  if (!selectedGameId.value || !borrowerName.value.trim()) return
  lending.value = true

  try {
    await createLoan({
      game_id: selectedGameId.value,
      borrower_name: borrowerName.value.trim(),
      loaned_at: new Date(loanedAt.value).toISOString(),
      due_date: dueDate.value || null,
    })
    toast.success('Ausgeliehen.')
    selectedGameId.value = ''
    borrowerName.value = ''
    dueDate.value = ''
    showLendModal.value = false
  } finally {
    lending.value = false
  }
}

async function onReturn(loanId: string): Promise<void> {
  await markLoanReturned(loanId)
  toast.success('Als zurückgegeben markiert.')
}

async function onDelete(loanId: string): Promise<void> {
  if (!confirm('Diesen Ausleih-Eintrag wirklich löschen?')) return
  await deleteLoan(loanId)
}
</script>

<template>
  <section>
    <NuxtLink :to="`/collections/${collectionId}`" class="back-link">
      <svg class="icon-svg"><use :href="iconHref('arrow_back')" /></svg>
      Zurück zur Sammlung
    </NuxtLink>

    <div class="page-header">
      <h1>Ausleihen</h1>
      <div class="buttons">
        <CollectionSubnav :collection-id="collectionId" />
        <button v-if="canEdit" type="button" class="button button-primary" @click="showLendModal = true">
          <svg class="icon-svg"><use :href="iconHref('handshake')" /></svg>
          Verleihen
        </button>
      </div>
    </div>

    <div class="card">
      <h2>Aktuell verliehen</h2>
      <p v-if="activeLoans.length === 0" class="secondary-text">Gerade ist nichts verliehen.</p>
      <ul v-else class="loan-list">
        <li v-for="row in activeLoans" :key="row.loan.id" class="loan-item">
          <div>
            <strong>{{ row.game.title }}</strong> an {{ row.loan.borrower_name }}
            <span v-if="isOverdue(row.loan.due_date)" class="badge badge-error">Überfällig</span>
            <p class="secondary-text">
              Verliehen am {{ new Date(row.loan.loaned_at).toLocaleDateString('de-AT') }}
              <template v-if="row.loan.due_date"> · fällig am {{ new Date(row.loan.due_date).toLocaleDateString('de-AT') }}</template>
            </p>
          </div>
          <div v-if="canEdit" class="buttons">
            <button type="button" class="button button-sm" @click="onReturn(row.loan.id)">Zurückgegeben</button>
            <button type="button" class="button button-sm button-outline" @click="onDelete(row.loan.id)">
              <svg class="icon-svg"><use :href="iconHref('delete')" /></svg>
            </button>
          </div>
        </li>
      </ul>
    </div>

    <div v-if="returnedLoans.length > 0" class="card">
      <h2>Verlauf</h2>
      <ul class="loan-list">
        <li v-for="row in returnedLoans" :key="row.loan.id" class="loan-item">
          <div>
            <strong>{{ row.game.title }}</strong> an {{ row.loan.borrower_name }}
            <p class="secondary-text">
              {{ new Date(row.loan.loaned_at).toLocaleDateString('de-AT') }} –
              {{ new Date(row.loan.returned_at!).toLocaleDateString('de-AT') }}
            </p>
          </div>
          <button v-if="canEdit" type="button" class="button button-sm button-outline" @click="onDelete(row.loan.id)">
            <svg class="icon-svg"><use :href="iconHref('delete')" /></svg>
          </button>
        </li>
      </ul>
    </div>

    <AppModal v-model="showLendModal" header="Spiel verleihen">
      <form class="lend-form" @submit.prevent="onLend">
        <div class="form-group">
          <label for="loan-game">Spiel</label>
          <select id="loan-game" v-model="selectedGameId" required>
            <option value="" disabled>Bitte wählen …</option>
            <option v-for="row in games" :key="row.game.id" :value="row.game.id">{{ row.game.title }}</option>
          </select>
        </div>
        <div class="form-group">
          <label for="loan-borrower">An</label>
          <input id="loan-borrower" v-model="borrowerName" type="text" placeholder="Name" required />
        </div>
        <div class="row">
          <div class="column form-group">
            <label for="loan-date">Verliehen am</label>
            <input id="loan-date" v-model="loanedAt" type="date" />
          </div>
          <div class="column form-group">
            <label for="loan-due">Fällig am</label>
            <input id="loan-due" v-model="dueDate" type="date" />
          </div>
        </div>
      </form>

      <template #footer>
        <div class="buttons">
          <button type="button" class="button button-outline" @click="showLendModal = false">Abbrechen</button>
          <button type="button" class="button button-primary" :disabled="lending" @click="onLend">
            {{ lending ? 'Speichert …' : 'Verleihen' }}
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

.card {
  margin-bottom: 1rem;
}

.lend-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.loan-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.loan-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--divider);
}
</style>
