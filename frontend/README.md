# BasixMeeple – Frontend

Nuxt 3/4-PWA für BasixMeeple. Nutzt [`@dodlhuat/basix`](https://github.com/dodlhuat/basix) als UI-Grundlage (Vanilla-CSS/TS, keine Vue-Komponenten – siehe `app/assets/css/main.scss`) und [Dexie.js](https://dexie.org/) (`app/utils/db.ts`) für den Offline-Zustand.

## Voraussetzungen

- Node 22+
- Laufendes Backend unter `http://localhost:8000` (siehe `../backend/README.md`)

## Setup (ohne Docker)

```bash
npm install
npm run dev
```

Die App läuft danach unter `http://localhost:3000`.

## Setup (mit Docker)

Im Repo-Root:

```bash
docker compose up
```

## Umgebungsvariablen

- `NUXT_PUBLIC_API_BASE` – Basis-URL des Backends (Default `http://localhost:8000`)

## PWA

Manifest und Service Worker werden über [`@vite-pwa/nuxt`](https://vite-pwa-org.netlify.app/frameworks/nuxt.html) generiert (siehe `nuxt.config.ts`). App-Icons unter `public/icons/` sind aktuell Platzhalter (einfarbig, Basix-Akzentfarbe) und müssen vor dem Launch durch echtes Artwork ersetzt werden.

## Build

```bash
npm run build
npm run preview
```
