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

// Presentational only — a two-letter monogram for the borrower avatar chip.
function initials(name: string): string {
  return (
    name
      .trim()
      .split(/\s+/)
      .map((part) => part[0])
      .filter(Boolean)
      .slice(0, 2)
      .join('')
      .toUpperCase() || '?'
  )
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
      <div class="card-section-header">
        <svg class="icon-svg"><use :href="iconHref('handshake')" /></svg>
        <h2>Aktuell verliehen</h2>
        <span v-if="activeLoans.length > 0" class="count-chip">{{ activeLoans.length }}</span>
      </div>

      <div v-if="activeLoans.length === 0" class="empty-state">
        <svg class="icon-svg empty-state-icon"><use :href="iconHref('inventory_2')" /></svg>
        <p class="text-secondary">Gerade ist nichts verliehen.</p>
      </div>

      <TransitionGroup v-else tag="ul" name="loan-list" class="loan-list" appear>
        <li
          v-for="row in activeLoans"
          :key="row.loan.id"
          class="loan-item"
          :class="{ 'is-overdue': isOverdue(row.loan.due_date) }"
        >
          <div class="loan-top">
            <div class="loan-avatar" aria-hidden="true">{{ initials(row.loan.borrower_name) }}</div>
            <div class="loan-main">
              <div class="loan-title-row">
                <strong>{{ row.game.title }}</strong> an {{ row.loan.borrower_name }}
                <span v-if="isOverdue(row.loan.due_date)" class="badge badge-error">
                  <svg class="icon-svg"><use :href="iconHref('schedule')" /></svg>
                  Überfällig
                </span>
              </div>
              <p class="text-secondary loan-meta">
                Verliehen am {{ new Date(row.loan.loaned_at).toLocaleDateString('de-AT') }}
                <template v-if="row.loan.due_date"> · fällig am {{ new Date(row.loan.due_date).toLocaleDateString('de-AT') }}</template>
              </p>
            </div>
          </div>
          <div v-if="canEdit" class="buttons loan-actions">
            <button type="button" class="button button-sm" @click="onReturn(row.loan.id)">
              <svg class="icon-svg"><use :href="iconHref('check_circle')" /></svg>
              Zurückgegeben
            </button>
            <button type="button" class="button button-sm button-outline" @click="onDelete(row.loan.id)">
              <svg class="icon-svg"><use :href="iconHref('delete')" /></svg>
            </button>
          </div>
        </li>
      </TransitionGroup>
    </div>

    <div v-if="returnedLoans.length > 0" class="card">
      <div class="card-section-header">
        <svg class="icon-svg"><use :href="iconHref('history')" /></svg>
        <h2>Verlauf</h2>
      </div>
      <ul class="timeline">
        <li v-for="row in returnedLoans" :key="row.loan.id" class="timeline-item">
          <div class="timeline-marker success">
            <svg class="icon-svg"><use :href="iconHref('check_circle')" /></svg>
          </div>
          <div class="timeline-content">
            <span class="timeline-time">
              {{ new Date(row.loan.loaned_at).toLocaleDateString('de-AT') }} –
              {{ new Date(row.loan.returned_at!).toLocaleDateString('de-AT') }}
            </span>
            <p class="timeline-title"><strong>{{ row.game.title }}</strong> an {{ row.loan.borrower_name }}</p>
            <button v-if="canEdit" type="button" class="button button-sm button-outline" @click="onDelete(row.loan.id)">
              <svg class="icon-svg"><use :href="iconHref('delete')" /></svg>
            </button>
          </div>
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
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
  gap: 1rem;
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
  }

  .icon-svg {
    width: 1.25rem;
    height: 1.25rem;
    color: var(--accent-color);
    flex-shrink: 0;
  }
}

.count-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.5rem;
  height: 1.5rem;
  padding: 0 0.4rem;
  border-radius: 999px;
  background: var(--accent-color-tint);
  color: var(--accent-color);
  font-size: 0.75rem;
  font-weight: 700;
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
}

// Cards with a coloured left edge — accent for a normal loan, error for an
// overdue one — so the "does anything here need attention" scan is instant.
// Actions live in their own full-width row below the avatar/text row rather
// than squeezed into the same flex row — a fixed-width action cluster next
// to flexible text would otherwise force the title into a razor-thin
// column on narrow viewports.
.loan-item {
  // basix's `ul li { list-style-type: disc }` (typography.scss:150) targets
  // the <li> directly, overriding the parent <ul>'s inherited `list-style:
  // none` — the override has to live here, not just on `.loan-list`.
  list-style-type: none;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  padding: 0.75rem;
  margin-bottom: 0.5rem;
  border: 1px solid var(--divider);
  border-left: 3px solid var(--accent-color);
  border-radius: 0.6rem;
  background: var(--background);
  transition: box-shadow 0.2s ease, transform 0.15s ease, border-color 0.2s ease;

  &:last-child {
    margin-bottom: 0;
  }

  &:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.07);
    transform: translateY(-1px);
  }

  &.is-overdue {
    border-left-color: var(--error);
  }
}

.loan-top {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.loan-avatar {
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--accent-color-tint);
  color: var(--accent-color);
  font-size: 0.75rem;
  font-weight: 700;
}

.loan-item.is-overdue .loan-avatar {
  background: var(--error-tint);
  color: var(--error);
}

.loan-main {
  flex: 1;
  min-width: 0;
}

.loan-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.4rem;

  .badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;

    .icon-svg {
      width: 0.8rem;
      height: 0.8rem;
    }
  }
}

.loan-meta {
  margin: 0.2rem 0 0;
}

.loan-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

// TransitionGroup hooks: a light rise-in for new items, a quick slide-out
// on return/delete.
.loan-list-enter-active {
  transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.loan-list-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.loan-list-move {
  transition: transform 0.3s ease;
}
.loan-list-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}
.loan-list-leave-to {
  opacity: 0;
  transform: translateX(16px);
}

.timeline {
  margin-top: 0.25rem;
}

.timeline-content .button {
  margin-top: 0.5rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.5rem;
  padding: 2rem 1rem;
}

.empty-state-icon {
  width: 2.25rem;
  height: 2.25rem;
  color: var(--secondary-text);
  opacity: 0.5;
}

@media (prefers-reduced-motion: reduce) {
  .loan-list-enter-active,
  .loan-list-leave-active,
  .loan-list-move,
  .loan-item {
    transition: none;
  }
}
</style>
