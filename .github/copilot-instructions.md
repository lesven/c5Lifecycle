# GitHub Copilot Instructions

This document provides guidance for GitHub Copilot when working with code in this repository.

## Project Overview

C5 Asset Management Evidence Tool (MVP) — a compliance evidence management system for C5 certification. It captures structured data via web forms for asset lifecycle events and sends evidence emails to an archive mailbox. Optional Jira ticket creation per event.

**Language**: All documentation and UI text is in **German**.

## Technology Stack

- **Frontend**: Static HTML + CSS + Vanilla JavaScript (no frameworks, no build toolchain)
- **Backend**: PHP 8.1+ with PHPMailer, Symfony YAML, and Composer
- **Infrastructure**: Docker Compose (nginx frontend + PHP backend)
- **Testing**: PHPUnit 10.5+

## Development Environment

### Prerequisites

- Docker and Docker Compose
- Make

### Critical Rule: Docker-Based Development

⚠️ **Everything runs inside Docker containers.** Do not run PHP, composer, or tests directly on the host system.

### Setup Commands

```bash
# First-time setup (config, build, start)
make setup

# Build Docker images
make build

# Start containers
make up

# Stop containers
make down

# View logs
make logs              # All logs
make logs-backend      # Backend only

# Check container status
make status
```

The application will be available at http://localhost:8080 (or the port specified in APP_PORT environment variable).

## Testing

### **MANDATORY: Always Run Tests Before Committing**

```bash
make test
```

This command:
1. Builds the test Docker image (if needed)
2. Runs PHPUnit tests inside a Docker container
3. Must pass before any code changes are committed

### Test Structure

- Tests are located in `backend/tests/`
- PHPUnit configuration: `backend/phpunit.xml`
- Test namespace: `C5\Tests\`

## Architecture

### Two Tracks, Seven Forms

- **Track A — RZ-Hardware** (datacenter hardware):
  - `/rz/provision` - New datacenter asset provisioning
  - `/rz/retire` - Asset retirement
  - `/rz/owner-confirm` - Owner confirmation

- **Track B — Admin-Endgeräte** (privileged workstations):
  - `/admin/provision` - New admin device provisioning
  - `/admin/user-commitment` - User commitment signing
  - `/admin/return` - Device return
  - `/admin/access-cleanup` - Access cleanup

### Evidence-First Design

- **Primary proof**: Email archive via SMTP relay to configured mailbox
- **Secondary** (optional): Jira tickets, configurable per event (`none` | `optional` | `required`)
- **Stateless MVP**: No persistent data storage in the application

### Directory Structure

```
.
├── index.html              # Main overview page
├── preview.html            # UI prototype (reference)
├── forms/                  # Seven form HTML files
├── css/style.css          # Shared stylesheet
├── js/app.js              # Shared form logic
├── backend/
│   ├── src/               # PHP backend code
│   │   ├── Router.php
│   │   ├── SubmitHandler.php
│   │   ├── MailBuilder.php
│   │   ├── MailSender.php
│   │   ├── JiraClient.php
│   │   └── Logger.php
│   ├── tests/             # PHPUnit tests
│   ├── config/            # YAML configuration
│   └── composer.json      # PHP dependencies
├── docker/
│   ├── Dockerfile.backend
│   ├── Dockerfile.test
│   └── nginx.conf
└── docker-compose.yml
```

## Key Design Constraints

1. **No Frontend Frameworks**: Use vanilla JavaScript only (no React, Vue, Angular)
2. **No Build Toolchain**: Static files served directly (NFR-01)
3. **Email-First Evidence**: Submit only succeeds if evidence email is sent (NFR-05)
4. **No Silent Failures**: All errors must be visible to the user (NFR-05)
5. **Maintainability**: Avoid code duplication across forms (NFR-06)
6. **Request Tracing**: Each submission must have a unique Request-ID for logging (NFR-04)

## Configuration

- Configuration file: `backend/config/config.yaml`
- Template: `backend/config/config.yaml.example`
- Controls: SMTP settings, email recipients per track, Jira rules per event
- Application refuses to start without valid configuration

## Email Format Requirements

Email subject must contain:
- `[C5 Evidence]` prefix
- Category: `RZ` or `ADM`
- Event type (e.g., `provision`, `retire`)
- Asset-ID

Example: `[C5 Evidence] RZ provision SRV-12345`

## API Endpoint

- **POST** `/api/submit/{event-type}`
- Body: JSON with form data
- Returns: JSON with success/error status and optional Jira ticket number

## Common Development Tasks

### Make Changes

1. Edit code in your IDE
2. If backend changes, containers will auto-reload (volume-mounted)
3. If frontend changes, refresh browser (volume-mounted)

### Run Tests

```bash
# Always before committing
make test

# View test output in detail
make test 2>&1 | less
```

### View Logs

```bash
# All container logs
make logs

# Backend only (for debugging PHP)
make logs-backend
```

### Rebuild After Dependency Changes

```bash
# After changing composer.json
make build
make restart
```

### Clean Up

```bash
# Remove containers, images, and volumes
make clean
```

## Documentation Reference

- `README.md` — Complete requirements specification (German), source of truth for form fields and validation rules
- `CLAUDE.md` — Additional guidance for Claude AI assistant
- `backend/config/config.yaml.example` — Configuration template with comments

## Best Practices

1. **Always run `make test` before committing**
2. **Work inside Docker** — use `docker compose exec` if you need to run commands in containers
3. **Preserve German language** in all UI text and documentation
4. **Minimal changes** — modify as few lines as possible
5. **Test in Docker** — don't rely on host environment
6. **Check logs** — use `make logs` to debug issues
7. **Validate config** — ensure `backend/config/config.yaml` exists and is valid

## Troubleshooting

### Tests Won't Run

```bash
make test-build    # Rebuild test image
make test          # Run again
```

### Application Won't Start

```bash
make down          # Stop all containers
make config        # Ensure config exists
make build         # Rebuild images
make up            # Start again
```

### Changes Not Reflected

```bash
make restart       # Restart containers to pick up changes
```

### View Container Status

```bash
make status        # Check if containers are running
docker compose ps  # Detailed container info
```
