# E2E-Runbook (TestCafe)

## Scope (v1)
Die E2E-Suite deckt aktuell folgende Flows mit echter Authentifizierung ab:
- Login
- `rz_provision` (Happy Path)
- `rz_retire` (Happy Path + Negativfall `data_handling_ref`)

## Voraussetzungen
1. Lokale Anwendung läuft im Docker-Setup (`make up`).
2. Login-fähiger Test-User existiert.
3. Integrationen (Jira/NetBox/Mail) sind für den gewünschten Lauf erreichbar.
4. Node-Abhängigkeiten sind installiert (`npm install`).

## Benötigte Umgebungsvariablen
```bash
export E2E_BASE_URL="http://localhost:8080"
export E2E_USER_EMAIL="user@example.org"
export E2E_USER_PASSWORD="super-secret"
```

## Testdaten/Fixtures
Fixtures liegen versioniert unter:
- `tests/fixtures/e2e/login.json`
- `tests/fixtures/e2e/rz_provision.json`
- `tests/fixtures/e2e/rz_retire.json`

Konvention:
- Asset-IDs mit `E2E-...` Präfix nutzen.
- Für reale Zielsysteme pro Lauf eindeutige IDs verwenden.

## Ausführung
Headless (Standard):
```bash
make test-e2e
```

Headed (Debug):
```bash
make test-e2e-headed
```

## Stabilitätsleitlinien
- Assertions auf fachlich stabile Zustände ausrichten (Status-Text, Pflichtfeld-Fehler, Redirect).
- Keine Screenshots/Videos als Pflichtartefakt.
- Bei Ausfällen zuerst Vorbedingungen prüfen (Login, Integrationsreichweite, verfügbare Selektor-Optionen).

## Definition of Done für neue E2E-Fälle
- Neuer Fall besitzt Fixture-Daten im Ordner `tests/fixtures/e2e/`.
- Positivfall + mindestens ein relevanter Negativfall vorhanden.
- Lauf ist lokal reproduzierbar via `make test-e2e`.
- Selektoren sind test-stabil (`id` oder `data-testid`) und im Page-Object/Helper zentralisiert.
