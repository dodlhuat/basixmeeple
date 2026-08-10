// Validated per the dataviz skill: 8-hue categorical order (fixed, never
// cycled), re-checked with scripts/validate_palette.js against this app's
// actual chart surface — basix's --secondary-background (the .card
// background charts render on), not the skill's own reference surface.
// Light: PASS (relief required — visible labels/legend, no color-only
// slots 2-3-4-5). Dark: PASS outright (all 8 clear 3:1).
const CATEGORICAL_LIGHT = [
  '#2a78d6',
  '#eb6834',
  '#1baf7a',
  '#eda100',
  '#e87ba4',
  '#008300',
  '#4a3aa7',
  '#e34948',
] as const

const CATEGORICAL_DARK = [
  '#3987e5',
  '#d95926',
  '#199e70',
  '#c98500',
  '#d55181',
  '#008300',
  '#9085e9',
  '#e66767',
] as const

function isDarkTheme(): boolean {
  if (!import.meta.client) return false
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false
}

/** Fixed hue order — always slice from the front, never pick hues out of order. */
export function categoricalPalette(): readonly string[] {
  return isDarkTheme() ? CATEGORICAL_DARK : CATEGORICAL_LIGHT
}

/** Slot 1 (blue) — the default single hue for magnitude/ranking charts with one series. */
export function singleSeriesColor(): string {
  return categoricalPalette()[0]!
}
