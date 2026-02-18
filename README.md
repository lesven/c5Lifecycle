# Anforderungskatalog – C5 Asset Management Evidence Tool (MVP)

## 1. Ziel und Kontext

### Ziel
Entwicklung eines schlanken Asset-Management-Tools zur Unterstützung der **C5-Testierung** im Bereich Asset Lifecycle Management für:

- **RZ-Hardware** (Server/Storage/Netzwerk/Firewall)
- **Admin-Endgeräte** (Privileged Workstations, Jump Hosts, Break-Glass Devices)

Das Tool stellt für definierte Lifecycle-Ereignisse jeweils **Webformulare** bereit, sammelt strukturierte Daten und versendet diese **konsolidiert als Evidence-E-Mail** an ein konfigurierbares Archivpostfach.

Optional (konfigurierbar) kann zusätzlich ein **Jira-Ticket** erzeugt und das **NetBox-DCIM** synchronisiert werden.

### Leitplanken
- Keine erfundenen Inhalte oder Best Practices – nur Datenerfassung und Evidence.
- **Evidence-first**: Mail-Archiv ist primärer Nachweis; Jira und NetBox sind optional.

---

## 2. Scope

### In Scope

#### Track A – RZ-Hardware
1. Inbetriebnahme RZ-Asset
2. Außerbetriebnahme RZ-Asset (inkl. Data Handling)
3. Owner-Betriebsbestätigung

#### Track B – Admin-Endgeräte (separat)
1. Inbetriebnahme Admin-Endgerät (inkl. Baseline/Hardening)
2. Verpflichtung Admin-User (zulässiger Gebrauch / sicherer Umgang / Rückgabe)
3. Rückgabe Admin-Endgerät
4. Entzug privilegierter Zugänge (IAM Cleanup)

### Optional
- Jira-Ticket-Erzeugung für definierte Events (z. B. Admin Rückgabe, Access Cleanup)
- NetBox DCIM/IPAM-Integration für Asset-Lookup und Lifecycle-Synchronisation

### Out of Scope (MVP)
- Vollwertige CMDB / automatisches Discovery
- Automatische technische Compliance-Checks (EDR/MFA live prüfen)
- Mobile App / Barcode Scan
- Eigenes Rollen-/Usermanagement im Tool

---

## 3. Nutzergruppen

- Infrastruktur-Team: RZ-Assets in Betrieb/außer Betrieb
- IT-Security / Compliance: Owner-Bestätigung, Admin-Baseline, Access Cleanup
- IT-Service: Rückgabe Admin-Endgeräte

---

## 4. Funktionale Anforderungen (FR)

### FR-01 Webformulare pro Event
Für jedes Event existiert eine eigene Seite (separates Formular):

- `/rz/provision`
- `/rz/retire`
- `/rz/owner-confirm`
- `/admin/provision`
- `/admin/user-commitment`
- `/admin/return`
- `/admin/access-cleanup`

### FR-02 Pflichtfelder und Validierung
- Pflichtfelder müssen clientseitig validiert werden.
- Submit erst möglich, wenn alle Pflichtfelder erfüllt sind.
- Serverseitige Validierung erfolgt zusätzlich (Schutz vor API-Missbrauch).
- Bedingte Pflichtfelder werden dynamisch ausgewertet (z. B. Nachweisreferenz bei Data Handling).

### FR-03 Evidence-Mail erzeugen und versenden
- Nach Submit wird eine konsolidierte Evidence-Mail erzeugt und versendet.
- Betreff enthält mindestens: `[C5 Evidence]`, Kategorie (`RZ`/`ADM`), Event-Typ, Asset-ID.
- Mailbody enthält alle erfassten Daten in standardisiertem Format.
- Die Evidence-Mail wird **vor** Jira-Ticket und NetBox-Sync gesendet – sie ist der primäre Nachweis.

### FR-04 Konfiguration per Datei
- Evidence-Empfänger (To/CC) sind per Config-Datei steuerbar.
- Unterscheidung mindestens nach `rz_assets` und `admin_devices`.

### FR-05 Jira optional (konfigurierbar)
- Jira-Integration kann global aktiviert/deaktiviert werden.
- Pro Event Regel: `none` | `optional` | `required`
- Bei `required` gilt Submit nur als erfolgreich, wenn Ticket erstellt wurde.

