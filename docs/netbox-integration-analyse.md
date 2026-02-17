# NetBox-Integration -- Analyse und Konzept

## 1. Zusammenfassung

NetBox (Open-Source DCIM/IPAM) eignet sich als zentrale Asset-Datenquelle fuer das C5 Evidence Tool.
Die Integration ermoeglicht:
- **Asset-Lookup**: Formulare koennen per Asset-ID/Seriennummer Stammdaten aus NetBox vorab befuellen
- **Status-Synchronisation**: Lifecycle-Events (Provision/Retire) aktualisieren den NetBox-Device-Status
- **Audit-Trail**: Journal Entries in NetBox dokumentieren C5-Evidence-Events pro Device

---

## 2. Feldvergleich: C5-Formulare vs. NetBox-Datenmodell

### 2.1 Direkt abbildbare Felder (NetBox-Standardfelder)

| C5-Feld | NetBox-Feld | NetBox-Endpoint | Anmerkung |
|---------|-------------|-----------------|-----------|
| `asset_id` | `asset_tag` | `device.asset_tag` | Unique in NetBox, direkte 1:1-Zuordnung |
| `serial_number` | `serial` | `device.serial` | Herstellerseriennummer |
| `manufacturer` | `manufacturer.name` | `device.device_type.manufacturer` | Ueber DeviceType aufgeloest |
| `model` | `device_type.model` | `device.device_type.model` | Ueber DeviceType aufgeloest |
| `device_type` | `role.name` | `device.role` | Mapping: Server/Storage/Switch/Firewall -> NetBox Device Roles |
| `location` | `site.name` + `rack.name` + `position` | `device.site`, `device.rack`, `device.position` | Kombination aus Site/Rack/Position |

### 2.2 Felder die als NetBox Custom Fields angelegt werden muessen

Diese Felder existieren NICHT im NetBox-Standardmodell und muessen als Custom Fields (`/api/extras/custom-fields/`) erstellt werden:

#### Track A -- RZ-Hardware

| C5-Feld | Custom-Field-Name | Typ | Event(s) |
|---------|-------------------|-----|----------|
| `asset_owner` | `cf_asset_owner` | `text` | rz_provision, rz_owner_confirm |
| `service` | `cf_service` | `text` | rz_provision |
| `criticality` | `cf_criticality` | `select` (hoch/mittel/niedrig) | rz_provision |
| `change_ref` | `cf_change_ref` | `text` | rz_provision |
| `monitoring_active` | `cf_monitoring_active` | `boolean` | rz_provision |
| `patch_process` | `cf_patch_process` | `boolean` | rz_provision |
| `access_controlled` | `cf_access_controlled` | `boolean` | rz_provision |
| `commission_date` | `cf_commission_date` | `date` | rz_provision |
| `retire_date` | `cf_retire_date` | `date` | rz_retire |
| `retire_reason` | `cf_retire_reason` | `select` (EOL/Defekt/Migration/Sonstiges) | rz_retire |
| `data_handling` | `cf_data_handling` | `select` | rz_retire |
| `data_handling_ref` | `cf_data_handling_ref` | `text` | rz_retire |
| `followup` | `cf_followup` | `select` | rz_retire |

#### Track B -- Admin-Endgeraete

| C5-Feld | Custom-Field-Name | Typ | Event(s) |
|---------|-------------------|-----|----------|
| `admin_user` | `cf_admin_user` | `text` | admin_provision, admin_user_commitment, admin_return, admin_access_cleanup |
| `security_owner` | `cf_security_owner` | `text` | admin_provision |
| `purpose` | `cf_purpose` | `text` | admin_provision |
| `disk_encryption` | `cf_disk_encryption` | `boolean` | admin_provision |
| `mfa_active` | `cf_mfa_active` | `boolean` | admin_provision |
| `edr_active` | `cf_edr_active` | `boolean` | admin_provision |
| `no_private_use` | `cf_no_private_use` | `boolean` | admin_provision |

### 2.3 Felder die NICHT in NetBox gehoeren (Event-spezifisch, nur Evidence)

Diese Felder sind transaktional und gehoeren nicht ins Asset-Register:

| C5-Feld | Grund |
|---------|-------|
| `owner_approval` (rz_retire) | Einmalige Freigabe, kein Device-Attribut |
| `purpose_bound`, `change_process`, `admin_access_controlled`, `lifecycle_managed` (rz_owner_confirm) | Checkbox-Bestaetigungen einer Pruefung |
| `admin_tasks_only`, `no_mail_office`, `no_credential_sharing`, `report_loss`, `return_on_change` (admin_user_commitment) | Verpflichtungserklaerung |
| `return_reason`, `condition`, `accessories_complete` (admin_return) | Rueckgabe-Protokoll |
| `account_removed`, `keys_revoked`, `device_wiped`, `ticket_ref` (admin_access_cleanup) | Cleanup-Checkliste |

