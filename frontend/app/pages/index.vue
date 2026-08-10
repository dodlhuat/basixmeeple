<script setup lang="ts">
// The auth middleware sends unauthenticated visitors to /login; anyone
// authenticated landing on "/" belongs on their collection list.
await navigateTo('/collections')
</script>

<template>
  <div />
</template>