### FR-06 Erfolg/Fehler-Feedback
UI zeigt nach Submit:

- Erfolg: „Evidence-Mail versendet" (+ optional Jira Ticket-Key, NetBox-Status)
- Fehler: klarer Hinweis (Mail/Jira/NetBox fehlgeschlagen)
- Wenn Mail erfolgreich, Jira/NetBox aber fehlgeschlagen: Warnung mit Hinweis, dass Evidence gesichert ist.

### FR-07 Zusammenfassung nach Submit
Nach erfolgreichem Submit wird eine Zusammenfassung der gesendeten Evidence angezeigt:
- Request-ID, Jira Ticket-Key, NetBox-Syncstatus
- Tabellarische Darstellung aller übermittelten Felder mit deutschen Labels
- „Neues Formular"-Button setzt alles zurück

---

## 5. Datenanforderungen je Formular

---

## Track A – RZ-Hardware

### A1) Inbetriebnahme RZ-Asset

**Pflichtfelder**
- Asset-ID / Inventarnummer
- Gerätetyp (Server/Storage/Switch/Firewall)
- Hersteller, Modell
- Seriennummer
- Standort (RZ + Rack/Position)
- Datum Inbetriebnahme
- Asset Owner (Name/Rolle)
- Zugehöriger Service/Plattform
- Kritikalität (hoch/mittel/niedrig)
- Change-/Ticket-Referenz

**Checkbox Pflicht**
- Monitoring aktiv
- Patch/Firmware-Prozess definiert
- Zugriff nur über berechtigte Admin-Gruppen

---

### A2) Außerbetriebnahme RZ-Asset

**Pflichtfelder**
- Asset-ID
- Datum Stilllegung
- Grund (EOL/Defekt/Migration/sonstiges)
- Freigabe durch Owner
- Folgeweg (Entsorgung/Leasing-Rückgabe/Ersatzteilspender)

**Data Handling Pflicht**
- Secure Wipe
- Physische Zerstörung
- Löschzertifikat Dienstleister
- Nicht relevant

**Nachweisreferenz Pflicht**, wenn Data Handling ≠ „Nicht relevant"

---

### A3) Owner-Betriebsbestätigung

**Pflichtfelder**
- Asset-ID
- Owner (Name/Rolle)
- Datum

**Checkboxen Pflicht**
- Zweckgebundener Betrieb
- Changes nur via Change-Prozess
- Admin-Zugriff kontrolliert
- Lifecycle aktiv gemanagt

---

## Track B – Admin-Endgeräte (separat)

### B1) Inbetriebnahme Admin-Endgerät

**Pflichtfelder**
- Asset-ID
- Gerätetyp (Admin Laptop/Jump Host/Break-Glass)
- Hersteller, Modell
- Seriennummer
- Datum Inbetriebnahme
- Zugewiesener Admin-User (Name + Mail/ID)
- Security Owner (Name/Rolle)
- Zweck / Privileged Role (z. B. Firewall Admin)

**Baseline Hardening (Checkbox Pflicht)**
- Disk Encryption aktiv
- MFA aktiv
- EDR/AV aktiv
- Patch-Prozess aktiv
- Keine private Nutzung

---

### B2) Verpflichtung Admin-User

**Pflichtfelder**
- Asset-ID
- Admin-User (Name + Mail/ID)
- Datum

**Checkboxen Pflicht**
- Nur Admin-Tätigkeiten
- Kein Mail/Office/Surfing
- Keine Weitergabe von Credentials
- Verlust sofort melden
- Rückgabe bei Rollenwechsel/Austritt

---

### B3) Rückgabe Admin-Endgerät

**Pflichtfelder**
- Asset-ID
- Admin-User (Name/ID)
- Rückgabedatum
- Rückgabegrund (Rollenwechsel/Austritt/Ersatz)
- Zustand (OK/Defekt)
- Zubehör vollständig (Ja/Nein)

---

### B4) Privileged Access Cleanup (IAM Cleanup)

**Pflichtfelder**
- Asset-ID
- Admin-User (Name/ID)
- Datum

