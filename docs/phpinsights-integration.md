# PHP Insights Code Quality Integration

## Übersicht

**PHP Insights** ist ein automatisiertes Code-Quality-Review-Tool, das kontinuierlich die Architektur, Code-Standards und Best Practices des Projekts überwacht.

Dies ist ein **freiwilliges Analyse-Tool**, das:
- ✅ Lokal via `make insights` ausführbar ist
- ✅ Parallel in der CI/CD-Pipeline läuft (GitHub Actions)
- ✅ DDD-Architektur-Struktur respektiert
- ⚠️ **Nicht blockiert** – Fehler beim Insights-Scan verhindern keinen Merge

---

## Installation & Setup

### Voraussetzungen
- Docker & Docker Compose (für lokale Entwicklung)
- PHP 8.2+ (für direktes Ausführen)

### Installation
```bash
# phpinsights ist bereits in composer.json registriert
make up     # Container starten

# Konfiguration liegt in:
cat config/phpinsights.php
```

---

## Lokale Nutzung

### Scan durchführen
```bash
# Simplest: via Make
make insights

# oder direkt:
vendor/bin/phpinsights
```

### Output verstehen
Der Report gliedert sich in **4 Metriken** (0–100 %):

| Metrik | Bedeutung |
|--------|-----------|
| **Code** | Codequalität, Komplexität, Patterns |
| **Complexity** | Zyklomatische Komplexität, Länge von Methoden |
| **Architecture** | Struktur, Abhängigkeiten, Schichtenmodell |
| **Style** | PSR Standards, Formatierung, Import-Ordnung |

**Beispiel Report:**
```
86.0%  Code
83.6%  Complexity
82.4%  Architecture
92.8%  Style
```

---

## Konfiguration

Die Konfiguration liegt unter [config/phpinsights.php](../config/phpinsights.php).

### Wichtige Anpassungen nach DDD-Architektur

```php
// Domain Layer: Strikte Standards
Domain => ['strict' => true, ...]

// Application Layer: Flexible Standards
Application => ['max_complexity' => 7, ...]

// Infrastructure Layer: Pragmatische Standards
Infrastructure => ['max_methods' => 20, ...]

// Tests: Ausgenommen von einigen Rules
tests => ['exclude_complexity_checks' => true, ...]
```

### Schwellenwerte

```php
'requirements' => [
    'min-quality' => 70,       // Min. Code Quality Score
    'min-architecture' => 75,  // Min. Architecture Score
    'min-style' => 70,         // Min. Style Score
];
```

**Diese Werte sind Richtlinien, nicht erzwungen!**

---

## CI/CD Integration

### GitHub Actions
phpinsights läuft parallel zu anderen Checks in `.github/workflows/ci.yml`:

```yaml
insights:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - uses: shivammathur/setup-php@v2
    - run: composer install --no-interaction
    - run: vendor/bin/phpinsights
```

**Job-Status**: ✅ Bei Fehlern **nicht blockierend** (nur informativ)

### Reports
Insights-Reports werden **nicht archivert** (im `.gitignore` ausgeschlossen):
```gitignore
.phpinsights.json
/var/insights/
```

---

## Best Practices

### 1. Regelmäßig aufgreifen
```bash
# Vor Feature-Branches:
make insights
```

### 2. Trending verfolgen
Speichern Sie Baseline-Scores initial:
- **Baseline (2026-02-27)**: Code 86% | Complexity 83% | Architecture 82% | Style 92%

### 3. Bewusste Ausnahmen
Falls ein Insights-Warning zu locker ist → in `config/phpinsights.php` dokumentieren:

```php
MethodLengthSniff::class => [
    'maxLength' => 40,
    'exclude' => [
        'src/Domain/Service/ComplexService.php', // Begründung: MultiStep Validator
    ],
],
```

### 4. Architektur validieren
Nutze PhpInsights, um DDD-Verstöße zu erkennen:
- ✅ Domain/: nur reine Business Logic
- ✅ Application/: Use Cases, DTOs, Validator
- ✅ Infrastructure/: Frameworks, Persistence, HTTP-Clients

---

## Häufige Probleme & Lösungen

### Problem: *"Insights schlägt fehl wegen Doctrine-Entities"*
**Lösung**: Entities in den Exclude-Listen: `src/Infrastructure/Persistence/Entity`

### Problem: *"Zu viele Warnings, nicht aussagekräftig"*
**Lösung**: 
1. Justiere `requirements.min-quality` (z. B. auf 65%)
2. oder nutze `--no-interaction` Flag, um nur Fehler zu zeigen

### Problem: *"Unterscheidliche Ergebnisse lokal vs. CI"*
**Lösung**: Überprüfe PHP-Version Unterschied → `php --version`

---

## Weiterführend

- 📖 [PHP Insights Dokumentation](https://phpinsights.com/)
- 📊 [Object Calisthenics Rules](https://github.com/nunomaduro/phpinsights-src)
- 🎯 [Code-Quality-Baseline dokumentieren](https://github.com/lesven/c5Lifecycle/issues) (neues Issue)

---

## Changelog

- **2026-02-27**: Initial setup & integration
  - phpinsights v2.13.3 installiert
  - DDD-konfigurierte Rules in `config/phpinsights.php`
  - GitHub Actions Job hinzugefügt
  - Makefile-Target `make insights` erstellt