-> Diese Felder werden weiterhin nur im Formular erfasst und per Evidence-Mail archiviert.
   Sie koennen zusaetzlich als NetBox **Journal Entry** am Device hinterlegt werden.

### 2.4 Status-Mapping

| C5-Event | NetBox-Status vorher | NetBox-Status nachher |
|----------|---------------------|----------------------|
| `rz_provision` | `planned` / `staged` | `active` |
| `rz_retire` | `active` | `decommissioning` |
| `admin_provision` | `planned` / `inventory` | `active` |
| `admin_return` | `active` | `inventory` |
| `admin_access_cleanup` | beliebig | unveraendert (Journal Entry) |
| `rz_owner_confirm` | beliebig | unveraendert (Journal Entry) |
| `admin_user_commitment` | beliebig | unveraendert (Journal Entry) |

---

## 3. API-Anbindung -- Technisches Konzept

### 3.1 Architektur

```
Browser (Formular)
  |
  |-- (1) Asset-ID eingeben --> JS fetch an PHP-Backend
  |                                |
  |                                +--> GET /api/dcim/devices/?asset_tag={id}
  |                                |    (NetBox REST API)
  |                                |
  |-- (2) Felder vorab befuellt <--+
  |
  |-- (3) Formular ausfuellen + Submit
  |                                |
  |                                +--> POST /api/submit/{event}  (bestehend)
  |                                |    |
  |                                |    +-- Evidence-Mail senden (bestehend)
  |                                |    +-- Jira-Ticket (bestehend, optional)
  |                                |    +-- NEU: NetBox-Update (PATCH device + Journal Entry)
  |                                |
  |-- (4) Erfolg/Fehler <---------+
```

### 3.2 Neue Backend-Komponente: NetBoxClient

Pfad: `backend/src/NetBox/NetBoxClient.php`

```php
<?php
declare(strict_types=1);

namespace C5\NetBox;

class NetBoxClient
{
    private string $baseUrl;
    private string $token;
    private int $timeout;

    public function __construct(string $baseUrl, string $token, int $timeout = 10)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->timeout = $timeout;
    }

    /**
     * Suche Device per asset_tag (= C5 asset_id).
     * @return array|null  Device-Daten oder null wenn nicht gefunden
     */
    public function findDeviceByAssetTag(string $assetTag): ?array
    {
        $response = $this->get('/api/dcim/devices/', [
            'asset_tag' => $assetTag,
            'limit' => 1,
        ]);
        $results = $response['results'] ?? [];
        return $results[0] ?? null;
    }

    /**
     * Suche Device per Seriennummer.
     */
    public function findDeviceBySerial(string $serial): ?array
    {
        $response = $this->get('/api/dcim/devices/', [
            'serial' => $serial,
            'limit' => 1,
        ]);
        $results = $response['results'] ?? [];
        return $results[0] ?? null;
    }

    /**
     * Device-Status aktualisieren.
     */
    public function updateDeviceStatus(int $deviceId, string $status): array
    {
        return $this->patch("/api/dcim/devices/{$deviceId}/", [
            'status' => $status,
        ]);
    }

    /**
     * Custom Fields am Device aktualisieren.
     */
    public function updateDeviceCustomFields(int $deviceId, array $customFields): array
    {
        return $this->patch("/api/dcim/devices/{$deviceId}/", [
            'custom_fields' => $customFields,
        ]);
    }

    /**
     * Journal Entry am Device anlegen (Audit-Trail).
     */
    public function createJournalEntry(int $deviceId, string $kind, string $comments): array
    {
        return $this->post('/api/extras/journal-entries/', [
            'assigned_object_type' => 'dcim.device',
            'assigned_object_id' => $deviceId,
            'kind' => $kind,
            'comments' => $comments,
        ]);
    }

    // -- HTTP-Methoden --

    private function get(string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url);
    }

    private function patch(string $path, array $data): array
    {
        return $this->request('PATCH', $this->baseUrl . $path, $data);
    }

    private function post(string $path, array $data): array
    {
        return $this->request('POST', $this->baseUrl . $path, $data);
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", [
                    "Authorization: Token {$this->token}",
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ];

        if ($body !== null) {
            $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        $context = stream_context_create($opts);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException("NetBox API request failed: {$method} {$url}");
        }

        $statusCode = $this->extractStatusCode($http_response_header ?? []);
        $decoded = json_decode($response, true) ?? [];

        if ($statusCode >= 400) {
            throw new \RuntimeException(
                "NetBox API error {$statusCode}: " . ($decoded['detail'] ?? $response)
            );
        }

        return $decoded;
    }

    private function extractStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/[\d.]+ (\d{3})/', $header, $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }
}
```