**Checkboxen Pflicht**
- Admin-Account entfernt/angepasst
- Keys/Zertifikate revoked
- Gerät wiped oder reprovisioniert

**Ticket-Referenz Pflicht**, wenn Wipe/Reprovision nicht sofort abgeschlossen

---

## 6. Konfiguration (Datei)

### CFG-01 Format
- YAML
- Tool startet nicht ohne gültige Config

### CFG-02 Inhalte

```yaml
smtp:
  host: "smtp.company.de"
  port: 587
  encryption: "tls"        # tls | ssl | "" (none)
  username: "..."
  password: "..."
  from_address: "c5-evidence@company.de"
  from_name: "C5 Evidence Tool"

evidence:
  rz_assets:
    to: "rz-evidence@company.de"
    cc: []
  admin_devices:
    to: "privileged-evidence@company.de"
    cc:
      - "it-security@company.de"

jira:
  enabled: false
  base_url: "https://jira.company.de"
  project_key: "ITOPS"
  issue_type: "Task"
  api_token: "..."

jira_rules:
  rz_provision: "none"
  rz_retire: "optional"
  rz_owner_confirm: "none"
  admin_provision: "none"
  admin_user_commitment: "none"
  admin_return: "required"
  admin_access_cleanup: "required"

netbox:
  enabled: false
  base_url: "https://netbox.company.de"
  api_token: "..."
  timeout: 10
  verify_ssl: true
  on_error: "warn"          # warn | fail
  debug: false

  sync_rules:
    rz_provision: "update_status"   # update_status | journal_only | none
    rz_retire: "update_status"
    rz_owner_confirm: "journal_only"
    admin_provision: "update_status"
    admin_user_commitment: "journal_only"
    admin_return: "update_status"
    admin_access_cleanup: "journal_only"

  create_on_provision: false
  provision_defaults:
    device_type_id: 0
    site_id: 0
    role_id: 0
```

---

## 7. Nicht-funktionale Anforderungen (NFR)

### NFR-01 Technologie-Constraint
- Frontend ausschließlich: HTML + CSS + Vanilla JavaScript
- Keine Frameworks (kein React/Vue/Angular)
- Keine Build-Toolchain notwendig

### NFR-02 Minimaler Backend-Service
Mailversand, Jira und NetBox erfordern einen Server-Endpunkt:
- SMTP-Relay für Evidence-Mail
- Jira REST Call
- NetBox REST Call
- Config-Datei serverseitig lesen
UI bleibt rein HTML/CSS/JS.

### NFR-03 Sicherheit
- HTTPS verpflichtend
- Keine dauerhafte Speicherung sensibler Daten im Tool (stateless)
- Authentifizierung optional über Reverse Proxy/SSO statt eigener Userverwaltung

### NFR-04 Logging und Request-Tracking
- Technische Logs für Mail/Jira/NetBox Erfolg/Fehler
- **UUID v4 Request-ID** pro Submit – wird durchgängig in Logs, Mails, Jira-Tickets und NetBox-Journaleinträgen mitgeführt

### NFR-05 Robustheit
- Kein stilles Scheitern: Fehler müssen sichtbar sein
- Submit gilt nur als erfolgreich, wenn Evidence-Mail versendet wurde
- NetBox- und Jira-Fehler sind separat konfigurierbar (warn/fail)

### NFR-06 Wartbarkeit
- Formulare und Templates sollen leicht anpassbar sein (keine Copy-Paste JS-Duplizierung)

---

## 8. Globale Akzeptanzkriterien

- Alle 7 Formulare sind verfügbar und validieren Pflichtfelder korrekt
- Jede Aktion erzeugt eine Evidence-Mail an das konfigurierte Archivpostfach
- Admin-Endgeräte werden separat behandelt (eigene Formulare + Empfänger möglich)
- Jira ist per Config deaktivierbar ohne Verlust der Evidence-Funktion
- Bei Jira-Regel `required` ist Ticket-Erstellung zwingend
- NetBox ist per Config deaktivierbar ohne Verlust der Evidence-Funktion

---

## 9. Deliverables

1. Statische UI (HTML/CSS/JS) mit 7 Formularseiten
2. Backend-Service (PHP) für SMTP, Jira, NetBox
3. Mail-Templates je Formular
4. NetBox Custom-Field-Setup (`scripts/netbox-setup.sh`)
5. Docker-Compose-Deployment

