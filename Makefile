.PHONY: help install up down logs migrate backend frontend test typecheck

help:
	@echo "Verfuegbare Targets:"
	@echo "  make install    - Backend- und Frontend-Abhaengigkeiten installieren"
	@echo "  make up         - Backend, Frontend und MariaDB via Docker Compose starten"
	@echo "  make down       - Docker-Compose-Stack stoppen"
	@echo "  make logs       - Docker-Compose-Logs verfolgen"
	@echo "  make migrate    - Datenbankmigrationen im Backend-Container ausfuehren"
	@echo "  make backend    - Backend nativ starten (php artisan serve)"
	@echo "  make frontend   - Frontend nativ starten (npm run dev)"
	@echo "  make test       - Backend-Testsuite ausfuehren (SQLite, kein Docker noetig)"
	@echo "  make typecheck  - Frontend-TypeScript-Check ausfuehren"

install:
	cd backend && composer install
	cd frontend && npm install

up:
	docker compose up

down:
	docker compose down

logs:
	docker compose logs -f

migrate:
	docker compose exec backend php artisan migrate

backend:
	cd backend && php artisan serve

frontend:
	cd frontend && npm run dev

test:
	cd backend && php artisan test

typecheck:
	cd frontend && npm run typecheck

larastan:
	cd backend && php -d memory_limit=1G vendor/bin/phpstan analyse > phpstan-report.txt
