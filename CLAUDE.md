# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

C5 Asset Management Evidence Tool — an enterprise compliance evidence management system for C5 certification. It captures structured data via web forms for asset lifecycle events, sends evidence emails to an archive mailbox, creates Jira tickets, and syncs device state to NetBox.

All documentation and UI text is in **German**.

## Common Commands

```bash
# Docker-based development
make setup              # Build, start, migrate
make up                 # Start all containers
make down               # Stop all containers
make test               # Run PHPUnit tests (235+ tests)
make test-unit          # Run only unit tests
make test-integration   # Run only integration tests
make stan               # PHPStan Level 8 analysis
make insights           # PHP Insights code quality analysis
make lint               # PHP-CS-Fixer dry-run
make lint-fix           # Apply code style fixes
make migrate            # Run Doctrine migrations
make coverage           # Generate coverage report

# Direct PHP (requires PHP 8.4+)
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/phpinsights
vendor/bin/php-cs-fixer fix --dry-run
```

## Architecture

### DDD Layered Architecture (Symfony 7.x)

```
src/
├── Controller/           # Symfony controllers (API + Form)
│   ├── Api/              # 7 JSON API endpoints (submit, asset-lookup, tenants, contacts,
│   │                       locations, health, field-labels)
│   └── FormController    # Twig form rendering
├── Domain/               # Pure business logic — no framework deps
│   ├── ValueObject/      # EventType, Track (enums), RequestId, EventDefinition
│   ├── Service/          # EventRegistry, StatusMapper, CustomFieldMapper,
│   │                       DeviceTransformer, JournalBuilder, EvidenceMailBuilder,
│   │                       FieldLabelRegistry
│   └── Repository/       # Interfaces only (incl. EvidenceConfigInterface)
├── Application/          # Use cases, DTOs, validators
│   ├── DTO/              # EvidenceSubmission, SubmissionResult
│   ├── UseCase/          # SubmitEvidence (pipeline), SendMail, CreateJira, SyncNetBox, LookupAsset
│   │   └── Step/         # Pipeline steps: SendMailStep, CreateJiraStep, SyncNetBoxStep, PersistLogStep
│   ├── Validator/        # EventDataValidator (declarative conditional rules)
│   ├── Message/          # Async messages (Jira, NetBox)
│   └── MessageHandler/   # Symfony Messenger handlers
├── Infrastructure/       # Framework implementations
│   ├── NetBox/           # NetBoxClient (HttpClient)
│   ├── Jira/             # JiraClient (HttpClient)
│   ├── Mail/             # EvidenceMailSender (Symfony Mailer)
│   ├── Config/           # EvidenceConfig, EventDefinitionLoader, EventRegistryFactory
│   └── Persistence/      # Doctrine entities + repositories
├── EventListener/        # RequestIdListener (UUID on every response)
└── Kernel.php
```

### Event Flow

1. User fills Twig form → `app.js` validates → POST `/api/submit/{event-type}`
2. `SubmitController` → `SubmitEvidenceUseCase` → validates via `EventDataValidator`
3. `SendEvidenceMailUseCase` → builds + sends evidence email (mandatory, synchronous)
4. `CreateJiraTicketUseCase` → creates Jira ticket if configured
5. `SyncNetBoxUseCase` → syncs device state to NetBox
6. Returns JSON response with request-id, mail/jira/netbox status

### Key Services

| Service | Purpose |
|---------|---------|
| `EventRegistry` | Defines all 7 lifecycle events (loaded from `config/event_definitions.yaml`) |
| `FieldLabelRegistry` | Single source of truth for field labels (German) |
| `EvidenceConfigInterface` | Domain interface for config access (implemented by `EvidenceConfig`) |
| `EvidenceConfig` | Readonly DTO from `config/c5_evidence.yaml` with boot-time validation |
| `EventDefinitionLoader` | Parses YAML event definitions into `EventDefinition` value objects |
| `SubmitEvidenceUseCase` | Pipeline pattern: iterates `SubmissionStepInterface[]` steps |
| `EventDataValidator` | Validates form data using declarative conditional rules from YAML |
| `NetBoxClient` | HTTP client for NetBox API (scoped Symfony HttpClient) |
| `JiraClient` | HTTP client for Jira API (scoped Symfony HttpClient) |
| `EvidenceMailSender` | Sends evidence emails via Symfony Mailer |

### API Endpoints

| Method | Path | Controller |
|--------|------|-----------|
| GET | `/` | `FormController::index` |
| GET | `/forms/{slug}` | `FormController::form` |
| POST | `/api/submit/{eventType}` | `SubmitController` |
| GET | `/api/asset-lookup` | `AssetLookupController` |
| GET | `/api/tenants` | `TenantsController` |
| GET | `/api/contacts` | `ContactsController` |
| GET | `/api/field-labels` | `FieldLabelsController` |
| GET | `/api/health` | `HealthController` |

### Async Processing

Jira ticket creation and NetBox sync can be processed asynchronously via Symfony Messenger with Doctrine transport. Mail delivery is always synchronous (evidence-first).

## Configuration

All integration settings live in `config/c5_evidence.yaml`:
- SMTP settings, per-track email recipients
- Jira rules per event type (`none`/`optional`/`required`)
- NetBox sync rules per event type
- Feature flags for integrations

Environment variables in `.env` / `.env.local`:
- `DATABASE_URL` — PostgreSQL connection
- `MAILER_DSN` — SMTP transport
- `NETBOX_URL`, `NETBOX_TOKEN` — NetBox API
- `JIRA_BASE_URL`, `JIRA_API_TOKEN` — Jira API

## Testing Strategy

```bash
vendor/bin/phpunit tests/Unit              # Domain + Application + Infrastructure
vendor/bin/phpunit tests/Integration       # Controller WebTestCase tests
vendor/bin/phpunit --filter testMethodName  # Single test method
```

235+ tests covering:
- Domain services (EventRegistry, StatusMapper, CustomFieldMapper, DeviceTransformer, JournalBuilder, EvidenceMailBuilder, FieldLabelRegistry)
- Application use cases and validators (SubmitEvidence pipeline, EventDataValidator with conditional rules)
- Pipeline steps (SendMailStep, CreateJiraStep, SyncNetBoxStep, PersistLogStep)
- Infrastructure (EvidenceConfig validation, EventDefinitionLoader, NetBoxClient, JiraClient, EvidenceMailSender — mock-based)
- Message handlers (CreateJiraTicketHandler, SyncNetBoxHandler)
- Controller integration tests (Health, Submit, Form rendering)

## Important Constraints

- **No JavaScript frameworks** — vanilla JS, modularized (`public/js/c5-*.js` + `app.js`)
- **No build process** — direct file serving
- **German UI/docs** — all user-facing text in German
- **Evidence-first** — email delivery is mandatory, never optional
- **Request tracking** — UUID per submission via `RequestIdListener`
- **No silent failures** — all errors must be user-visible
- **Domain purity** — `Domain/` layer has no Symfony/Doctrine imports