### 3.3 Neuer API-Endpoint: Asset-Lookup

Pfad: `backend/src/Handler/AssetLookupHandler.php`

Endpoint: `GET /api/asset-lookup?asset_id={id}`

Funktion: Sucht Device in NetBox per asset_tag, gibt Stammdaten zurueck fuer Formular-Prefill.

Response-Beispiel:
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
    "criticality": "hoch"
  }
}
```

### 3.4 Konfigurations-Erweiterung (config.yaml)

```yaml
# Bestehende Konfiguration bleibt unveraendert...

netbox:
  enabled: false                          # Feature-Toggle
  base_url: "https://netbox.company.de"
  api_token: "CHANGE_ME"
  timeout: 10                             # Sekunden
  verify_ssl: true
  # Welche Events sollen NetBox aktualisieren?
  sync_rules:
    rz_provision: "update_status"         # Status -> active
    rz_retire: "update_status"            # Status -> decommissioning
    rz_owner_confirm: "journal_only"      # Nur Journal Entry
    admin_provision: "update_status"      # Status -> active
    admin_user_commitment: "journal_only" # Nur Journal Entry
    admin_return: "update_status"         # Status -> inventory
    admin_access_cleanup: "journal_only"  # Nur Journal Entry
  # Fehlerbehandlung
  on_error: "warn"                        # "warn" = Log + weiter | "fail" = Submit abbrechen
```

### 3.5 Frontend-Integration: Asset-Lookup mit Prefill

Erweiterung in `js/app.js` -- bei Verlassen des `asset_id`-Feldes:

```javascript
// Asset-ID Feld: blur-Event fuer NetBox-Lookup
const assetIdField = document.getElementById('asset_id');
if (assetIdField) {
    assetIdField.addEventListener('blur', async function() {
        const assetId = this.value.trim();
        if (!assetId) return;

        try {
            const resp = await fetch(`/api/asset-lookup?asset_id=${encodeURIComponent(assetId)}`);
            const data = await resp.json();
            if (!data.found) return;

            // Nur leere Felder befuellen (User-Eingaben nicht ueberschreiben)
            const prefillMap = {
                'serial_number': data.serial_number,
                'manufacturer': data.manufacturer,
                'model': data.model,
                'device_type': data.device_type,
                'location': data.location,
                'asset_owner': data.custom_fields?.asset_owner,
                'service': data.custom_fields?.service,
                'criticality': data.custom_fields?.criticality,
                'admin_user': data.custom_fields?.admin_user,
                'security_owner': data.custom_fields?.security_owner,
            };

            for (const [fieldId, value] of Object.entries(prefillMap)) {
                if (!value) continue;
                const field = document.getElementById(fieldId);
                if (field && !field.value) {
                    field.value = value;
                    field.dispatchEvent(new Event('change'));
                }
            }

            // Visuelles Feedback
            showNetBoxBadge(assetId, data.status);
        } catch (e) {
            console.warn('NetBox-Lookup fehlgeschlagen:', e);
            // Kein Fehler fuer den User -- NetBox ist optional
        }
    });
}
```

---

## 4. Erforderliche NetBox-Konfiguration

### 4.1 Device Roles anlegen

```
POST /api/dcim/device-roles/
```

| Name | Slug | Beschreibung |
|------|------|-------------|
| Server | `server` | RZ-Server |
| Storage | `storage` | Storage-Systeme |
| Switch | `switch` | Netzwerk-Switches |
| Firewall | `firewall` | Firewalls |
| Admin Laptop | `admin-laptop` | Privilegierte Workstations |
| Jump Host | `jump-host` | Jump-Server |
| Break-Glass | `break-glass` | Break-Glass-Systeme |

### 4.2 Custom Fields anlegen

Setup-Script (einmalig via API oder NetBox-UI):

```bash
TOKEN="your-netbox-token"
NETBOX="https://netbox.company.de"

# Beispiel: Criticality als Select-Custom-Field
# 1. Choice Set anlegen
curl -s -X POST "$NETBOX/api/extras/custom-field-choice-sets/" \
  -H "Authorization: Token $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Criticality",
    "extra_choices": [
      ["hoch", "Hoch"],
      ["mittel", "Mittel"],
      ["niedrig", "Niedrig"]
    ]
  }'

# 2. Custom Field mit Choice Set anlegen
curl -s -X POST "$NETBOX/api/extras/custom-fields/" \
  -H "Authorization: Token $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "object_types": ["dcim.device"],
    "name": "cf_criticality",
    "label": "Kritikalitaet",
    "type": "select",
    "choice_set": 1,
    "required": false
  }'

