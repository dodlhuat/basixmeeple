# BasixMeeple

PWA zur Verwaltung einer privaten Brettspielsammlung inkl. Gruppen-Sharing, Spielverlauf-Tracking und Statistiken. Eigenständig von "Alle unsere Abenteuer" (AUA) – kein geteiltes Datenmodell, nur ein späterer Einweg-Export.

- [`backend/`](backend/README.md) – Laravel-API (Sanctum, MariaDB)
- [`frontend/`](frontend/README.md) – Nuxt-PWA (`@dodlhuat/basix`, Dexie.js)

## Lokale Entwicklung

```bash
cp backend/.env.example backend/.env
docker compose up
docker compose exec backend php artisan migrate
```

Backend: `http://localhost:8000` · Frontend: `http://localhost:3000`

Alternativ per `Makefile` (`make help` für alle Targets, u.a. `make up`, `make backend`, `make frontend`, `make test`).

## Deployment

Siehe [`DEPLOYMENT.md`](DEPLOYMENT.md) für das Produktions-Setup (Docker Compose auf eigenem Hetzner-VPS, Nginx + Let's Encrypt).

## Pre-commit-Hook

`.githooks/pre-commit` blockt Commits, wenn die Backend-Testsuite nicht grün ist. Git liest Hooks standardmäßig aus `.git/hooks/` (nicht versioniert), deshalb muss jeder Checkout einmalig auf das versionierte Verzeichnis umgestellt werden:

```bash
git config core.hooksPath .githooks
```

(In diesem Checkout ist der Hook bereits zusätzlich direkt unter `.git/hooks/pre-commit` installiert.)
