# Anforderungskatalog – C5 Asset Management Evidence Tool (MVP)

## 1. Ziel und Kontext

### Ziel
Entwicklung eines schlanken Asset-Management-Tools zur Unterstützung der **C5-Testierung** im Bereich Asset Lifecycle Management für:

- **RZ-Hardware** (Server/Storage/Netzwerk/Firewall)
- **Admin-Endgeräte** (Privileged Workstations, Jump Hosts, Break-Glass Devices)

Das Tool stellt für definierte Lifecycle-Ereignisse jeweils **Webformulare** bereit, sammelt strukturierte Daten und versendet diese **konsolidiert als Evidence-E-Mail** an ein konfigurierbares Archivpostfach.

Optional (konfigurierbar) kann zusätzlich ein **Jira-Ticket** erzeugt werden.

### Leitplanken
- Keine erfundenen Inhalte oder Best Practices – nur Datenerfassung und Evidence.
- **Evidence-first**: Mail-Archiv ist primärer Nachweis; Jira ist optional.

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

### FR-03 Evidence-Mail erzeugen und versenden
- Nach Submit wird eine konsolidierte Evidence-Mail erzeugt und versendet.
- Betreff enthält mindestens: `[C5 Evidence]`, Kategorie (`RZ`/`ADM`), Event-Typ, Asset-ID.
- Mailbody enthält alle erfassten Daten in standardisiertem Format.

### FR-04 Konfiguration per Datei
- Evidence-Empfänger (To/CC) sind per Config-Datei steuerbar.
- Unterscheidung mindestens nach `rz_assets` und `admin_devices`.

### FR-05 Jira optional (konfigurierbar)
- Jira-Integration kann global aktiviert/deaktiviert werden.
- Pro Event Regel: `none` | `optional` | `required`
- Bei `required` gilt Submit nur als erfolgreich, wenn Ticket erstellt wurde.

### FR-06 Erfolg/Fehler-Feedback
UI zeigt nach Submit:

- Erfolg: „Evidence-Mail versendet“ (+ optional Jira Ticket-Key)
- Fehler: klarer Hinweis (Mail/Jira fehlgeschlagen)

### FR-07 Zusammenfassung nach Submit
Nach erfolgreichem Submit wird eine Zusammenfassung der gesendeten Evidence angezeigt.

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

**Nachweisreferenz Pflicht**, wenn Data Handling ≠ „Nicht relevant“

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
- YAML oder JSON (präferiert YAML)
- Tool startet nicht ohne gültige Config

### CFG-02 Inhalte (MVP)

```yaml
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

jira_rules:
  rz_provision: "none"
  rz_retire: "optional"
  rz_owner_confirm: "none"
  admin_provision: "none"
  admin_user_commitment: "none"
  admin_return: "required"
  admin_access_cleanup: "required"



Nicht-funktionale Anforderungen (NFR)
NFR-01 Technologie-Constraint (wichtig)
* Frontend ausschließlich: HTML + CSS + Vanilla JavaScript
* Keine Frameworks (kein React/Vue/Angular)
* Keine Build-Toolchain notwendig
NFR-02 Minimaler Relay-Service erlaubt
Mailversand/Jira erfordern typischerweise einen Server-Endpunkt:
* SMTP Relay für Evidence-Mail
* Jira REST Call
* Config-Datei serverseitig lesen
UI bleibt rein HTML/CSS/JS.
NFR-03 Sicherheit
* HTTPS verpflichtend
* Keine dauerhafte Speicherung sensibler Daten im Tool (stateless MVP)
* Authentifizierung optional über Reverse Proxy/SSO statt eigener Userverwaltung
NFR-04 Logging
* Technische Logs für Mail/Jira Erfolg/Fehler
* Request-ID pro Submit
NFR-05 Robustheit
* Kein stilles Scheitern: Fehler müssen sichtbar sein
* Submit gilt nur als erfolgreich, wenn Evidence-Mail versendet wurde
NFR-06 Wartbarkeit
* Formulare und Templates sollen leicht anpassbar sein (keine Copy-Paste JS-Duplizierung)
8. Globale Akzeptanzkriterien
* Alle 7 Formulare sind verfügbar und validieren Pflichtfelder korrekt
* Jede Aktion erzeugt eine Evidence-Mail an das konfigurierte Archivpostfach
* Admin-Endgeräte werden separat behandelt (eigene Formulare + Empfänger möglich)
* Jira ist per Config deaktivierbar ohne Verlust der Evidence-Funktion
* Bei Jira-Regel required ist Ticket-Erstellung zwingend
9. Deliverables für das Entwicklungsteam
1. Statische UI (HTML/CSS/JS) mit 7 Formularseiten
2. Minimaler Relay-Service für SMTP + optional Jira
3. Mail-Templates je Formular
4. Betriebs-/Deployment-Dokumentation
5. Beispiel-Evidence-Mail je Event-Typ
