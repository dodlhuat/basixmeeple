# BasixMeeple – Backend

Laravel-API für BasixMeeple (Sanctum-Auth, Gruppen/Collections, Spielverlauf, Statistiken, BGG-Import, Sync-Endpoint für das Offline-Frontend).

## Voraussetzungen

- PHP 8.3+, Composer
- MariaDB (lokal oder via `docker compose` im Repo-Root)

## Setup (ohne Docker)

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

Die API läuft danach unter `http://localhost:8000`.

## Setup (mit Docker)

Im Repo-Root:

```bash
cp backend/.env.example backend/.env
docker compose up
```

Danach in einer zweiten Shell die Migrationen ausführen:

```bash
docker compose exec backend php artisan migrate
```

## Tests

```bash
php artisan test
```

## Auth

Token-basierte Auth über [Laravel Sanctum](https://laravel.com/docs/sanctum) (`routes/api.php`). Kein SPA-Cookie-Flow – das Frontend schickt Bearer-Tokens.