---

## 10. User Stories – NetBox-Integration

### US-N01 Asset-Lookup und Formular-Vorbefüllung

**Als** Infrastruktur-Mitarbeiter **möchte ich** beim Eingeben der Asset-ID im Formular die Gerätedaten automatisch aus NetBox vorbelegt bekommen, **damit** ich Zeit spare und Tippfehler vermeide.

**Akzeptanzkriterien:**
- Bei Verlassen des Feldes `Asset-ID` wird automatisch ein Lookup gegen NetBox ausgelöst.
- Folgende Felder werden vorbefüllt, sofern sie noch leer sind: Seriennummer, Hersteller, Modell, Gerätetyp, Standort, Asset Owner, Service, Kritikalität, Admin-User, Security Owner.
- Bereits ausgefüllte Felder werden nicht überschrieben.
- Wenn das Asset gefunden wurde, wird ein Badge „NetBox: {Status}" beim Asset-ID-Feld angezeigt.
- Wenn das Asset nicht gefunden wird oder NetBox nicht erreichbar ist, wird das Formular ohne Fehlermeldung weiter verwendet.
- Die Funktion ist nur aktiv, wenn `netbox.enabled: true` in der Konfiguration gesetzt ist.

---

### US-N02 Status-Synchronisation bei Lifecycle-Ereignissen

**Als** CMDB-Verantwortlicher **möchte ich** dass der NetBox-Gerätestatus automatisch auf das jeweilige Lifecycle-Ereignis aktualisiert wird, **damit** DCIM und Evidence-Archiv synchron bleiben.

**Akzeptanzkriterien:**
- Bei `rz_provision` und `admin_provision` wird der Status auf `active` gesetzt.
- Bei `rz_retire` wird der Status auf `decommissioning` gesetzt.
- Bei `admin_return` wird der Status auf `inventory` gesetzt.
- Status-Updates sind per `sync_rules` pro Event-Typ konfigurierbar (`update_status` | `journal_only` | `none`).
- Der Status-Update erfolgt nach dem Mailversand – ein Fehler bricht den Submit nur ab, wenn `on_error: fail` konfiguriert ist.

---

### US-N03 Journal-Eintrag bei jedem Lifecycle-Ereignis

**Als** Auditor **möchte ich** dass jedes Lifecycle-Ereignis als Journal-Eintrag im NetBox-Asset erfasst wird, **damit** eine vollständige, rückverfolgbare Änderungshistorie direkt im DCIM vorliegt.

**Akzeptanzkriterien:**
- Für alle Events mit `sync_rules` ≠ `none` wird ein Journal-Eintrag erstellt.
- Der Eintrag enthält: Event-Bezeichnung, Request-ID, Asset-ID, Datum, Submitter (Name aus Formular), Evidenz-Empfänger-Adresse.
- Bei `rz_retire` enthält der Journal-Eintrag zusätzlich das Data-Handling-Verfahren und die Nachweisreferenz.
- Der Journal-Typ (`success` / `info`) wird automatisch anhand des Event-Typs gesetzt.
- Journal-Einträge werden auch erstellt, wenn `sync_rules: journal_only` gesetzt ist (ohne Status-Update).

---

### US-N04 Custom-Field-Synchronisation

**Als** CMDB-Pfleger **möchte ich** dass beim Lifecycle-Ereignis relevante Metadaten aus dem Formular in die NetBox-Custom-Fields des Assets geschrieben werden, **damit** das DCIM immer den aktuellen Stand widerspiegelt.

**Akzeptanzkriterien:**
- Bei `rz_provision` werden folgende Custom-Fields geschrieben: Asset Owner, Service, Kritikalität, Datum Inbetriebnahme, Monitoring aktiv, Patch-Prozess, Zugriffssteuerung, Change-Referenz.
- Bei `rz_retire` werden geschrieben: Datum Stilllegung, Grund, Data Handling, Nachweisreferenz, Folgeweg.
- Bei `admin_provision` werden geschrieben: Admin-User, Security Owner, Zweck, Disk Encryption, MFA, EDR, Private-Use-Verbot.
- Erfolgt nur, wenn `sync_rules: update_status` konfiguriert ist.
- Felder werden als NetBox-Custom-Fields mit Präfix `cf_` gesetzt.

