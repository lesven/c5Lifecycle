# NetBox-Integration – Analyse

## Uebersicht

Das C5 Evidence Tool wird um eine optionale NetBox-Integration erweitert. NetBox dient als zentrale DCIM/IPAM-Datenquelle fuer Asset-Stammdaten. Die Integration ermoeglicht:

1. **Asset-Lookup** (Read): Automatisches Vorausfuellen von Formularfeldern basierend auf NetBox-Daten
2. **Status-Sync** (Write): Aktualisierung des Device-Status in NetBox nach Evidence-Versand
3. **Journal Entries** (Write): Audit-Trail in NetBox pro C5-Event
4. **Custom-Field-Sync** (Write): Aktualisierung von Custom Fields am Device

## Architektur

```
┌─────────────┐     blur-Event     ┌──────────────┐     GET /api/dcim/devices/
│  Frontend   │ ──────────────────→│   Backend    │ ──────────────────────────→ NetBox
│  (Browser)  │     asset_id       │   (PHP)      │     ?asset_tag=...
│             │ ←──────────────────│              │ ←──────────────────────────
│  Prefill    │   JSON Response    │ AssetLookup  │     Device JSON
│  Felder     │                    │  Handler     │
└─────────────┘                    └──────────────┘

┌─────────────┐     POST /submit   ┌──────────────┐
│  Frontend   │ ──────────────────→│   Backend    │
│  (Browser)  │                    │   (PHP)      │
│             │                    │              │─── 1. Mail senden
│             │                    │              │─── 2. Jira Ticket (optional)
│             │                    │              │─── 3. NetBox Sync (optional)
│             │ ←──────────────────│              │     ├── PATCH /api/dcim/devices/{id}/
│  Feedback   │   JSON Response    │ SubmitHandler│     └── POST /api/extras/journal-entries/
└─────────────┘                    └──────────────┘
```

## Feature-Toggle

Die gesamte NetBox-Integration ist ueber `config.yaml` steuerbar:

```yaml
netbox:
  enabled: false    # Haupt-Toggle
  on_error: "warn"  # warn | fail
  sync_rules:       # Pro Event-Typ konfigurierbar
    rz_provision: "update_status"
    rz_owner_confirm: "journal_only"
```

- Bei `enabled: false` werden **keine** NetBox-API-Calls ausgefuehrt
- Das Tool startet auch komplett ohne `netbox:`-Konfigurationsblock

## Fehlerbehandlung

- NetBox-Fehler blockieren **niemals** den Evidence-Mail-Versand
- Reihenfolge im SubmitHandler: (1) Mail → (2) Jira → (3) NetBox
- Bei `on_error: warn`: Submit laeuft weiter, Warnung wird angezeigt
- Bei `on_error: fail`: Submit wird nach Mail-Versand abgebrochen (HTTP 502)

## Status-Mapping

| C5-Event          | NetBox-Status    | Sync-Regel      |
|-------------------|-----------------|-----------------|
| `rz_provision`    | `active`        | update_status   |
| `rz_retire`       | `decommissioning` | update_status |
| `rz_owner_confirm`| (kein Update)   | journal_only    |
| `admin_provision`  | `active`       | update_status   |
| `admin_user_commitment` | (kein Update) | journal_only |
| `admin_return`     | `inventory`    | update_status   |
| `admin_access_cleanup` | (kein Update) | journal_only |

## Custom Fields

Die folgenden Custom Fields werden bei entsprechenden Events synchronisiert:

### rz_provision
`cf_asset_owner`, `cf_service`, `cf_criticality`, `cf_commission_date`, `cf_monitoring_active`, `cf_patch_process`, `cf_access_controlled`, `cf_change_ref`, `cf_nutzungstyp`

### rz_retire
`cf_retire_date`, `cf_retire_reason`, `cf_data_handling`, `cf_data_handling_ref`, `cf_followup`

### admin_provision
`cf_admin_user`, `cf_security_owner`, `cf_purpose`, `cf_disk_encryption`, `cf_mfa_active`, `cf_edr_active`, `cf_no_private_use`

## Voraussetzungen

- NetBox v3.5+ mit API-Zugang
- API-Token mit Lese-/Schreibrechten auf Devices und Journal Entries
- Custom Fields und Device Roles muessen in NetBox angelegt sein (siehe `scripts/netbox-setup.sh`)
- Netzwerkfreigabe: PHP-Backend → NetBox API (HTTPS)

## Dateien

| Datei | Beschreibung |
|-------|-------------|
| `backend/src/NetBox/NetBoxClient.php` | HTTP-Client mit Token-Auth |
| `backend/src/NetBox/DeviceTransformer.php` | NetBox → C5 Feldmapping |
| `backend/src/NetBox/StatusMapper.php` | Event → NetBox-Status |
| `backend/src/NetBox/JournalBuilder.php` | Journal-Entry-Text-Builder |
| `backend/src/NetBox/CustomFieldMapper.php` | Custom-Field-Mapping pro Event |
| `backend/src/Handler/AssetLookupHandler.php` | GET /api/asset-lookup Endpoint |
| `backend/src/Config.php` | NetBox-Config-Methoden |
| `backend/src/Handler/SubmitHandler.php` | NetBox-Sync nach Mail+Jira |
| `backend/src/Router.php` | /api/asset-lookup Route |
| `js/app.js` | Frontend Prefill + Feedback |
| `css/style.css` | NetBox-Badge + Warning Styles |
| `scripts/netbox-setup.sh` | NetBox Custom Fields + Device Roles Setup |
