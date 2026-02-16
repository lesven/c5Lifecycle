.PHONY: help setup build up down restart logs logs-backend status clean config

COMPOSE = docker compose

help: ## Hilfe anzeigen
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

setup: config build up ## Ersteinrichtung: Config anlegen, bauen, starten

config: ## Config aus Template erzeugen (überschreibt nicht)
	@if [ ! -f backend/config/config.yaml ]; then \
		cp backend/config/config.yaml.example backend/config/config.yaml; \
		echo "backend/config/config.yaml angelegt — bitte SMTP/Jira-Daten eintragen!"; \
	else \
		echo "backend/config/config.yaml existiert bereits."; \
	fi

build: ## Docker-Images bauen
	$(COMPOSE) build

up: ## Container starten
	$(COMPOSE) up -d
	@echo ""
	@echo "C5 Evidence Tool läuft unter http://localhost:$${APP_PORT:-8080}"

down: ## Container stoppen
	$(COMPOSE) down

restart: ## Container neu starten
	$(COMPOSE) restart

logs: ## Alle Logs anzeigen (live)
	$(COMPOSE) logs -f

logs-backend: ## Nur Backend-Logs (live)
	$(COMPOSE) logs -f backend

status: ## Container-Status anzeigen
	$(COMPOSE) ps

clean: ## Container, Images und Volumes entfernen
	$(COMPOSE) down -v --rmi local
	@echo "Aufgeräumt."
