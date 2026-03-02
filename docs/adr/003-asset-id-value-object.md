# ADR 003 – AssetId als Domain Value Object

**Status**: Akzeptiert  
**Datum**: 2026-03-02  
**Bereich**: Domain Layer / Value Objects

---

## Kontext

Asset-Identifikatoren (z. B. `SRV-12345`, `WS-0042`) wurden ausschließlich als `string` durch das System transportiert – identisch zur Behandlung von `requestId`, Event-Typ-Namen oder beliebigen Pflicht-Strings.

**Problem:**  
- Kein Typ-System-Schutz gegen versehentliche Vertauschung: `buildSubject($assetId, $requestId)` vs. `buildSubject($requestId, $assetId)` – PHP kompiliert beide Varianten problemlos.
- Der Fallback `'UNKNOWN'` war an drei verschiedenen Stellen dupliziert (`EvidenceSubmission::assetId()`, `SubmitEvidenceUseCase`, `JiraClient`).
- `isUnknown()`-Logik war nirgends zentralisiert.

## Entscheidung

Wir führen `AssetId` als **readonly Value Object** mit `Stringable`-Interface im Domain Layer ein:

```php
final readonly class AssetId implements \Stringable
{
    public const UNKNOWN = 'UNKNOWN';

    private function __construct(public string $value) {}

    public static function from(?string $raw): self  // null/'' → UNKNOWN
    public function isUnknown(): bool
    public function __toString(): string
}
```

**Scope:**  
- `EvidenceSubmission::assetId()` gibt `AssetId` zurück.  
- `EventRegistry::buildSubject()` akzeptiert `AssetId|string` (für rückwärtskompatible Tests).  
- `SubmissionResult::$assetId` bleibt `string` (reine Daten-DTO für den JSON-Response).  
- Infrastructure-Grenze (Doctrine-Entity `SubmissionLog`, `JiraClient`) verwendet `->value`.

## Konsequenzen

**Positiv:**
- Single source of truth für UNKNOWN-Fallback und Leerstring-Normalisierung.
- Namedtyp im Domain: PHPStan meldet Fehler bei Typ-Vertauschungen in Domain-Signaturen.
- `isUnknown()` als ausdrucksstarke Methode statt Magic-String-Vergleich.
- `Stringable` erlaubt transparente Nutzung in Format-Strings und `sprintf()`.

**Negativ / Einschränkungen:**
- Kleine Inkonsistenz: `SubmissionResult::$assetId` ist noch `string`. Ein vollständiges Durchziehen würde Doctrine-Entity-Änderungen bedingen.
- Infrastruktur-Klassen müssen explizit `.value` verwenden.
