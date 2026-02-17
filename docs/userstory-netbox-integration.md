# User Story: NetBox als Asset-Datenquelle integrieren

## Epic

**C5-NB: NetBox Asset Management Integration**

---

## User Story

**Als** Infrastruktur-Mitarbeiter,
**moechte ich**, dass das C5 Evidence Tool Asset-Stammdaten automatisch aus unserer NetBox-Instanz laedt und Lifecycle-Statusaenderungen dorthin zurueckschreibt,
**damit** ich Formulare nicht manuell mit bereits bekannten Daten befuellen muss und unser Asset-Register (NetBox) stets den aktuellen Lifecycle-Status widerspiegelt.

---

## Kontext

- NetBox ist das zentrale DCIM/IPAM-Tool (Open Source) und fuehrt alle RZ- und Admin-Assets
- Das C5 Evidence Tool erfasst bisher Daten rein manuell ueber Formulare
- Die Integration ist **optional** (Feature-Toggle) und darf den bestehenden Evidence-Prozess nicht brechen
- Referenz-Dokument: `docs/netbox-integration-analyse.md`

---

## Akzeptanzkriterien

### AK-1: Konfiguration

- [ ] In `config.yaml` existiert ein `netbox:`-Block mit folgenden Einstellungen:
  - `enabled` (bool) -- Feature-Toggle, Default: `false`
  - `base_url` (string) -- NetBox-URL, z.B. `https://netbox.company.de`
  - `api_token` (string) -- API-Token fuer Authentifizierung
  - `timeout` (int) -- Timeout in Sekunden, Default: `10`
  - `verify_ssl` (bool) -- SSL-Zertifikatspruefung, Default: `true`
  - `sync_rules` (map) -- Pro Event-Typ: `update_status` | `journal_only` | `none`
  - `on_error` (string) -- `warn` (Default, Submit laeuft weiter) | `fail` (Submit wird abgebrochen)
- [ ] `config.yaml.example` wird um den `netbox:`-Block mit Kommentaren ergaenzt
- [ ] Wenn `netbox.enabled: false`, wird kein einziger NetBox-API-Call ausgefuehrt
- [ ] Das Tool startet auch ohne NetBox-Konfiguration fehlerfrei (Rueckwaertskompatibilitaet)

### AK-2: Asset-Lookup (Read-Only, Frontend-Prefill)

- [ ] Neuer Backend-Endpoint: `GET /api/asset-lookup?asset_id={id}`
- [ ] Der Endpoint sucht in NetBox per `asset_tag` (1:1 Mapping zu C5 `asset_id`)
- [ ] Response-Format:
  ```json
  {
    "found": true,
    "netbox_id": 42,
    "asset_id": "SRV-2024-001",
    "serial_number": "ABC123XYZ",
    "manufacturer": "Dell",
    "model": "PowerEdge R750",
    "device_type": "Server",
    "location": "RZ-Nord / Rack A3 / U22",
    "status": "active",
    "custom_fields": {
      "asset_owner": "Team Platform",
      "service": "Kubernetes Cluster",
      "criticality": "hoch",
      "admin_user": "max.mustermann@company.de",
      "security_owner": "IT-Security Team"
    }
  }
  ```
- [ ] Bei nicht gefundenem Asset: `{"found": false}`
- [ ] Bei deaktiviertem NetBox (`enabled: false`): `{"found": false, "reason": "netbox_disabled"}`
- [ ] Bei NetBox-Fehler (Timeout, Auth, etc.): `{"found": false, "reason": "netbox_error"}` + Server-Log
- [ ] Im Frontend: Wenn der User das Feld `asset_id` verlaesst (blur-Event), wird automatisch `/api/asset-lookup` aufgerufen
- [ ] Nur **leere** Formularfelder werden vorausgefuellt -- bereits ausgefuellte Felder werden NICHT ueberschrieben
- [ ] Felder-Mapping fuer Prefill:

  | API-Response-Feld | Formularfeld(er) |
  |-------------------|-----------------|
  | `serial_number` | `#serial_number` |
  | `manufacturer` | `#manufacturer` |
  | `model` | `#model` |
  | `device_type` | `#device_type` (Select) |
  | `location` | `#location` |
  | `custom_fields.asset_owner` | `#asset_owner` |
  | `custom_fields.service` | `#service` |
  | `custom_fields.criticality` | `#criticality` (Select) |
  | `custom_fields.admin_user` | `#admin_user` |
  | `custom_fields.security_owner` | `#security_owner` |

