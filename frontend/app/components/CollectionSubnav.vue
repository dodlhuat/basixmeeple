<script setup lang="ts">
import { buildCollectionExport } from '~/utils/exportCollection'

const props = defineProps<{ collectionId: string }>()

const route = useRoute()
const toast = useToast()
const exporting = ref(false)

// Presentational only — drives which tab reads as "current page" so the
// shared subnav actually reflects where you are.
const navItems = computed(() => [
  { to: `/collections/${props.collectionId}/stats`, icon: 'dashboard', label: 'Statistik' },
  { to: `/collections/${props.collectionId}/loans`, icon: 'handshake', label: 'Ausleihen' },
  { to: `/collections/${props.collectionId}/wishlist`, icon: 'favorite', label: 'Wunschliste' },
])

function isActive(to: string): boolean {
  return route.path === to
}

function slugify(name: string): string {
  return (
    name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'sammlung'
  )
}

async function onExport(): Promise<void> {
  exporting.value = true

  try {
    const data = await buildCollectionExport(props.collectionId)
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)

    const link = document.createElement('a')
    link.href = url
    link.download = `${slugify(data.collection.name)}-export.json`
    link.click()

    URL.revokeObjectURL(url)
    toast.success('Export heruntergeladen.')
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div class="collection-subnav">
    <!-- basix's underline tabs language (tabs-container/tabs-header/tabs-list/tab-item),
         styled onto real NuxtLinks rather than driven by basix's tabs.js panel-switcher —
         this is route navigation, not same-page content panels. Chosen over the
         previous pill-strip because a pill track has no room to grow: three German
         labels ("Statistik"/"Ausleihen"/"Wunschliste") read as cramped icon chips in
         a track, but as generously padded tab labels here, with a proper scroll
         affordance (basix's built-in fade + overflow-x) as the fallback if a page
         header genuinely runs out of room instead of silently hiding the labels. -->
    <nav class="tabs-container" aria-label="Sammlungsnavigation">
      <div class="tabs-header">
        <div class="tabs-list">
          <NuxtLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="tab-item"
            :class="{ active: isActive(item.to) }"
          >
            <svg class="icon-svg"><use :href="iconHref(item.icon)" /></svg>
            <span class="tab-label">{{ item.label }}</span>
          </NuxtLink>
        </div>
      </div>
    </nav>

    <!-- Export doesn't represent a page you're "currently on", so it deliberately
         sits outside the tab strip as its own outline button rather than as a
         fourth, semantically-wrong tab. -->
    <button
      type="button"
      class="button button-outline subnav-export"
      title="Sammlung exportieren"
      :disabled="exporting"
      @click="onExport"
    >
      <svg class="icon-svg" :class="{ 'is-spinning': exporting }"><use :href="iconHref('download')" /></svg>
      <span>{{ exporting ? 'Exportiert …' : 'Export' }}</span>
    </button>
  </div>
</template>

<style lang="scss" scoped>
// This whole group stays a single inline-flex item. The `.buttons` wrapper
// these pages place it in carries no layout CSS of its own in this app (it's
// a bare marker class) — so, same as the previous pill-strip build, the
// component supplies its own inline-flex + margin to sit correctly beside
// whatever primary action button (or nothing, on stats.vue) the page puts
// next to it.
.collection-subnav {
  display: inline-flex;
  align-items: center;
  gap: 0.625rem;
  flex-shrink: 1;
  min-width: 0;
  margin-right: 0.75rem;
}

.tabs-container {
  // basix defaults this to `hidden` to clip a tabs-content panel we don't
  // use here — keep it visible so the inset focus ring never gets clipped.
  overflow: visible;
  flex-shrink: 1;
  min-width: 0;
}

.tabs-header,
.tabs-list {
  min-width: 0;
}

.tab-item {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  text-decoration: none;
  // basix's push-menu.scss ships an unscoped `nav a { text-transform: uppercase;
  // letter-spacing: .03em }` rule that leaks into any <nav>, not just its own
  // widget — this component uses a real <nav>, so override explicitly.
  text-transform: none;
  letter-spacing: normal;

  .icon-svg {
    width: 1.125rem;
    height: 1.125rem;
    flex-shrink: 0;
  }
}

.tab-label {
  white-space: nowrap;
}

.subnav-export {
  flex-shrink: 0;
  gap: 0.4rem;

  .icon-svg {
    width: 1.0625rem;
    height: 1.0625rem;
  }
}

.is-spinning {
  animation: subnav-spin 0.9s linear infinite;
}

@keyframes subnav-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .is-spinning {
    animation: none;
  }
}
</style>
