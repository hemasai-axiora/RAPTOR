# Multi-Tier Automated Testing Strategy

```
Document:             TEST-STRATEGY.md
Directory:            RAPTOR-V2.2-BLUEPRINT/TESTING/
Classification:       Enterprise Quality Assurance Strategy
Target Release:       Raptor v2.2
```

---

## 1. Quality Philosophy: Shift-Left Multi-Tier Pyramid

Raptor v2.2 establishes an enterprise testing pyramid designed to catch syntax errors, static analysis defects, logic regressions, and visual bugs as early as possible in the development lifecycle.

```mermaid
flowchart TD
    E2E[Tier 4: E2E Browser & User Journeys (Playwright / Chrome DevTools MCP)] --> Integration[Tier 3: Multi-Module Integration & Workflow Tests]
    Integration --> Unit[Tier 2: Unit Logic, Statutory Payroll & Deliverability Assertions (772 Tests)]
    Unit --> Static[Tier 1: Static Analysis, PHPStan Level 5, Semgrep SAST & Syntax Checks]
```

---

## 2. Testing Tiers & Tooling

### Tier 1: Static Analysis & Security Scanning
- **PHP Syntax Linter**: `php -l` executed across all modified files.
- **PHPStan (Level 5)**: Enforces return type declarations, null safety, and dead code elimination.
- **Semgrep SAST**: Scans controllers and views for SQL injection, unsanitized XSS variables, and insecure deserialization.
- **Composer Audit**: Scans `composer.lock` against the National Vulnerability Database (NVD).

### Tier 2: Unit Logic & Rule Engines
- **Test Runner**: Native PHP CLI runner (`bin/run_all_campaign_tests.php`).
- **Database Backend**: High-speed PDO SQLite in-memory / file mock (`Database::setMockConnection($pdo)`). Eliminates external daemon dependencies for lightning-fast test cycles (<70s).
- **Core Verification Domains**:
  - Statutory Payroll (Pay Bands 1–8, EPF ceiling Rs. 15,000, ESIC ceiling Rs. 21,000, PT slabs, Gratuity, LOP proration).
  - Deliverability Engine (20 preflight compliance rules `D-001` through `D-020`).
  - RFC 8058 One-Click Unsubscribe ledger and suppression list synchronization.
  - Multi-LLM provider client connectivity (`AiService::testConnection`).

### Tier 3: Cross-Module Integration Tests
- **Event Bus Pipeline**: Validates that publishing a domain event (`LeadCreatedEvent`) correctly triggers downstream listeners without throwing unhandled exceptions.
- **Database Queue Lifecycle**: Verifies row reservation (`FOR UPDATE SKIP LOCKED`), attempt counter increments, exponential backoff, and DLQ quarantine.
- **Correlation Propagation**: Verifies that `X-Request-ID` is correctly assigned and persisted across activity logs and DLQ failure records.

### Tier 4: End-to-End Browser UI & Accessibility Tests
- **Harness**: Playwright & Chrome DevTools MCP (`tests/e2e/raptor.e2e.spec.ts`).
- **Focus Areas**:
  - Command Palette (`Ctrl + K`) open, search, and keyboard arrow selection.
  - Theme switcher toggle (dark mode ➔ light mode ➔ zero FOUT).
  - Responsive mobile drawer navigation at 375px viewport.
  - DataTables column visibility and CSV export.
  - Accessibility tree verification (WCAG 2.2 Level AA).