- [ ] Visuelles Feedback nach erfolgreichem Lookup: Badge/Hinweis am `asset_id`-Feld mit NetBox-Status (z.B. "NetBox: active")
- [ ] Wenn Lookup fehlschlaegt: **kein** Fehler fuer den User -- Formular bleibt normal bedienbar

### AK-3: Status-Synchronisation (Write-Back)

- [ ] Nach erfolgreichem Evidence-Mail-Versand wird (wenn `sync_rules` != `none` fuer den Event-Typ) der NetBox-Device-Status aktualisiert
- [ ] Status-Mapping:

  | C5-Event | NetBox-Status nachher |
  |----------|----------------------|
  | `rz_provision` | `active` |
  | `rz_retire` | `decommissioning` |
  | `admin_provision` | `active` |
  | `admin_return` | `inventory` |

- [ ] Fuer Events mit `sync_rules: journal_only` (`rz_owner_confirm`, `admin_user_commitment`, `admin_access_cleanup`) wird **kein** Status-Update durchgefuehrt, nur ein Journal Entry
- [ ] Das Status-Update erfolgt via `PATCH /api/dcim/devices/{id}/`
- [ ] Die Device-ID wird ueber `asset_tag`-Suche ermittelt (Lookup im Submit-Flow)

### AK-4: Journal Entries (Audit-Trail)

- [ ] Bei **jedem** Event (sofern `sync_rules` != `none`) wird ein Journal Entry am NetBox-Device angelegt
- [ ] Endpoint: `POST /api/extras/journal-entries/`
- [ ] Journal-Entry-Format:

  | Feld | Wert |
  |------|------|
  | `assigned_object_type` | `dcim.device` |
  | `assigned_object_id` | NetBox Device-ID |
  | `kind` | `success` (bei Provision/Retire) oder `info` (bei Bestaetigungen) |
  | `comments` | Strukturierter Text, z.B.: |

  ```
  C5 Evidence: Inbetriebnahme RZ-Asset
  Request-ID: 550e8400-e29b-41d4-a716-446655440000
  Asset-ID: SRV-2024-001
  Datum: 2026-02-17
  Erfasst von: [Formulardaten]
  Evidence-Mail versendet an: rz-evidence@company.de
  ```

- [ ] Bei Ausserbetriebnahme zusaetzlich: Data-Handling-Methode und Nachweisreferenz im Journal Entry

### AK-5: Custom-Field-Sync (Write-Back)

- [ ] Bei `rz_provision` und `admin_provision` werden relevante Custom Fields am Device aktualisiert:

  | Event | Custom Fields |
  |-------|--------------|
  | `rz_provision` | `cf_asset_owner`, `cf_service`, `cf_criticality`, `cf_commission_date`, `cf_monitoring_active`, `cf_patch_process`, `cf_access_controlled`, `cf_change_ref` |
  | `rz_retire` | `cf_retire_date`, `cf_retire_reason`, `cf_data_handling`, `cf_data_handling_ref`, `cf_followup` |
  | `admin_provision` | `cf_admin_user`, `cf_security_owner`, `cf_purpose`, `cf_disk_encryption`, `cf_mfa_active`, `cf_edr_active`, `cf_no_private_use` |

- [ ] Custom-Field-Update erfolgt im selben PATCH-Request wie das Status-Update

### AK-6: Fehlerbehandlung

- [ ] NetBox-Fehler blockieren **niemals** den Evidence-Mail-Versand
- [ ] Die Reihenfolge im SubmitHandler ist immer: (1) Mail -> (2) Jira -> (3) NetBox
- [ ] Bei `on_error: warn` (Default):
  - NetBox-Fehler wird geloggt (Logger)
  - Submit-Response enthaelt `"netbox_synced": false` + `"netbox_error": "Fehlermeldung"`
  - Frontend zeigt Warnung: "Evidence-Mail versendet. NetBox-Sync fehlgeschlagen."
- [ ] Bei `on_error: fail`:
  - Submit wird abgebrochen (nach Mail-Versand), HTTP 502
  - Response enthaelt `"mail_sent": true`, `"error": "NetBox-Update fehlgeschlagen"`
