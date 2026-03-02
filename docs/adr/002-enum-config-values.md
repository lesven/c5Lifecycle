# ADR 002 – Konfigurationswerte als Backed Enums

**Status**: Akzeptiert  
**Datum**: 2026-03-02  
**Bereich**: Domain Layer / Konfiguration

---

## Kontext

Die Anwendung kennt drei Gruppen von Konfigurationswerten mit festem Wertebereich:

| Gruppe | Werte | Verwendet in |
|---|---|---|
| Jira-Pflicht | `none`, `optional`, `required` | `EvidenceConfigInterface`, `CreateJiraTicketUseCase` |
| NetBox-Synchronisierung | `none`, `update_status`, `journal_only` | `EvidenceConfigInterface`, `SyncNetBoxUseCase` |
| NetBox-Fehlerverhalten | `warn`, `fail` | `EvidenceConfigInterface`, `SyncNetBoxUseCase` |

**Ursprünglicher Zustand:**  
Alle drei Gruppen wurden als `string` weitergereicht. Die erlaubten Werte waren nur in YAML-Kommentaren dokumentiert.  
Vergleiche sahen aus wie `if ($rule === 'required')` – ein Tippfehler wurde weder vom Typ-System noch von PHPStan erkannt.

```php
// Vorher: nicht typsicher
public function getJiraRule(string $eventType): string { ... }
if ($this->config->getJiraRule($eventType) === 'required') { ... }
```

## Entscheidung

Wir ersetzen die Magic Strings durch **PHP backed enums** im Domain Layer:

- `JiraRule: string { None = 'none'; Optional = 'optional'; Required = 'required' }`
- `NetBoxSyncRule: string { None = 'none'; UpdateStatus = 'update_status'; JournalOnly = 'journal_only' }`
- `NetBoxErrorMode: string { Warn = 'warn'; Fail = 'fail' }`

`EvidenceConfigInterface` gibt nun Enum-Instanzen zurück. `EvidenceConfig::fromYamlFile()` mappt YAML-Strings via `EnumType::from()` – ein ungültiger Wert wirft eine `ValueError` noch beim Laden der Konfiguration.

```php
// Nachher: typsicher
public function getJiraRule(string $eventType): JiraRule { ... }
if ($this->config->getJiraRule($eventType) === JiraRule::Required) { ... }
```

## Konsequenzen

**Positiv:**
- PHPStan Level 8 erkennt jeden falschen Vergleichstyp sofort.
- IDE-Autovervollständigung für alle erlaubten Werte.
- Ungültige YAML-Werte werden beim Boot erkannt, nicht erst zur Laufzeit.
- Alle erlaubten Werte sind durch den Enum-Typ selbst dokumentiert.

**Negativ / Einschränkungen:**
- Breaking Change für Aufrufer, die `string` erwartet haben (Tests mussten angepasst werden).
- `config/c5_evidence.yaml` bleibt weiterhin im String-Format; die Übersetzung geschieht in `EvidenceConfig`.

## Alternativen verworfen

- **Klassenbasierte Konstanten** (`JiraRule::REQUIRED`): Kein exhaustiveness-Check durch PHPStan-Match.
- **Validator zur Laufzeit**: Fehler erst beim ersten Zugriff sichtbar, nicht beim Anwendungsstart.
