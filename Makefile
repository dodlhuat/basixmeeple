.PHONY: help install up down logs migrate backend frontend test typecheck larastan \
        prod-up prod-down prod-logs prod-deploy prod-shell prod-artisan prod-ssl

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
	@echo "  make larastan   - Statische Analyse (PHPStan/Larastan) ausfuehren"
	@echo ""
	@echo "Produktion (siehe DEPLOYMENT.md):"
	@echo "  make prod-ssl        - Einmalig: Let's-Encrypt-Zertifikat holen"
	@echo "  make prod-up         - Prod-Container starten"
	@echo "  make prod-down       - Prod-Container stoppen"
	@echo "  make prod-logs       - Prod-Logs verfolgen"
	@echo "  make prod-deploy SERVER=user@host - Deploy ausfuehren (deploy.sh)"
	@echo "  make prod-shell      - Shell im Prod-Backend-Container"
	@echo "  make prod-artisan CMD=\"migrate:status\" - Artisan-Befehl auf Prod"

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

prod-ssl:
	./docker/setup-ssl.sh

prod-up:
	docker compose -f docker-compose.prod.yml --env-file .env.prod up -d

prod-down:
	docker compose -f docker-compose.prod.yml down

prod-logs:
	docker compose -f docker-compose.prod.yml logs -f

prod-deploy:
	./deploy.sh $(SERVER)

prod-shell:
	docker compose -f docker-compose.prod.yml exec backend sh

prod-artisan:
	docker compose -f docker-compose.prod.yml exec backend php artisan $(CMD)
