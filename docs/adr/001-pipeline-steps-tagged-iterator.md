# ADR 001 – Pipeline-Schritte via Symfony Service-Tags

**Status**: Akzeptiert  
**Datum**: 2026-03-02  
**Bereich**: Application Layer / Dependency Injection

---

## Kontext

`SubmitEvidenceUseCase` orchestriert die Evidenz-Einreichung in vier Schritten:  
Mail senden → Jira-Ticket anlegen → NetBox synchronisieren → Log persistieren.

**Ursprünglicher Zustand (vor Refactoring):**
- Der UseCase hatte vier konkrete Abhängigkeiten im Konstruktor  
  (`SendEvidenceMailUseCase`, `CreateJiraTicketUseCase`, `SyncNetBoxUseCase`, `SubmissionLogRepositoryInterface`).
- Die Schritt-Reihenfolge war implizit durch die `if ($result->success)` / `instanceof`-Ketten kodiert.
- Fehlerbehandlung für jeden Schritt war inline im UseCase als separate `handleStepFailure()` + `stepErrorKey()`-Methoden.

**Problem:**  
Jeder neue Schritt erforderte Änderungen am UseCase-Konstruktor, der Fehlerbehandlung und der Reihenfolgelogik – drei verschiedene Codestellen.

## Entscheidung

Wir stellen auf ein **tagged-iterator-basiertes Pipeline-Muster** um:

1. `SubmissionStepInterface` definiert `execute()`, `getStepName()`, `handleFailure()`.
2. Jede Implementierung deklariert ihre Registrierung und Priorität per PHP-Attribut:
   ```php
   #[AutoconfigureTag('c5.submission_step', ['priority' => 100])]
   ```
3. `SubmitEvidenceUseCase` empfängt nur noch ein sortiertes `iterable` aller Steps:
   ```php
   #[TaggedIterator('c5.submission_step')]
   iterable $submissionSteps,
   ```
4. Der UseCase führt die Schritte generisch aus – ohne `instanceof`-Checks oder hartcodierte Namen.

## Konsequenzen

**Positiv:**
- Neuer Schritt = neue Klasse mit Attribut, kein UseCase-Umbau nötig.
- Reihenfolge ist sichtbar und zentral (priority-Werte: 100, 50, 25, 10).
- Jeder Step trägt seine eigene Fehlerbehandlung.
- Unit-Tests für UseCase und Steps sind unabhängig.

**Negativ / Einschränkungen:**
- Symfony DI als implizite Abhängigkeit; außerhalb von Symfony muss der `$steps`-Array manuell gebaut werden (in Tests bereits so gelöst).
- Priorität-Werte sind Konvention, nicht erzwungen durch den Typen.

## Alternativen verworfen

- **Chain-of-Responsibility per explizitem `->next()`**: Höhere Kopplung zwischen Steps.
- **Event-System (Symfony EventDispatcher)**: Overkill für synchrone, sequential Pipeline.