- [ ] Bei Asset nicht in NetBox gefunden:
  - Wird als Warnung geloggt, kein Fehler
  - Response enthaelt `"netbox_synced": false`, `"netbox_error": "Asset nicht in NetBox gefunden"`
- [ ] Timeout: NetBox-API-Calls haben konfigurierbares Timeout (Default: 10s)

### AK-7: Erfolgs-Feedback im Frontend

- [ ] Bei erfolgreichem NetBox-Sync wird in der Erfolgs-Meldung angezeigt:
  "NetBox-Status aktualisiert: {neuer Status}" (oder "NetBox: Journal Entry erstellt")
- [ ] Bei fehlgeschlagenem NetBox-Sync:
  Warnung (gelb): "NetBox-Synchronisation fehlgeschlagen. Evidence-Mail wurde trotzdem versendet."
- [ ] In der Submit-Zusammenfassung (Overlay) wird der NetBox-Status als zusaetzliche Zeile angezeigt

---

## Technische Tasks

### Phase 1: Backend-Grundlage + Read-Only Lookup

| # | Task | Datei(en) | Schaetzung |
|---|------|-----------|-----------|
| T1.1 | `NetBoxClient.php` erstellen -- HTTP-Client mit Token-Auth, GET/PATCH/POST | `backend/src/NetBox/NetBoxClient.php` | M |
| T1.2 | Config-Klasse um `netbox`-Block erweitern | `backend/src/Config.php` | S |
| T1.3 | `config.yaml.example` um `netbox:`-Block ergaenzen | `backend/config/config.yaml.example` | S |
| T1.4 | `AssetLookupHandler.php` erstellen -- `GET /api/asset-lookup` | `backend/src/Handler/AssetLookupHandler.php` | M |
| T1.5 | Router um `/api/asset-lookup` Route erweitern | `backend/public/index.php` oder Router | S |
| T1.6 | NetBox-Response auf C5-Feldnamen mappen (Transformer) | `backend/src/NetBox/DeviceTransformer.php` | S |
| T1.7 | Frontend: `asset_id` blur-Handler mit Prefill-Logik | `js/app.js` | M |
| T1.8 | Frontend: NetBox-Badge/Status-Anzeige am Asset-ID-Feld | `js/app.js`, `css/style.css` | S |

### Phase 2: Write-Back (Status + Custom Fields + Journal)

| # | Task | Datei(en) | Schaetzung |
|---|------|-----------|-----------|
| T2.1 | Status-Mapping definieren (Event -> NetBox-Status) | `backend/src/NetBox/StatusMapper.php` | S |
| T2.2 | Journal-Entry-Builder (formatiert C5-Event-Daten als Kommentar) | `backend/src/NetBox/JournalBuilder.php` | S |
| T2.3 | `SubmitHandler.php` erweitern: NetBox-Sync nach Mail+Jira | `backend/src/Handler/SubmitHandler.php` | L |
| T2.4 | `sync_rules` Config auswerten (update_status / journal_only / none) | `backend/src/Config.php` | S |
| T2.5 | Custom-Field-Mapping pro Event definieren | `backend/src/NetBox/CustomFieldMapper.php` | M |
| T2.6 | Frontend: NetBox-Sync-Status in Erfolgs-/Warnmeldung anzeigen | `js/app.js` | S |
| T2.7 | Frontend: NetBox-Status in Zusammenfassungs-Overlay | `js/app.js` | S |

### Phase 3: Tooling + Robustheit

| # | Task | Datei(en) | Schaetzung |
|---|------|-----------|-----------|
| T3.1 | Setup-Script: NetBox Custom Fields + Choice Sets anlegen | `scripts/netbox-setup.sh` | M |
| T3.2 | Setup-Script: NetBox Device Roles anlegen | `scripts/netbox-setup.sh` | S |
| T3.3 | NetBox-Verbindungstest beim Backend-Start (wenn enabled) | `backend/src/Config.php` oder Boot-Check | S |
| T3.4 | Health-Endpoint: NetBox-Status anzeigen | `backend/src/Handler/HealthHandler.php` | S |

**Legende:** S = Small (< 1h), M = Medium (1-3h), L = Large (3-5h)

---

## Nicht-funktionale Anforderungen

