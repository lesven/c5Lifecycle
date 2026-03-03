.PHONY: help setup build up down restart logs status clean test test-unit test-integration test-js test-e2e test-e2e-headed setup-e2e-user stan lint lint-fix coverage migrate deploy create-admin owner-report
#.PHONY: help setup build up down restart logs status clean test test-unit test-integration test-js stan lint lint-fix coverage migrate deploy create-admin owner-report

COMPOSE = docker compose
# compute absolute path once (avoid tricky escaping of $$(pwd))
PWD := $(shell pwd)
# helper image for running arbitrary PHP commands on the host tree
PHP = docker run --rm -v "$(PWD):/app" -w /app php:8.4-cli-alpine
# use official Composer image since the app container doesn't include composer
COMPOSER = docker run --rm -v "$(PWD):/app" -w /app composer:2

help: ## Hilfe anzeigen
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

setup: build up migrate ## Ersteinrichtung: bauen, starten, migrieren

build: ## Docker-Images bauen
	$(COMPOSE) build

up: ## Container starten
	$(COMPOSE) up -d
	@echo ""
	@echo "C5 Evidence Tool laeuft unter http://localhost:$${APP_PORT:-8080}"

up-dev: ## Container starten
	$(COMPOSE) --profile dev up -d
	@echo ""
	@echo "C5 Evidence Tool laeuft unter http://localhost:$${APP_PORT:-8080}"

down: ## Container stoppen
	$(COMPOSE) down

restart: ## Container neu starten
	$(COMPOSE) restart

composer-install: ## Composer-Abhängigkeiten installieren (setzt composer.json voraus)
	$(COMPOSER) install --no-interaction --prefer-dist --ignore-platform-req=ext-gd

composer-update: ## Composer-Abhängigkeiten aktualisieren (setzt composer.json voraus)	
	$(COMPOSER) update

# generic helper offering arbitrary composer commands
composer: ## Führe einen beliebigen Composer-Befehl aus. Nutze ARGS="<command>"
	$(COMPOSER) $(ARGS)

logs: ## Alle Logs anzeigen (live)
	$(COMPOSE) logs -f

status: ## Container-Status anzeigen
	$(COMPOSE) ps

migrate: ## Doctrine-Migrationen ausfuehren
	$(COMPOSE) exec app php bin/console doctrine:migrations:migrate --no-interaction

create-admin: ## Admin-Benutzer interaktiv anlegen
	$(COMPOSE) exec app php bin/console app:create-admin

owner-report: ## Owner-Report als Excel exportieren (owner-report.xlsx im Projektverzeichnis)
	$(COMPOSE) exec app php bin/console app:export-owner-report --output=/tmp/owner-report.xlsx
	docker cp c5lifecycle-app-1:/tmp/owner-report.xlsx ./owner-report.xlsx
	@echo "Gespeichert: $(PWD)/owner-report.xlsx"

test: ## PHPUnit-Tests ausfuehren
	$(PHP) vendor/bin/phpunit --colors=always
	make test-js

test-unit: ## Nur Unit-Tests
	$(PHP) vendor/bin/phpunit tests/Unit --colors=always

test-integration: ## Nur Integration-Tests
	$(PHP) vendor/bin/phpunit tests/Integration --colors=always

test-js: ## JavaScript-Tests (Vitest + jsdom), setzt node_modules voraus
	node_modules/.bin/vitest run

test-js-coverage: ## JavaScript-Tests mit Coverage-Report
	node_modules/.bin/vitest run --coverage

test-e2e: ## End-to-End-Tests (TestCafe, headless; lädt .env.e2e wenn vorhanden)
	@set -a; [ -f .env.e2e ] && . ./.env.e2e; set +a; \
	  node_modules/.bin/testcafe chromium:headless tests/e2e --config-file .testcaferc.json

test-e2e-headed: ## End-to-End-Tests (TestCafe, headed für Debugging)
	@set -a; [ -f .env.e2e ] && . ./.env.e2e; set +a; \
	  node_modules/.bin/testcafe chromium tests/e2e --config-file .testcaferc.json

setup-e2e-user: ## E2E-Testbenutzer anlegen (liest E-Mail/Passwort aus .env.e2e)
	@. ./.env.e2e; \
	  printf "%s\n%s\n%s\n" "$$E2E_USER_EMAIL" "E2E Testuser" "$$E2E_USER_PASSWORD" \
	  | docker compose exec -T app php bin/console app:create-admin 2>&1 || true

stan: ## PHPStan statische Analyse (Level 6)
	$(PHP) vendor/bin/phpstan analyse --memory-limit=256M

insights: ## PHP Insights Code-Qualitäts-Analyse
	$(PHP) vendor/bin/phpinsights

lint: ## PHP-CS-Fixer Dry-Run
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

lint-fix: ## PHP-CS-Fixer anwenden
	$(PHP) vendor/bin/php-cs-fixer fix

phpmd: ## PHP Mess Detector (phpmd) ausfuehren
	$(PHP) vendor/bin/phpmd src,tests text cleancode,codesize,controversial,unusedcode

coverage: ## PHPUnit mit Coverage-Report
	$(PHP) php -dpcov.enabled=1 vendor/bin/phpunit --coverage-html=var/coverage --colors=always

clean: ## Container, Images und Volumes entfernen
	$(COMPOSE) down -v --rmi local
	@echo "Aufgeraeumt."

deploy: ## Deployment auf externem Server: Code pullen, bauen, migrieren, neustarten
	@echo "⚡ Starte Deployment..."
	git pull origin main
	@echo "📦 Baue Docker-Images..."
	$(COMPOSE) build
	@echo "🚀 Starte Container..."
	$(COMPOSE) up -d
	@echo "🗄️  Führe Datenbankmigrationen aus..."
	$(COMPOSE) exec -T app php bin/console doctrine:migrations:migrate --no-interaction
	@echo "🧹 Leere Cache..."
	$(COMPOSE) exec -T app php bin/console cache:clear --env=prod
	@echo "✅ Deployment abgeschlossen!"