---

### US-N05 Evidence-Mail-Inhalt im NetBox-Kommentarfeld

**Als** Auditor **möchte ich** dass der vollständige Inhalt der Evidence-Mail im Kommentarfeld (`comments`) des NetBox-Assets gespeichert wird, **damit** die vollständige Evidenz auch direkt im DCIM nachvollziehbar ist – ohne externe Mailarchive öffnen zu müssen.

**Akzeptanzkriterien:**
- Bei jedem NetBox-Sync (unabhängig von `sync_rules`-Wert `update_status` oder `journal_only`) wird der vollständige Mailbody in das `comments`-Feld des Geräts geschrieben.
- Das `comments`-Feld enthält immer den Stand des letzten Lifecycle-Ereignisses.
- Der Kommentar wird in derselben PATCH-Anfrage wie Status und Custom-Fields gesetzt (keine zusätzliche API-Anfrage).
- Schlägt das PATCH fehl, greift die konfigurierte Fehlerbehandlung (`on_error: warn/fail`).

---

### US-N06 Automatische Device-Anlage bei Inbetriebnahme

**Als** Infrastruktur-Mitarbeiter **möchte ich** dass ein neues Asset bei der Inbetriebnahme optional automatisch in NetBox angelegt wird, wenn es dort noch nicht erfasst ist, **damit** ich NetBox nicht manuell vorpflegen muss, bevor ich das C5-Formular ausfüllen kann.

**Akzeptanzkriterien:**
- Nur aktiv, wenn `netbox.create_on_provision: true` konfiguriert ist.
- Gilt ausschließlich für `rz_provision` und `admin_provision`.
- Der Device-Typ wird zuerst per Hersteller + Modell in NetBox gesucht; bei keinem Treffer wird `provision_defaults.device_type_id` als Fallback verwendet.
- Site und Role werden aus `provision_defaults.site_id` und `provision_defaults.role_id` übernommen.
- Sind Pflicht-IDs (device_type, site, role) nicht konfiguriert, schlägt die Anlage mit einer klaren Fehlermeldung fehl.
- Nach Anlage läuft der Sync (Status, Custom Fields, Comments, Journal) regulär weiter.

---

### US-N07 Konfigurierbare Fehlerbehandlung für NetBox

**Als** Betriebsverantwortlicher **möchte ich** pro Umgebung konfigurieren können, ob ein NetBox-Fehler den Submit abbricht oder nur als Warnung behandelt wird, **damit** ich in Produktivumgebungen strenge Konsistenz erzwingen und in Testumgebungen flexibel bleiben kann.

**Akzeptanzkriterien:**
- `on_error: warn`: Submit läuft trotz NetBox-Fehler weiter. Fehler wird geloggt. API-Antwort enthält `netbox_error`-Feld. Mail ist bereits gesichert.
- `on_error: fail`: Submit wird nach erfolgtem Mailversand abgebrochen. Antwort gibt HTTP 502 zurück mit `mail_sent: true`.
- Fehlerdetails (Exception-Trace) werden nur in der API-Antwort sichtbar, wenn `netbox.debug: true` konfiguriert ist.
- Alle NetBox-Fehler werden unabhängig von `on_error` vollständig in die Log-Datei geschrieben.

---

### US-N08 Debug-Modus für NetBox-Fehler

**Als** Entwickler **möchte ich** bei NetBox-Fehlern detaillierte Stacktraces optional in der API-Antwort erhalten, **damit** ich Integrationsprobleme ohne Log-Zugriff schnell diagnostizieren kann.

**Akzeptanzkriterien:**
- Wenn `netbox.debug: true` gesetzt ist, enthält die API-Antwort bei NetBox-Fehlern das Feld `netbox_error_trace` mit dem vollständigen PHP-Stacktrace.
- Im Normalbetrieb (`debug: false`) sind keine Stacktraces in API-Antworten enthalten.

---

### US-N09 Mandanten-Auswahl aus NetBox

