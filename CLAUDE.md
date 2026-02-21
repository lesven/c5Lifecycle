# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

C5 Asset Management Evidence Tool (MVP) — a compliance evidence management system for C5 certification. It captures structured data via web forms for asset lifecycle events and sends evidence emails to an archive mailbox with optional Jira ticket creation. Includes NetBox integration for asset data lookup.

All documentation and UI text is in **German**.

## Common Commands

```bash
# Docker-based development (recommended)
make setup              # Initial setup: config, build, start
make up                 # Start all containers
make down               # Stop all containers
make test               # Run PHPUnit tests in Docker
make logs-backend       # View backend logs (live)

# Local development (PHP 8.1+ required)
cd backend && composer install        # Install PHP dependencies
composer test                         # Run PHPUnit tests locally
php -S localhost:8080 -t backend/public/  # Start backend API server

# NetBox setup (requires NETBOX_URL and NETBOX_TOKEN env vars)
./scripts/netbox-setup.sh            # Configure NetBox custom fields
```

## Architecture

### Three-Layer Design

1. **Frontend Layer** — Static HTML/CSS/JS, no build process required (NFR-01)
   - Entry: `index.html` → 7 form pages in `forms/`
   - Shared logic: `js/app.js` handles validation, conditional fields, API calls
   - Styling: `css/style.css` (design system from `preview.html`)

2. **Backend Layer** — PHP 8.1+ API service
   - Router pattern: `src/Router.php` dispatches to handlers
   - Main handlers: `SubmitHandler` (form processing), `AssetLookupHandler` (NetBox)
   - Email: `MailBuilder` → `MailSender` (PHPMailer)
   - External APIs: `JiraClient`, `NetBoxClient`
   - Config-driven: YAML config controls all integrations

3. **Integration Layer** — External systems
   - NetBox: Asset data source (device lookup, custom fields)
   - Jira: Optional ticket creation per event
   - SMTP: Evidence email delivery

### Event Flow

1. User fills form → `js/app.js` validates → POST to `/api/submit/{event-type}`
2. `Router` → `SubmitHandler` → validates against `EventRegistry`
3. Builds evidence email → sends via SMTP (mandatory)
4. Creates Jira ticket if configured (optional/required per event)
5. Returns success/error to frontend

### NetBox Integration

- **Asset Lookup**: `/api/asset-lookup?asset_id={id}` fetches device data
- **Custom Fields**: Maps NetBox fields to form fields (see `CustomFieldMapper`)
- **Device Transform**: `DeviceTransformer` converts NetBox API response
- **Status Mapping**: `StatusMapper` translates NetBox status to German

## Key Design Patterns

### Configuration-Driven Behavior

All integration settings live in `backend/config/config.yaml`:
- SMTP settings, per-track email recipients
- Jira rules per event type (`none`/`optional`/`required`)
- NetBox API endpoint and credentials
- Feature flags for integrations

### Event Registry Pattern

`EventRegistry::getDefinition()` defines all 7 events with:
- Required/optional fields
- Validation rules
- Email template data
- Jira ticket mapping

### Stateless Design

No database, no sessions. Each request is independent:
- Evidence = email in archive mailbox
- Tracking = Request-ID in logs
- State = external systems (Jira, NetBox)

## Testing Strategy

```bash
# Run all tests
make test                    # Docker environment
cd backend && composer test  # Local environment

# Run specific test
vendor/bin/phpunit tests/ConfigTest.php
vendor/bin/phpunit --filter testNetBoxEnabled
```

Test coverage includes:
- Config validation and feature flags
- Event registry definitions
- NetBox transformers and mappers
- Jira client behavior
- Bootstrap and routing

## Important Constraints

- **No JavaScript frameworks** — vanilla JS only (NFR-01)
- **No build process** — direct file serving (NFR-01)
- **German UI/docs** — all user-facing text in German
- **Evidence-first** — email delivery is mandatory, never optional
- **Request tracking** — UUID per submission for audit trail (NFR-04)
- **No silent failures** — all errors must be user-visible (NFR-05)
