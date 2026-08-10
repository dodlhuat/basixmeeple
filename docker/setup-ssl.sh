#!/usr/bin/env bash
# Einmalig auf dem vServer ausführen, um das Let's Encrypt-Zertifikat zu holen.
# Voraussetzung: .env.prod ist befüllt, Port 80 ist erreichbar, noch kein
# nginx-Container aktiv (der certonly --standalone-Aufruf braucht Port 80 selbst).
set -euo pipefail

if [[ ! -f .env.prod ]]; then
  echo "Fehler: .env.prod nicht gefunden"
  exit 1
fi

DOMAIN=$(grep -E '^DOMAIN=' .env.prod | cut -d= -f2 | tr -d ' "')
EMAIL=$(grep -E '^MAIL_FROM_ADDRESS=' .env.prod | cut -d= -f2 | tr -d ' "')

if [[ -z "$DOMAIN" || -z "$EMAIL" ]]; then
  echo "Fehler: DOMAIN oder MAIL_FROM_ADDRESS fehlt in .env.prod"
  exit 1
fi

echo "==> SSL-Zertifikat für $DOMAIN holen..."

mkdir -p letsencrypt

# Für den allerersten Start gibt es noch kein Zertifikat und der
# nginx-Container läuft noch nicht — daher Certbot standalone auf Port 80.
docker run --rm -it \
  -p 80:80 \
  -v "$(pwd)/letsencrypt:/etc/letsencrypt" \
  certbot/certbot certonly \
    --standalone \
    --preferred-challenges http \
    --agree-tos \
    --non-interactive \
    --email "$EMAIL" \
    -d "$DOMAIN"

echo "==> Empfohlene TLS-Parameter herunterladen..."
curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf \
  -o letsencrypt/options-ssl-nginx.conf

echo "==> Diffie-Hellman-Parameter generieren (dauert 1-2 Minuten)..."
openssl dhparam -out letsencrypt/ssl-dhparams.pem 2048

echo "✓ Zertifikat erstellt. Jetzt mit 'make prod-up' starten."