| ID | Anforderung |
|----|-------------|
| NF-NB-01 | NetBox-Integration ist vollstaendig optional (Feature-Toggle). Bei `enabled: false` keine API-Calls, kein Einfluss auf bestehende Funktionalitaet. |
| NF-NB-02 | Evidence-Mail bleibt primaerer Nachweis. NetBox-Fehler duerfen den Evidence-Versand nicht blockieren (bei `on_error: warn`). |
| NF-NB-03 | Kein zusaetzliches Build-Toolchain oder Framework im Frontend (bestehende NFR-01 bleibt). |
| NF-NB-04 | NetBox-API-Token wird nur serverseitig verwendet, nie ans Frontend exponiert. |
| NF-NB-05 | API-Calls an NetBox haben konfigurierbares Timeout (Default 10s), um den Submit-Flow nicht unnoetig zu verzoegern. |
| NF-NB-06 | Alle NetBox-Interaktionen werden mit Request-ID geloggt (bestehende NFR-04). |

---

## Abgrenzung (Out of Scope)

- Automatisches Anlegen neuer Devices in NetBox (nur Lookup + Update bestehender)
- NetBox-Webhook-Empfang (C5 Tool reagiert nicht auf NetBox-Events)
- NetBox-UI-Customization (Custom Links, Dashboards)
- Sync von IP-Adressen, Interfaces oder Verkabelung
- Batch-Import oder Migration bestehender Daten

---

## Abhaengigkeiten

| Abhaengigkeit | Verantwortlich | Status |
|---------------|---------------|--------|
| NetBox-Instanz muss erreichbar sein (min. v3.5+) | Infrastruktur-Team | offen |
| NetBox API-Token mit Lese-/Schreibrechten auf Devices + Journal Entries | NetBox-Admin | offen |
| Custom Fields und Device Roles in NetBox angelegt (Phase 3 Setup-Script) | NetBox-Admin / C5-Team | offen |
| Netzwerkfreigabe: PHP-Backend -> NetBox API (HTTPS) | Netzwerk-Team | offen |

---

## NetBox Custom Fields (Voraussetzung)

Folgende Custom Fields muessen in NetBox existieren, bevor Phase 2 produktiv geht:

### Select-Felder (benoetigen Choice Sets)

| Custom Field | Choice Set | Werte |
|-------------|-----------|-------|
| `cf_criticality` | Criticality | hoch, mittel, niedrig |
| `cf_retire_reason` | Retire Reason | EOL, Defekt, Migration, Sonstiges |
| `cf_data_handling` | Data Handling | Secure Wipe, Physische Zerstoerung, Loeschzertifikat Dienstleister, Nicht relevant |
| `cf_followup` | Followup | Entsorgung, Leasing-Rueckgabe, Ersatzteilspender |

### Text-Felder

`cf_asset_owner`, `cf_service`, `cf_change_ref`, `cf_data_handling_ref`, `cf_admin_user`, `cf_security_owner`, `cf_purpose`

### Boolean-Felder

`cf_monitoring_active`, `cf_patch_process`, `cf_access_controlled`, `cf_disk_encryption`, `cf_mfa_active`, `cf_edr_active`, `cf_no_private_use`

### Date-Felder

`cf_commission_date`, `cf_retire_date`

---

## Device Roles (Voraussetzung)

| Name | Slug | Fuer Track |
|------|------|-----------|
| Server | `server` | A (RZ) |
| Storage | `storage` | A (RZ) |
| Switch | `switch` | A (RZ) |
| Firewall | `firewall` | A (RZ) |
| Admin Laptop | `admin-laptop` | B (ADM) |
| Jump Host | `jump-host` | B (ADM) |
| Break-Glass | `break-glass` | B (ADM) |

---

## Definition of Done

- [ ] Alle Akzeptanzkriterien (AK-1 bis AK-7) sind erfuellt
- [ ] `config.yaml.example` enthaelt den vollstaendigen `netbox:`-Block mit Kommentaren
- [ ] Bestehende Funktionalitaet (Mail, Jira) ist unveraendert und funktioniert auch ohne NetBox
- [ ] Alle NetBox-API-Calls sind mit Request-ID geloggt
- [ ] Fehlerfall getestet: NetBox nicht erreichbar -> Submit laeuft trotzdem durch
- [ ] Fehlerfall getestet: Asset nicht in NetBox -> Formular normal nutzbar
- [ ] Setup-Script fuer NetBox Custom Fields + Device Roles liegt im Repo
- [ ] Analyse-Dokument (`docs/netbox-integration-analyse.md`) ist aktuell
