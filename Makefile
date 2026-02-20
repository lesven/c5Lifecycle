.PHONY: help setup build up down restart logs status clean test stan lint lint-fix coverage migrate

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
	$(COMPOSER) install --no-interaction --prefer-dist

# generic helper offering arbitrary composer commands
composer: ## Führe einen beliebigen Composer-Befehl aus. Nutze ARGS="<command>"
	$(COMPOSER) $(ARGS)

logs: ## Alle Logs anzeigen (live)
	$(COMPOSE) logs -f

status: ## Container-Status anzeigen
	$(COMPOSE) ps

migrate: ## Doctrine-Migrationen ausfuehren
	$(COMPOSE) exec app php bin/console doctrine:migrations:migrate --no-interaction

test: ## PHPUnit-Tests ausfuehren
	$(PHP) vendor/bin/phpunit --colors=always

test-unit: ## Nur Unit-Tests
	$(PHP) vendor/bin/phpunit tests/Unit --colors=always

test-integration: ## Nur Integration-Tests
	$(PHP) vendor/bin/phpunit tests/Integration --colors=always

stan: ## PHPStan statische Analyse (Level 6)
	$(PHP) vendor/bin/phpstan analyse --memory-limit=256M

lint: ## PHP-CS-Fixer Dry-Run
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

lint-fix: ## PHP-CS-Fixer anwenden
	$(PHP) vendor/bin/php-cs-fixer fix

coverage: ## PHPUnit mit Coverage-Report
	$(PHP) php -dpcov.enabled=1 vendor/bin/phpunit --coverage-html=var/coverage --colors=always

clean: ## Container, Images und Volumes entfernen
	$(COMPOSE) down -v --rmi local
	@echo "Aufgeraeumt."
