<script setup lang="ts">
import { Chart, type ChartCurve, type ChartSeries, type ChartType } from '@dodlhuat/basix/js/chart.js'

const props = withDefaults(
  defineProps<{
    type: ChartType
    series: ChartSeries[]
    title?: string
    subtitle?: string
    height?: number
    showLegend?: boolean
    curve?: ChartCurve
  }>(),
  {
    title: undefined,
    subtitle: undefined,
    height: 260,
    showLegend: true,
    curve: 'smooth',
  },
)

const container = ref<HTMLElement | null>(null)
let chart: Chart | null = null

onMounted(() => {
  if (!container.value) return
  chart = new Chart(container.value, {
    type: props.type,
    series: props.series,
    title: props.title,
    subtitle: props.subtitle,
    height: props.height,
    showLegend: props.showLegend,
    curve: props.curve,
  })
})

onUnmounted(() => chart?.destroy())

watch(
  () => props.series,
  (series) => chart?.update(series),
  { deep: true },
)

watch(
  () => props.type,
  (type) => chart?.setType(type),
)
</script>

<template>
  <div ref="container" />
</template>