**Als** Infrastruktur-Mitarbeiter **möchte ich** beim Ausfüllen eines RZ-Formulars den zugehörigen Mandanten aus einer NetBox-Dropdown-Liste auswählen können, **damit** Assets korrekt einem Mandanten zugeordnet werden und diese Zuordnung sowohl in der Evidence-Mail als auch in NetBox gespeichert ist.

**Akzeptanzkriterien:**
- In den Formularen `rz_provision`, `rz_retire` und `rz_owner_confirm` erscheint ein optionales Dropdown-Feld „Mandant".
- Die Dropdown-Liste wird beim Laden der Seite automatisch über `GET /api/tenants` aus NetBox befüllt.
- Wird ein Asset per Asset-ID-Lookup aus NetBox vorbelegt, wird der zugehörige Mandant im Dropdown vorausgewählt (sofern vorhanden).
- In der Evidence-Mail erscheint der ausgewählte Mandant als lesbarer Name unter dem Label „Mandant"; die rohe `tenant_id` wird nicht angezeigt.
- Bei Submit wird die `tenant_id` per PATCH an das NetBox-Gerät gesetzt (Synchronisation).
- Bei automatischer Device-Anlage (`create_on_provision: true`) wird der Mandant ebenfalls beim POST mitgeliefert.
- Das Feld ist optional – fehlt eine Auswahl, wird kein Mandant gesetzt.
- Die Funktion ist nur aktiv, wenn `netbox.enabled: true` in der Konfiguration gesetzt ist.

---

## 11. User Stories – Technische Infrastruktur

### US-T01 Health-Endpoint für Container-Monitoring

**Als** DevOps-Verantwortlicher **möchte ich** einen Health-Endpoint, den Docker und andere Monitoring-Systeme regelmäßig abfragen können, **damit** der Betriebsstatus des Backends automatisch überwacht wird.

**Akzeptanzkriterien:**
- `GET /api/health` antwortet mit HTTP 200 und `{"status": "ok"}`.
- Der Endpoint erfordert keine Authentifizierung.
- Er wird als `healthcheck` im Docker-Compose-Service konfiguriert.

---

### US-T02 Durchgängiges Request-ID-Tracking

**Als** Auditor **möchte ich** dass jeder Submit eine eindeutige Request-ID erhält, die durch alle Systeme mitgeführt wird, **damit** ich einen einzelnen Vorgang in Logs, E-Mails, Jira und NetBox lückenlos nachverfolgen kann.

**Akzeptanzkriterien:**
- Jeder Submit generiert eine UUID v4 als Request-ID.
- Die Request-ID erscheint in: API-Antwort, Evidence-Mail (Header `X-Request-ID` + Mailbody), Jira-Ticket-Beschreibung, NetBox-Journal-Eintrag, Server-Log.
- Alle Log-Einträge eines Submits enthalten dieselbe Request-ID.

---

### US-T03 Serverseitige Validierung als zweite Sicherheitslinie

**Als** Sicherheitsverantwortlicher **möchte ich** dass alle Formulardaten zusätzlich serverseitig validiert werden, **damit** manipulierte API-Aufrufe ohne UI keine unvollständige Evidence erzeugen.

**Akzeptanzkriterien:**
- Alle Pflichtfelder werden serverseitig geprüft (Text, Booleans, bedingte Felder).
- Bei Validierungsfehler antwortet der Server mit HTTP 422 und feldgenauen Fehlermeldungen.
- Dieselben bedingten Regeln wie im Frontend gelten auch serverseitig (`rz_retire`: Nachweisreferenz, `admin_access_cleanup`: Ticket-Referenz).

---

### US-T04 Tägliche Log-Dateien mit strukturiertem Format

**Als** Betriebsverantwortlicher **möchte ich** strukturierte tägliche Log-Dateien, **damit** ich Fehler und Ereignisse zeitlich eingrenzen und maschinell auswerten kann.

**Akzeptanzkriterien:**
- Logs werden täglich rotiert: `logs/YYYY-MM-DD.log`.
- Jeder Eintrag enthält: Zeitstempel, Log-Level (INFO/WARNING/ERROR), Nachricht, JSON-Context mit Request-ID.
- Alle relevanten Ereignisse werden geloggt: Submit, Mailversand, Jira-Ticket, NetBox-API-Aufrufe, Fehler mit vollständigem Trace.
