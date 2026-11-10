# Seven-Stage Enterprise Quality Gates

```
Document:             QUALITY-GATES.md
Directory:            RAPTOR-V2.2-BLUEPRINT/TESTING/
Classification:       Release Verification & Quality Assurance Gates
Target Release:       Raptor v2.2
```

---

## 1. Quality Gates Pipeline

Before any code can be merged from `feature/*` to `develop`, or tagged from `develop` to `main`, it must pass all **Seven Automated Quality Gates**. A failure at any gate halts the pipeline immediately.

```mermaid
flowchart LR
    G1[Gate 1: Syntax & Lint] --> G2[Gate 2: Static Analysis]
    G2 --> G3[Gate 3: Unit & Regression]
    G3 --> G4[Gate 4: Security Scan]
    G4 --> G5[Gate 5: UI & Accessibility]
    G5 --> G6[Gate 6: E2E User Journeys]
    G6 --> G7[Gate 7: Release Readiness]
    G7 --> Deploy[Production Deploy]
```

---

## 2. Gate Criteria & Enforcement Rules

| Gate | Tooling / Command | Success Criteria | Failure Action |
|---|---|---|---|
| **Gate 1: Syntax & Formatting** | `php -l`, Prettier check | 0 PHP syntax errors, valid JSON/CSS syntax across all modified files. | Block build. Report file and line error. |
| **Gate 2: Static Analysis** | `phpstan analyse -c phpstan.neon` | 0 errors at Level 5. No missing types, dead methods, or undeclared properties. | Block merge. Developer/AI must type-hint or fix method. |
| **Gate 3: Unit & Regression** | `php bin/run_all_campaign_tests.php` | 100% pass rate: **All 772 baseline assertions must pass** + all new tests pass. | Block merge. Zero tolerance for regression. |
| **Gate 4: Security Verification** | `bin/raptor test:security`, `composer audit` | 0 High/Critical OWASP findings. 0 vulnerable Composer packages. All CSRF/RBAC tests pass. | Immediate P0 Security Block. Requires SecOps review. |
| **Gate 5: UI & Accessibility** | Chrome DevTools MCP / Axe Core | 0 WCAG 2.2 AA violations. 0 contrast ratio failures (< 4.5:1). Valid focus indicators. | Return to designer/frontend developer. |
| **Gate 6: E2E User Journeys** | Playwright (`npm test`) | 10 critical user journeys succeed across Desktop (1280px) and Mobile (375px). | Block release candidate. |
| **Gate 7: Release Readiness** | `bin/raptor health:check`, Sentry DSN | Valid migration sequencing, clean git working tree, verified Sentry connection, CTO sign-off. | Block deploy. |

---

## 3. Automated CLI Quality Runner

In Raptor v2.2, developers and AI agents can execute all gates locally in one unified command:

```powershell
php bin/raptor test:all
```

*Runs Gates 1 through 4 synchronously and outputs a consolidated ANSI scorecard.*

