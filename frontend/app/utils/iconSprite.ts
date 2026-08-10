// Copied from @dodlhuat/basix by the postinstall script (see package.json)
// into public/svg-icons/icons.svg — a stable, unhashed path, since basix's
// own JS widgets (Toast etc.) default their `iconBasePath` option to
// 'svg-icons/' + a literal 'icons.svg' filename and can't resolve a
// Vite-hashed asset URL.
export const ICON_BASE_PATH = '/svg-icons/'

export function iconHref(name: string): string {
  return `${ICON_BASE_PATH}icons.svg#${name}`
}
