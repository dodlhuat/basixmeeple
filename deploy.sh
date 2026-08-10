#!/usr/bin/env bash
# ./deploy.sh user@yourserver.com
# Liest DOMAIN aus dem lokalen .env.prod (für den Frontend-Build).
set -euo pipefail

SERVER="${1:-}"
if [[ -z "$SERVER" ]]; then
  echo "Usage: ./deploy.sh user@yourserver.com"
  exit 1
fi

ENV_FILE=".env.prod"
APP_DIR="/opt/basixmeeple"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Fehler: $ENV_FILE nicht gefunden. Kopiere .env.prod.example → .env.prod und fülle die Werte aus."
  exit 1
fi
DOMAIN=$(grep -E '^DOMAIN=' "$ENV_FILE" | cut -d= -f2 | tr -d ' "')
if [[ -z "$DOMAIN" ]]; then
  echo "Fehler: DOMAIN ist in $ENV_FILE nicht gesetzt."
  exit 1
fi

echo "==> Deploy auf $SERVER (Domain: $DOMAIN)"

# ── 1. Frontend lokal bauen ─────────────────────────────────────
echo "→ [1/4] Frontend bauen..."
cd frontend
npm ci --prefer-offline
NUXT_PUBLIC_API_BASE="https://${DOMAIN}/api" npm run generate
cd ..

# ── 2. Frontend auf Server übertragen ──────────────────────────
echo "→ [2/4] Frontend auf Server kopieren..."
ssh "$SERVER" "mkdir -p ${APP_DIR}/frontend-dist"
rsync -az --delete --progress \
  frontend/.output/public/ \
  "${SERVER}:${APP_DIR}/frontend-dist/"

# ── 3. .env.prod auf Server synchronisieren ────────────────────
echo "→ [3/4] .env.prod übertragen..."
rsync -az "$ENV_FILE" "${SERVER}:${APP_DIR}/.env.prod"

# ── 4. Auf dem Server deployen ─────────────────────────────────
echo "→ [4/4] Server aktualisieren..."
ssh "$SERVER" bash << REMOTE
set -euo pipefail
cd ${APP_DIR}

echo "  git pull..."
git pull origin main

echo "  Docker Build + Restart..."
docker compose -f docker-compose.prod.yml --env-file .env.prod build backend
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d

echo "  Warten auf Backend..."
for i in \$(seq 1 30); do
  docker compose -f docker-compose.prod.yml exec -T backend php artisan --version > /dev/null 2>&1 && break
  [ "\$i" -eq 30 ] && echo "Fehler: Backend nicht erreichbar nach 90s" && exit 1
  sleep 3
done

echo "  Migrationen + Cache..."
docker compose -f docker-compose.prod.yml exec -T backend php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T backend php artisan optimize

echo ""
echo "✓ Deploy abgeschlossen → https://${DOMAIN}"
REMOTE