# Weitere Custom Fields analog...
```

### 4.3 Optionale Custom Statuses

```python
# In NetBox configuration.py
FIELD_CHOICES = {
    'dcim.Device.status+': (
        ('decommissioned', 'Decommissioned', 'brown'),
    )
}
```

---

## 5. Integrations-Ablauf pro Event

### 5.1 rz_provision (Inbetriebnahme)

```
1. User gibt asset_id ein
2. JS: GET /api/asset-lookup?asset_id=SRV-001
3. Backend: GET netbox/api/dcim/devices/?asset_tag=SRV-001
4. Formular wird mit NetBox-Daten vorausgefuellt
5. User ergaenzt fehlende Felder, submit
6. Backend:
   a) Evidence-Mail senden (bestehend)
   b) Jira-Ticket (wenn konfiguriert)
   c) NetBox: PATCH /api/dcim/devices/42/ {status: "active", custom_fields: {...}}
   d) NetBox: POST /api/extras/journal-entries/ {kind: "success", comments: "C5 Evidence: Inbetriebnahme..."}
7. Response an Frontend
```

### 5.2 rz_retire (Ausserbetriebnahme)

```
1. Asset-Lookup (wie oben)
2. Submit:
   a) Evidence-Mail
   b) NetBox: PATCH {status: "decommissioning", custom_fields: {cf_retire_date, cf_retire_reason, ...}}
   c) NetBox: Journal Entry mit Data-Handling-Nachweis
```

### 5.3 Bestaetigungs-Events (owner_confirm, user_commitment, access_cleanup)

```
1. Asset-Lookup
2. Submit:
   a) Evidence-Mail
   b) NetBox: Nur Journal Entry (kein Status-Update)
      kind: "info", comments: Zusammenfassung der Bestaetigungen
```

---

## 6. Fehlerbehandlung

### Grundsatz: NetBox ist NICHT kritisch fuer Evidence

Die Evidence-Mail bleibt der primaere Nachweis. NetBox-Fehler duerfen den
Evidence-Prozess nicht blockieren (konfigurierbar via `on_error`):

| `on_error` | Verhalten |
|------------|-----------|
| `warn` (Default) | NetBox-Fehler wird geloggt, Submit ist trotzdem erfolgreich |
| `fail` | NetBox-Fehler bricht Submit ab (nur wenn NetBox zwingend sein soll) |

### Fehlerfaelle

| Szenario | Behandlung |
|----------|-----------|
| NetBox nicht erreichbar | Lookup liefert leeres Ergebnis, Submit laeuft ohne NetBox-Update |
| Asset nicht in NetBox gefunden | Lookup: `{found: false}`, Formular manuell ausfuellen |
| NetBox-Update schlaegt fehl | Je nach `on_error`: warn (weiter) oder fail (abbruch nach Mail) |
| Ungueltige Credentials | Fehler-Log, kein NetBox-Update |

---

## 7. Implementierungs-Reihenfolge

### Phase 1: Read-Only Lookup (geringes Risiko)
- [ ] `NetBoxClient.php` mit `findDeviceByAssetTag()`
- [ ] `AssetLookupHandler.php` (neuer GET-Endpoint)
- [ ] Config-Erweiterung (`netbox.enabled`, `base_url`, `api_token`)
- [ ] Frontend: Prefill bei asset_id blur
- [ ] Router-Erweiterung fuer `/api/asset-lookup`

### Phase 2: Write-Back (Status + Custom Fields)
- [ ] Status-Mapping im NetBoxClient
- [ ] Integration in SubmitHandler nach Evidence-Mail
- [ ] Journal Entries fuer alle Events
- [ ] `sync_rules` Config pro Event

### Phase 3: Setup-Tooling
- [ ] Setup-Script fuer NetBox Custom Fields
- [ ] Validierung der NetBox-Verbindung beim Startup
- [ ] NetBox-Status im Health-Endpoint

---

## 8. Zusammenfassung fehlende Felder

Von den ~50 Formularfeldern im C5 Evidence Tool:

| Kategorie | Anzahl | NetBox-Abbildung |
|-----------|--------|-----------------|
| Direkt in NetBox vorhanden | 6 | `asset_tag`, `serial`, `manufacturer`, `model`, `role`, `site/rack/position` |
| Als Custom Fields noetig | ~13 | `cf_asset_owner`, `cf_service`, `cf_criticality`, `cf_admin_user`, etc. |
| Transaktionale Event-Daten | ~31 | Nur Evidence-Mail + Journal Entry (nicht als Device-Attribut) |

NetBox deckt die **Stammdaten** gut ab. Die **Compliance-Checkboxen** und **Event-Protokolldaten** gehoeren nicht ins Asset-Register, sondern in den Evidence-Nachweis (Mail) und optional als Journal Entry in NetBox.
