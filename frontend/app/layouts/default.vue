<script setup lang="ts">
import { iconHref } from '~/utils/iconSprite'

const { user, logout } = useAuth()
const collections = useCollections()
const { id: activeCollectionId, setActiveCollectionId } = useActiveCollectionId()
const syncStatus = useSyncStatus()

const activeCollectionName = computed(() => {
  const active = collections.value.find((c) => c.id === activeCollectionId.value)
  return active?.name ?? 'Sammlungen'
})

const showCollectionMenu = ref(false)
const showUserMenu = ref(false)
const headerRef = ref<HTMLElement | null>(null)

function selectCollection(collectionId: string): void {
  setActiveCollectionId(collectionId)
  showCollectionMenu.value = false
  navigateTo(`/collections/${collectionId}`)
}

async function onLogout(): Promise<void> {
  showUserMenu.value = false
  await logout()
  await navigateTo('/login')
}

function onDocumentClick(event: MouseEvent): void {
  if (headerRef.value && !headerRef.value.contains(event.target as Node)) {
    showCollectionMenu.value = false
    showUserMenu.value = false
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onUnmounted(() => document.removeEventListener('click', onDocumentClick))
</script>

<template>
  <div class="app-shell">
    <header ref="headerRef" class="app-header">
      <NuxtLink to="/collections" class="app-header-title">BasixMeeple</NuxtLink>

      <div class="dropdown-container collection-switcher" :class="{ active: showCollectionMenu }">
        <button type="button" class="dropdown-trigger button button-sm" @click="showCollectionMenu = !showCollectionMenu">
          {{ activeCollectionName }}
          <svg class="icon-svg"><use :href="iconHref('expand_more')" /></svg>
        </button>
        <ul class="dropdown-menu">
          <li v-for="collection in collections" :key="collection.id">
            <div class="dropdown-item" @click="selectCollection(collection.id)">{{ collection.name }}</div>
          </li>
          <li>
            <div class="dropdown-item" @click="showCollectionMenu = false; navigateTo('/collections')">
              Alle Sammlungen
            </div>
          </li>
        </ul>
      </div>

      <div class="app-header-actions">
        <button
          type="button"
          class="button button-icon button-sm sync-button"
          :title="syncStatus.isOnline.value ? 'Jetzt synchronisieren' : 'Offline'"
          @click="syncStatus.syncNow()"
        >
          <svg class="icon-svg" :class="{ spinning: syncStatus.isSyncing.value }">
            <use :href="iconHref(syncStatus.isOnline.value ? 'cloud_done' : 'cloud_off')" />
          </svg>
        </button>

        <div class="dropdown-container" :class="{ active: showUserMenu }">
          <button type="button" class="dropdown-trigger button button-icon button-sm" @click="showUserMenu = !showUserMenu">
            <svg class="icon-svg"><use :href="iconHref('person')" /></svg>
          </button>
          <ul class="dropdown-menu">
            <li class="dropdown-user-name">{{ user?.name }}</li>
            <li><div class="dropdown-item" @click="onLogout">Abmelden</div></li>
          </ul>
        </div>
      </div>
    </header>
    <main class="app-main">
      <slot />
    </main>
  </div>
</template>

<style lang="scss" scoped>
.app-shell {
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: var(--background);
}

.app-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem max(1rem, env(safe-area-inset-left)) 0.75rem max(1rem, env(safe-area-inset-right));
  padding-top: calc(0.75rem + env(safe-area-inset-top));
  background: var(--primary-bg);
  border-bottom: 1px solid var(--divider);
  position: sticky;
  top: 0;
  z-index: 10;
}

.app-header-title {
  font-weight: 600;
  color: var(--accent-color);
  white-space: nowrap;
}

.collection-switcher {
  flex: 1;
  min-width: 0;

  .dropdown-trigger {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.app-header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.dropdown-user-name {
  padding: 0.4rem 1rem;
  font-weight: 600;
  color: var(--secondary-text);
}

.spinning {
  animation: sync-spin 1s linear infinite;
}

@keyframes sync-spin {
  to {
    transform: rotate(360deg);
  }
}

.app-main {
  flex: 1;
  padding: 1rem;
  padding-bottom: calc(1rem + env(safe-area-inset-bottom));
}
</style>
