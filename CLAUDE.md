# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

C5 Asset Management Evidence Tool (MVP) — a compliance evidence management system for C5 certification. It captures structured data via web forms for asset lifecycle events and sends evidence emails to an archive mailbox. Optional Jira ticket creation per event.

All documentation and UI text is in **German**.

## Architecture

### Two Tracks, Seven Forms

- **Track A — RZ-Hardware** (datacenter: Server/Storage/Switch/Firewall):
  `/rz/provision`, `/rz/retire`, `/rz/owner-confirm`
- **Track B — Admin-Endgeräte** (privileged workstations, jump hosts, break-glass):
  `/admin/provision`, `/admin/user-commitment`, `/admin/return`, `/admin/access-cleanup`

### Evidence-First Design

- Primary proof: Email archive via SMTP relay to configured mailbox
- Secondary (optional): Jira tickets, configurable per event (`none` | `optional` | `required`)
- Stateless MVP — no persistent data storage in the application

### Frontend / Backend Split

- **Frontend**: Static HTML + CSS + Vanilla JavaScript. **No frameworks** (no React/Vue/Angular), **no build toolchain** (NFR-01)
- **Backend**: PHP relay service (`backend/`) for SMTP mail sending via PHPMailer, optional Jira REST calls, and YAML config
- Configuration file (`backend/config/config.yaml`) controls SMTP, email recipients per track, and Jira rules per event. Tool refuses startup without valid config.

## Key Files

- `README.md` — Complete requirements specification (German), the source of truth for all form fields, validation rules, and acceptance criteria
- `preview.html` — Original UI prototype (kept for reference)
- `index.html` — Production overview page linking to all 7 forms
- `forms/*.html` — Seven form pages (static HTML)
- `css/style.css` — Shared stylesheet (extracted from preview.html design system)
- `js/app.js` — Shared form logic: validation, conditional required, submit, summary overlay
- `backend/src/` — PHP backend: Router, SubmitHandler, MailBuilder, MailSender, JiraClient, Logger
- `backend/config/config.yaml.example` — Config template

## Build & Run

```bash
# 1. Install PHP dependencies
cd backend && composer install

# 2. Create config from template
cp config/config.yaml.example config/config.yaml
# Edit config.yaml with real SMTP + Jira settings

# 3. Start PHP dev server (serves API at /api/*)
php -S localhost:8080 -t public/

# 4. Serve frontend (separate server or same via reverse proxy)
# For development, open index.html directly or use:
# python3 -m http.server 3000  (from project root)
```

API endpoint: `POST /api/submit/{event-type}` (JSON body)

## Design Constraints

- All form fields and validation rules are defined in README.md sections 5 (Datenanforderungen) and 4 (FR-02)
- Email subject must contain: `[C5 Evidence]`, category (`RZ`/`ADM`), event type, Asset-ID (FR-03)
- Submit is only successful if the evidence email was sent (NFR-05). For events with `jira_rules: required`, Jira ticket creation is also mandatory (FR-05)
- No silent failures — all errors must be visible to the user (NFR-05)
- Formulare and templates must be maintainable without copy-paste JS duplication (NFR-06)
- Request-ID per submission for logging (NFR-04)
