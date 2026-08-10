<script setup lang="ts">
import { buildCollectionExport } from '~/utils/exportCollection'

const props = defineProps<{ collectionId: string }>()

const toast = useToast()
const exporting = ref(false)

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
  <div class="buttons collection-subnav">
    <NuxtLink :to="`/collections/${collectionId}/stats`" class="button button-outline button-icon" title="Statistik">
      <svg class="icon-svg"><use :href="iconHref('dashboard')" /></svg>
    </NuxtLink>
    <NuxtLink :to="`/collections/${collectionId}/loans`" class="button button-outline button-icon" title="Ausleihen">
      <svg class="icon-svg"><use :href="iconHref('handshake')" /></svg>
    </NuxtLink>
    <NuxtLink :to="`/collections/${collectionId}/wishlist`" class="button button-outline button-icon" title="Wunschliste">
      <svg class="icon-svg"><use :href="iconHref('favorite')" /></svg>
    </NuxtLink>
    <button
      type="button"
      class="button button-outline button-icon"
      title="Sammlung exportieren"
      :disabled="exporting"
      @click="onExport"
    >
      <svg class="icon-svg"><use :href="iconHref('download')" /></svg>
    </button>
  </div>
</template>
