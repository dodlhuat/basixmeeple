// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  // Client-only SPA: all app state lives in IndexedDB (Dexie) and
  // localStorage (auth token), neither available during SSR, and the app
  // has no public/SEO-relevant content — it's entirely behind login.
  ssr: false,

  css: ['~/assets/css/main.scss'],

  modules: ['@vite-pwa/nuxt'],

  pwa: {
    registerType: 'autoUpdate',
    manifest: {
      name: 'BasixMeeple',
      short_name: 'BasixMeeple',
      description: 'Verwaltung der privaten Brettspielsammlung, Spielverlauf und Statistiken.',
      lang: 'de',
      theme_color: '#3D63DD',
      background_color: '#F9F9FB',
      display: 'standalone',
      start_url: '/',
      icons: [
        { src: 'icons/icon-192.png', sizes: '192x192', type: 'image/png' },
        { src: 'icons/icon-512.png', sizes: '512x512', type: 'image/png' },
        { src: 'icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
      ],
    },
    workbox: {
      navigateFallback: '/',
      globPatterns: ['**/*.{js,css,html,png,svg,ico,woff2}'],
    },
    devOptions: {
      enabled: true,
      type: 'module',
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
    },
  },
})
