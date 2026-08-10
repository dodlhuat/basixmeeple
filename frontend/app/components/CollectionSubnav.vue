<script setup lang="ts">
defineProps<{ collectionId: string }>()
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
  </div>
</template>
