# Deployment

Docker-Compose-Setup auf einem eigenen Hetzner-VPS (Docker läuft in Produktion,
anders als beim Schwesterprojekt `game-database`, das direkt auf dem Server per
`git pull` + PM2 deployed wird — hier stattdessen ein eigener Nginx-Container
mit SSL-Terminierung + Let's-Encrypt via Certbot vor einem statischen
Nuxt-Build und einem PHP-FPM-Container).

| Teil | Technologie |
|---|---|
| Backend (Laravel) | PHP-FPM-Container, Code im Image gebacken |
| Frontend (Nuxt) | Statischer `nuxt generate`-Build (`ssr: false`), ausgeliefert direkt von Nginx |
| Datenbank | MariaDB-Container |
| Reverse Proxy / TLS | Nginx-Container + Certbot-Container (Let's Encrypt) |

Das komplette Repo liegt als ein Git-Checkout unter `/opt/basixmeeple` auf dem
Server — `docker-compose.prod.yml` baut das Backend-Image direkt aus diesem
Checkout, das Frontend wird **lokal** gebaut und per `rsync` hochgeladen (siehe
`deploy.sh`) statt im Container gebaut zu werden.

---

## 1. Erstmaliges Server-Setup

Voraussetzungen: ein frischer Server (separat von `game-database`s VPS, damit
Port 80/443 nicht kollidieren), eine Domain, die auf den Server zeigt, Docker +
Docker Compose Plugin installiert, `deploy` als SSH-Zugang.

```bash
# Auf dem Server
sudo mkdir -p /opt/basixmeeple && sudo chown $USER:$USER /opt/basixmeeple
cd /opt/basixmeeple
git clone <repo-url> .
cp .env.prod.example .env.prod
# .env.prod ausfuellen: DOMAIN, APP_KEY (lokal mit `php artisan key:generate --show` erzeugen),
# DB_*, MAIL_*
```

SSL-Zertifikat holen (einmalig, bevor der Nginx-Container das erste Mal läuft —
`certonly --standalone` braucht Port 80 selbst):

```bash
make prod-ssl
```

Container starten:

```bash
make prod-up
```

Danach fehlt noch der Frontend-Build (der wird nicht im Container gebaut,
sondern über `deploy.sh` von einer lokalen Maschine aus hochgeladen — siehe
Abschnitt 2). Ohne diesen Schritt liefert Nginx unter `/` einen leeren Ordner.

Migrationen laufen automatisch nicht mit — einmalig manuell:

```bash
make prod-artisan CMD="migrate --force"
```

---

## 2. Routine-Deploy

Von der lokalen Maschine aus, im Repo-Root:

```bash
./deploy.sh deploy@yourserver.com
```

Das Skript (siehe `deploy.sh` für Details):

1. Baut das Frontend lokal (`npm run generate`, `NUXT_PUBLIC_API_BASE` auf die
   Produktions-Domain gesetzt).
2. Kopiert den statischen Build per `rsync` nach `/opt/basixmeeple/frontend-dist`.
3. Kopiert `.env.prod` auf den Server.
4. Führt auf dem Server aus: `git pull`, Backend-Image neu bauen, Container
   neu starten, warten bis das Backend antwortet, Migrationen + `artisan optimize`.

Kein manueller SSH-Schritt nötig — `make prod-deploy SERVER=deploy@yourserver.com`
ruft dasselbe Skript auf.

---

## 3. Nach dem Deployment prüfen

- [ ] Backend antwortet: `curl https://yourdomain.com/up`
- [ ] Frontend lädt im Browser: `https://yourdomain.com/`
- [ ] Login funktioniert (Seed-User bzw. Einladung)
- [ ] E-Mail-Versand (Einladungsmail bei "Mitglied hinzufügen" testen)
- [ ] `docker compose -f docker-compose.prod.yml ps` zeigt alle vier Container als "healthy"/"running"

---

## 4. Zertifikatsverlängerung

Der `certbot`-Container erneuert automatisch alle 12h (`certbot renew`) —
kein manueller Eingriff nötig, solange der Container läuft. Bei Problemen:

```bash
docker compose -f docker-compose.prod.yml logs certbot
```

---

## 5. Bekannte Stolperfallen

### 5.1 Nginx startet nicht ("cannot load certificate")

Passiert, wenn `make prod-up` **vor** `make prod-ssl` ausgeführt wird — die
Nginx-Config referenziert Zertifikate, die noch nicht existieren. Erst
`make prod-ssl`, dann `make prod-up`.

### 5.2 `npm run generate` vs. `npm run build`

Anders als bei `game-database` (dort läuft der Nuxt-Server unter PM2 und
braucht `npm run build`) erwartet dieses Setup **ausschließlich** einen
statischen Build (`npm run generate`) — es gibt keinen Node-Prozess in
Produktion, Nginx liefert nur Dateien aus `frontend-dist`. `deploy.sh` macht
das automatisch richtig; nur bei manuellen Builds beachten.

### 5.3 `.env.prod` ist nicht in Git

Enthält Secrets (`APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`) und wird nie
committet (`.dockerignore` schließt es zusätzlich vom Docker-Build-Context
aus). `deploy.sh` synchronisiert die lokale Kopie bei jedem Deploy auf den
Server — die lokale `.env.prod` ist damit die Quelle der Wahrheit, nicht die
Server-Kopie.
