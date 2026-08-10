<script setup lang="ts">
import { iconHref } from '~/utils/iconSprite'

type ModalType = 'default' | 'success' | 'error' | 'warning' | 'info'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    header?: string
    type?: ModalType
    closeable?: boolean
  }>(),
  {
    header: undefined,
    type: 'default',
    closeable: true,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

function close(): void {
  if (props.closeable) emit('update:modelValue', false)
}

function onBackgroundClick(event: MouseEvent): void {
  if (event.target === event.currentTarget) close()
}

watch(
  () => props.modelValue,
  (visible) => {
    if (import.meta.client) document.body.style.overflow = visible ? 'hidden' : ''
  },
)

onScopeDispose(() => {
  if (import.meta.client) document.body.style.overflow = ''
})
</script>

<!--
  Vue-native re-implementation of basix's Modal markup (modal.scss expects
  exactly this .modal-wrapper > .modal-background + .modal structure) rather
  than the imperative Modal JS class — that class builds its content from an
  HTML string, which would throw away Vue's reactivity for form content. See
  [[basixmeeple-project]] memory on this tradeoff.
-->
<template>
  <Teleport to="body">
    <div class="modal-wrapper" :class="{ 'is-visible': modelValue }">
      <div class="modal-background" @click="onBackgroundClick" />
      <div class="modal" :class="`modal-${type}`" role="dialog" aria-modal="true">
        <svg v-if="closeable" class="icon-svg close" aria-label="Schließen" @click="close">
          <use :href="iconHref('close')" />
        </svg>
        <div v-if="header" class="header" :class="`${type}-bg`">{{ header }}</div>
        <div class="content">
          <slot />
        </div>
        <div v-if="$slots.footer" class="footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
